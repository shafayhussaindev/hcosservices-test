<?php
declare(strict_types=1);

/* ---------- public pages ----------------------------------------------------------------
   The build writes the three dynamic pages as PHP views under
   _app/views/pages/. Everything static in them is already resolved; only the
   event-driven fragments are left as `<?= $v['KEY'] ?>`. These functions
   compute $v and include the view. */

function page_headers(int $status = 200): void {
  http_response_code($status);
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-cache');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('X-Frame-Options: SAMEORIGIN');
}

function page_view(string $name, array $v, int $status = 200): never {
  $file = HCOS_APP . '/views/pages/' . $name . '.php';
  if (!is_file($file)) { page_404(); }
  page_headers($status);
  include $file;
  exit;
}

function page_home(): never {
  global $HCOS;
  $db = db() !== null;
  $events = repo_events_public();
  $popup = [];
  foreach ($events as $e) {
    if (!$e['upcoming']) continue;
    $details = array_column($e['details'], 'value', 'label');
    $popup[] = ['slug' => $e['slug'], 'title' => $e['title'], 'image' => $e['image'], 'alt' => $e['alt'],
      'date' => $details['Date'] ?? '', 'endDate' => $details['End date'] ?? '', 'registrationOpen' => $e['registration_open']];
  }
  page_view('home', ['EVENT_CARDS' => render_event_cards($events),
    'FORM_ENDPOINT' => $db ? '/api/contact' : '',
    'FORM_FALLBACK_EMAIL' => h((string)$HCOS['notify_email']),
    'FORM_HIDDEN' => $db ? antibot_fields() : '',
    'POPUP_EVENTS' => h(json_encode($popup, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR))]);
}

function page_events(): never {
  page_view('events', ['EVENT_CARDS' => render_event_cards(repo_events_public())]);
}

function page_event(string $slug, bool $registration = false): never {
  $e = repo_event_by_slug($slug);
  if (!$e) page_404();
  $open = $e['upcoming'] && $e['registration_open'];
  if ($registration && !$open) page_404();
  $eventUrl = 'events/' . $e['slug'];
  $details = array_column($e['details'], 'value', 'label');
  $all = repo_events_public();
  $others = array_slice(array_values(array_filter($all, fn($x) => $x['slug'] !== $e['slug'])), 0, 3);
  $desc = mb_substr(str_replace('"', "'", $e['intro'] ?: $e['teaser']), 0, 180);
  page_view($registration ? 'event-registration' : 'event-detail', [
    'PAGE_TITLE' => esc_js(($registration ? 'Registration: ' : '') . $e['title'] . ' — HCOS'),
    'PAGE_DESC' => esc_js($desc),
    'CANONICAL' => hcos_url($eventUrl . ($registration ? '/register' : '')),
    'EVENT_URL' => '/' . $eventUrl,
    'REGISTRATION_URL' => $open ? '/' . $eventUrl . '/register' : '',
    'EVENT_ACTION' => render_event_action($e),
    'COUNTDOWN_DATE' => $e['upcoming'] ? h($details['Date'] ?? '') : '',
    'TITLE' => h($e['title']),
    'EYEBROW' => $e['upcoming'] ? 'Upcoming event' : 'Event',
    'IMAGE' => h('/' . $e['image']),
    'ALT' => esc_js($e['alt']),
    'INTRO' => esc_js($e['intro'] ?: $e['teaser']),
    'BROCHURE' => render_event_brochure($e),
    'REGISTRATION' => $registration ? render_event_registration($e) : '',
    'DETAILS' => render_event_details($e),
    'SPONSORS' => render_logo_groups('Sponsors', $e['sponsors']),
    'PARTNERS' => render_logo_groups('Partners', $e['partners']),
    'PEOPLE' => render_event_people($e),
    'MATERIALS' => render_event_materials($e),
    'GALLERY' => render_event_gallery($e),
    'MORE_CARDS' => render_event_cards($others),
  ]);
}

