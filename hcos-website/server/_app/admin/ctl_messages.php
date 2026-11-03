<?php
declare(strict_types=1);

/* ---------- contact form inbox ------------------------------------------------------------ */

function ctl_messages(): never {
  $id = get_int('id');
  if ($id) {
    $m = row('SELECT * FROM contact_messages WHERE id = ?', [$id]);
    if (!$m) { flash('error', 'That message no longer exists.'); redirect(admin_url('messages')); }
    if ($m['status'] === 'new') q("UPDATE contact_messages SET status = 'read' WHERE id = ?", [$id]);
    $content = page_head('Message from ' . ($m['name'] ?: $m['email']), '<a class="b b--ghost b--sm" href="' . h(admin_url('messages')) . '">← All messages</a>')
      . '<section class="card"><dl class="dl">'
      . '<div><dt>From</dt><dd>' . h($m['name'] ?? '') . ' &lt;<a href="mailto:' . h($m['email']) . '">' . h($m['email']) . '</a>&gt;</dd></div>'
      . ($m['phone'] ? '<div><dt>Phone</dt><dd>' . h($m['phone']) . '</dd></div>' : '')
      . '<div><dt>Received</dt><dd>' . h(fmt_dt($m['created_at'])) . '</dd></div>'
      . '<div><dt>Status</dt><dd>' . badge($m['status'] === 'new' ? 'read' : $m['status'], status_tone($m['status'])) . '</dd></div></dl>'
      . '<div class="message">' . nl2br(h($m['message'])) . '</div>'
      . '<div class="actions"><a class="b b--primary" href="mailto:' . h($m['email']) . '?subject=' . rawurlencode('Re: your enquiry to HCOS') . '">Reply by email</a> '
      . action_button('msg-status', ['id' => $id, 'status' => 'replied'], 'Mark as replied', 'b b--ghost') . ' '
      . action_button('msg-delete', ['id' => $id], 'Delete', 'b b--danger', 'Delete this message?') . '</div></section>';
    admin_layout('Message', $content, 'messages');
  }
  $list = rows('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 500');
  $rowsHtml = '';
  foreach ($list as $m) {
    $rowsHtml .= '<tr class="' . ($m['status'] === 'new' ? 'is-new' : '') . '"><td><a class="strong" href="' . h(admin_url('messages', ['id' => $m['id']])) . '">' . h($m['name'] ?: $m['email']) . '</a><br><small class="muted">' . h($m['email']) . '</small></td>'
      . '<td class="clip">' . h(mb_substr($m['message'], 0, 120)) . (mb_strlen($m['message']) > 120 ? '…' : '') . '</td>'
      . '<td>' . badge($m['status'], status_tone($m['status'])) . '</td><td class="num">' . h(fmt_dt($m['created_at'])) . '</td>'
      . '<td class="acts">' . action_button('msg-delete', ['id' => $m['id']], 'Delete', 'b b--danger b--sm', 'Delete this message?') . '</td></tr>';
  }
  $content = page_head('Messages', '', 'Enquiries sent through the Contact page. A copy of each is also emailed.')
    . '<section class="card">' . ($list ? '<div class="tablewrap"><table class="table"><thead><tr><th>From</th><th>Message</th><th>Status</th><th class="num">Received</th><th></th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>' : '<p class="muted">No messages yet.</p>') . '</section>';
  admin_layout('Messages', $content, 'messages');
}

function ctl_msg_status(): never {
  require_post();
  $s = post_str('status', 10);
  if (in_array($s, ['new', 'read', 'replied'], true)) q('UPDATE contact_messages SET status = ? WHERE id = ?', [$s, post_int('id')]);
  flash('ok', 'Updated.');
  back(admin_url('messages'));
}

function ctl_msg_delete(): never {
  require_post();
  q('DELETE FROM contact_messages WHERE id = ?', [post_int('id')]);
  audit('message_delete', 'contact_message', post_int('id'));
  flash('ok', 'Message deleted.');
  redirect(admin_url('messages'));
}
