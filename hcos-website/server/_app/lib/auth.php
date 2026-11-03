<?php
declare(strict_types=1);

/* ---------- admin authentication -----------------------------------------------------
   - Sessions: own name, own directory outside the web root, Secure + HttpOnly
     + SameSite=Strict cookie, id regenerated on login, idle and absolute
     timeouts, bound to the browser's user agent.
   - Passwords: Argon2id where PHP offers it, bcrypt otherwise. A failed login
     still runs a hash check so timing does not reveal valid usernames.
   - Throttling: after N failures in the window (per IP AND per username) the
     login is refused for the rest of the window.
   - CSRF: one token per session, required on every admin POST.                */

const HCOS_DUMMY_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEeO5xhY8k9xk0kQ3wU6d.7rZ2sI2i0cUvS';

function session_boot(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  global $HCOS;
  $dir = (string)$HCOS['session_path'];
  if ($dir !== '') {
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
  }
  $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_name('hcos_admin');
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict',
  ]);
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.gc_maxlifetime', (string)((int)$HCOS['admin_max_hours'] * 3600));
  session_start();
}

function csrf_token(): string {
  session_boot();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = random_token(32);
  return $_SESSION['csrf'];
}

function csrf_field(): string {
  return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void {
  session_boot();
  $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
    http_response_code(419);
    if (hcos_wants_json()) json_out(['ok' => false, 'error' => 'Your session expired. Please reload and try again.'], 419);
    echo '<!doctype html><meta charset="utf-8"><p style="font:16px system-ui;padding:40px">Your session expired. Please go back, reload the page and try again.</p>';
    exit;
  }
}

function admin_id(): ?int {
  if (session_status() !== PHP_SESSION_ACTIVE) return null;
  return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function admin_user(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  $id = admin_id();
  $u = $id ? row('SELECT id, username, name, email, active FROM admin_users WHERE id = ? AND active = 1', [$id]) : null;
  return $u;
}

function ua_fingerprint(): string { return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')); }

/** True when a valid, unexpired admin session exists. Clears it otherwise. */
function admin_logged_in(): bool {
  session_boot();
  global $HCOS;
  if (empty($_SESSION['admin_id'])) return false;
  $now = time();
  $idle = (int)$HCOS['admin_idle_minutes'] * 60;
  $max = (int)$HCOS['admin_max_hours'] * 3600;
  if (($_SESSION['ua'] ?? '') !== ua_fingerprint()
    || $now - (int)($_SESSION['last'] ?? 0) > $idle
    || $now - (int)($_SESSION['start'] ?? 0) > $max
    || !admin_user()) {
    admin_logout();
    return false;
  }
  $_SESSION['last'] = $now;
  return true;
}

function login_throttled(string $username): bool {
  global $HCOS;
  $since = date('Y-m-d H:i:s', time() - (int)$HCOS['login_window_minutes'] * 60);
  $max = (int)$HCOS['login_max_failures'];
  $byIp = (int)scalar('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND attempted_at > ?', [client_ip(), $since]);
  if ($byIp >= $max) return true;
  $byUser = (int)scalar('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND succeeded = 0 AND attempted_at > ?', [$username, $since]);
  return $byUser >= $max;
}

/** @return string|null error message, null on success */
function admin_login(string $username, string $password): ?string {
  session_boot();
  $username = mb_strtolower(trim($username));
  if ($username === '' || $password === '' || mb_strlen($username) > 60 || strlen($password) > 1024) return 'Please enter your user ID and password.';
  if (login_throttled($username)) {
    q('INSERT INTO login_attempts (username, ip, succeeded) VALUES (?,?,0)', [$username, client_ip()]);
    return 'Too many attempts. Please wait 15 minutes and try again.';
  }
  $u = row('SELECT * FROM admin_users WHERE username = ? AND active = 1', [$username]);
  $ok = password_verify($password, $u['password_hash'] ?? HCOS_DUMMY_HASH) && $u !== null;
  q('INSERT INTO login_attempts (username, ip, succeeded) VALUES (?,?,?)', [$username, client_ip(), $ok ? 1 : 0]);
  if (!$ok) { usleep(random_int(150000, 400000)); return 'That user ID and password do not match.'; }

  session_regenerate_id(true);
  $_SESSION = ['admin_id' => (int)$u['id'], 'start' => time(), 'last' => time(), 'ua' => ua_fingerprint(), 'csrf' => random_token(32)];
  q('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [(int)$u['id']]);
  if (password_needs_rehash($u['password_hash'], password_algo())) {
    q('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($password, password_algo()), (int)$u['id']]);
  }
  audit('login', 'admin_user', (int)$u['id']);
  return null;
}

function admin_logout(): void {
  session_boot();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
  }
  session_destroy();
}

function password_algo(): string {
  return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

/** Minimum strength for admin passwords. */
function password_policy_error(string $pw): ?string {
  if (strlen($pw) < 12) return 'Use at least 12 characters.';
  if (strlen($pw) > 256) return 'That password is too long.';
  if (!preg_match('/[a-z]/', $pw) || !preg_match('/[A-Z]/', $pw) || !preg_match('/\d/', $pw)) return 'Use upper and lower case letters and at least one number.';
  return null;
}
