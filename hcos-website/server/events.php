<?php
// /events  and  /events/<slug>   (rewritten here by .htaccess)
require __DIR__ . '/_app/bootstrap.php';
$slug = $_GET['slug'] ?? '';
if ($slug === '') page_events();
if (!is_string($slug) || !valid_slug($slug)) page_404();
page_event($slug, ($_GET['view'] ?? '') === 'register');
