# Running the whole thing locally

The full site — PHP pages, the database and the Event Setup portal — on your
own machine. Nothing here touches the live site.

**There is no separate "backend folder" to be sent.** The backend is in this
repository at `server/`, and the database is not a folder at all: it
is a MySQL database you create locally from `_app/schema.sql`. Everything below
comes from the repo you already have.

---

## 1. What to install

| | Why | Check |
|---|---|---|
| **PHP 8.2+** with `pdo_mysql`, `gd` (or `imagick`), `fileinfo`, `mbstring` | the application | `php -v` and `php -m` |
| **MySQL 8 or MariaDB 10.4+** | the database | `mysql --version` |
| **Node 22.13+ (22.x) or Node 24+**, with npm | React build and tests only | `node -v` and `npm -v` |

On Windows the simplest route is **Laragon** or **XAMPP** (both bundle PHP and
MySQL); make sure `php` and `mysql` are on your `PATH`. On macOS,
`brew install php mysql`. On Linux, your package manager.

---

## 2. Clone and build

Run these commands in PowerShell or Bash. If the repository is already on your
machine, open its root folder and start with `npm ci`.

```text
git clone https://github.com/learneralizah-spec/hcos-website.git
cd hcos-website
npm ci
npm run build
```

That writes `dist/` — the web root, containing the PHP application. Run it
again after any change to source, data or assets. **The build deletes and
recreates `dist/`, including local test uploads.** Back up local uploads outside
`dist/` first if you need to keep them; `private/` is outside this folder.

---

## 3. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE hcos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hcos'@'localhost' IDENTIFIED BY 'choose-your-own-local-password';
GRANT ALL PRIVILEGES ON hcos.* TO 'hcos'@'localhost'; FLUSH PRIVILEGES;"
```

This password is local and disposable. **Never reuse the production one, and
never copy the production password onto a laptop.**

---

## 4. Point the app at it

Config lives one level above the web root — so with `dist/` as the web
root, it goes in `private/`. Git ignores that folder.

You should still be in the repository root after step 2. In PowerShell:

```powershell
New-Item -ItemType Directory -Force private
Copy-Item server/_app/config.example.php private/config.php
```

In Bash instead:

```bash
mkdir -p private
cp server/_app/config.example.php private/config.php
```

Edit `private/config.php`:

```php
'db' => ['host' => 'localhost', 'name' => 'hcos', 'user' => 'hcos', 'pass' => 'choose-your-own-local-password'],
'app_key' => '<paste the output of the command below>',
'site_url' => 'http://localhost:8000',
```

Generate the key:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

(You can put the password straight in `config.php` locally. The server uses a
separate `db.pass` file so the password is not in the same file as everything
else — worth keeping in mind, but not necessary on your own machine.)

---

## 5. Create the tables and load content

```bash
cd dist
php _app/cli.php status        # expect: Database : connected (hcos)
php _app/cli.php migrate       # creates the 14 tables
php _app/cli.php import:json   # loads the 7 events from the snapshot
php _app/cli.php admin:create  # your local admin login
```

On Windows, the CLI cannot hide password input and prints a warning. Use a
local-only password and do not share terminal recordings containing it.

`import:json` seeds from `_app/data/events.json`, which is committed to the
repo and contains **no personal data** — events, speakers, sponsors and photo
paths only. That is the right way to get realistic content locally.

> **Do not copy the production database to your machine.** It holds
> registrants' names, email addresses, phone numbers and IPs. If you ever need
> to reproduce a bug with real data, discuss it first — the answer is usually a
> targeted query on the server, not a copy of the whole thing.

---

## 6. Run it

```bash
# You should still be in dist/ after step 5.
php -S localhost:8000 router-dev.php
```

Open **http://localhost:8000**. The portal is at
**http://localhost:8000/event-setup/**.

`router-dev.php` reproduces the live `.htaccess` rules — clean URLs,
`/events/<slug>`, the attendee share link, `/api/<name>` and the branded 404 —
because PHP's built-in server ignores `.htaccess`. Apache never uses this file;
it is denied in the live config.

**Rebuild after editing source:** `npm run build` from the repository root, then reload.
The built-in server picks up PHP changes immediately, but `dist/` is generated,
so editing files *inside* `dist/` means losing them on the next build. Always
edit in `src/` or `public/assets/`.

---

## 7. What differs from production

| | Locally |
|---|---|
| **Email** | `mail()` usually does nothing. Registrations and messages are still stored; the confirmation email just never arrives. Check the `registrations` table, and find the share link there in `upload_token`. |
| **Uploaded files** | `uploads/` starts empty. Events imported from the snapshot reference a few files that only exist on the server, so a couple of images 404. Upload your own through the portal to exercise the flow. |
| **HTTPS** | Not used, so the session cookie drops its `Secure` flag automatically. Nothing to configure. |
| **Image compression** | Uses Imagick if you have it, otherwise GD. Both work; output differs slightly. |

---

## 8. Making a change, end to end

1. Edit public layouts in `src/`, PHP in `server/`, or CSS/assets in `public/assets/`
2. `npm run build` from the repository root
3. Reload `localhost:8000` and check it
4. Commit with a message that says *why*
5. Deploy — see [DEPLOYMENT.md](DEPLOYMENT.md)
6. Verify on the live site

Before any schema change, read [DATABASE.md](DATABASE.md) §3 — the schema lives
in two places on purpose and both must be updated.

For a frontend-only preview, use `npm run dev` from the repository root and open
`http://localhost:5173`. This uses the JSON snapshot and does not run PHP or the
admin. Vite updates the preview automatically after frontend edits.
Run `npm test` for React hydration, navigation, popup and form-transport checks.
Those checks simulate PHP template substitutions; they do not test PHP execution
or a real database and also replace local `dist/`.
