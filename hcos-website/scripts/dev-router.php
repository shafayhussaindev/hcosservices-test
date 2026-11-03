<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server' || !getenv('HCOS_DEV_ROOT')) { http_response_code(404); exit; }
$root = getenv('HCOS_DEV_ROOT');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, '..') || preg_match('#^/(_app|private|src)(/|$)#', $path)) { http_response_code(404); exit; }
// Source PHP changes are reflected without restarting Vite or the backend.
$source = dirname(__DIR__) . '/server';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
  $relative = substr($file->getPathname(), strlen($source) + 1);
  if (str_starts_with(str_replace('\\', '/', $relative), 'uploads/')) continue;
  $dest = $root . '/' . $relative;
  if (!is_file($dest) || filemtime($dest) !== $file->getMTime()) {
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    copy($file->getPathname(), $dest);
    touch($dest, $file->getMTime());
  }
}
if (str_starts_with($path, '/uploads/') && !is_file($root . $path) && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
  header('Location: https://hcosservices.com' . $path, true, 302); exit;
}
return require $root . '/router-dev.php';