function page_contact(): never {
  global $HCOS;
  $db = db() !== null;
  page_view('contact', [
    'FORM_ENDPOINT' => $db ? '/api/contact' : '',
    'FORM_FALLBACK_EMAIL' => h((string)$HCOS['notify_email']),
    'FORM_HIDDEN' => $db ? antibot_fields() : '',
  ]);
}

function page_404(): never {
  page_headers(404);
  $f = HCOS_ROOT . '/404.html';
  if (is_file($f)) readfile($f); else echo 'Not found';
  exit;
}

/* ---------- attendee share page ------------------------------------------------------ */

function page_share(string $slug, string $token): never {
  if (!db()) page_404();
  $reg = row('SELECT r.*, e.slug, e.title, e.attendee_uploads, e.status AS event_status FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.upload_token = ? AND e.slug = ?', [$token, $slug]);
  if (!$reg || $reg['event_status'] !== 'published') page_404();
  header('X-Robots-Tag: noindex, nofollow');
  header('Referrer-Policy: no-referrer');

  $files = rows('SELECT kind, original_name, size, status, created_at FROM registration_uploads WHERE registration_id = ? ORDER BY created_at DESC', [(int)$reg['id']]);
  $open = (int)$reg['attendee_uploads'] && $reg['status'] !== 'cancelled';
  $first = h(explode(' ', trim((string)$reg['name']))[0] ?: 'there');

  $body = '<div class="prose"><p class="lead reveal">Hello ' . $first . ' — you are registered for <strong>' . h($reg['title']) . '</strong>. '
        . 'The HCOS events team will be in touch with confirmation and joining instructions. A copy of this link has been sent to <strong>' . h($reg['email']) . '</strong>.</p></div>';

  if ($open) {
    $body .= '
      <section class="share reveal" aria-labelledby="shareTitle">
        <div class="share__head">
          <span class="register__badge">Your private link</span>
          <h2 class="register__title" id="shareTitle">Share your photos &amp; documents</h2>
          <p class="register__text">Took photos at the event, or have a presentation or document to share? Upload them here. Everything is reviewed by HCOS before it appears on the event page. Photos: JPG, PNG or WebP up to 3 MB each (they are compressed automatically). Documents: PDF up to 10 MB. Up to 20 files at a time.</p>
        </div>
        <form class="form share__form" data-endpoint="/api/upload" data-reload="1" data-subject="Event upload" novalidate>
          <input type="hidden" name="token" value="' . h($token) . '">
          ' . antibot_fields() . '
          <div class="form__row">
            <div class="form__field form__field--full">
              <label class="share__drop" for="shareFiles">
                <span class="share__drop-title">Choose files</span>
                <span class="share__drop-text">or drag and drop them here</span>
                <input id="shareFiles" name="files[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" required>
              </label>
            </div>
          </div>
          <button class="btn btn--primary" type="submit">Upload ' . HCOS_ARROW . '</button>
          <p class="form__notice" role="status" hidden></p>
        </form>
      </section>';
  } else {
    $body .= '<aside class="placeholder reveal"><span class="placeholder__badge">Closed</span><h2 class="placeholder__title">Uploads are closed for this event</h2><p class="placeholder__text">Thank you for your interest. Photos and documents are no longer being collected for this event.</p></aside>';
  }

  if ($files) {
    $rowsHtml = '';
    foreach ($files as $f) {
      $rowsHtml .= '<li class="upload-list__item"><span class="upload-list__name">' . h($f['original_name']) . '</span>'
        . '<span class="upload-list__meta">' . ($f['kind'] === 'photo' ? 'Photo' : 'Document') . ' · ' . fmt_bytes((int)$f['size']) . ' · ' . h(fmt_dt($f['created_at'])) . '</span>'
        . '<span class="upload-list__status upload-list__status--' . h($f['status']) . '">' . ucfirst($f['status']) . '</span></li>';
    }
    $body .= '<section class="event-block reveal"><h2 class="event-block__title">Your uploads</h2><ul class="upload-list">' . $rowsHtml . '</ul></section>';
  }

  page_view('share', ['SHARE_TITLE' => 'You are registered', 'SHARE_EVENT' => h($reg['slug']), 'SHARE_BODY' => $body]);
}
