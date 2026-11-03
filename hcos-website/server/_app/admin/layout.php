<?php
declare(strict_types=1);

/* ---------- admin layout + small UI helpers --------------------------------------- */

function admin_headers(): void {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store, max-age=0');
  header('Pragma: no-cache');
  header('X-Robots-Tag: noindex, nofollow, noarchive');
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
}

function flash(string $kind, string $msg): void { session_boot(); $_SESSION['flash'][] = [$kind, $msg]; }

function flash_html(): string {
  session_boot();
  $out = '';
  foreach ($_SESSION['flash'] ?? [] as [$kind, $msg]) $out .= '<div class="flash flash--' . h($kind) . '" role="status">' . h($msg) . '</div>';
  unset($_SESSION['flash']);
  return $out;
}

function admin_url(string $route, array $params = []): string {
  $q = ['r' => $route] + $params;
  return '/event-setup/?' . http_build_query($q);
}

/** Wrap page content in the admin chrome. */
function admin_layout(string $title, string $content, string $active = ''): never {
  admin_headers();
  $u = admin_user();
  $counts = ['mod' => 0, 'msg' => 0];
  try { $counts['mod'] = (int)scalar("SELECT COUNT(*) FROM registration_uploads WHERE status = 'pending'"); $counts['msg'] = (int)scalar("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"); } catch (Throwable $e) {}
  $nav = [
    ['dashboard', 'Dashboard', 0], ['events', 'Events', 0], ['registrations', 'Registrations', 0],
    ['moderation', 'Uploads to review', $counts['mod']], ['messages', 'Messages', $counts['msg']], ['account', 'My account', 0],
  ];
  $links = '';
  foreach ($nav as [$r, $label, $n]) {
    $links .= '<a class="side__link' . ($active === $r ? ' is-active' : '') . '" href="' . h(admin_url($r)) . '">' . h($label)
      . ($n ? '<span class="side__count">' . $n . '</span>' : '') . '</a>';
  }
  echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<meta name="robots" content="noindex, nofollow"><title>' . h($title) . ' — Event Setup</title>'
    . '<link rel="icon" href="/assets/logos/hcos-logo.png">'
    . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="/event-setup/admin.css?v=' . HCOS_VERSION . '-' . filemtime(HCOS_ROOT . '/event-setup/admin.css') . '"></head>'
    . '<body class="admin"><a class="skip" href="#content">Skip to content</a>'
    . '<aside class="side"><div class="side__brand"><img src="/assets/logos/hcos-logo.png" alt="" width="40" height="40"><span>Event Setup</span></div>'
    . '<nav class="side__nav" aria-label="Admin">' . $links . '</nav>'
    . '<div class="side__foot"><span class="side__user">' . h($u['name'] ?? '') . '</span>'
    . '<form method="post" action="' . h(admin_url('logout')) . '">' . csrf_field() . '<button class="b b--ghost b--sm" type="submit">Sign out</button></form>'
    . '<a class="side__site" href="/" target="_blank" rel="noopener">View website ↗</a></div></aside>'
    . '<main class="main" id="content">' . flash_html() . $content . '</main>'
    . '<script src="/event-setup/admin.js?v=' . HCOS_VERSION . '" defer></script></body></html>';
  exit;
}

/** Bare layout for the login page. */
function admin_layout_bare(string $title, string $content): never {
  admin_headers();
  echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<meta name="robots" content="noindex, nofollow"><title>' . h($title) . '</title>'
    . '<link rel="icon" href="/assets/logos/hcos-logo.png">'
    . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="/event-setup/admin.css?v=' . HCOS_VERSION . '-' . filemtime(HCOS_ROOT . '/event-setup/admin.css') . '"></head>'
    . '<body class="admin admin--bare"><main class="bare">' . $content . '</main></body></html>';
  exit;
}

/* ---------- tiny view helpers ------------------------------------------------------- */

function badge(string $text, string $tone = 'muted'): string { return '<span class="badge badge--' . h($tone) . '">' . h($text) . '</span>'; }

function status_tone(string $s): string {
  return match ($s) { 'published', 'approved', 'confirmed', 'attended', 'replied' => 'ok', 'draft', 'pending', 'new' => 'warn', 'archived', 'rejected', 'cancelled' => 'muted', default => 'muted' };
}

