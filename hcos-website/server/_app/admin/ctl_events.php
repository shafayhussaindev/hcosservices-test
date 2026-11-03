<?php
declare(strict_types=1);

/* ---------- events list ------------------------------------------------------------------ */

function ctl_events(): never {
  $list = repo_events_all();
  $rowsHtml = '';
  foreach ($list as $e) {
    $rowsHtml .= '<tr>'
      . '<td>' . thumb($e['image'], '', 'thumb thumb--wide') . '</td>'
      . '<td><a class="strong" href="' . h(admin_url('event', ['id' => $e['id']])) . '">' . h($e['title']) . '</a><br><small class="muted">/events/' . h($e['slug']) . '</small></td>'
      . '<td>' . badge($e['status'], status_tone($e['status'])) . ($e['upcoming'] ? ' ' . badge('Upcoming', 'accent') : '') . ($e['registration_open'] ? ' ' . badge('Registration open', 'ok') : '') . '</td>'
      . '<td class="num"><a href="' . h(admin_url('event', ['id' => $e['id'], 'tab' => 'registrations'])) . '">' . (int)$e['registrations'] . '</a></td>'
      . '<td class="num">' . ($e['pending_uploads'] ? '<a href="' . h(admin_url('moderation', ['event' => $e['id']])) . '">' . (int)$e['pending_uploads'] . '</a>' : '0') . '</td>'
      . '<td class="num">' . h(fmt_dt($e['updated_at'])) . '</td>'
      . '<td class="acts"><a class="b b--ghost b--sm" href="' . h(admin_url('event', ['id' => $e['id']])) . '">Edit</a> '
      . '<a class="b b--ghost b--sm" href="/events/' . h($e['slug']) . '" target="_blank" rel="noopener">View</a></td>'
      . '</tr>';
  }
  $new = '<form class="inline" method="post" action="' . h(admin_url('event-new')) . '">' . csrf_field() . '<button class="b b--primary" type="submit">+ New event</button></form>';
  $content = page_head('Events', $new, count($list) . ' event' . (count($list) === 1 ? '' : 's') . '. Order on the website: newest first, then by the "position" number.')
    . '<section class="card">'
    . ($list ? '<div class="tablewrap"><table class="table"><thead><tr><th></th><th>Event</th><th>Status</th><th class="num">Registrations</th><th class="num">To review</th><th class="num">Updated</th><th></th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>'
             : '<p class="muted">No events yet. Create the first one.</p>')
    . '</section>';
  admin_layout('Events', $content, 'events');
}

function ctl_event_new(): never {
  require_post();
  $n = 1; $slug = 'new-event';
  while (row('SELECT id FROM events WHERE slug = ?', [$slug])) $slug = 'new-event-' . (++$n);
  q("INSERT INTO events (slug, title, status, sort_order) VALUES (?, 'New event', 'draft', -1)", [$slug]);
  $id = last_id();
  audit('event_create', 'event', $id);
  flash('ok', 'Draft created. Fill in the catalogue details, then publish when ready.');
  redirect(admin_url('event', ['id' => $id, 'tab' => 'catalog']));
}

/* ---------- event editor (tabs) ---------------------------------------------------------- */

const HCOS_TABS = ['catalog' => '1 · Catalogue', 'details' => '2 · Registration & details', 'people' => '3 · Speakers & panel', 'orgs' => 'Sponsors & partners', 'materials' => 'Agenda & documents', 'gallery' => 'Photo gallery', 'registrations' => 'Registrations'];

function ctl_event(): never {
  $e = need_event();
  $tab = $_GET['tab'] ?? 'catalog';
  if (!is_string($tab) || !isset(HCOS_TABS[$tab])) $tab = 'catalog';

  $tabs = '<nav class="tabs" aria-label="Sections">';
  foreach (HCOS_TABS as $k => $label) {
    $n = match ($k) { 'people' => count($e['people']), 'orgs' => count($e['sponsors']) + count($e['partners']), 'materials' => count($e['materials']) + ($e['brochure'] ? 1 : 0), 'gallery' => count($e['gallery']), 'registrations' => (int)scalar('SELECT COUNT(*) FROM registrations WHERE event_id = ?', [$e['id']]), default => null };
    $tabs .= '<a class="tabs__link' . ($k === $tab ? ' is-active' : '') . '" href="' . h(admin_url('event', ['id' => $e['id'], 'tab' => $k])) . '">' . h($label) . ($n !== null ? ' <span class="tabs__n">' . $n . '</span>' : '') . '</a>';
  }
  $tabs .= '</nav>';

  $statusActions = '';
  if ($e['status'] !== 'published') $statusActions .= action_button('event-status', ['event_id' => $e['id'], 'status' => 'published'], 'Publish', 'b b--primary b--sm');
  else $statusActions .= action_button('event-status', ['event_id' => $e['id'], 'status' => 'draft'], 'Unpublish', 'b b--ghost b--sm', 'Take this event off the website? It stays here as a draft.');
  if ($e['status'] !== 'archived') $statusActions .= ' ' . action_button('event-status', ['event_id' => $e['id'], 'status' => 'archived'], 'Archive', 'b b--ghost b--sm', 'Archive this event? It is removed from the website but kept here.');
  $statusActions .= ' ' . action_button('event-status', ['event_id' => $e['id'], 'status' => 'delete'], 'Delete', 'b b--danger b--sm', 'Delete this event, its speakers, files, gallery and ALL its registrations? This cannot be undone.');

  $view = $e['status'] === 'published' ? '<a class="b b--ghost b--sm" href="/events/' . h($e['slug']) . '" target="_blank" rel="noopener">View on site ↗</a> ' : '';
  $head = page_head($e['title'], $view . $statusActions, badge($e['status'], status_tone($e['status'])) . ($e['upcoming'] ? ' ' . badge('Upcoming', 'accent') : '') . ($e['registration_open'] ? ' ' . badge('Registration open', 'ok') : '') . ' <span class="muted">/events/' . h($e['slug']) . '</span>');

  $body = match ($tab) {
    'catalog' => tab_catalog($e),
    'details' => tab_details($e),
    'people' => tab_people($e),
    'orgs' => tab_orgs($e),
    'materials' => tab_materials($e),
    'gallery' => tab_gallery($e),
    'registrations' => registrations_table((int)$e['id']),
  };
  admin_layout($e['title'], $head . $tabs . $body, 'events');
}

