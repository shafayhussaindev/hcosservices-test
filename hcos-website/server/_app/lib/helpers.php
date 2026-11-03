<?php
declare(strict_types=1);

/* ---------- output escaping ----------------------------------------------------
   h() is the ONLY way a value reaches HTML. Everything the admin or a visitor
   typed goes through it. */

function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function hcos_wants_json(): bool {
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  $xhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
  return $xhr || str_contains($accept, 'application/json') || str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
}

function json_out(array $data, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function redirect(string $to, int $code = 303): never {
  // Only ever redirect within this site.
  if (!preg_match('#^/(?!/)#', $to)) $to = '/';
  header('Location: ' . $to, true, $code);
  exit;
}

/* ---------- request helpers ------------------------------------------------- */

function client_ip(): string {
  // Hostinger's CDN sits in front; it sets X-Forwarded-For. Take the first hop only.
  $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
  $ip = $xff !== '' ? trim(explode(',', $xff)[0]) : ($_SERVER['REMOTE_ADDR'] ?? '');
  return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/** Trimmed string from POST, capped in length, control characters stripped. */
function post_str(string $key, int $max = 255): string {
  $v = $_POST[$key] ?? '';
  if (!is_string($v)) return '';
  $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
  $v = trim($v);
  return mb_substr($v, 0, $max);
}

function post_int(string $key, int $default = 0): int {
  $v = $_POST[$key] ?? null;
  return is_numeric($v) ? (int)$v : $default;
}

function get_int(string $key, int $default = 0): int {
  $v = $_GET[$key] ?? null;
  return is_numeric($v) ? (int)$v : $default;
}

function valid_email(string $e): bool {
  return $e !== '' && mb_strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n]/', $e);
}

function valid_slug(string $s): bool { return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && strlen($s) <= 120; }

function slugify(string $s): string {
  $s = mb_strtolower(trim($s));
  $s = str_replace(['&amp;', '&'], 'and', $s);
  $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
  return trim($s, '-');
}

function random_token(int $bytes = 32): string { return bin2hex(random_bytes($bytes)); }

/* ---------- signed timestamps for public forms -------------------------------
   A hidden field carries "<unix>.<hmac>". A form that comes back in under 3
   seconds, or more than 6 hours later, or with a bad signature, is a bot. */

function form_stamp(): string {
  global $HCOS;
  $t = (string)time();
  return $t . '.' . hash_hmac('sha256', 'stamp|' . $t, (string)$HCOS['app_key']);
}

function form_stamp_ok(string $stamp, int $minSeconds = 3, int $maxSeconds = 21600): bool {
  global $HCOS;
  if ((string)$HCOS['app_key'] === '') return true; // not configured yet — do not lock visitors out
  $parts = explode('.', $stamp, 2);
  if (count($parts) !== 2 || !ctype_digit($parts[0])) return false;
  [$t, $sig] = $parts;
  if (!hash_equals(hash_hmac('sha256', 'stamp|' . $t, (string)$HCOS['app_key']), $sig)) return false;
  $age = time() - (int)$t;
  return $age >= $minSeconds && $age <= $maxSeconds;
}

/* ---------- rate limiting (fixed window, DB-backed) -------------------------- */

function rate_limit(string $key, int $max, int $windowSeconds): bool {
  $pdo = db();
  if (!$pdo) return true;
  $key = substr($key, 0, 120);
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE rl_key = ? FOR UPDATE');
    $st->execute([$key]);
    $row = $st->fetch();
    $now = time();
    if (!$row || strtotime((string)$row['window_start']) < $now - $windowSeconds) {
      $pdo->prepare('REPLACE INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)')
          ->execute([$key, date('Y-m-d H:i:s', $now)]);
      $pdo->commit();
      return true;
    }
    if ((int)$row['hits'] >= $max) { $pdo->commit(); return false; }
    $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?')->execute([$key]);
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[HCOS] rate_limit: ' . $e->getMessage());
    return true;
  }
}

/* ---------- misc ----------------------------------------------------------------- */

function hcos_url(string $path = ''): string {
  global $HCOS;
  return rtrim((string)$HCOS['site_url'], '/') . '/' . ltrim($path, '/');
}

function fmt_bytes(int $b): string {
  if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
  if ($b >= 1024) return round($b / 1024) . ' KB';
  return $b . ' B';
}

function fmt_dt(?string $dt): string {
  if (!$dt) return '';
  $t = strtotime($dt);
  return $t ? date('d M Y, H:i', $t) : '';
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void {
  $pdo = db();
  if (!$pdo) return;
  try {
    $pdo->prepare('INSERT INTO audit_log (admin_id, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?)')
        ->execute([admin_id(), $action, $entity, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, client_ip()]);
  } catch (Throwable $e) { error_log('[HCOS] audit: ' . $e->getMessage()); }
}

/** Hidden anti-bot fields for public forms: signed timestamp + honeypot. */
function antibot_fields(): string {
  return '<input type="hidden" name="stamp" value="' . h(form_stamp()) . '">' . "\n    "
    . '<div class="hp" aria-hidden="true" style="position:absolute;left:-5000px" tabindex="-1">'
    . '<label>Leave this field empty <input type="text" name="website" autocomplete="off" tabindex="-1"></label></div>';
}

/** Redirect back to the referring admin page (same host only), else to $default. */
function back(string $default): never {
  $p = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''));
  if (is_array($p) && ($p['host'] ?? '') === ($_SERVER['HTTP_HOST'] ?? '-') && !empty($p['path'])) {
    redirect($p['path'] . (isset($p['query']) ? '?' . $p['query'] : ''));
  }
  redirect($default);
}
