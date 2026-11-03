<?php
/**
 * POST /api/upload — attendee photo/document upload via their private link.
 * Everything lands as "pending"; an admin approves it before it is public.
 */
require __DIR__ . '/../_app/bootstrap.php';

if (!is_post()) json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (!db()) json_out(['ok' => false, 'error' => 'Uploads are not available right now.'], 503);

$token = post_str('token', 64);
if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_out(['ok' => false, 'error' => 'This link is not valid.'], 403);
if (!form_stamp_ok(post_str('stamp', 120), 1)) json_out(['ok' => false, 'error' => 'Please reload the page and try again.'], 400);

$reg = row('SELECT r.*, e.slug, e.title, e.attendee_uploads, e.status AS event_status FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.upload_token = ?', [$token]);
if (!$reg || $reg['event_status'] !== 'published') json_out(['ok' => false, 'error' => 'This link is not valid.'], 403);
if (!(int)$reg['attendee_uploads'] || $reg['status'] === 'cancelled') json_out(['ok' => false, 'error' => 'Uploads are closed for this event.'], 403);

if (!rate_limit('upload:' . $token, 60, 3600)) json_out(['ok' => false, 'error' => 'Upload limit reached for now. Please try again later.'], 429);
$existing = (int)scalar('SELECT COUNT(*) FROM registration_uploads WHERE registration_id = ?', [(int)$reg['id']]);
if ($existing >= 100) json_out(['ok' => false, 'error' => 'You have reached the maximum number of files for this event.'], 429);

$files = files_list($_FILES['files'] ?? null);
if (!$files) json_out(['ok' => false, 'error' => 'Please choose at least one file.'], 422);
if (count($files) > 20) json_out(['ok' => false, 'error' => 'Please upload at most 20 files at a time.'], 422);

$saved = []; $errors = [];
foreach ($files as $f) {
  $orig = clean_filename((string)$f['name']);
  try {
    $stored = store_image_or_pdf($f, $reg['slug'], 'contributions', 2400, HCOS_PUBLIC_IMAGE_MAX, HCOS_PUBLIC_PDF_MAX);
    $kind = $stored['mime'] === 'application/pdf' ? 'document' : 'photo';
    $size = (int)@filesize(HCOS_ROOT . '/' . $stored['path']);
    q('INSERT INTO registration_uploads (registration_id, event_id, kind, file, original_name, mime, size) VALUES (?,?,?,?,?,?,?)',
      [(int)$reg['id'], (int)$reg['event_id'], $kind, $stored['path'], $orig, $stored['mime'], $size]);
    $saved[] = ['name' => $orig, 'kind' => $kind];
  } catch (UploadException $e) {
    $errors[] = $orig . ': ' . $e->getMessage();
  } catch (Throwable $e) {
    error_log('[HCOS] upload: ' . $e->getMessage());
    $errors[] = $orig . ': could not be saved.';
  }
}

if ($saved) {
  notify_admin('Attendee upload: ' . $reg['title'],
    "{$reg['name']} ({$reg['email']}) shared " . count($saved) . " file(s) for {$reg['title']}.\n\n"
    . 'Review: ' . hcos_url('event-setup/?r=moderation'));
}

json_out(['ok' => (bool)$saved, 'saved' => $saved, 'errors' => $errors,
  'error' => $saved ? null : ($errors[0] ?? 'Nothing was uploaded.')], $saved ? 200 : 422);