function input(string $name, string $label, ?string $value = '', array $o = []): string {
  $type = $o['type'] ?? 'text';
  $id = $o['id'] ?? ($name . '_' . substr(md5($name . ($o['key'] ?? '')), 0, 6));
  $attrs = '';
  foreach (['placeholder', 'maxlength', 'autocomplete', 'accept', 'pattern', 'inputmode'] as $a) if (isset($o[$a])) $attrs .= ' ' . $a . '="' . h((string)$o[$a]) . '"';
  if (!empty($o['required'])) $attrs .= ' required';
  if (!empty($o['multiple'])) $attrs .= ' multiple';
  $help = isset($o['help']) ? '<span class="field__help">' . $o['help'] . '</span>' : '';
  $ctl = $type === 'textarea'
    ? '<textarea id="' . h($id) . '" name="' . h($name) . '" rows="' . (int)($o['rows'] ?? 4) . '"' . $attrs . '>' . h($value) . '</textarea>'
    : '<input id="' . h($id) . '" type="' . h($type) . '" name="' . h($name) . '"' . ($type !== 'file' ? ' value="' . h($value) . '"' : '') . $attrs . '>';
  return '<div class="field' . (!empty($o['class']) ? ' ' . h($o['class']) : '') . '"><label for="' . h($id) . '">' . h($label) . '</label>' . $ctl . $help . '</div>';
}

function select(string $name, string $label, array $options, ?string $value = '', array $o = []): string {
  $id = $o['id'] ?? ($name . '_' . substr(md5($name . ($o['key'] ?? '')), 0, 6));
  $opts = '';
  foreach ($options as $v => $l) $opts .= '<option value="' . h((string)$v) . '"' . ((string)$v === (string)$value ? ' selected' : '') . '>' . h($l) . '</option>';
  return '<div class="field' . (!empty($o['class']) ? ' ' . h($o['class']) : '') . '"><label for="' . h($id) . '">' . h($label) . '</label><select id="' . h($id) . '" name="' . h($name) . '">' . $opts . '</select></div>';
}

function checkbox(string $name, string $label, bool $checked, string $help = ''): string {
  return '<label class="check"><input type="checkbox" name="' . h($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span>' . h($label) . ($help ? '<small>' . h($help) . '</small>' : '') . '</span></label>';
}

/** Small POST form with one button — for every state-changing action. */
function action_button(string $route, array $params, string $label, string $class = 'b b--ghost b--sm', ?string $confirm = null): string {
  $hidden = '';
  foreach ($params as $k => $v) $hidden .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h((string)$v) . '">';
  return '<form class="inline" method="post" action="' . h(admin_url($route)) . '"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>'
    . csrf_field() . $hidden . '<button class="' . h($class) . '" type="submit">' . h($label) . '</button></form>';
}

function thumb(?string $file, string $alt = '', string $class = 'thumb'): string {
  if (!$file) return '<span class="' . h($class) . ' ' . h($class) . '--empty" aria-hidden="true">—</span>';
  if (str_ends_with(strtolower($file), '.pdf')) return '<a class="' . h($class) . ' ' . h($class) . '--pdf" href="/' . h($file) . '" target="_blank" rel="noopener">PDF</a>';
  return '<a class="' . h($class) . '" href="/' . h($file) . '" target="_blank" rel="noopener"><img src="/' . h($file) . '" alt="' . h($alt) . '" loading="lazy"></a>';
}

function page_head(string $title, string $actions = '', string $sub = ''): string {
  return '<header class="phead"><div><h1 class="phead__title">' . h($title) . '</h1>' . ($sub ? '<p class="phead__sub">' . $sub . '</p>' : '') . '</div>' . ($actions ? '<div class="phead__actions">' . $actions . '</div>' : '') . '</header>';
}

function csv_cell(?string $v): string {
  $v = (string)$v;
  if ($v !== '' && str_contains('=+-@', $v[0])) $v = "'" . $v; // neutralise spreadsheet formulas
  return '"' . str_replace('"', '""', $v) . '"';
}
