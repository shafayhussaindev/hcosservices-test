<?php
/** POST /api/contact — contact form. Stores the message and notifies HCOS. */
require __DIR__ . '/../_app/bootstrap.php';

if (!is_post()) json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (!db()) json_out(['ok' => false, 'error' => 'Sending is not available right now.'], 503);

if (post_str('website', 10) !== '') json_out(['ok' => true]); // honeypot: pretend success
if (!form_stamp_ok(post_str('stamp', 120))) json_out(['ok' => false, 'error' => 'Please reload the page and try again.'], 400);
if (!rate_limit('contact:' . client_ip(), 6, 3600)) json_out(['ok' => false, 'error' => 'Too many messages from this connection. Please try later.'], 429);

$name = post_str('name', 160);
$email = mb_strtolower(post_str('email', 190));
$phone = post_str('phone', 40);
$message = post_str('message', 5000);

if (!valid_email($email)) json_out(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
if (mb_strlen($message) < 5) json_out(['ok' => false, 'error' => 'Please write a message.'], 422);
if (preg_match_all('#https?://#i', $message) > 3) json_out(['ok' => false, 'error' => 'Please remove the links from your message.'], 422);

q('INSERT INTO contact_messages (name, email, phone, message, ip) VALUES (?,?,?,?,?)', [$name ?: null, $email, $phone ?: null, $message, client_ip()]);
$id = last_id();

notify_admin('Website enquiry from ' . ($name ?: $email),
  "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n{$message}\n\n" . 'View: ' . hcos_url('event-setup/?r=messages&id=' . $id), $email);

json_out(['ok' => true]);
