<?php
declare(strict_types=1);

/* ---------- event fragments ----------------------------------------------------------
   Straight port of the event functions in build.js. Markup and whitespace are
   kept identical on purpose: `dist` from the static build and the PHP output
   for the same data must diff clean, which is how the port is verified.     */

const HCOS_ARROW = '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">'
  . '<path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" stroke-width="1.6" '
  . 'stroke-linecap="round" stroke-linejoin="round"/></svg>';
const HCOS_ARROW_SM = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">'
  . '<path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" stroke-width="1.6" '
  . 'stroke-linecap="round" stroke-linejoin="round"/></svg>';

/** build.js `esc` — escapes & < > " only (not '), matched exactly for diff parity. */
function esc_js(?string $s): string {
  return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], (string)$s);
}

function render_event_cards(array $list, array $delays = [0, 90, 180]): string {
  $out = [];
  foreach (array_values($list) as $i => $e) {
    $flag = $e['upcoming'] ? '<span class="event-card__flag">Upcoming</span>' : '';
    $out[] = "
      <a class=\"event-card reveal\" style=\"--d:{$delays[$i % count($delays)]}ms\" href=\"/events/{$e['slug']}\">
        <div class=\"event-card__media\">
          <img src=\"/{$e['image']}\" alt=\"" . esc_js($e['alt']) . "\" loading=\"lazy\" decoding=\"async\">
        </div>
        <div class=\"event-card__body\">
          {$flag}
          <h3 class=\"event-card__title\">{$e['title']}</h3>
          <p class=\"event-card__desc\">{$e['teaser']}</p>
          <span class=\"event-card__link\">View More
            " . HCOS_ARROW_SM . "
          </span>
        </div>
      </a>";
  }
  return implode("\n", $out);
}

const HCOS_DETAIL_LABELS = ['Theme' => 'Theme', 'Date' => 'Date', 'Time' => 'Time', 'Venue' => 'Venue', 'Call or Whats App' => 'Call / WhatsApp', 'Email' => 'Email', 'Contact' => 'Contact'];

function render_event_details(array $e): string {
  $rows = array_values(array_filter($e['details'], fn($d) => (string)$d['value'] !== ''));
  if (!$rows) return '';
  $cell = function (string $k, string $v): string {
    if (preg_match('/email/i', $k)) return '<a href="mailto:' . esc_js($v) . '">' . esc_js($v) . '</a>';
    if (preg_match('/call|whats|contact/i', $k)) return '<a href="tel:' . esc_js(preg_replace('/[^\d+]/', '', $v) ?? '') . '">' . esc_js($v) . '</a>';
    return esc_js($v);
  };
  $items = array_map(fn($d) => '<div><dt>' . esc_js(HCOS_DETAIL_LABELS[$d['label']] ?? $d['label']) . '</dt><dd>' . $cell($d['label'], $d['value']) . '</dd></div>', $rows);
  return "
        <div class=\"event-side reveal\">
          <h2 class=\"event-side__title\">Event details</h2>
          <dl class=\"event-dl\">
            " . implode("\n            ", $items) . "
          </dl>
        </div>";
}

function render_logo_groups(string $title, array $list): string {
  if (!$list) return '';
  $groups = [];
  foreach ($list as $l) $groups[(string)($l['group'] ?? '')][] = $l;
  $blocks = '';
  foreach ($groups as $g => $items) {
    $imgs = array_map(fn($l) => '<img src="/' . $l['image'] . '" alt="' . esc_js($l['name'] ?: ($g ?: $title)) . '" loading="lazy" decoding="async">', $items);
    $blocks .= "
          " . ($g !== '' ? '<h3 class="event-side__sub">' . esc_js($g) . '</h3>' : '') . "
          <div class=\"logo-row\">
            " . implode("\n            ", $imgs) . "
          </div>";
  }
  return "
        <div class=\"event-side reveal\">
          <h2 class=\"event-side__title\">{$title}</h2>
          {$blocks}
        </div>";
}

function render_event_people(array $e): string {
  if (!$e['people']) return '';
  $groups = [];
  foreach ($e['people'] as $p) $groups[$p['role'] ?: 'Speakers'][] = $p;
  $initials = function (string $n): string {
    $w = array_slice(array_values(array_filter(preg_split('/\s+/', $n) ?: [])), 0, 2);
    return mb_strtoupper(implode('', array_map(fn($x) => mb_substr($x, 0, 1), $w)));
  };
  $blocks = '';
  foreach ($groups as $g => $items) {
    $figs = '';
    foreach ($items as $p) {
      $photo = $p['photo']
        ? '<img class="person__photo" src="/' . $p['photo'] . '" alt="' . esc_js($p['name']) . '" loading="lazy" decoding="async">'
        : '<span class="person__photo person__photo--initials" aria-hidden="true">' . esc_js($initials($p['name'])) . '</span>';
      $figs .= "
            <figure class=\"person\">
              {$photo}
              <figcaption>
                <strong class=\"person__name\">" . esc_js($p['name']) . "</strong>
                " . ($p['designation'] ? '<span class="person__role">' . esc_js($p['designation']) . '</span>' : '') . "
                " . ($p['organization'] ? '<span class="person__org">' . esc_js($p['organization']) . '</span>' : '') . "
              </figcaption>
            </figure>";
    }
    $blocks .= "
          <h3 class=\"event-block__sub\">" . esc_js($g) . "</h3>
          <div class=\"people-grid\">
            {$figs}
          </div>";
  }
  return "
        <section class=\"event-block reveal\">
          <h2 class=\"event-block__title\">Speakers &amp; panel</h2>
          {$blocks}
        </section>";
}

