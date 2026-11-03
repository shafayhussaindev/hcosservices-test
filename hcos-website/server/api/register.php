<?php
/**
 * POST /api/register — event registration.
 * Stores the registration, emails the attendee their private upload link,
 * notifies HCOS, and tells the front end where to send the visitor next.
 */
require __DIR__ . '/../_app/bootstrap.php';

if (!is_post()) json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (!db()) json_out(['ok' => false, 'error' => 'Registration is not available right now.'], 503);

// Bot checks: honeypot must be empty, signed timestamp must be sane.
if (post_str('website', 10) !== '') json_out(['ok' => true, 'redirect' => '/events']); // silently drop
if (!form_stamp_ok(post_str('stamp', 120))) json_out(['ok' => false, 'error' => 'Please reload the page and try again.'], 400);
if (!rate_limit('register:' . client_ip(), 10, 3600)) json_out(['ok' => false, 'error' => 'Too many registrations from this connection. Please try later.'], 429);

$slug = post_str('event_slug', 120);
$event = valid_slug($slug) ? repo_event_by_slug($slug) : null;
if (!$event || !$event['upcoming'] || !$event['registration_open']) json_out(['ok' => false, 'error' => 'Registration for this event is closed.'], 400);

$name = post_str('name', 160);
$org = post_str('organization', 200);
$role = post_str('designation', 200);
$email = mb_strtolower(post_str('email', 190));
$phone = post_str('phone', 40);
$linkedin = post_str('linkedin_url', 512);
$gender = post_str('gender', 32);
$priceOption = post_str('price_option', 32);

if ($name === '' || mb_strlen($name) < 2) json_out(['ok' => false, 'error' => 'Please enter your full name.'], 422);
if (!valid_email($email)) json_out(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
if (!preg_match('/^[\d\s()+.-]{6,40}$/', $phone)) json_out(['ok' => false, 'error' => 'Please enter a valid phone number.'], 422);
if ($org === '') json_out(['ok' => false, 'error' => 'Please enter your organization.'], 422);
if ($role === '') json_out(['ok' => false, 'error' => 'Please enter your designation.'], 422);
if (!valid_linkedin_profile($linkedin)) json_out(['ok' => false, 'error' => 'Please enter a valid HTTPS LinkedIn profile URL.'], 422);
if (!isset(HCOS_REG_GENDERS[$gender])) json_out(['ok' => false, 'error' => 'Please select a gender option.'], 422);
if (!isset(HCOS_REG_PRICES[$priceOption])) json_out(['ok' => false, 'error' => 'Please select a participant price option.'], 422);
$photo = files_list($_FILES['headshot'] ?? null);
if (count($photo) !== 1) json_out(['ok' => false, 'error' => 'Please upload one headshot photo.'], 422);

// One registration per email per event: re-use the existing record so a
// second submit just re-sends the link instead of creating a duplicate.
$existing = row('SELECT * FROM registrations WHERE event_id = ? AND email = ?', [(int)$event['id'], $email]);
$headshot = null;
try {
  $headshot = store_image($photo[0], 'event-' . (int)$event['id'], 'headshots', 1000, HCOS_PUBLIC_IMAGE_MAX);
  if ($existing) {
    // A public resubmission must not overwrite another attendee's saved profile.
    delete_upload($headshot['path']);
    $token = $existing['upload_token'];
    $regId = (int)$existing['id'];
    $name = $existing['name']; $org = $existing['organization']; $role = $existing['designation'];
    $phone = $existing['phone']; $linkedin = $existing['linkedin_url'] ?? ''; $gender = $existing['gender'] ?? '';
    $priceOption = $existing['price_option'] ?? '';
  } else {
    $token = random_token(32);
    q('INSERT INTO registrations (event_id, name, organization, designation, email, phone, linkedin_url, gender, price_option, headshot, upload_token, ip, user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [(int)$event['id'], $name, $org ?: null, $role ?: null, $email, $phone ?: null, $linkedin, $gender, $priceOption, $headshot['path'], $token, client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $regId = last_id();
  }
} catch (UploadException $e) {
  if ($headshot) delete_upload($headshot['path']);
  json_out(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
  if ($headshot) delete_upload($headshot['path']);
  throw $e;
}

$shareUrl = hcos_url('events/' . $event['slug'] . '/share/' . $token);
$priceLabel = HCOS_REG_PRICES[$priceOption] ?? 'Not supplied';

send_mail($email, 'Registration received — ' . $event['title'],
  "Dear {$name},\n\n"
  . "Thank you for registering for {$event['title']}. The HCOS events team will be in touch with confirmation and joining instructions.\n\n"
  . "Participant price: {$priceLabel}\nThese options are subject to verification before payment can be accepted\n\n"
  . ($event['attendee_uploads'] ? "After the event you can share your photos and documents with us through your private link:\n{$shareUrl}\n\nPlease keep this link to yourself.\n\n" : '')
  . "Kind regards,\nHCOS Services\n" . hcos_url());

notify_admin('New registration: ' . $event['title'],
  "Participant price: {$priceLabel}\n"
  . "Event: {$event['title']}\nName: {$name}\nOrganization: {$org}\nDesignation: {$role}\nEmail: {$email}\nPhone: {$phone}\nLinkedIn: {$linkedin}\nGender: " . (registration_gender_label($gender)) . "\n\n"
  . 'Manage: ' . hcos_url('event-setup/?r=registrations&event=' . (int)$event['id']), $email);

json_out(['ok' => true, 'redirect' => '/events/' . $event['slug'] . '/share/' . $token]);
