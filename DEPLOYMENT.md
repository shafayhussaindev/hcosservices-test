# Deployment

The live site runs on **Hostinger shared hosting**, PHP 8.3 + MySQL, deployed
over SSH. This document covers deploying a change, setting the project up on a
new machine, and rebuilding the server from scratch.

The server details below come from the handover; verify your access before
deploying. Examples marked `bash` require Git Bash/WSL locally or the Linux SSH
shell. Do not paste the archive pipelines into PowerShell; binary pipeline
handling varies by version. For local setup, use [LOCAL-SETUP.md](LOCAL-SETUP.md).

| | |
|---|---|
| Host | `92.112.189.240`, SSH port **65002**, user `u203446351` |
| Web root | `~/domains/hcosservices.com/public_html` |
| Private config | `~/domains/hcosservices.com/private` (outside the web root) |
| Database | `u203446351_hcos`, user `u203446351_mali`, localhost |
| Web PHP | 8.3 · CLI PHP: `/opt/alt/php82/usr/bin/php` |

---

## 1. Deploying a change

Start at the repository root. Back up the database and uploads first when the
change could affect them; commands are in [DATABASE.md](DATABASE.md#4-backups).
Keep the previous source revision so it can be rebuilt for rollback.

```bash
npm ci                            # install locked React/build dependencies locally
npm run build                     # writes dist/ — fails if JSX or scripts are invalid

tar -C dist -cf - . | ssh -i ~/.ssh/hcos_deploy -p 65002 \
  u203446351@92.112.189.240 'cd ~/domains/hcosservices.com/public_html && tar -xf -'
```

A single file instead (still in the repository root after the commands above):

```bash
scp -i ~/.ssh/hcos_deploy -P 65002 dist/_app/lib/pages.php \
  u203446351@92.112.189.240:~/domains/hcosservices.com/public_html/_app/lib/pages.php
```

**Never `rm -rf` the web root.** `uploads/` lives there and holds every file
staff and attendees have uploaded; it is not in the build and cannot be
regenerated. The `tar -xf` above adds and overwrites but never deletes.

Upload the whole build, including `assets/react/main-<hash>.js`. React is bundled
for browsers; do not upload `node_modules/` or run a Node service on Hostinger.
The first React deployment must include the updated PHP views and controllers
together with the React assets so the initial HTML and hydration data agree.

This also means removed routes can remain on the server. If a change deletes
or renames a generated page, identify and remove only that obsolete deployed
file after verification; preserve `uploads/` and private configuration.

### Verify afterwards

```bash
for p in / /about /services /events /events/hr-seasons /careers /newsroom \
         /newsroom/the-rules-have-changed /contact /api/visits; do
  printf "%-40s " "$p"; curl -s -o /dev/null -w "%{http_code}\n" "https://hcosservices.com$p"
done
```

Everything `200`. `/this-does-not-exist` should give the branded 404.

If you changed CSS or JS, confirm the page references the new hashed URL
(`styles.css?v=…`) — assets are cached for a year, so a changed file must get a
new URL, which the build does automatically.

### Roll back a code change

Build the previous known-good revision in a separate checkout and deploy its
`dist/` using the same archive command. Verify the affected pages again. This
restores code only: it does not undo database migrations, admin content changes
or uploaded files. Database recovery requires a compatible backup and a plan
for changes made since that backup; see [DATABASE.md](DATABASE.md#4-backups).

---

## 2. Access

### Your SSH key

Generate your own; do not share anyone else's.

```bash
ssh-keygen -t ed25519 -C "hcos-deploy@<your-name>" -f ~/.ssh/hcos_deploy -N ""
cat ~/.ssh/hcos_deploy.pub        # send this half to whoever manages the server
```

It is installed by appending that public key to
`~/.ssh/authorized_keys` on the server. Test:

```bash
ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 'whoami'
```

### Your admin account

```bash
ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'cd ~/domains/hcosservices.com/public_html && /opt/alt/php82/usr/bin/php _app/cli.php admin:create'
```

It asks for a user ID, display name, email, and a password (12+ characters,
upper, lower and a number). Sign in at https://hcosservices.com/event-setup/.

Forgot it? Same command with `admin:password`. Never edit `admin_users` in
phpMyAdmin — the password must be hashed by the app.

---

## 3. Server setup from scratch

Only needed on a new host or a rebuilt account.

1. **Create the database** — hPanel → Databases → MySQL Databases. Note the
   database name and user.
2. **Create the private folder and config:**

   ```bash
   mkdir -p ~/domains/hcosservices.com/private && chmod 700 $_
   ```

   Copy `server/_app/config.example.php` to
   `private/config.php`, fill in the database name/user, generate an
   `app_key` (`php -r 'echo bin2hex(random_bytes(32));'`), set the
   notification addresses. `chmod 600`.
3. **Put the database password** on one line in `private/db.pass` (`chmod 600`).
   Nothing else in that file — no quotes, no trailing text.
4. **Deploy** the build (section 1).
5. **Create the tables and the first admin:**

   ```bash
   cd ~/domains/hcosservices.com/public_html
   PHP=/opt/alt/php82/usr/bin/php
   $PHP _app/cli.php status      # should say: connected
   $PHP _app/cli.php migrate
   $PHP _app/cli.php import:json # ONLY on a fresh database — loads the committed snapshot
   $PHP _app/cli.php admin:create
   ```

6. **Mailboxes** — `info@hcosservices.com` (receives registrations and
   messages) and `noreply@hcosservices.com` (sends them). Both must exist on the
   domain or mail will fail SPF.

---

## 4. Without a database

If MySQL is unreachable, the site does not break:

- every public page renders from `_app/data/events.json`
- the forms show *"Online sending is not connected yet"* with a mailto link
- the visitor counter shows its seeded number and stops counting
- the admin shows "not configured" and refuses to load

A plain static host cannot execute the PHP build. Use `npm run build:static`
to produce a static preview without the database or admin. Files uploaded on
the server are not included in that build, so some images may be missing.

---

## 5. If something goes wrong

| Symptom | Look at |
|---|---|
| Blank pages, sections missing | Check that the hashed `assets/react/main-<hash>.js` loads, then check the browser console. Rebuild and upload views and assets together. |
| "Too many redirects" | A cached 301 in the visitor's browser. Check `.htaccess` has no redirect pair; trailing-slash URLs must serve, not redirect. |
| 500 on a PHP page | `~/domains/hcosservices.com/private/logs/app.log` |
| Admin says "not configured" | `cli.php status` — usually `db.pass` has wrong contents |
| Old CSS still showing | The asset URL hash didn't change, or a file was hand-edited on the server |
| Uploads failing | Folder permissions on `uploads/`, and that its `.htaccess` is intact |
