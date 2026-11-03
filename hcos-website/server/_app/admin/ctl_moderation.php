<?php
declare(strict_types=1);

/* ---------- attendee uploads: review queue --------------------------------------------- */

function ctl_moderation(): never {
  $status = $_GET['status'] ?? 'pending';
  if (!is_string($status) || !in_array($status, ['pending', 'approved', 'rejected'], true)) $status = 'pending';
  $eventId = get_int('event') ?: null;
  $regId = get_int('reg') ?: null;
  $where = ['u.status = ?']; $params = [$status];
  if ($eventId) { $where[] = 'u.event_id = ?'; $params[] = $eventId; }
  if ($regId) { $where[] = 'u.registration_id = ?'; $params[] = $regId; }
  $list = rows('SELECT u.*, r.name AS reg_name, r.email AS reg_email, e.title AS event_title, e.id AS eid FROM registration_uploads u JOIN registrations r ON r.id = u.registration_id JOIN events e ON e.id = u.event_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 500', $params);

  $tabs = '<nav class="tabs" aria-label="Filter">';
  foreach (['pending' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l) {
    $tabs .= '<a class="tabs__link' . ($k === $status ? ' is-active' : '') . '" href="' . h(admin_url('moderation', array_filter(['status' => $k, 'event' => $eventId, 'reg' => $regId]))) . '">' . $l . '</a>';
  }
  $tabs .= '</nav>';

  $cards = '';
  foreach ($list as $u) {
    $isPhoto = $u['kind'] === 'photo';
    $preview = $isPhoto
      ? '<a class="review__img" href="/' . h($u['file']) . '" target="_blank" rel="noopener"><img src="/' . h($u['file']) . '" alt="" loading="lazy"></a>'
      : '<a class="review__img review__img--doc" href="/' . h($u['file']) . '" target="_blank" rel="noopener">PDF</a>';
    $acts = '';
    if ($u['status'] !== 'approved') $acts .= action_button('mod-approve', ['id' => $u['id']], $isPhoto ? 'Approve → gallery' : 'Approve → documents', 'b b--primary b--sm');
    if ($u['status'] !== 'rejected') $acts .= ' ' . action_button('mod-reject', ['id' => $u['id']], 'Reject', 'b b--ghost b--sm');
    $acts .= ' ' . action_button('mod-delete', ['id' => $u['id']], 'Delete', 'b b--danger b--sm', 'Delete this file permanently?');
    $cards .= '<article class="review">' . $preview . '<div class="review__body">'
      . '<p class="review__name">' . h($u['original_name']) . ' <small class="muted">' . fmt_bytes((int)$u['size']) . '</small></p>'
      . '<p class="muted">From <strong>' . h($u['reg_name']) . '</strong> (' . h($u['reg_email']) . ')<br>for <a href="' . h(admin_url('event', ['id' => $u['eid'], 'tab' => $isPhoto ? 'gallery' : 'materials'])) . '">' . h($u['event_title']) . '</a> · ' . h(fmt_dt($u['created_at'])) . '</p>'
      . '<p>' . badge($u['status'], status_tone($u['status'])) . '</p>'
      . '<div class="review__acts">' . $acts . '</div></div></article>';
  }

  $content = page_head('Uploads to review', '', 'Photos and documents shared by registered attendees. Nothing is public until you approve it.')
    . $tabs . '<section class="card">' . ($cards ? '<div class="reviews">' . $cards . '</div>' : '<p class="muted">Nothing here.</p>') . '</section>';
  admin_layout('Uploads to review', $content, 'moderation');
}

function need_upload(): array {
  $u = row('SELECT * FROM registration_uploads WHERE id = ?', [post_int('id')]);
  if (!$u) { flash('error', 'That file no longer exists.'); back(admin_url('moderation')); }
  return $u;
}

function ctl_mod_approve(): never {
  require_post();
  $u = need_upload();
  $pdo = db_required();
  $pdo->beginTransaction();
  if ($u['status'] !== 'approved') {
    if ($u['kind'] === 'photo') {
      q('INSERT INTO event_gallery (event_id, file, caption, sort_order, registration_id) VALUES (?,?,?,?,?)', [(int)$u['event_id'], $u['file'], null, next_sort('event_gallery', (int)$u['event_id']), (int)$u['registration_id']]);
    } else {
      q("INSERT INTO event_materials (event_id, kind, title, file, mime, sort_order) VALUES (?,'document',?,?,?,?)", [(int)$u['event_id'], preg_replace('/\.pdf$/i', '', $u['original_name']), $u['file'], $u['mime'], next_sort('event_materials', (int)$u['event_id'], "kind <> 'brochure'")]);
    }
    q("UPDATE registration_uploads SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?", [admin_id(), $u['id']]);
    touch_event((int)$u['event_id']);
  }
  $pdo->commit();
  audit('upload_approve', 'registration_upload', (int)$u['id']);
  flash('ok', 'Approved — it is now on the event page.');
  back(admin_url('moderation'));
}

function ctl_mod_reject(): never {
  require_post();
  $u = need_upload();
  // If it had been approved, take it off the public page again.
  q('DELETE FROM event_gallery WHERE event_id = ? AND file = ?', [(int)$u['event_id'], $u['file']]);
  q('DELETE FROM event_materials WHERE event_id = ? AND file = ?', [(int)$u['event_id'], $u['file']]);
  q("UPDATE registration_uploads SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?", [admin_id(), $u['id']]);
  touch_event((int)$u['event_id']);
  audit('upload_reject', 'registration_upload', (int)$u['id']);
  flash('ok', 'Rejected.');
  back(admin_url('moderation'));
}

function ctl_mod_delete(): never {
  require_post();
  $u = need_upload();
  q('DELETE FROM event_gallery WHERE event_id = ? AND file = ?', [(int)$u['event_id'], $u['file']]);
  q('DELETE FROM event_materials WHERE event_id = ? AND file = ?', [(int)$u['event_id'], $u['file']]);
  q('DELETE FROM registration_uploads WHERE id = ?', [$u['id']]);
  delete_upload($u['file']);
  touch_event((int)$u['event_id']);
  audit('upload_delete', 'registration_upload', (int)$u['id'], ['name' => $u['original_name']]);
  flash('ok', 'File deleted.');
  back(admin_url('moderation'));
}
