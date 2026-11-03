<?php
$state = json_decode(file_get_contents(__DIR__ . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:5173';
if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/D', $host)) $host = 'localhost:5173';
return [
  'db' => ['host' => '127.0.0.1', 'port' => 3307, 'name' => 'hcos_local', 'user' => 'hcos', 'pass' => $state['dbPassword']],
  'app_key' => $state['appKey'], 'site_url' => 'http://' . $host,
  'notify_email' => 'dev@example.invalid', 'from_email' => 'noreply@example.invalid',
  'local_mail_log' => __DIR__ . '/mailbox.log',
];
