<?php
/**
 * Local development router for PHP's built-in server.
 *
 *   cd dist && php -S localhost:8000 router-dev.php
 *
 * The live site routes through .htaccess, which the built-in server ignores.
 * This file reproduces the same rules so local behaviour matches production:
 * clean URLs, /events/<slug>, the attendee share link, /api/<name>, and a
 * branded 404. It is a development aid only — Apache never loads it.
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim(rawurldecode($path), '/');
$root = __DIR__;

// Real file on disk (asset, uploaded file, pre-built .html) — let the server serve it.
if ($path !== '/' && is_file($root . $path)) {
  // ...except application internals, which Apache denies.
  if (preg_match('#^/(_app|src)(/|$)#', $path)) { http_response_code(404); readfile($root . '/404.html'); return true; }
  return false;
}

// /events/<slug>/share/<token>
if (preg_match('#^/events/([a-z0-9-]+)/share/([a-f0-9]{64})/?$#', $path, $m)) {
  $_GET['slug'] = $m[1]; $_GET['t'] = $m[2];
  require $root . '/share.php';
  return true;
}

// /events/<slug>/register
if (preg_match('#^/events/([a-z0-9-]+)/register/?$#', $path, $m)) {
  $_GET['slug'] = $m[1]; $_GET['view'] = 'register';
  require $root . '/events.php';
  return true;
}

// /events/<slug>
if (preg_match('#^/events/([a-z0-9-]+)/?$#', $path, $m)) {
  $_GET['slug'] = $m[1];
  require $root . '/events.php';
  return true;
}

// /api/<name>
if (preg_match('#^/api/([a-z]+)/?$#', $path, $m) && is_file($root . "/api/{$m[1]}.php")) {
  require $root . "/api/{$m[1]}.php";
  return true;
}

// /event-setup/ and any directory with an index.php
$dir = rtrim($path, '/');
if ($dir !== '' && is_file($root . $dir . '/index.php')) { require $root . $dir . '/index.php'; return true; }

// / → index.php
if ($path === '/' || $path === '') { require $root . '/index.php'; return true; }

// /about → about.php, else about.html   (the clean-URL rule)
$base = $root . rtrim($path, '/');
foreach (['.php', '.html'] as $ext) {
  if (is_file($base . $ext)) {
    if ($ext === '.php') { require $base . '.php'; } else { readfile($base . '.html'); }
    return true;
  }
}

http_response_code(404);
readfile($root . '/404.html');
return true;
