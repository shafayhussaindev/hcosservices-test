<?php
/**
 * HCOS website — command line tasks. Run ON THE SERVER, from public_html:
 *
 *   php _app/cli.php migrate            create / update the database tables
 *   php _app/cli.php admin:create       add an admin user (asks for the password)
 *   php _app/cli.php admin:password     reset an admin user's password
 *   php _app/cli.php import:json        load _app/data/events.json into the database (first time only)
 *   php _app/cli.php export:json        refresh that snapshot FROM the database (run after content changes)
 *   php _app/cli.php status             show what is configured
 *
 * Use PHP 8.2+ (on Hostinger: /opt/alt/php82/usr/bin/php).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';

$cmd = $argv[1] ?? 'status';
$out = fn(string $s) => fwrite(STDOUT, $s . PHP_EOL);
// Hostinger disables shell_exec/system/exec; stty still works through proc_open.
$ttyEcho = function (bool $on): bool {
  if (DIRECTORY_SEPARATOR !== '/' || !function_exists('proc_open')) return false;
  $p = @proc_open(['stty', $on ? 'echo' : '-echo'], [0 => STDIN, 1 => STDOUT, 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($p)) return false;
  fclose($pipes[2]);
  return proc_close($p) === 0;
};
$ask = function (string $prompt, bool $hidden = false) use ($ttyEcho): string {
  if ($hidden) {
    $off = $ttyEcho(false);
    if (!$off) fwrite(STDOUT, "(this terminal cannot hide input — the password WILL be visible; clear the screen afterwards)
");
    fwrite(STDOUT, $prompt);
    $v = fgets(STDIN);
    if ($off) { $ttyEcho(true); fwrite(STDOUT, PHP_EOL); }
  } else {
    fwrite(STDOUT, $prompt);
    $v = fgets(STDIN);
  }
  return trim((string)$v);
};

switch ($cmd) {
  case 'status':
    $out('HCOS website ' . HCOS_VERSION);
    $out('Config file : ' . (is_file(HCOS_PRIVATE . '/config.php') ? HCOS_PRIVATE . '/config.php' : 'MISSING — copy _app/config.example.php to ' . HCOS_PRIVATE . '/config.php'));
    $out('Database    : ' . (db() ? 'connected (' . $HCOS['db']['name'] . ')' : 'not connected — public site renders from the JSON snapshot'));
    $out('App key     : ' . ($HCOS['app_key'] !== '' && $HCOS['app_key'] !== 'CHANGE-ME' ? 'set' : 'NOT SET'));
    $out('Notify email: ' . $HCOS['notify_email'] . '   From: ' . $HCOS['from_email']);
    $out('Uploads dir : ' . $HCOS['uploads_dir'] . (is_writable($HCOS['uploads_dir']) ? ' (writable)' : ' (NOT writable)'));
    $out('Imagick     : ' . (class_exists('Imagick') ? 'yes' : 'no (GD fallback)'));
    if (db()) {
      try { $out('Admin users : ' . (int)scalar('SELECT COUNT(*) FROM admin_users')); $out('Events      : ' . (int)scalar('SELECT COUNT(*) FROM events')); }
      catch (Throwable $e) { $out('Tables      : not created yet — run: php _app/cli.php migrate'); }
    }
    break;

  case 'migrate':
    $n = db_migrate();
    $out("Schema applied ({$n} statements).");
    break;

  case 'admin:create':
  case 'admin:password':
    // Ask everything first, connect last: the shared MySQL server drops idle
    // connections after ~20 s, less time than it takes to type a password.
    $username = mb_strtolower($ask('User ID (letters, numbers, dots): '));
    if (!preg_match('/^[a-z0-9.]{3,60}$/', $username)) { fwrite(STDERR, "Invalid user ID.\n"); exit(1); }
    $name = $cmd === 'admin:create' ? $ask('Display name: ') : '';
    $email = $cmd === 'admin:create' ? $ask('Email (optional): ') : '';
    $pw = $ask('Password (not shown): ', true);
    $pw2 = $ask('Repeat password: ', true);
    if ($pw !== $pw2) { fwrite(STDERR, "Passwords do not match.\n"); exit(1); }
    if ($err = password_policy_error($pw)) { fwrite(STDERR, $err . "\n"); exit(1); }
    $hash = password_hash($pw, password_algo());
    db_required();
    $existing = row('SELECT id FROM admin_users WHERE username = ?', [$username]);
    if ($cmd === 'admin:create' && $existing) { fwrite(STDERR, "That user already exists. Use admin:password to reset it.\n"); exit(1); }
    if ($cmd === 'admin:password' && !$existing) { fwrite(STDERR, "No such user.\n"); exit(1); }
    if ($existing) { q('UPDATE admin_users SET password_hash = ?, active = 1 WHERE id = ?', [$hash, (int)$existing['id']]); $out('Password updated.'); }
    else { q('INSERT INTO admin_users (username, name, email, password_hash) VALUES (?,?,?,?)', [$username, $name ?: $username, $email ?: null, $hash]); $out('Admin user created. Sign in at ' . hcos_url('event-setup/')); }
    break;

  case 'import:json':
    db_required();
    if ((int)scalar('SELECT COUNT(*) FROM events') > 0 && ($argv[2] ?? '') !== '--force') {
      fwrite(STDERR, "Events already exist. Re-run with --force to import anyway (existing slugs are skipped).\n"); exit(1);
    }
    $list = repo_json_events();
    $pdo = db_required();
    $done = 0; $skipped = 0;
    foreach (array_reverse($list) as $i => $e) {   // reverse so the newest (first in JSON) gets the highest id
      if (row('SELECT id FROM events WHERE slug = ?', [$e['slug']])) { $skipped++; continue; }
      $pdo->beginTransaction();
      q("INSERT INTO events (slug, title, teaser, intro, hero_image, hero_alt, status, is_upcoming, registration_open, attendee_uploads, sort_order) VALUES (?,?,?,?,?,?,'published',?,?,1,0)",
        [$e['slug'], $e['title'], $e['teaser'], $e['intro'], $e['image'], $e['alt'], $e['upcoming'] ? 1 : 0, $e['upcoming'] ? 1 : 0]);
      $id = last_id();
      foreach ($e['details'] as $k => $d) q('INSERT INTO event_details (event_id, label, value, sort_order) VALUES (?,?,?,?)', [$id, $d['label'], $d['value'], $k]);
      foreach (['sponsor' => $e['sponsors'], 'partner' => $e['partners']] as $kind => $orgs)
        foreach ($orgs as $k => $o) q('INSERT INTO event_orgs (event_id, kind, group_label, name, logo, sort_order) VALUES (?,?,?,?,?,?)', [$id, $kind, $o['group'] ?: null, $o['name'], $o['image'], $k]);
      foreach ($e['people'] as $k => $p) q('INSERT INTO event_people (event_id, role_group, name, designation, organization, photo, sort_order) VALUES (?,?,?,?,?,?,?)', [$id, $p['role'], $p['name'], $p['designation'] ?: null, $p['organization'] ?: null, $p['photo'] ?: null, $k]);
      foreach ($e['materials'] as $k => $m) q("INSERT INTO event_materials (event_id, kind, title, file, mime, sort_order) VALUES (?,'agenda',NULL,?,?,?)", [$id, $m['file'], $m['mime'], $k]);
      foreach ($e['gallery'] as $k => $g) q('INSERT INTO event_gallery (event_id, file, sort_order) VALUES (?,?,?)', [$id, $g['file'], $k]);
      $pdo->commit();
      $done++;
      $out(sprintf('  %-40s people %2d  logos %2d  materials %2d  photos %2d', $e['slug'], count($e['people']), count($e['sponsors']) + count($e['partners']), count($e['materials']), count($e['gallery'])));
    }
    $out("Imported {$done} event(s), skipped {$skipped}.");
    break;

  case 'export:json':
    // Refresh the offline snapshot from the database. The public site falls
    // back to this file when MySQL is unreachable, so it has to be re-exported
    // after content changes or an outage shows stale events.
    db_required();
    $events = repo_events_public();
    $out = [];
    foreach ($events as $e) {
      $details = [];
      foreach ($e['details'] as $d) $details[$d['label']] = $d['value'];
      $orgs = fn(array $list) => array_values(array_map(
        fn($o) => array_filter(['group' => $o['group'], 'name' => $o['name'], 'image' => $o['image']], fn($v) => $v !== null && $v !== ''),
        $list));
      $out[] = array_filter([
        'slug' => $e['slug'], 'title' => $e['title'], 'image' => $e['image'], 'alt' => $e['alt'],
        'teaser' => $e['teaser'], 'upcoming' => $e['upcoming'] ?: null, 'intro' => $e['intro'],
        'details' => $details,
        'sponsors' => $orgs($e['sponsors']), 'partners' => $orgs($e['partners']),
        'people' => array_values(array_map(fn($p) => array_filter([
          'role' => $p['role'], 'name' => $p['name'], 'designation' => $p['designation'],
          'organization' => $p['organization'], 'photo' => $p['photo'],
        ], fn($v) => $v !== null && $v !== ''), $e['people'])),
        'brochure' => $e['brochure'] ? array_filter(['kind' => 'brochure', 'title' => $e['brochure']['title'], 'file' => $e['brochure']['file'], 'mime' => $e['brochure']['mime']], fn($v) => $v !== null && $v !== '') : null,
        'materials' => array_values(array_map(fn($m) => array_filter([
          'kind' => $m['kind'], 'title' => $m['title'], 'file' => $m['file'], 'mime' => $m['mime'],
        ], fn($v) => $v !== null && $v !== ''), $e['materials'])),
        'gallery' => array_values(array_map(fn($g) => array_filter(['file' => $g['file'], 'caption' => $g['caption']], fn($v) => $v !== null && $v !== ''), $e['gallery'])),
      ], fn($v) => $v !== null && $v !== [] && $v !== '');
    }
    $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) { fwrite(STDERR, "Could not encode the snapshot.\n"); exit(1); }
    $dest = HCOS_APP . '/data/events.json';
    // Write beside the target then rename: a half-written snapshot would be
    // served to the public the moment the database hiccups.
    $tmp = $dest . '.tmp';
    if (file_put_contents($tmp, $json . "\n") === false || !rename($tmp, $dest)) {
      @unlink($tmp);
      fwrite(STDERR, "Could not write {$dest}\n"); exit(1);
    }
    $people = array_sum(array_map(fn($e) => count($e['people']), $events));
    $photos = array_sum(array_map(fn($e) => count($e['gallery']), $events));
    $out_ = fn(string $s) => fwrite(STDOUT, $s . PHP_EOL);
    $out_(sprintf('Snapshot written: %d events, %d people, %d photos (%s).', count($events), $people, $photos, fmt_bytes(strlen($json))));
    $out_('Copy it back into the repository so the two do not drift:');
    $out_('  src/data/events.json');
    break;

  default:
    fwrite(STDERR, "Unknown command. See the header of this file.\n");
    exit(1);
}
