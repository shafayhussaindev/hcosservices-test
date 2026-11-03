<?php
declare(strict_types=1);

/* ---------- /event-setup/ router ---------------------------------------------------------
   Nothing on the public site links here. Every route except `login` requires
   a live admin session; every POST requires the session's CSRF token.         */

require HCOS_APP . '/admin/layout.php';
require HCOS_APP . '/admin/ctl_auth.php';
require HCOS_APP . '/admin/ctl_events.php';
require HCOS_APP . '/admin/ctl_registrations.php';
require HCOS_APP . '/admin/ctl_moderation.php';
require HCOS_APP . '/admin/ctl_messages.php';

function admin_dispatch(): never {
  $r = $_GET['r'] ?? 'dashboard';
  if (!is_string($r) || !preg_match('/^[a-z-]{1,40}$/', $r)) $r = 'dashboard';

  if (!db()) {
    admin_headers();
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Not configured</title><p style="font:16px system-ui;padding:40px">The database is not configured yet. See <code>_app/config.example.php</code>.</p>';
    exit;
  }

  session_boot();

  if ($r === 'login') ctl_login();
  if (!admin_logged_in()) {
    if (is_post()) { http_response_code(403); admin_layout_bare('Signed out', '<div class="card"><p>Your session has ended. <a href="' . h(admin_url('login')) . '">Sign in again</a>.</p></div>'); }
    redirect(admin_url('login'));
  }
  if (is_post()) csrf_check();

  match ($r) {
    'dashboard' => ctl_dashboard(),
    'logout' => ctl_logout(),
    'account' => ctl_account(),
    'account-password' => ctl_account_password(),

    'events' => ctl_events(),
    'event-new' => ctl_event_new(),
    'event' => ctl_event(),
    'event-save' => ctl_event_save(),
    'event-hero' => ctl_event_hero(),
    'event-brochure' => ctl_event_brochure(),
    'event-status' => ctl_event_status(),
    'details-save' => ctl_details_save(),
    'person-save' => ctl_person_save(),
    'person-delete' => ctl_person_delete(),
    'person-move' => ctl_person_move(),
    'org-save' => ctl_org_save(),
    'org-delete' => ctl_org_delete(),
    'org-move' => ctl_org_move(),
    'material-upload' => ctl_material_upload(),
    'material-save' => ctl_material_save(),
    'material-delete' => ctl_material_delete(),
    'material-move' => ctl_material_move(),
    'gallery-upload' => ctl_gallery_upload(),
    'gallery-save' => ctl_gallery_save(),
    'gallery-delete' => ctl_gallery_delete(),
    'gallery-move' => ctl_gallery_move(),

    'registrations' => ctl_registrations(),
    'reg-status' => ctl_reg_status(),
    'reg-delete' => ctl_reg_delete(),
    'reg-export' => ctl_reg_export(),
    'reg-headshot' => ctl_reg_headshot(),

    'moderation' => ctl_moderation(),
    'mod-approve' => ctl_mod_approve(),
    'mod-reject' => ctl_mod_reject(),
    'mod-delete' => ctl_mod_delete(),

    'messages' => ctl_messages(),
    'msg-status' => ctl_msg_status(),
    'msg-delete' => ctl_msg_delete(),

    default => ctl_dashboard(),
  };
}

/* ---------- shared guards used by controllers -------------------------------------- */

function require_post(): void {
  if (!is_post()) { http_response_code(405); admin_layout('Not allowed', '<div class="card"><p>That action needs a form submission.</p></div>'); }
}

/** Load an event by id (from GET or POST) or stop with a 404. */
function need_event(): array {
  $id = is_post() ? post_int('event_id') : get_int('id');
  $e = $id > 0 ? repo_event_by_id($id) : null;
  if (!$e) { http_response_code(404); admin_layout('Not found', '<div class="card"><p>That event does not exist.</p> <a class="b b--ghost" href="' . h(admin_url('events')) . '">Back to events</a></div>'); }
  return $e;
}

function back_to_event(array $e, string $tab): never { redirect(admin_url('event', ['id' => $e['id'], 'tab' => $tab])); }

/** Re-number sort_order 0..n for a child table of an event. */
function renumber(string $table, int $eventId, ?string $extraWhere = null, array $extraParams = []): void {
  $rows = rows("SELECT id FROM {$table} WHERE event_id = ?" . ($extraWhere ? " AND {$extraWhere}" : '') . ' ORDER BY sort_order, id', array_merge([$eventId], $extraParams));
  foreach ($rows as $i => $r) q("UPDATE {$table} SET sort_order = ? WHERE id = ?", [$i, (int)$r['id']]);
}

/** Swap a row with its neighbour. */
function move_row(string $table, int $eventId, int $id, string $dir, ?string $extraWhere = null, array $extraParams = []): void {
  renumber($table, $eventId, $extraWhere, $extraParams);
  $rows = rows("SELECT id, sort_order FROM {$table} WHERE event_id = ?" . ($extraWhere ? " AND {$extraWhere}" : '') . ' ORDER BY sort_order, id', array_merge([$eventId], $extraParams));
  $idx = array_search($id, array_map(fn($r) => (int)$r['id'], $rows), true);
  if ($idx === false) return;
  $j = $dir === 'up' ? $idx - 1 : $idx + 1;
  if ($j < 0 || $j >= count($rows)) return;
  q("UPDATE {$table} SET sort_order = ? WHERE id = ?", [$j, (int)$rows[$idx]['id']]);
  q("UPDATE {$table} SET sort_order = ? WHERE id = ?", [$idx, (int)$rows[$j]['id']]);
}

function next_sort(string $table, int $eventId, ?string $extraWhere = null, array $extraParams = []): int {
  return (int)scalar("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE event_id = ?" . ($extraWhere ? " AND {$extraWhere}" : ''), array_merge([$eventId], $extraParams));
}

function touch_event(int $id): void { q('UPDATE events SET updated_at = NOW() WHERE id = ?', [$id]); }
