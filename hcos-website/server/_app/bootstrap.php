<?php
/**
 * HCOS website — PHP bootstrap.
 *
 * Included by every PHP entry point (index.php, events.php, share.php,
 * api/*.php, event-setup/index.php). Loads the private config, sets hardened
 * PHP defaults, and pulls in the library. Nothing here produces output.
 *
 * Config lives OUTSIDE the web root:  <domain>/private/config.php
 * (one level above public_html). See config.example.php for the keys.
 */
declare(strict_types=1);

define('HCOS_APP', __DIR__);
define('HCOS_ROOT', dirname(__DIR__));                 // public_html
define('HCOS_PRIVATE', dirname(HCOS_ROOT) . '/private'); // never web-served
define('HCOS_VERSION', '1.0.0');

/* ---------- hardened PHP defaults ------------------------------------------ */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('expose_php', '0');
error_reporting(E_ALL);
if (PHP_SAPI !== 'cli') header_remove('X-Powered-By');
date_default_timezone_set('Asia/Karachi');
mb_internal_encoding('UTF-8');

/* ---------- config ----------------------------------------------------------- */
$HCOS = [
  'db' => null,                         // ['host'=>..,'name'=>..,'user'=>..,'pass'=>..]
  'app_key' => '',                      // 32+ random bytes, hex. Signs form timestamps.
  'site_url' => 'https://hcosservices.com',
  'notify_email' => 'info@hcosservices.com',
  'from_email' => 'noreply@hcosservices.com',
  'from_name' => 'HCOS Services',
  'session_path' => HCOS_PRIVATE . '/sessions',
  'log_file' => HCOS_PRIVATE . '/logs/app.log',
  'uploads_dir' => HCOS_ROOT . '/uploads',
  'uploads_url' => 'uploads',           // relative to web root, no leading slash
  'admin_idle_minutes' => 60,
  'admin_max_hours' => 12,
  'login_max_failures' => 5,            // per IP and per username ...
  'login_window_minutes' => 15,         // ... inside this window
  'visits_seed' => 21945,
];

$cfgFile = HCOS_PRIVATE . '/config.php';
if (is_file($cfgFile)) {
  $user = require $cfgFile;
  if (is_array($user)) $HCOS = array_replace($HCOS, $user);
}

if (is_string($HCOS['log_file']) && $HCOS['log_file'] !== '') {
  $logDir = dirname($HCOS['log_file']);
  if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
  ini_set('error_log', $HCOS['log_file']);
}

/* ---------- library ----------------------------------------------------------- */
require HCOS_APP . '/lib/helpers.php';
require HCOS_APP . '/lib/db.php';
require HCOS_APP . '/lib/repo.php';
require HCOS_APP . '/lib/render.php';
require HCOS_APP . '/lib/pages.php';
require HCOS_APP . '/lib/uploads.php';
require HCOS_APP . '/lib/registrations.php';
require HCOS_APP . '/lib/mail.php';
require HCOS_APP . '/lib/auth.php';

/* ---------- last-resort error handling ------------------------------------- */
set_exception_handler(function (Throwable $e): void {
  error_log('[HCOS] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  if (PHP_SAPI === 'cli') { fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n"); exit(1); }
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
  }
  if (hcos_wants_json()) { echo json_encode(['ok' => false, 'error' => 'Server error']); return; }
  echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
     . '<p style="font:16px/1.5 system-ui;padding:40px">Sorry — something went wrong on our side. Please try again in a moment.</p>';
});
