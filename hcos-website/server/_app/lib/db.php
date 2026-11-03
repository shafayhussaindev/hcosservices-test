<?php
declare(strict_types=1);

/* ---------- PDO connection --------------------------------------------------------
   db() returns the shared PDO, or null when no database is configured. Every
   caller must handle null: the public site then renders from the JSON
   snapshot in _app/data/events.json and the forms fall back to mailto. */

function db(): ?PDO {
  static $pdo = null, $tried = false;
  global $HCOS;
  if ($tried) return $pdo;
  $tried = true;
  $c = $HCOS['db'] ?? null;
  if (!is_array($c) || empty($c['name']) || empty($c['user'])) return null;
  try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'] ?? 'localhost', $c['port'] ?? 3306, $c['name']);
    $pdo = new PDO($dsn, $c['user'], (string)($c['pass'] ?? ''), [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,   // real server-side prepared statements
      PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET time_zone = '+05:00'");
    try { $pdo->exec('SET SESSION wait_timeout = 300'); } catch (Throwable $e) { /* host may forbid it */ }
  } catch (Throwable $e) {
    error_log('[HCOS] DB connect failed: ' . $e->getMessage());
    $pdo = null;
  }
  return $pdo;
}

function db_required(): PDO {
  $pdo = db();
  if (!$pdo) throw new RuntimeException('Database is not configured');
  return $pdo;
}

/** Run a prepared statement; returns the statement. */
function q(string $sql, array $params = []): PDOStatement {
  $st = db_required()->prepare($sql);
  $st->execute($params);
  return $st;
}

function row(string $sql, array $params = []): ?array {
  $r = q($sql, $params)->fetch();
  return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array {
  return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []): mixed {
  $v = q($sql, $params)->fetchColumn();
  return $v === false ? null : $v;
}

function last_id(): int { return (int)db_required()->lastInsertId(); }

/** Apply schema.sql. Idempotent. */
function db_migrate(): int {
  $sql = file_get_contents(HCOS_APP . '/schema.sql');
  if ($sql === false) throw new RuntimeException('schema.sql missing');
  $pdo = db_required();
  $count = 0;
  foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    // strip leading comment lines, then skip anything that was only comments
    $stmt = trim((string)preg_replace('/^(--[^\n]*\n)+/', '', $stmt));
    if ($stmt === '') continue;
    $pdo->exec((string)$stmt);
    $count++;
  }
  // Columns added after the first release (CREATE TABLE IF NOT EXISTS does not alter).
  db_ensure_column('registrations', 'note', 'VARCHAR(255) NULL AFTER user_agent');
  db_ensure_column('registrations', 'linkedin_url', 'VARCHAR(512) NULL AFTER phone');
  db_ensure_column('registrations', 'gender', 'VARCHAR(32) NULL AFTER linkedin_url');
  db_ensure_column('registrations', 'headshot', 'VARCHAR(255) NULL AFTER gender');
  db_ensure_column('registrations', 'price_option', 'VARCHAR(32) NULL AFTER gender');
  // Seed the visitor counter row once, continuing from the old site's number.
  global $HCOS;
  $pdo->prepare('INSERT IGNORE INTO visits (id, count) VALUES (1, ?)')->execute([(int)$HCOS['visits_seed']]);
  return $count;
}

/** Add a column if it is missing. Idempotent. */
function db_ensure_column(string $table, string $column, string $definition): void {
  $exists = scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
  if (!(int)$exists) db_required()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}
