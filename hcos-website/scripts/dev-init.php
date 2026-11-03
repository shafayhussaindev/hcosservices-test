<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || empty($argv[1])) exit(1);
$root = realpath($argv[1]);
if (!$root || !is_file($root . '/_app/bootstrap.php')) throw new RuntimeException('Invalid local application root');
$private = dirname($root) . '/private';
$state = json_decode(file_get_contents($private . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $state['rootPassword'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS hcos_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER IF NOT EXISTS 'hcos'@'127.0.0.1' IDENTIFIED BY " . $pdo->quote($state['dbPassword']));
$pdo->exec("GRANT ALL ON hcos_local.* TO 'hcos'@'127.0.0.1'");
copy(__DIR__ . '/dev-config.php', $private . '/config.php');
require $root . '/_app/bootstrap.php';
db_migrate();
if ((int)scalar('SELECT COUNT(*) FROM events') === 0) {
  // The existing importer preserves all public event content and its relationships.
  $script = '$argv=["cli.php","import:json"]; require ' . var_export(HCOS_APP . '/cli.php', true) . ';';
  $command = [PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir')];
  foreach (['mbstring', 'pdo_mysql'] as $extension) { $command[] = '-d'; $command[] = 'extension=' . $extension; }
  $command[] = '-r'; $command[] = $script;
  $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
  if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Local event import failed');
}
if (!row('SELECT id FROM admin_users WHERE username = ?', ['local.admin'])) {
  q('INSERT INTO admin_users (username, name, password_hash) VALUES (?,?,?)', ['local.admin', 'Local development', password_hash($state['adminPassword'], password_algo())]);
}
echo "Local schema, public events and local administrator are ready.\n";
