<?php
declare(strict_types=1);
ini_set('display_errors', '0');

// Loopback-only test fixture. SQLite and mail stubs replace external services;
// registration, upload, session guards and admin controllers are production code.
$testDir = getenv('HCOS_TEST_DIR');
if (!$testDir) { http_response_code(500); exit; }
define('HCOS_APP', dirname(__DIR__, 2) . '/server/_app');
define('HCOS_ROOT', dirname(HCOS_APP));
define('HCOS_PRIVATE', $testDir . '/private');
define('HCOS_VERSION', 'test');
@mkdir(HCOS_PRIVATE, 0700, true);
$HCOS = ['app_key' => 'test-key', 'site_url' => 'http://localhost', 'uploads_url' => 'uploads',
  'uploads_dir' => $testDir . '/uploads', 'session_path' => HCOS_PRIVATE . '/sessions',
  'admin_idle_minutes' => 60, 'admin_max_hours' => 12, 'login_window_minutes' => 15, 'login_max_failures' => 5];
$pdo = new PDO('sqlite:' . $testDir . '/registrations.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, slug TEXT, title TEXT, status TEXT DEFAULT 'published', sort_order INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS registrations (id INTEGER PRIMARY KEY, event_id INTEGER REFERENCES events(id) ON DELETE CASCADE, name TEXT, organization TEXT, designation TEXT, email TEXT, phone TEXT, linkedin_url TEXT, gender TEXT, price_option TEXT, headshot TEXT, upload_token TEXT, ip TEXT, user_agent TEXT, note TEXT, status TEXT DEFAULT 'new', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS registration_uploads (id INTEGER PRIMARY KEY, registration_id INTEGER REFERENCES registrations(id) ON DELETE CASCADE, event_id INTEGER, file TEXT, original_name TEXT, status TEXT DEFAULT 'pending', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS admin_users (id INTEGER PRIMARY KEY, username TEXT, name TEXT, email TEXT, active INTEGER);
CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY, admin_id INTEGER, action TEXT, entity TEXT, entity_id INTEGER, details TEXT, ip TEXT);
INSERT OR IGNORE INTO events (id, slug, title) VALUES (1, 'first-event', 'First event'), (2, 'second-event', 'Second event');
INSERT OR IGNORE INTO admin_users VALUES (1, 'tester', 'Test administrator', 'test@example.com', 1);
CREATE TRIGGER IF NOT EXISTS simulate_failure BEFORE INSERT ON registrations WHEN NEW.email = 'fail@example.com' BEGIN SELECT RAISE(ABORT, 'Simulated write failure'); END;");
function db(): PDO { global $pdo; return $pdo; }
function q(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function row(string $sql, array $params = []): ?array { return q($sql, $params)->fetch() ?: null; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function scalar(string $sql, array $params = []): mixed { return q($sql, $params)->fetchColumn(); }
function last_id(): int { return (int)db()->lastInsertId(); }
function repo_event_by_slug(string $slug): ?array {
  $e = row('SELECT * FROM events WHERE slug = ?', [$slug]);
  return $e ? $e + ['upcoming' => true, 'registration_open' => true, 'attendee_uploads' => false] : null;
}
function repo_event_by_id(int $id): ?array {
  $e = row('SELECT * FROM events WHERE id = ?', [$id]);
  return $e ? $e + ['image' => '', 'people' => [], 'sponsors' => [], 'partners' => [], 'materials' => [], 'gallery' => [], 'brochure' => null] : null;
}
function repo_counts(): array {
  return ['published' => 2, 'upcoming' => 2, 'registrations' => scalar('SELECT COUNT(*) FROM registrations'), 'uploads_pending' => 0, 'messages_new' => 0, 'visits' => 0];
}
function send_mail(...$args): bool { return true; }
function notify_admin(...$args): void {}
require HCOS_APP . '/lib/helpers.php';
require HCOS_APP . '/lib/uploads.php';
require HCOS_APP . '/lib/registrations.php';
require HCOS_APP . '/lib/auth.php';
set_exception_handler(function (Throwable $e): void { http_response_code(500); echo json_encode(['error' => 'Test request failed']); });
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($route === '/state') {
  $photos = glob(HCOS_PRIVATE . '/headshots/*/*.webp') ?: [];
  json_out(['registrations' => rows('SELECT * FROM registrations ORDER BY id'), 'photos' => $photos]);
}
if ($route === '/test-login') {
  q("INSERT OR IGNORE INTO registration_uploads (id, registration_id, event_id, file, original_name, status) VALUES (1, 1, 1, 'uploads/first.pdf', 'Agenda <final>.pdf', 'pending'), (2, 1, 1, 'uploads/approved.pdf', 'Approved document.pdf', 'approved'), (3, 2, 2, 'uploads/second.pdf', 'Other event.pdf', 'rejected')");
  session_boot();
  $_SESSION = ['admin_id' => 1, 'start' => time(), 'last' => time(), 'ua' => ua_fingerprint(), 'csrf' => 'test-csrf'];
  json_out(['ok' => true]);
}
if ($route === '/photo') {
  header('Content-Type: image/png'); $im = imagecreatetruecolor(64, 64); imagepng($im); exit;
}
if ($route === '/api/register') {
  $source = file_get_contents(HCOS_ROOT . '/api/register.php');
  $source = str_replace("require __DIR__ . '/../_app/bootstrap.php';", '', $source);
  eval(substr($source, strlen('<?php')));
  exit;
}
if ($route === '/test-pdf') {
  try {
    json_out(['ok' => true, 'file' => store_image_or_pdf($_FILES['file'], 'test-event', 'materials')]);
  } catch (UploadException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
  }
}
if ($route === '/event-setup/') { require HCOS_APP . '/admin/router.php'; admin_dispatch(); }
http_response_code(404);