function render_event_materials(array $e): string {
  if (!$e['materials']) return '';
  $items = [];
  foreach ($e['materials'] as $m) {
    $isPdf = $m['mime'] === 'application/pdf';
    $label = esc_js($m['title'] ?: ($e['title'] . ' — poster or agenda'));
    $items[] = $isPdf
      ? '<a class="materials__item materials__item--doc" href="/' . $m['file'] . '" target="_blank" rel="noopener"><span class="materials__doc"><span class="materials__doc-icon" aria-hidden="true">PDF</span>' . $label . '</span></a>'
      : '<a class="materials__item" href="/' . $m['file'] . '" target="_blank" rel="noopener"><img src="/' . $m['file'] . '" alt="' . $label . '" loading="lazy" decoding="async"></a>';
  }
  return "
        <section class=\"event-block reveal\">
          <h2 class=\"event-block__title\">Poster &amp; agenda</h2>
          <div class=\"materials\">
            " . implode("\n            ", $items) . "
          </div>
        </section>";
}

function render_event_gallery(array $e): string {
  $photos = $e['gallery'];
  if (!$photos) {
    return "
        <aside class=\"placeholder reveal\">
          <span class=\"placeholder__badge\">Coming soon</span>
          <h2 class=\"placeholder__title\">Event photo gallery coming soon</h2>
          <p class=\"placeholder__text\">Photographs from this event are being added. To publish them, drop the image files into <code>assets/events/{$e['slug']}/photos/</code> and rebuild.</p>
        </aside>";
  }
  $items = [];
  foreach (array_values($photos) as $i => $p) {
    $alt = esc_js($p['caption'] ?: ($e['title'] . ' — photo ' . ($i + 1)));
    $items[] = '<a class="gallery__item" href="/' . $p['file'] . '" target="_blank" rel="noopener"><img src="/' . $p['file'] . '" alt="' . $alt . '" loading="lazy" decoding="async"></a>';
  }
  return "
        <section class=\"event-block reveal\">
          <h2 class=\"event-block__title\">Photo gallery</h2>
          <div class=\"gallery\">
            " . implode("\n            ", $items) . "
          </div>
        </section>";
}

/* ---------- new pieces (no static equivalent) ---------------------------------------- */

/** Catalogue / brochure card at the top of the event page. */
function render_event_brochure(array $e): string {
  $b = $e['brochure'];
  if (!$b) return '';
  $isPdf = $b['mime'] === 'application/pdf';
  $title = esc_js($b['title'] ?: 'Event catalogue');
  $cta = ($e['upcoming'] && $e['registration_open'])
    ? '<a class="btn btn--primary" href="/events/' . h($e['slug']) . '/register">Register Now ' . HCOS_ARROW . '</a>'
    : '';
  $preview = $isPdf
    ? '<span class="brochure__doc" aria-hidden="true"><span class="brochure__doc-icon">PDF</span></span>'
    : '<img class="brochure__thumb" src="/' . $b['file'] . '" alt="' . $title . '" loading="lazy" decoding="async">';
  return "
        <section class=\"brochure reveal\" aria-labelledby=\"brochureTitle\">
          <a class=\"brochure__preview\" href=\"/{$b['file']}\" target=\"_blank\" rel=\"noopener\">{$preview}</a>
          <div class=\"brochure__body\">
            <span class=\"brochure__eyebrow\">Event catalogue</span>
            <h2 class=\"brochure__title\" id=\"brochureTitle\">{$title}</h2>
            <p class=\"brochure__text\">Full programme, speakers and participation details for " . esc_js($e['title']) . ".</p>
            <div class=\"brochure__actions\">
              <a class=\"btn btn--ghost\" href=\"/{$b['file']}\" target=\"_blank\" rel=\"noopener\">" . ($isPdf ? 'Open catalogue (PDF)' : 'View catalogue') . " " . HCOS_ARROW . "</a>
              {$cta}
            </div>
          </div>
        </section>";
}

function render_event_action(array $e): string {
  if (!($e['upcoming'] && $e['registration_open'])) return '<p>Registration is closed for this event.</p>';
  return '<h2>Join us at the event</h2><p>Complete your registration on the next page. The HCOS events team will contact you with confirmation and joining instructions.</p><a class="btn btn--primary" href="/events/' . h($e['slug']) . '/register">Register Now ' . HCOS_ARROW . '</a>';
}

/** Registration form: the build's template, filled at request time. */
function render_event_registration(array $e): string {
  if (!($e['upcoming'] && $e['registration_open'])) return '';
  $tpl = file_get_contents(HCOS_APP . '/views/fragments/registration.html');
  if ($tpl === false) return '';
  $db = db() !== null;
  global $HCOS;
  $hidden = $db ? '<input type="hidden" name="event_slug" value="' . h($e['slug']) . '">' . "
    " . antibot_fields() : '';
  return strtr($tpl, [
    '{{FORM_ENDPOINT}}' => $db ? '/api/register' : '',
    '{{FORM_FALLBACK_EMAIL}}' => h((string)$HCOS['notify_email']),
    '{{FORM_HIDDEN}}' => $hidden,
    '{{EVENT_TITLE}}' => esc_js($e['title']),
  ]);
}