/* ---------- tab 1: catalogue ------------------------------------------------------------ */

function tab_catalog(array $e): string {
  $id = (int)$e['id'];
  $form = '<form method="post" action="' . h(admin_url('event-save')) . '" class="form-grid">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . input('title', 'Event title', $e['title'], ['required' => true, 'maxlength' => 200, 'class' => 'span2'])
    . input('slug', 'Web address (slug)', $e['slug'], ['required' => true, 'maxlength' => 120, 'pattern' => '[a-z0-9]+(-[a-z0-9]+)*', 'help' => 'Lower-case letters, numbers and dashes. Becomes /events/<em>slug</em>. Changing it changes the page link.'])
    . input('sort_order', 'Position', (string)(int)scalar('SELECT sort_order FROM events WHERE id = ?', [$id]), ['type' => 'number', 'help' => 'Lower numbers show first. Leave 0 to order by newest.'])
    . input('teaser', 'Teaser (card text)', $e['teaser'], ['type' => 'textarea', 'rows' => 3, 'maxlength' => 600, 'class' => 'span2', 'help' => 'One or two sentences shown on the event card.'])
    . input('intro', 'Introduction', $e['intro'], ['type' => 'textarea', 'rows' => 6, 'maxlength' => 5000, 'class' => 'span2', 'help' => 'Opening paragraph on the event page.'])
    . input('hero_alt', 'Banner image description', $e['alt'], ['maxlength' => 255, 'class' => 'span2', 'help' => 'Read by screen readers and search engines.'])
    . '<div class="span2 checks">'
    . checkbox('is_upcoming', 'Upcoming event', $e['upcoming'], 'Shows the Upcoming badge and moves the event to the front.')
    . checkbox('registration_open', 'Registration open', $e['registration_open'], 'Shows the registration form (only while Upcoming is also ticked).')
    . checkbox('attendee_uploads', 'Let registered attendees share photos and documents', $e['attendee_uploads'], 'Uploads are held for your approval before they appear.')
    . '</div>'
    . '<div class="actions span2"><button class="b b--primary" type="submit">Save catalogue</button></div></form>';

  $hero = '<form method="post" action="' . h(admin_url('event-hero')) . '" enctype="multipart/form-data" class="media-form">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . '<div class="media-form__preview">' . thumb($e['image'], $e['alt'], 'thumb thumb--hero') . '</div>'
    . '<div class="media-form__body">' . input('hero', 'Replace banner image', '', ['type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'help' => 'JPG, PNG or WebP. Landscape, at least 1600 × 1000 px works best. It is resized and converted automatically.'])
    . '<button class="b b--primary b--sm" type="submit">Upload banner</button></div></form>';

  $b = $e['brochure'];
  $brochure = '<form method="post" action="' . h(admin_url('event-brochure')) . '" enctype="multipart/form-data" class="media-form">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . '<div class="media-form__preview">' . thumb($b['file'] ?? null, 'Catalogue', 'thumb thumb--doc') . '</div>'
    . '<div class="media-form__body">'
    . ($b ? '<p><strong>' . h($b['title'] ?: 'Event catalogue') . '</strong> <small class="muted">(' . ($b['mime'] === 'application/pdf' ? 'PDF' : 'image') . ')</small></p>' : '<p class="muted">No catalogue uploaded. Visitors see the event without a catalogue card.</p>')
    . input('brochure_title', 'Catalogue title', $b['title'] ?? '', ['maxlength' => 200, 'placeholder' => 'e.g. Eco-Textile Conference 3.0 — Catalogue'])
    . input('brochure', ($b ? 'Replace' : 'Upload') . ' catalogue / brochure', '', ['type' => 'file', 'accept' => 'application/pdf,image/jpeg,image/png,image/webp', 'help' => 'PDF (recommended, up to 25 MB) or a single image.'])
    . '<div class="actions"><button class="b b--primary b--sm" type="submit" name="do" value="save">Save catalogue</button>'
    . ($b ? ' <button class="b b--danger b--sm" type="submit" name="do" value="remove" data-confirm="Remove the catalogue from this event?">Remove</button>' : '')
    . '</div></div></form>';

  return '<div class="grid2"><section class="card span2"><h2 class="card__title">Catalogue</h2>' . $form . '</section>'
    . '<section class="card"><h2 class="card__title">Banner image</h2>' . $hero . '</section>'
    . '<section class="card"><h2 class="card__title">Catalogue / brochure file</h2>' . $brochure . '</section></div>';
}

function ctl_event_save(): never {
  require_post();
  $e = need_event();
  $title = post_str('title', 200);
  $slug = post_str('slug', 120);
  if ($title === '') { flash('error', 'The title cannot be empty.'); back_to_event($e, 'catalog'); }
  if ($slug === '') $slug = slugify($title);
  if (!valid_slug($slug)) { flash('error', 'The web address may only contain lower-case letters, numbers and dashes.'); back_to_event($e, 'catalog'); }
  if (row('SELECT id FROM events WHERE slug = ? AND id <> ?', [$slug, $e['id']])) { flash('error', 'Another event already uses that web address.'); back_to_event($e, 'catalog'); }
  $up = !empty($_POST['is_upcoming']);
  q('UPDATE events SET title=?, slug=?, teaser=?, intro=?, hero_alt=?, is_upcoming=?, registration_open=?, attendee_uploads=?, sort_order=? WHERE id=?', [
    $title, $slug, post_str('teaser', 600) ?: null, post_str('intro', 5000) ?: null, post_str('hero_alt', 255) ?: null,
    $up ? 1 : 0, ($up && !empty($_POST['registration_open'])) ? 1 : 0, !empty($_POST['attendee_uploads']) ? 1 : 0, post_int('sort_order'), $e['id'],
  ]);
  audit('event_save', 'event', $e['id'], ['title' => $title, 'slug' => $slug]);
  flash('ok', 'Catalogue saved.');
  redirect(admin_url('event', ['id' => $e['id'], 'tab' => 'catalog']));
}

function ctl_event_hero(): never {
  require_post();
  $e = need_event();
  try {
    $f = files_list($_FILES['hero'] ?? null)[0] ?? null;
    if (!$f) throw new UploadException('Choose an image first.');
    $s = store_image($f, $e['slug'], 'hero', 2400);
    delete_upload($e['image']);
    q('UPDATE events SET hero_image = ? WHERE id = ?', [$s['path'], $e['id']]);
    audit('event_hero', 'event', $e['id'], ['file' => $s['path']]);
    flash('ok', 'Banner image updated.');
  } catch (UploadException $x) { flash('error', $x->getMessage()); }
  back_to_event($e, 'catalog');
}

function ctl_event_brochure(): never {
  require_post();
  $e = need_event();
  $do = post_str('do', 10);
  $b = $e['brochure'];
  try {
    if ($do === 'remove') {
      if ($b) { q('DELETE FROM event_materials WHERE id = ?', [$b['id']]); delete_upload($b['file']); audit('brochure_remove', 'event', $e['id']); }
      flash('ok', 'Catalogue removed.');
      back_to_event($e, 'catalog');
    }
    $title = post_str('brochure_title', 200);
    $f = files_list($_FILES['brochure'] ?? null)[0] ?? null;
    if ($f) {
      $s = store_image_or_pdf($f, $e['slug'], 'brochure', 2400);
      if ($b) { delete_upload($b['file']); q('UPDATE event_materials SET file=?, mime=?, title=? WHERE id=?', [$s['path'], $s['mime'], $title ?: null, $b['id']]); }
      else q("INSERT INTO event_materials (event_id, kind, title, file, mime, sort_order) VALUES (?,'brochure',?,?,?,0)", [$e['id'], $title ?: null, $s['path'], $s['mime']]);
      audit('brochure_upload', 'event', $e['id'], ['file' => $s['path']]);
      flash('ok', 'Catalogue uploaded.');
    } elseif ($b) {
      q('UPDATE event_materials SET title = ? WHERE id = ?', [$title ?: null, $b['id']]);
      flash('ok', 'Catalogue title saved.');
    } else {
      flash('error', 'Choose a PDF or image to upload.');
    }
  } catch (UploadException $x) { flash('error', $x->getMessage()); }
  back_to_event($e, 'catalog');
}

function ctl_event_status(): never {
  require_post();
  $e = need_event();
  $s = post_str('status', 12);
  if ($s === 'delete') {
    // Remove files we own (uploads/), then the rows (children cascade).
    foreach (array_merge($e['people'], $e['sponsors'], $e['partners'], $e['materials'], $e['gallery'], $e['brochure'] ? [$e['brochure']] : []) as $item) delete_upload($item['photo'] ?? $item['image'] ?? $item['file'] ?? null);
    foreach (rows('SELECT file FROM registration_uploads WHERE event_id = ?', [$e['id']]) as $u) delete_upload($u['file']);
    foreach (rows('SELECT headshot FROM registrations WHERE event_id = ?', [$e['id']]) as $r) delete_upload($r['headshot'] ?? null);
    delete_upload($e['image']);
    q('DELETE FROM events WHERE id = ?', [$e['id']]);
    audit('event_delete', 'event', $e['id'], ['title' => $e['title']]);
    flash('ok', 'Event deleted.');
    redirect(admin_url('events'));
  }
  if (!in_array($s, ['draft', 'published', 'archived'], true)) back_to_event($e, 'catalog');
  if ($s === 'published' && ($e['title'] === '' || $e['title'] === 'New event')) { flash('error', 'Give the event a real title before publishing.'); back_to_event($e, 'catalog'); }
  q('UPDATE events SET status = ? WHERE id = ?', [$s, $e['id']]);
  audit('event_status', 'event', $e['id'], ['status' => $s]);
  flash('ok', $s === 'published' ? 'Event is now live on the website.' : 'Event is no longer shown on the website.');
  back_to_event($e, 'catalog');
}

/* ---------- tab 2: registration & details ------------------------------------------------- */

function tab_details(array $e): string {
  $id = (int)$e['id'];
  $rowsHtml = '';
  $n = 0;
  foreach ($e['details'] as $d) {
    $rowsHtml .= detail_row($n++, $d['label'], $d['value']);
  }
  for ($i = 0; $i < 2; $i++) $rowsHtml .= detail_row($n++, '', '');
  $intro = '<p class="muted">These rows appear in the <strong>Event details</strong> box on the event page, in this order. Typical labels: Date, Time, Venue, Call or Whats App, Email. Empty rows are ignored.</p>';
  $form = '<form method="post" action="' . h(admin_url('details-save')) . '">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . '<div class="kv" id="kvRows">' . $rowsHtml . '</div>'
    . '<div class="actions"><button class="b b--ghost b--sm" type="button" data-add-row="kvRows">+ Add row</button> <button class="b b--primary" type="submit">Save details</button></div></form>';
  $reg = '<p>' . ($e['upcoming'] && $e['registration_open']
      ? 'The registration form is <strong>live</strong> on the event page. Registrations arrive in the <a href="' . h(admin_url('event', ['id' => $id, 'tab' => 'registrations'])) . '">Registrations</a> tab and by email.'
      : 'The registration form is <strong>hidden</strong>. To show it, tick <em>Upcoming event</em> and <em>Registration open</em> in the Catalogue tab.') . '</p>'
    . '<p class="muted">Each registration gets a private link where the attendee can share photos and documents after the event' . ($e['attendee_uploads'] ? '' : ' (currently switched off for this event)') . '.</p>';
  return '<div class="grid2"><section class="card span2"><h2 class="card__title">Event details box</h2>' . $intro . $form . '</section>'
    . '<section class="card span2"><h2 class="card__title">Registration</h2>' . $reg . '</section></div>';
}

function detail_row(int $i, string $label, string $value): string {
  return '<div class="kv__row"><input type="text" name="label[]" value="' . h($label) . '" placeholder="Label (e.g. Venue)" maxlength="80" aria-label="Label">'
    . '<input type="text" name="value[]" value="' . h($value) . '" placeholder="Value" maxlength="255" aria-label="Value">'
    . '<button class="b b--ghost b--sm" type="button" data-remove-row aria-label="Remove row">✕</button></div>';
}

function ctl_details_save(): never {
  require_post();
  $e = need_event();
  $labels = $_POST['label'] ?? []; $values = $_POST['value'] ?? [];
  if (!is_array($labels) || !is_array($values)) back_to_event($e, 'details');
  $pdo = db_required();
  $pdo->beginTransaction();
  q('DELETE FROM event_details WHERE event_id = ?', [$e['id']]);
  $n = 0;
  foreach ($labels as $i => $l) {
    $l = is_string($l) ? mb_substr(trim($l), 0, 80) : ''; $v = is_string($values[$i] ?? '') ? mb_substr(trim($values[$i]), 0, 255) : '';
    if ($l === '' || $v === '') continue;
    q('INSERT INTO event_details (event_id, label, value, sort_order) VALUES (?,?,?,?)', [$e['id'], $l, $v, $n++]);
    if ($n >= 30) break;
  }
  touch_event($e['id']);
  $pdo->commit();
  audit('details_save', 'event', $e['id'], ['rows' => $n]);
  flash('ok', 'Event details saved.');
  back_to_event($e, 'details');
}

/* ---------- tab 3: speakers & panel --------------------------------------------------------- */

function tab_people(array $e): string {
  $id = (int)$e['id'];
  $groups = array_values(array_unique(array_merge(['Keynote Speakers', 'Speakers', 'Panelist', 'Moderator', 'Guests of Honour'], array_map(fn($p) => $p['role'], $e['people']))));
  $add = '<form method="post" action="' . h(admin_url('person-save')) . '" enctype="multipart/form-data" class="form-grid form-grid--4">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . input('role', 'Group', 'Speakers', ['maxlength' => 80, 'help' => 'e.g. Keynote Speakers, Panelist', 'key' => 'new'])
    . input('name', 'Full name', '', ['required' => true, 'maxlength' => 160, 'key' => 'new'])
    . input('designation', 'Designation', '', ['maxlength' => 200, 'key' => 'new'])
    . input('organization', 'Organization', '', ['maxlength' => 200, 'key' => 'new'])
    . input('photo', 'Photo', '', ['type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'key' => 'new', 'class' => 'span2'])
    . '<div class="actions span2"><button class="b b--primary" type="submit">Add person</button></div>'
    . '<datalist id="roleGroups">' . implode('', array_map(fn($g) => '<option value="' . h($g) . '">', $groups)) . '</datalist></form>';

  $list = '';
  $count = count($e['people']);
  foreach ($e['people'] as $i => $p) {
    $pid = (int)$p['id'];
    $list .= '<form method="post" action="' . h(admin_url('person-save')) . '" enctype="multipart/form-data" class="row-edit">' . csrf_field()
      . '<input type="hidden" name="event_id" value="' . $id . '"><input type="hidden" name="id" value="' . $pid . '">'
      . '<div class="row-edit__media">' . thumb($p['photo'], $p['name'], 'thumb thumb--person') . '<label class="filebtn">Replace photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div>'
      . '<div class="row-edit__fields">'
      . input('role', 'Group', $p['role'], ['maxlength' => 80, 'key' => (string)$pid])
      . input('name', 'Full name', $p['name'], ['required' => true, 'maxlength' => 160, 'key' => (string)$pid])
      . input('designation', 'Designation', $p['designation'] ?? '', ['maxlength' => 200, 'key' => (string)$pid])
      . input('organization', 'Organization', $p['organization'] ?? '', ['maxlength' => 200, 'key' => (string)$pid])
      . '</div>'
      . '<div class="row-edit__acts"><button class="b b--primary b--sm" type="submit">Save</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="up"' . ($i === 0 ? ' disabled' : '') . ' formaction="' . h(admin_url('person-move')) . '" aria-label="Move up">↑</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="down"' . ($i === $count - 1 ? ' disabled' : '') . ' formaction="' . h(admin_url('person-move')) . '" aria-label="Move down">↓</button>'
      . '<button class="b b--danger b--sm" type="submit" formaction="' . h(admin_url('person-delete')) . '" data-confirm="Remove ' . h($p['name']) . ' from this event?">Delete</button></div>'
      . '</form>';
  }
  return '<section class="card"><h2 class="card__title">Add a speaker, panelist or guest</h2>' . $add . '</section>'
    . '<section class="card"><h2 class="card__title">People on this event <span class="muted">(' . $count . ')</span></h2>'
    . '<p class="muted">Edit any field and press <strong>Save</strong> on that row. People are grouped on the page by their Group label, in this order.</p>'
    . ($list ?: '<p class="muted">No one added yet.</p>') . '</section>';
}

function ctl_person_save(): never {
  require_post();
  $e = need_event();
  $pid = post_int('id');
  $name = post_str('name', 160);
  if ($name === '') { flash('error', 'A name is required.'); back_to_event($e, 'people'); }
  $role = post_str('role', 80) ?: 'Speakers';
  $des = post_str('designation', 200) ?: null;
  $org = post_str('organization', 200) ?: null;
  $existing = $pid ? row('SELECT * FROM event_people WHERE id = ? AND event_id = ?', [$pid, $e['id']]) : null;
  try {
    $photo = $existing['photo'] ?? null;
    $f = files_list($_FILES['photo'] ?? null)[0] ?? null;
    if ($f) { $s = store_image($f, $e['slug'], 'people', 900); if ($existing) delete_upload($existing['photo']); $photo = $s['path']; }
    if ($existing) {
      q('UPDATE event_people SET role_group=?, name=?, designation=?, organization=?, photo=? WHERE id=?', [$role, $name, $des, $org, $photo, $pid]);
      audit('person_update', 'event_people', $pid, ['name' => $name]);
      flash('ok', 'Saved ' . $name . '.');
    } else {
      q('INSERT INTO event_people (event_id, role_group, name, designation, organization, photo, sort_order) VALUES (?,?,?,?,?,?,?)', [$e['id'], $role, $name, $des, $org, $photo, next_sort('event_people', $e['id'])]);
      audit('person_add', 'event_people', last_id(), ['name' => $name]);
      flash('ok', 'Added ' . $name . '.');
    }
    touch_event($e['id']);
  } catch (UploadException $x) { flash('error', $x->getMessage()); }
  back_to_event($e, 'people');
}

function ctl_person_delete(): never {
  require_post();
  $e = need_event();
  $p = row('SELECT * FROM event_people WHERE id = ? AND event_id = ?', [post_int('id'), $e['id']]);
  if ($p) { delete_upload($p['photo']); q('DELETE FROM event_people WHERE id = ?', [$p['id']]); renumber('event_people', $e['id']); audit('person_delete', 'event_people', (int)$p['id'], ['name' => $p['name']]); touch_event($e['id']); flash('ok', 'Removed ' . $p['name'] . '.'); }
  back_to_event($e, 'people');
}

function ctl_person_move(): never {
  require_post();
  $e = need_event();
  move_row('event_people', $e['id'], post_int('id'), post_str('move', 4) === 'up' ? 'up' : 'down');
  back_to_event($e, 'people');
}

/* ---------- sponsors & partners -------------------------------------------------------------- */

function tab_orgs(array $e): string {
  $id = (int)$e['id'];
  $add = '<form method="post" action="' . h(admin_url('org-save')) . '" enctype="multipart/form-data" class="form-grid form-grid--4">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . select('kind', 'Type', ['sponsor' => 'Sponsor', 'partner' => 'Partner'], 'sponsor', ['key' => 'new'])
    . input('group_label', 'Tier / group label', '', ['maxlength' => 120, 'help' => 'e.g. Platinum Sponsor, In Collaboration with', 'key' => 'new'])
    . input('name', 'Organization name', '', ['required' => true, 'maxlength' => 200, 'key' => 'new'])
    . input('url', 'Website (optional)', '', ['type' => 'url', 'maxlength' => 255, 'placeholder' => 'https://', 'key' => 'new'])
    . input('logo', 'Logo', '', ['type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'key' => 'new', 'class' => 'span2', 'help' => 'PNG with transparent background works best.'])
    . '<div class="actions span2"><button class="b b--primary" type="submit">Add</button></div></form>';

  $list = '';
  foreach (['sponsor' => $e['sponsors'], 'partner' => $e['partners']] as $kind => $items) {
    $list .= '<h3 class="card__sub">' . ucfirst($kind) . 's <span class="muted">(' . count($items) . ')</span></h3>';
    if (!$items) { $list .= '<p class="muted">None yet.</p>'; continue; }
    $n = count($items);
    foreach ($items as $i => $o) {
      $oid = (int)$o['id'];
      $list .= '<form method="post" action="' . h(admin_url('org-save')) . '" enctype="multipart/form-data" class="row-edit">' . csrf_field()
        . '<input type="hidden" name="event_id" value="' . $id . '"><input type="hidden" name="id" value="' . $oid . '">'
        . '<div class="row-edit__media">' . thumb($o['image'], $o['name'], 'thumb thumb--logo') . '<label class="filebtn">Replace logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label></div>'
        . '<div class="row-edit__fields">'
        . select('kind', 'Type', ['sponsor' => 'Sponsor', 'partner' => 'Partner'], $kind, ['key' => (string)$oid])
        . input('group_label', 'Tier / group', $o['group'], ['maxlength' => 120, 'key' => (string)$oid])
        . input('name', 'Name', $o['name'], ['required' => true, 'maxlength' => 200, 'key' => (string)$oid])
        . input('url', 'Website', $o['url'] ?? '', ['type' => 'url', 'maxlength' => 255, 'key' => (string)$oid])
        . '</div>'
        . '<div class="row-edit__acts"><button class="b b--primary b--sm" type="submit">Save</button>'
        . '<button class="b b--ghost b--sm" type="submit" name="move" value="up"' . ($i === 0 ? ' disabled' : '') . ' formaction="' . h(admin_url('org-move')) . '" aria-label="Move up">↑</button>'
        . '<button class="b b--ghost b--sm" type="submit" name="move" value="down"' . ($i === $n - 1 ? ' disabled' : '') . ' formaction="' . h(admin_url('org-move')) . '" aria-label="Move down">↓</button>'
        . '<button class="b b--danger b--sm" type="submit" formaction="' . h(admin_url('org-delete')) . '" data-confirm="Remove ' . h($o['name']) . '?">Delete</button></div></form>';
    }
  }
  return '<section class="card"><h2 class="card__title">Add a sponsor or partner</h2>' . $add . '</section>'
    . '<section class="card"><h2 class="card__title">Sponsors &amp; partners</h2><p class="muted">Logos are shown in the side column of the event page, grouped by tier label.</p>' . $list . '</section>';
}

function ctl_org_save(): never {
  require_post();
  $e = need_event();
  $oid = post_int('id');
  $name = post_str('name', 200);
  if ($name === '') { flash('error', 'A name is required.'); back_to_event($e, 'orgs'); }
  $kind = post_str('kind', 10) === 'partner' ? 'partner' : 'sponsor';
  $group = post_str('group_label', 120) ?: null;
  $url = post_str('url', 255);
  if ($url !== '' && !preg_match('#^https?://[^\s]+$#i', $url)) { flash('error', 'The website must start with http:// or https://'); back_to_event($e, 'orgs'); }
  $existing = $oid ? row('SELECT * FROM event_orgs WHERE id = ? AND event_id = ?', [$oid, $e['id']]) : null;
  try {
    $logo = $existing['logo'] ?? null;
    $f = files_list($_FILES['logo'] ?? null)[0] ?? null;
    if ($f) { $s = store_image($f, $e['slug'], 'logos', 800); if ($existing) delete_upload($existing['logo']); $logo = $s['path']; }
    if (!$logo) { flash('error', 'Please upload a logo.'); back_to_event($e, 'orgs'); }
    if ($existing) {
      q('UPDATE event_orgs SET kind=?, group_label=?, name=?, logo=?, url=? WHERE id=?', [$kind, $group, $name, $logo, $url ?: null, $oid]);
      audit('org_update', 'event_orgs', $oid, ['name' => $name]);
    } else {
      q('INSERT INTO event_orgs (event_id, kind, group_label, name, logo, url, sort_order) VALUES (?,?,?,?,?,?,?)', [$e['id'], $kind, $group, $name, $logo, $url ?: null, next_sort('event_orgs', $e['id'], 'kind = ?', [$kind])]);
      audit('org_add', 'event_orgs', last_id(), ['name' => $name]);
    }
    touch_event($e['id']);
    flash('ok', 'Saved ' . $name . '.');
  } catch (UploadException $x) { flash('error', $x->getMessage()); }
  back_to_event($e, 'orgs');
}

function ctl_org_delete(): never {
  require_post();
  $e = need_event();
  $o = row('SELECT * FROM event_orgs WHERE id = ? AND event_id = ?', [post_int('id'), $e['id']]);
  if ($o) { delete_upload($o['logo']); q('DELETE FROM event_orgs WHERE id = ?', [$o['id']]); audit('org_delete', 'event_orgs', (int)$o['id'], ['name' => $o['name']]); touch_event($e['id']); flash('ok', 'Removed ' . $o['name'] . '.'); }
  back_to_event($e, 'orgs');
}

function ctl_org_move(): never {
  require_post();
  $e = need_event();
  $o = row('SELECT kind FROM event_orgs WHERE id = ? AND event_id = ?', [post_int('id'), $e['id']]);
  if ($o) move_row('event_orgs', $e['id'], post_int('id'), post_str('move', 4) === 'up' ? 'up' : 'down', 'kind = ?', [$o['kind']]);
  back_to_event($e, 'orgs');
}

/* ---------- agenda, posters, documents -------------------------------------------------- */

function tab_materials(array $e): string {
  $id = (int)$e['id'];
  $add = '<form method="post" action="' . h(admin_url('material-upload')) . '" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . select('kind', 'Type', ['agenda' => 'Agenda page', 'poster' => 'Poster', 'document' => 'Document'], 'agenda', ['key' => 'new'])
    . input('files', 'Files', '', ['type' => 'file', 'multiple' => true, 'accept' => 'application/pdf,image/jpeg,image/png,image/webp', 'key' => 'new', 'help' => 'Images or PDFs. Select several at once.'])
    . '<div class="actions span2"><button class="b b--primary" type="submit">Upload</button></div></form>';
  $list = '';
  $n = count($e['materials']);
  foreach ($e['materials'] as $i => $m) {
    $mid = (int)$m['id'];
    $list .= '<form method="post" action="' . h(admin_url('material-save')) . '" class="row-edit">' . csrf_field()
      . '<input type="hidden" name="event_id" value="' . $id . '"><input type="hidden" name="id" value="' . $mid . '">'
      . '<div class="row-edit__media">' . thumb($m['file'], $m['title'] ?? '', 'thumb thumb--doc') . '</div>'
      . '<div class="row-edit__fields">'
      . select('kind', 'Type', ['agenda' => 'Agenda page', 'poster' => 'Poster', 'document' => 'Document'], $m['kind'], ['key' => (string)$mid])
      . input('title', 'Title (optional)', $m['title'] ?? '', ['maxlength' => 200, 'key' => (string)$mid, 'class' => 'span2'])
      . '</div>'
      . '<div class="row-edit__acts"><button class="b b--primary b--sm" type="submit">Save</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="up"' . ($i === 0 ? ' disabled' : '') . ' formaction="' . h(admin_url('material-move')) . '" aria-label="Move up">↑</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="down"' . ($i === $n - 1 ? ' disabled' : '') . ' formaction="' . h(admin_url('material-move')) . '" aria-label="Move down">↓</button>'
      . '<button class="b b--danger b--sm" type="submit" formaction="' . h(admin_url('material-delete')) . '" data-confirm="Delete this file?">Delete</button></div></form>';
  }
  return '<section class="card"><h2 class="card__title">Upload agenda pages, posters or documents</h2>' . $add . '</section>'
    . '<section class="card"><h2 class="card__title">Poster &amp; agenda <span class="muted">(' . $n . ')</span></h2><p class="muted">Shown in the "Poster &amp; agenda" section of the event page, in this order. The catalogue/brochure is managed in the Catalogue tab.</p>' . ($list ?: '<p class="muted">Nothing uploaded yet.</p>') . '</section>';
}

function ctl_material_upload(): never {
  require_post();
  $e = need_event();
  $kind = post_str('kind', 10);
  if (!in_array($kind, ['agenda', 'poster', 'document'], true)) $kind = 'agenda';
  $files = files_list($_FILES['files'] ?? null);
  if (!$files) { flash('error', 'Choose at least one file.'); back_to_event($e, 'materials'); }
  $ok = 0; $errs = [];
  foreach (array_slice($files, 0, 30) as $f) {
    try {
      $s = store_image_or_pdf($f, $e['slug'], 'materials', 2400);
      q('INSERT INTO event_materials (event_id, kind, title, file, mime, sort_order) VALUES (?,?,?,?,?,?)', [$e['id'], $kind, null, $s['path'], $s['mime'], next_sort('event_materials', $e['id'], "kind <> 'brochure'")]);
      $ok++;
    } catch (UploadException $x) { $errs[] = clean_filename((string)$f['name']) . ': ' . $x->getMessage(); }
  }
  if ($ok) { audit('material_upload', 'event', $e['id'], ['count' => $ok]); touch_event($e['id']); flash('ok', $ok . ' file' . ($ok === 1 ? '' : 's') . ' uploaded.'); }
  foreach ($errs as $m) flash('error', $m);
  back_to_event($e, 'materials');
}

function ctl_material_save(): never {
  require_post();
  $e = need_event();
  $kind = post_str('kind', 10);
  if (!in_array($kind, ['agenda', 'poster', 'document'], true)) $kind = 'agenda';
  q("UPDATE event_materials SET kind = ?, title = ? WHERE id = ? AND event_id = ? AND kind <> 'brochure'", [$kind, post_str('title', 200) ?: null, post_int('id'), $e['id']]);
  touch_event($e['id']);
  flash('ok', 'Saved.');
  back_to_event($e, 'materials');
}

function ctl_material_delete(): never {
  require_post();
  $e = need_event();
  $m = row("SELECT * FROM event_materials WHERE id = ? AND event_id = ? AND kind <> 'brochure'", [post_int('id'), $e['id']]);
  if ($m) { delete_upload($m['file']); q('DELETE FROM event_materials WHERE id = ?', [$m['id']]); audit('material_delete', 'event_materials', (int)$m['id']); touch_event($e['id']); flash('ok', 'File deleted.'); }
  back_to_event($e, 'materials');
}

function ctl_material_move(): never {
  require_post();
  $e = need_event();
  move_row('event_materials', $e['id'], post_int('id'), post_str('move', 4) === 'up' ? 'up' : 'down', "kind <> 'brochure'");
  back_to_event($e, 'materials');
}

/* ---------- photo gallery ---------------------------------------------------------------------- */

function tab_gallery(array $e): string {
  $id = (int)$e['id'];
  $add = '<form method="post" action="' . h(admin_url('gallery-upload')) . '" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<input type="hidden" name="event_id" value="' . $id . '">'
    . input('files', 'Photos', '', ['type' => 'file', 'multiple' => true, 'accept' => 'image/jpeg,image/png,image/webp', 'key' => 'new', 'class' => 'span2', 'help' => 'JPG, PNG or WebP, up to 15 MB each. Select many at once; they are resized and converted automatically.'])
    . '<div class="actions span2"><button class="b b--primary" type="submit">Upload photos</button></div></form>';
  $grid = '';
  $n = count($e['gallery']);
  foreach ($e['gallery'] as $i => $g) {
    $gid = (int)$g['id'];
    $grid .= '<form method="post" action="' . h(admin_url('gallery-save')) . '" class="photo">' . csrf_field()
      . '<input type="hidden" name="event_id" value="' . $id . '"><input type="hidden" name="id" value="' . $gid . '">'
      . '<a class="photo__img" href="/' . h($g['file']) . '" target="_blank" rel="noopener"><img src="/' . h($g['file']) . '" alt="" loading="lazy"></a>'
      . '<input class="photo__cap" type="text" name="caption" value="' . h($g['caption'] ?? '') . '" placeholder="Caption (optional)" maxlength="255" aria-label="Caption">'
      . '<div class="photo__acts"><button class="b b--ghost b--sm" type="submit" aria-label="Save caption">Save</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="up"' . ($i === 0 ? ' disabled' : '') . ' formaction="' . h(admin_url('gallery-move')) . '" aria-label="Move earlier">←</button>'
      . '<button class="b b--ghost b--sm" type="submit" name="move" value="down"' . ($i === $n - 1 ? ' disabled' : '') . ' formaction="' . h(admin_url('gallery-move')) . '" aria-label="Move later">→</button>'
      . '<button class="b b--danger b--sm" type="submit" formaction="' . h(admin_url('gallery-delete')) . '" data-confirm="Delete this photo?">✕</button></div></form>';
  }
  return '<section class="card"><h2 class="card__title">Add photos</h2>' . $add . '</section>'
    . '<section class="card"><h2 class="card__title">Photo gallery <span class="muted">(' . $n . ')</span></h2>'
    . '<p class="muted">Photos shared by attendees appear here once you approve them in <a href="' . h(admin_url('moderation', ['event' => $id])) . '">Uploads to review</a>.</p>'
    . ($grid ? '<div class="photos">' . $grid . '</div>' : '<p class="muted">No photos yet — the event page shows a "gallery coming soon" note.</p>') . '</section>';
}

function ctl_gallery_upload(): never {
  require_post();
  $e = need_event();
  $files = files_list($_FILES['files'] ?? null);
  if (!$files) { flash('error', 'Choose at least one photo.'); back_to_event($e, 'gallery'); }
  $ok = 0; $errs = [];
  foreach (array_slice($files, 0, 60) as $f) {
    try {
      $s = store_image($f, $e['slug'], 'photos', 2400);
      q('INSERT INTO event_gallery (event_id, file, sort_order) VALUES (?,?,?)', [$e['id'], $s['path'], next_sort('event_gallery', $e['id'])]);
      $ok++;
    } catch (UploadException $x) { $errs[] = clean_filename((string)$f['name']) . ': ' . $x->getMessage(); }
  }
  if ($ok) { audit('gallery_upload', 'event', $e['id'], ['count' => $ok]); touch_event($e['id']); flash('ok', $ok . ' photo' . ($ok === 1 ? '' : 's') . ' added.'); }
  foreach ($errs as $m) flash('error', $m);
  back_to_event($e, 'gallery');
}

function ctl_gallery_save(): never {
  require_post();
  $e = need_event();
  q('UPDATE event_gallery SET caption = ? WHERE id = ? AND event_id = ?', [post_str('caption', 255) ?: null, post_int('id'), $e['id']]);
  flash('ok', 'Caption saved.');
  back_to_event($e, 'gallery');
}

function ctl_gallery_delete(): never {
  require_post();
  $e = need_event();
  $g = row('SELECT * FROM event_gallery WHERE id = ? AND event_id = ?', [post_int('id'), $e['id']]);
  if ($g) { delete_upload($g['file']); q('DELETE FROM event_gallery WHERE id = ?', [$g['id']]); renumber('event_gallery', $e['id']); audit('gallery_delete', 'event_gallery', (int)$g['id']); touch_event($e['id']); flash('ok', 'Photo deleted.'); }
  back_to_event($e, 'gallery');
}

function ctl_gallery_move(): never {
  require_post();
  $e = need_event();
  move_row('event_gallery', $e['id'], post_int('id'), post_str('move', 4) === 'up' ? 'up' : 'down');
  back_to_event($e, 'gallery');
}
