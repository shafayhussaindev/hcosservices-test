<?php
declare(strict_types=1);

const HCOS_REG_STATUSES = ['new' => 'New', 'confirmed' => 'Confirmed', 'attended' => 'Attended', 'cancelled' => 'Cancelled'];

/** Registrations and export controls for one selected event. */
function registrations_table(int $eventId): string {
  if ($eventId < 1) throw new InvalidArgumentException('Select an event to view registrations.');
  $list = rows('SELECT r.* FROM registrations r WHERE r.event_id = ? ORDER BY r.created_at DESC LIMIT 1000', [$eventId]);
  if (!$list) return '<section class="card"><p class="muted">No registrations for this event yet.</p></section>';
  $uploads = [];
  foreach (rows('SELECT u.* FROM registration_uploads u JOIN registrations r ON r.id = u.registration_id WHERE r.event_id = ? ORDER BY u.created_at DESC, u.id DESC', [$eventId]) as $u) $uploads[$u['registration_id']][] = $u;
  $rowsHtml = '';
  foreach ($list as $r) {
    $sel = '<form class="inline" method="post" action="' . h(admin_url('reg-status')) . '">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
      . '<select name="status" aria-label="Status for ' . h($r['name']) . '" data-autosubmit>';
    foreach (HCOS_REG_STATUSES as $k => $l) $sel .= '<option value="' . $k . '"' . ($r['status'] === $k ? ' selected' : '') . '>' . $l . '</option>';
    $sel .= '</select><noscript><button class="b b--ghost b--sm" type="submit">Set</button></noscript></form>';
    $files = '<span class="muted">No files uploaded</span>';
    if (!empty($uploads[$r['id']])) {
      $files = '<details class="reg-files"><summary>' . count($uploads[$r['id']]) . ' uploaded file(s)</summary><ul>';
      foreach ($uploads[$r['id']] as $u) $files .= '<li><a href="/' . h($u['file']) . '" target="_blank" rel="noopener">' . h($u['original_name']) . '</a><br>' . badge($u['status'], status_tone($u['status'])) . '</li>';
      $files .= '</ul><a href="' . h(admin_url('moderation', ['reg' => $r['id']])) . '">Review uploads →</a></details>';
    }
    $rowsHtml .= '<tr><td><strong>' . h($r['name']) . '</strong><br><small class="muted">' . h(trim(($r['designation'] ?? '') . ($r['designation'] && $r['organization'] ? ', ' : '') . ($r['organization'] ?? ''))) . '</small>'
      . '<br><small class="muted">' . h(registration_gender_label($r['gender'] ?? null)) . '</small>'
      . '<br><small class="muted">Participant price: ' . h(HCOS_REG_PRICES[$r['price_option'] ?? ''] ?? 'Not supplied') . '</small>' . (!empty($r['note']) ? '<br><small class="muted">' . h($r['note']) . '</small>' : '') . '</td>'
      . '<td><div class="reg-contact">' . ($r['email'] !== '' ? '<a href="mailto:' . h($r['email']) . '">' . h($r['email']) . '</a>' : '<span class="muted">Email not supplied</span>')
      . (!empty($r['phone']) ? '<a href="tel:' . h($r['phone']) . '">' . h($r['phone']) . '</a>' : '<span class="muted">Phone not supplied</span>')
      . (!empty($r['linkedin_url']) && valid_linkedin_profile($r['linkedin_url']) ? '<a href="' . h($r['linkedin_url']) . '" target="_blank" rel="noopener noreferrer">LinkedIn profile ↗</a>' : '<span class="muted">LinkedIn not supplied</span>') . '</div></td>'
      . '<td>' . (!empty($r['headshot']) ? '<a class="thumb reg-headshot" href="' . h(admin_url('reg-headshot', ['id' => $r['id']])) . '" target="_blank" rel="noopener"><img src="' . h(admin_url('reg-headshot', ['id' => $r['id']])) . '" alt="Headshot of ' . h($r['name']) . '" width="56" height="56" loading="lazy"></a>' : '<span class="muted">No photo</span>') . '</td>'
      . '<td>' . $sel . '</td>'
      . '<td>' . $files . '</td>'
      . '<td class="num">' . h(fmt_dt($r['created_at'])) . '</td>'
      . '<td class="acts">' . action_button('reg-delete', ['id' => $r['id']], 'Delete', 'b b--danger b--sm', 'Delete the registration for ' . $r['name'] . ' and any files they uploaded?') . '</td></tr>';
  }
  $export = '<a class="b b--ghost b--sm" href="' . h(admin_url('reg-export', ['event' => $eventId])) . '">Download CSV</a>';
  return '<section class="card"><div class="card__head"><h2 class="card__title">' . count($list) . ' registration' . (count($list) === 1 ? '' : 's') . '</h2>' . $export . '</div>'
    . '<div class="tablewrap"><table class="table"><thead><tr><th scope="col">Name</th><th scope="col">Contact</th><th scope="col">Headshot</th><th scope="col">Status</th><th scope="col">Uploaded files</th><th scope="col" class="num">Date registered</th><th scope="col">Actions</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div></section>';
}

