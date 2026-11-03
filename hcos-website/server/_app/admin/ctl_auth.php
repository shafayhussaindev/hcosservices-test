<?php
declare(strict_types=1);

function ctl_login(): never {
  if (admin_logged_in()) redirect(admin_url('dashboard'));
  $error = '';
  if (is_post()) {
    csrf_check();
    $error = admin_login(post_str('username', 60), (string)($_POST['password'] ?? '')) ?? '';
    if ($error === '') redirect(admin_url('dashboard'));
    usleep(random_int(100000, 300000));
  }
  $content = '<div class="login card">'
    . '<img class="login__logo" src="/assets/logos/hcos-logo.png" alt="HCOS" width="56" height="56">'
    . '<h1 class="login__title">Event Setup</h1><p class="login__sub">Sign in to manage events, registrations and uploads.</p>'
    . ($error ? '<div class="flash flash--error" role="alert">' . h($error) . '</div>' : '')
    . '<form method="post" action="' . h(admin_url('login')) . '" autocomplete="on">' . csrf_field()
    . input('username', 'User ID', '', ['autocomplete' => 'username', 'required' => true, 'maxlength' => 60])
    . input('password', 'Password', '', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true])
    . '<button class="b b--primary b--block" type="submit">Sign in</button></form></div>';
  admin_layout_bare('Sign in — Event Setup', $content);
}

function ctl_logout(): never {
  require_post();
  audit('logout');
  admin_logout();
  redirect(admin_url('login'));
}

function ctl_dashboard(): never {
  $c = repo_counts();
  $recent = rows('SELECT e.id AS event_id, e.title, COUNT(r.id) AS registrations, MAX(r.created_at) AS latest FROM events e JOIN registrations r ON r.event_id = e.id GROUP BY e.id, e.title ORDER BY latest DESC LIMIT 8');
  $tiles = '';
  foreach ([
    ['Published events', $c['published'], admin_url('events')],
    ['Upcoming', $c['upcoming'], admin_url('events')],
    ['Registrations', $c['registrations'], admin_url('registrations')],
    ['Uploads to review', $c['uploads_pending'], admin_url('moderation')],
    ['New messages', $c['messages_new'], admin_url('messages')],
    ['Site visits', number_format($c['visits']), '#'],
  ] as [$label, $n, $href]) {
    $tiles .= '<a class="tile" href="' . h($href) . '"><span class="tile__n">' . h((string)$n) . '</span><span class="tile__l">' . h($label) . '</span></a>';
  }
  $rowsHtml = '';
  foreach ($recent as $r) {
    $rowsHtml .= '<tr><td><a href="' . h(admin_url('registrations', ['event' => $r['event_id']])) . '">' . h($r['title']) . '</a></td><td class="num">' . (int)$r['registrations'] . '</td><td class="num">' . h(fmt_dt($r['latest'])) . '</td></tr>';
  }
  $content = page_head('Dashboard', '<a class="b b--primary" href="' . h(admin_url('events')) . '">Manage events</a>')
    . '<div class="tiles">' . $tiles . '</div>'
    . '<section class="card"><h2 class="card__title">Registration activity by event</h2>'
    . ($recent ? '<div class="tablewrap"><table class="table"><thead><tr><th>Event</th><th class="num">Registrations</th><th class="num">Latest registration</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>' : '<p class="muted">No registrations yet.</p>')
    . '</section>';
  admin_layout('Dashboard', $content, 'dashboard');
}

function ctl_account(): never {
  $u = admin_user();
  $content = page_head('My account')
    . '<section class="card"><h2 class="card__title">Change password</h2>'
    . '<form method="post" action="' . h(admin_url('account-password')) . '" class="form-grid form-grid--narrow">' . csrf_field()
    . '<input type="hidden" name="username" value="' . h($u['username'] ?? '') . '" autocomplete="username">'
    . input('current', 'Current password', '', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true])
    . input('new', 'New password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'help' => 'At least 12 characters with upper and lower case letters and a number.'])
    . input('confirm', 'Repeat new password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true])
    . '<div class="actions"><button class="b b--primary" type="submit">Update password</button></div></form></section>'
    . '<section class="card"><h2 class="card__title">Signed in as</h2><p><strong>' . h($u['name'] ?? '') . '</strong> · user ID <code>' . h($u['username'] ?? '') . '</code></p>'
    . '<p class="muted">Other admin users are added from the server with <code>php _app/cli.php admin:create</code>.</p></section>';
  admin_layout('My account', $content, 'account');
}

function ctl_account_password(): never {
  require_post();
  $u = row('SELECT * FROM admin_users WHERE id = ?', [admin_id()]);
  $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
  if (!$u || !password_verify($cur, $u['password_hash'])) { flash('error', 'Your current password was not correct.'); redirect(admin_url('account')); }
  if ($new !== $conf) { flash('error', 'The new passwords do not match.'); redirect(admin_url('account')); }
  if ($err = password_policy_error($new)) { flash('error', $err); redirect(admin_url('account')); }
  q('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($new, password_algo()), (int)$u['id']]);
  audit('password_change', 'admin_user', (int)$u['id']);
  flash('ok', 'Password updated.');
  redirect(admin_url('account'));
}
