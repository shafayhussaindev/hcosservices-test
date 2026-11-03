<?php
// /events/<slug>/share/<token> — an attendee's private page after registering.
require __DIR__ . '/_app/bootstrap.php';
$slug = $_GET['slug'] ?? '';
$token = $_GET['t'] ?? '';
if (!is_string($slug) || !is_string($token) || !valid_slug($slug) || !preg_match('/^[a-f0-9]{64}$/', $token)) page_404();
page_share($slug, $token);