function ctl_registrations(): never {
  $eventId = get_int('event') ?: null;
  $events = rows('SELECT e.id, e.title, e.status, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registrations FROM events e ORDER BY e.sort_order, e.id DESC');
  if (!$eventId) {
    $items = '';
    foreach ($events as $ev) $items .= '<tr><td><a href="' . h(admin_url('registrations', ['event' => $ev['id']])) . '"><strong>' . h($ev['title']) . '</strong></a></td><td>' . badge($ev['status'], status_tone($ev['status'])) . '</td><td class="num">' . (int)$ev['registrations'] . '</td><td><a class="b b--ghost b--sm" href="' . h(admin_url('registrations', ['event' => $ev['id']])) . '">Open registrations</a></td></tr>';
    $overview = $items ? '<div class="tablewrap"><table class="table"><thead><tr><th>Event</th><th>Status</th><th class="num">Registrations</th><th></th></tr></thead><tbody>' . $items . '</tbody></table></div>' : '<p class="muted">No events yet.</p>';
    admin_layout('Registrations by event', page_head('Registrations by event', '', 'Choose an event to view its attendees and download its CSV. Records are never combined in this view.') . '<section class="card">' . $overview . '</section>', 'registrations');
  }
  $selected = null;
  foreach ($events as $ev) if ((int)$ev['id'] === $eventId) $selected = $ev;
  if (!$selected) { http_response_code(404); admin_layout('Event not found', '<section class="card"><p>This event does not exist.</p></section>', 'registrations'); }
  $filter = '<form method="get" action="/event-setup/" class="inline"><input type="hidden" name="r" value="registrations"><select name="event" aria-label="Event" data-autosubmit>';
  foreach ($events as $ev) $filter .= '<option value="' . (int)$ev['id'] . '"' . ($eventId === (int)$ev['id'] ? ' selected' : '') . '>' . h($ev['title']) . '</option>';
  $filter .= '</select><noscript><button class="b b--ghost b--sm" type="submit">Filter</button></noscript></form>';
  admin_layout('Registrations', page_head($selected['title'] . ' — registrations', $filter) . '<p><a href="' . h(admin_url('registrations')) . '">← All event folders</a></p>' . registrations_table($eventId), 'registrations');
}

function ctl_reg_status(): never {
  require_post();
  $s = post_str('status', 12);
  if (!isset(HCOS_REG_STATUSES[$s])) redirect(admin_url('registrations'));
  $r = row('SELECT * FROM registrations WHERE id = ?', [post_int('id')]);
  if ($r) { q('UPDATE registrations SET status = ? WHERE id = ?', [$s, $r['id']]); audit('registration_status', 'registration', (int)$r['id'], ['status' => $s]); flash('ok', $r['name'] . ' marked ' . HCOS_REG_STATUSES[$s] . '.'); }
  back(admin_url('registrations'));
}

function ctl_reg_delete(): never {
  require_post();
  $r = row('SELECT * FROM registrations WHERE id = ?', [post_int('id')]);
  if ($r) {
    delete_upload($r['headshot'] ?? null);
    foreach (rows('SELECT file FROM registration_uploads WHERE registration_id = ?', [$r['id']]) as $u) delete_upload($u['file']);
    q('DELETE FROM registrations WHERE id = ?', [$r['id']]);
    audit('registration_delete', 'registration', (int)$r['id'], ['name' => $r['name'], 'email' => $r['email']]);
    flash('ok', 'Registration deleted.');
  }
  back(admin_url('registrations'));
}

function ctl_reg_export(): never {
  $eventId = get_int('event') ?: null;
  if (!$eventId || !row('SELECT id FROM events WHERE id = ?', [$eventId])) redirect(admin_url('registrations'));
  $list = rows('SELECT r.*, e.title FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.event_id = ? ORDER BY r.created_at', [$eventId]);
  audit('registration_export', 'event', $eventId, ['rows' => count($list)]);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="registrations-event-' . $eventId . '-' . date('Y-m-d') . '.csv"');
  header('Cache-Control: no-store');
  echo "\xEF\xBB\xBF"; // BOM so Excel reads UTF-8
  echo "Event,Name,Organization,Designation,Email,Phone,LinkedIn,Gender,Headshot,Status,Registered,Note,Participant price\n";
  foreach ($list as $r) {
    echo implode(',', array_map('csv_cell', [$r['title'], $r['name'], $r['organization'], $r['designation'], $r['email'], $r['phone'], $r['linkedin_url'] ?? '', registration_gender_label($r['gender'] ?? null), !empty($r['headshot']) ? hcos_url(ltrim(admin_url('reg-headshot', ['id' => $r['id']]), '/')) : '', $r['status'], $r['created_at'], $r['note'] ?? '', HCOS_REG_PRICES[$r['price_option'] ?? ''] ?? 'Not supplied'])) . "\n";
  }
  exit;
}

function ctl_reg_headshot(): never {
  $r = row('SELECT headshot FROM registrations WHERE id = ?', [get_int('id')]);
  $file = $r['headshot'] ?? '';
  $base = realpath(HCOS_PRIVATE . '/headshots');
  $real = preg_match('#^headshots/event-[0-9]+/[a-f0-9]{16}\.webp$#D', $file) ? realpath(HCOS_PRIVATE . '/' . $file) : false;
  if (!$base || !$real || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404); exit;
  }
  admin_headers();
  header('Content-Type: image/webp');
  header('Content-Disposition: inline; filename="headshot.webp"');
  header('Content-Length: ' . filesize($real));
  readfile($real);
  exit;
}
