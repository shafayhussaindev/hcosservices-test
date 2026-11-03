<?php
declare(strict_types=1);

/* ---------- events repository -----------------------------------------------------
   One shape for an event whether it comes from MySQL or from the JSON
   snapshot (_app/data/events.json, written by the build). Renderers only
   ever see this shape:

   id, slug, title, image, alt, teaser, intro, upcoming(bool), status,
   registration_open(bool), attendee_uploads(bool),
   details    => [ ['label'=>..,'value'=>..], ... ]
   sponsors   => [ ['id','group','name','image','url'], ... ]
   partners   => same
   people     => [ ['id','role','name','designation','organization','photo'], ... ]
   brochure   => ['id','file','mime','title'] | null
   materials  => [ ['id','kind','title','file','mime'], ... ]   (agenda/poster/document)
   gallery    => [ ['id','file','caption'], ... ]
   File paths are relative to the web root, no leading slash.               */

function repo_mode(): string { return db() ? 'db' : 'json'; }

/** Published events, newest first (sort_order, then id desc). */
function repo_events_public(): array {
  if (!db()) return repo_json_events();
  $list = rows("SELECT * FROM events WHERE status = 'published' ORDER BY sort_order ASC, id DESC");
  return array_map('repo_hydrate', $list);
}

/** Every event, for the admin. */
function repo_events_all(): array {
  $list = rows('SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registrations,
                       (SELECT COUNT(*) FROM registration_uploads u WHERE u.event_id = e.id AND u.status = \'pending\') AS pending_uploads
                FROM events e ORDER BY sort_order ASC, id DESC');
  return array_map('repo_hydrate', $list);
}

function repo_event_by_slug(string $slug, bool $publishedOnly = true): ?array {
  if (!valid_slug($slug)) return null;
  if (!db()) {
    foreach (repo_json_events() as $e) if ($e['slug'] === $slug) return $e;
    return null;
  }
  $sql = 'SELECT * FROM events WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : '');
  $r = row($sql, [$slug]);
  return $r ? repo_hydrate($r) : null;
}

function repo_event_by_id(int $id): ?array {
  $r = row('SELECT * FROM events WHERE id = ?', [$id]);
  return $r ? repo_hydrate($r) : null;
}

/** Attach child rows to a raw events row. */
function repo_hydrate(array $r): array {
  $id = (int)$r['id'];
  $e = [
    'id' => $id,
    'slug' => $r['slug'],
    'title' => $r['title'],
    'image' => $r['hero_image'] ?: 'assets/img/event-placeholder.webp',
    'alt' => $r['hero_alt'] ?: $r['title'],
    'teaser' => $r['teaser'] ?? '',
    'intro' => $r['intro'] ?? '',
    'upcoming' => (bool)$r['is_upcoming'],
    'status' => $r['status'],
    'registration_open' => (bool)$r['registration_open'],
    'attendee_uploads' => (bool)$r['attendee_uploads'],
    'registrations' => isset($r['registrations']) ? (int)$r['registrations'] : null,
    'pending_uploads' => isset($r['pending_uploads']) ? (int)$r['pending_uploads'] : null,
    'created_at' => $r['created_at'] ?? null,
    'updated_at' => $r['updated_at'] ?? null,
    'details' => [], 'sponsors' => [], 'partners' => [], 'people' => [],
    'brochure' => null, 'materials' => [], 'gallery' => [],
  ];
  foreach (rows('SELECT id, label, value FROM event_details WHERE event_id = ? ORDER BY sort_order, id', [$id]) as $d)
    $e['details'][] = ['id' => (int)$d['id'], 'label' => $d['label'], 'value' => $d['value']];
  foreach (rows('SELECT id, kind, group_label, name, logo, url FROM event_orgs WHERE event_id = ? ORDER BY kind, sort_order, id', [$id]) as $o) {
    $item = ['id' => (int)$o['id'], 'group' => $o['group_label'] ?? '', 'name' => $o['name'], 'image' => $o['logo'], 'url' => $o['url']];
    $e[$o['kind'] === 'partner' ? 'partners' : 'sponsors'][] = $item;
  }
  foreach (rows('SELECT id, role_group, name, designation, organization, photo FROM event_people WHERE event_id = ? ORDER BY sort_order, id', [$id]) as $p)
    $e['people'][] = ['id' => (int)$p['id'], 'role' => $p['role_group'], 'name' => $p['name'], 'designation' => $p['designation'], 'organization' => $p['organization'], 'photo' => $p['photo']];
  foreach (rows('SELECT id, kind, title, file, mime FROM event_materials WHERE event_id = ? ORDER BY sort_order, id', [$id]) as $m) {
    $item = ['id' => (int)$m['id'], 'kind' => $m['kind'], 'title' => $m['title'], 'file' => $m['file'], 'mime' => $m['mime']];
    if ($m['kind'] === 'brochure' && $e['brochure'] === null) $e['brochure'] = $item; else $e['materials'][] = $item;
  }
  foreach (rows('SELECT id, file, caption FROM event_gallery WHERE event_id = ? ORDER BY sort_order, id', [$id]) as $g)
    $e['gallery'][] = ['id' => (int)$g['id'], 'file' => $g['file'], 'caption' => $g['caption']];
  return $e;
}

/* ---------- JSON snapshot (no database yet) -------------------------------------- */

function repo_json_events(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $file = HCOS_APP . '/data/events.json';
  $raw = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
  $cache = [];
  foreach (is_array($raw) ? $raw : [] as $j) {
    $slug = (string)($j['slug'] ?? '');
    if (!valid_slug($slug)) continue;
    $details = [];
    foreach ((array)($j['details'] ?? []) as $k => $v) if ($v) $details[] = ['id' => null, 'label' => (string)$k, 'value' => (string)$v];
    $orgs = fn($list) => array_map(fn($o) => ['id' => null, 'group' => $o['group'] ?? '', 'name' => $o['name'] ?? '', 'image' => $o['image'] ?? null, 'url' => null], (array)$list);
    $people = array_map(fn($p) => ['id' => null, 'role' => $p['role'] ?? 'Speakers', 'name' => $p['name'] ?? '', 'designation' => $p['designation'] ?? null, 'organization' => $p['organization'] ?? null, 'photo' => $p['photo'] ?? null], (array)($j['people'] ?? []));
    // A material is either a bare path (the original hand-written snapshot) or
    // an object written by `cli.php export:json`.
    $material = fn($m) => is_array($m)
      ? ['id' => null, 'kind' => $m['kind'] ?? 'agenda', 'title' => $m['title'] ?? null, 'file' => (string)($m['file'] ?? ''), 'mime' => $m['mime'] ?? 'image/webp']
      : ['id' => null, 'kind' => 'agenda', 'title' => null, 'file' => (string)$m, 'mime' => 'image/webp'];
    $materials = array_values(array_filter(array_map($material, (array)($j['materials'] ?? [])), fn($m) => $m['file'] !== ''));
    $brochure = isset($j['brochure']) && is_array($j['brochure']) ? $material($j['brochure'] + ['kind' => 'brochure']) : null;

    // Photos: the exported list if there is one, otherwise whatever sits in
    // assets/events/<slug>/photos/ — the same rule the static build uses.
    $gallery = [];
    foreach ((array)($j['gallery'] ?? []) as $g) {
      $file = is_array($g) ? (string)($g['file'] ?? '') : (string)$g;
      if ($file !== '') $gallery[] = ['id' => null, 'file' => $file, 'caption' => is_array($g) ? ($g['caption'] ?? null) : null];
    }
    $dir = HCOS_ROOT . '/assets/events/' . $slug . '/photos';
    if (!$gallery && is_dir($dir)) {
      $files = array_values(array_filter(scandir($dir) ?: [], fn($f) => preg_match('/\.(webp|jpe?g|png)$/i', $f)));
      sort($files);
      foreach ($files as $f) $gallery[] = ['id' => null, 'file' => "assets/events/$slug/photos/$f", 'caption' => null];
    }
    $cache[] = [
      'id' => null, 'slug' => $slug, 'title' => (string)($j['title'] ?? ''), 'image' => (string)($j['image'] ?? ''),
      'alt' => (string)($j['alt'] ?? ''), 'teaser' => (string)($j['teaser'] ?? ''), 'intro' => (string)($j['intro'] ?? ''),
      'upcoming' => !empty($j['upcoming']), 'status' => 'published',
      'registration_open' => !empty($j['upcoming']), 'attendee_uploads' => false,
      'registrations' => null, 'pending_uploads' => null, 'created_at' => null, 'updated_at' => null,
      'details' => $details, 'sponsors' => $orgs($j['sponsors'] ?? []), 'partners' => $orgs($j['partners'] ?? []),
      'people' => $people, 'brochure' => $brochure, 'materials' => $materials, 'gallery' => $gallery,
    ];
  }
  return $cache;
}

/* ---------- counters used by the admin dashboard ---------------------------------- */

function repo_counts(): array {
  return [
    'events' => (int)scalar('SELECT COUNT(*) FROM events'),
    'published' => (int)scalar("SELECT COUNT(*) FROM events WHERE status = 'published'"),
    'upcoming' => (int)scalar("SELECT COUNT(*) FROM events WHERE is_upcoming = 1 AND status = 'published'"),
    'registrations' => (int)scalar('SELECT COUNT(*) FROM registrations'),
    'registrations_new' => (int)scalar("SELECT COUNT(*) FROM registrations WHERE status = 'new'"),
    'uploads_pending' => (int)scalar("SELECT COUNT(*) FROM registration_uploads WHERE status = 'pending'"),
    'messages_new' => (int)scalar("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
    'visits' => (int)(scalar('SELECT count FROM visits WHERE id = 1') ?? 0),
  ];
}
