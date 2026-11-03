<?php
declare(strict_types=1);

/* ---------- email -------------------------------------------------------------------
   Plain-text mail through PHP's mail() (Hostinger routes it via the account's
   mail server; From must be an address on the domain for SPF to pass).
   Header values are scrubbed of CR/LF so no input can inject headers.       */

function mail_header_safe(string $s): string {
  return trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
}

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
  global $HCOS;
  if (!valid_email($to)) return false;
  $from = mail_header_safe((string)$HCOS['from_email']);
  $fromName = mail_header_safe((string)$HCOS['from_name']);
  $headers = [
    'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>' : $from),
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: HCOS-Website',
  ];
  if ($replyTo && valid_email($replyTo)) $headers[] = 'Reply-To: ' . mail_header_safe($replyTo);
  $subj = '=?UTF-8?B?' . base64_encode(mail_header_safe($subject)) . '?=';
  try {
    if (PHP_SAPI === 'cli-server' && !empty($HCOS['local_mail_log'])) {
      return file_put_contents($HCOS['local_mail_log'], 'To: ' . $to . "\nSubject: " . $subject . "\n" . implode("\n", $headers) . "\n\n" . $body . "\n--- END LOCAL MESSAGE ---\n", FILE_APPEND | LOCK_EX) !== false;
    }
    return @mail($to, $subj, wordwrap($body, 990, "\n", true), implode("\r\n", $headers), '-f' . $from);
  } catch (Throwable $e) {
    error_log('[HCOS] mail: ' . $e->getMessage());
    return false;
  }
}

function notify_admin(string $subject, string $body, ?string $replyTo = null): void {
  global $HCOS;
  send_mail((string)$HCOS['notify_email'], $subject, $body, $replyTo);
}
