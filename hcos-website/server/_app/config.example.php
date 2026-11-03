<?php
/**
 * HCOS website — private configuration.
 *
 * Copy this file to:   <domain folder>/private/config.php
 * i.e. one level ABOVE public_html, e.g.
 *   /home/u203446351/domains/hcosservices.com/private/config.php
 * That folder is not web-served, so these values never leave the server.
 * Keep the file mode 0600.
 */
return [
  // MySQL database (create it in hPanel → Databases → MySQL Databases).
  'db' => [
    'host' => 'localhost',
    'name' => 'u203446351_hcos',
    'user' => 'u203446351_hcos',
    'pass' => 'CHANGE-ME',
  ],

  // Random secret used to sign form timestamps. Generate one with:
  //   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
  'app_key' => 'CHANGE-ME',

  // Where registrations and contact messages are sent.
  'notify_email' => 'info@hcosservices.com',
  // Sender address — must be a mailbox on this domain (create it in hPanel → Emails).
  'from_email' => 'noreply@hcosservices.com',
  'from_name' => 'HCOS Services',

  'site_url' => 'https://hcosservices.com',
];
