<?php
// Footer visitor counter. GET returns the count; POST (once per browser
// session, enforced client-side) increments it. Same JSON shape the front end
// already expects: { count, live }.
require __DIR__ . '/../_app/bootstrap.php';
global $HCOS;
$pdo = db();
if (!$pdo) json_out(['count' => (int)$HCOS['visits_seed'], 'live' => false]);
try {
  if (is_post()) $pdo->exec('UPDATE visits SET count = count + 1 WHERE id = 1');
  $n = (int)(scalar('SELECT count FROM visits WHERE id = 1') ?? $HCOS['visits_seed']);
  json_out(['count' => $n, 'live' => true]);
} catch (Throwable $e) {
  error_log('[HCOS] visits: ' . $e->getMessage());
  json_out(['count' => (int)$HCOS['visits_seed'], 'live' => false]);
}
