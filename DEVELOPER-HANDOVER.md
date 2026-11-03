# HCOS Website — Developer Handover

Architecture and maintenance guide for developers. Start with the
[README](README.md) for the reading order and documentation map.

Commands marked `bash` use Git Bash/WSL locally or the Linux SSH shell, not
PowerShell. Hosting details and past verification dates below were recorded
during the original handover; they have not been rechecked against the server.

**Live site:** https://hcosservices.com
**Admin (Event Setup):** https://hcosservices.com/event-setup/ — nothing on the
public site links here, by design. Do not add a link. Screen-by-screen guide:
**[ADMIN-PORTAL.md](ADMIN-PORTAL.md)**.

---

## 1. What this is, in one minute

A website for HCOS Services (Karachi — events, HR and sustainability
consulting). It started as a static site and now has a small PHP/MySQL
application behind the event pages so staff can manage events themselves.

- **Public pages** — Home, About, Services, Events, Careers, Newsroom, Contact,
  plus a detail page per event and per news article.
- **Event Setup** — a private admin where staff edit events: catalogue,
  registration details, speakers, sponsors, agenda, photo gallery; and where
  they read registrations, contact messages and attendee uploads.
- **Attendee flow** — a visitor registers for an upcoming event, gets a private
  link by email, and can upload photos/documents through it. Nothing they
  upload is public until an admin approves it.

Hosting is **Hostinger shared hosting**, PHP 8.3 + MySQL, deployed over SSH.
The public frontend uses React with JavaScript/JSX and Vite. PHP has no
framework or Composer requirement. Run `npm ci` in the repository root before building, using
Node 22.13+ (22.x) or Node 24+. Node and npm are not needed on the live server.
The Event Setup admin remains a PHP interface.

---

## 2. Architecture — how a request is served

The site is **built**, not served from the source tree. `scripts/build.cjs` composes
the source into `dist/`, and the contents of `dist/` are what lives in the
server's `public_html/`.

```
src/pages/*.jsx public page layouts (JavaScript, no TypeScript)
src/App.jsx, src/main.jsx  React app and browser entry point
src/components/           Navigation, Footer, EventPopup, Html
src/lib/                  interactions and event date logic
src/data/page-metadata.json  titles, descriptions and route metadata
index.html                Vite development document
vite.config.js            React plugin and dev/build integration
server/_app/views/document.html  production document and React boot data
server/_app/views/fragments/registration.html  PHP registration fragment
src/data/*.json       content: site.json, events.json, news.json, ...
public/assets/               images, video, css, js  ──┐
server/           PHP application           │
          │                                          │
          └── npm run build ─────────────────────────┴──> dist/  ──> public_html/
```

Two kinds of page come out of the build:

| Kind | Pages | How it works |
|---|---|---|
| **Static** | About, Services, Careers, Newsroom (+ articles), 404 | Plain `.html` files written at build time. |
| **PHP-rendered** | Home, Events, every event page, Contact, attendee share page | The build writes a *view* to `dist/_app/views/pages/<name>.php` with everything static already resolved and only the dynamic parts left as `<?= $v['KEY'] ?>`. At request time `index.php` / `events.php` / `contact.php` / `share.php` fill those from MySQL. |

**React rendering:** `build.js` uses the JSX components to render initial HTML
and bundles the browser entry `src/main.jsx` to a hashed file under
`dist/assets/react/`. The browser hydrates that HTML using JSON boot data. PHP
views include the same live values in the HTML and boot JSON, so they must be
deployed together. Never put secrets in React props; boot data is public.

Event content, registration forms and attendee pages still use application-
rendered HTML fragments from PHP (and matching build helpers for static preview).
`Html` and `dangerouslySetInnerHTML` accept only those trusted, escaped fragments,
never raw visitor input. `decode()` handles text values already escaped by PHP.
Legacy menu, slider, video, form, counter and reveal handlers are initialized
once after hydration in `interactions.js`; the popup uses React state/effects.
Navigation uses normal links and the existing server routes, not a client router.

`npm run build:static` produces a **static React preview** (no admin, no
database) — useful for local preview and as a fallback. The default (no flag)
produces the PHP site.

One caveat: anything staff upload through the admin is stored in `uploads/` on
the server, which is **not** part of a static build. A `--static` copy will
reference those files and not find them. It is a preview and a fallback, not a
substitute for the live site.

### Request routing

`.htaccess` in the web root does the routing. The important rules:

```
/events/<slug>/share/<64-hex>  →  share.php?slug=…&t=…
/events/<slug>/register        →  events.php?slug=…&view=register
/events/<slug>                 →  events.php?slug=…
/events                        →  events.php
/api/<name>                    →  api/<name>.php
/about                         →  about.php if it exists, else about.html
```

Plus: force HTTPS and the bare domain, canonical clean URLs (`/about.html` and
`/events.php` 301 to `/about` and `/events`), deny `/_app` and `/src`, long
cache for assets, no cache for HTML.

---

## 3. The code

### Public entry points (web root)

| File | Serves |
|---|---|
| `index.php` | `/` |
| `events.php` | `/events` and `/events/<slug>` |
| `contact.php` | `/contact` |
| `share.php` | `/events/<slug>/share/<token>` — attendee upload page |
| `api/register.php` | event registration (POST) |
| `api/contact.php` | contact form (POST) |
| `api/upload.php` | attendee file upload (POST) |
| `api/visits.php` | footer visitor counter (GET/POST) |
| `event-setup/index.php` | the admin — single entry, routes by `?r=` |

### Application (`_app/`, denied to the web)

| File | Responsibility |
|---|---|
| `bootstrap.php` | Loads private config, hardened PHP defaults, error handler. Every entry point includes this first. |
| `lib/db.php` | PDO connection, `q()` / `row()` / `rows()` / `scalar()`, `db_migrate()`. |
| `lib/repo.php` | Reads events into **one shape** whether they come from MySQL or the JSON snapshot. Renderers never touch SQL. |
| `lib/render.php` | Event fragments (cards, details, speakers, logos, materials, gallery, brochure, registration form). A port of the same functions in `build.js`. |
| `lib/pages.php` | Page controllers: `page_home()`, `page_events()`, `page_event()`, `page_contact()`, `page_share()`, `page_404()`. |
| `lib/uploads.php` | All file handling: validation, re-encoding, storage, deletion. |
| `lib/auth.php` | Admin sessions, login, throttling, CSRF, password policy. |
| `lib/helpers.php` | Escaping, request helpers, rate limiting, signed form timestamps, audit log. |
| `lib/mail.php` | Plain-text mail with header-injection protection. |
| `admin/router.php` | Maps `?r=…` to a controller. Everything except `login` requires a session; every POST requires CSRF. |
| `admin/ctl_*.php` | Controllers: auth/dashboard, events, registrations, moderation, messages. |
| `admin/layout.php` | Admin chrome + small UI helpers (`input()`, `select()`, `action_button()`, `thumb()`…). |
| `cli.php` | `migrate`, `admin:create`, `admin:password`, `import:json`, `export:json`, `status`. |
| `schema.sql` | The database. Idempotent. |
| `data/events.json` | Snapshot used **only** when the database is unreachable. |

### Config — the only secret

Lives **outside** the web root, at
`~/domains/hcosservices.com/private/`:

- `config.php` — database name/user, `app_key`, notification addresses (mode 600)
- `db.pass` — the database password, on one line, nothing else (mode 600)

`config.example.php` in `_app/` documents the keys. **Neither file is ever
committed, zipped, or pasted into chat.** If the database is unreachable the
site still renders every page from `data/events.json` and the forms fall back
to a mailto link — it degrades, it does not break.

---

## 4. Database

Fourteen tables, created by `php _app/cli.php migrate`. Full reference, including
access routes, migrations, backups and useful queries: **[DATABASE.md](DATABASE.md)**.

```
events ─┬─ event_details      label/value rows for the "Event details" box
        ├─ event_people       speakers & panel — every field editable
        ├─ event_orgs         sponsors / partners, grouped by tier
        ├─ event_materials    brochure, agenda pages, posters, documents
        ├─ event_gallery      approved photos
        └─ registrations ──── registration_uploads   (pending → approved/rejected)

contact_messages   admin_users   login_attempts   audit_log   rate_limits   visits
```

Things worth knowing:

- **`events.status`** is `draft | published | archived`. Only `published` is
  public. A draft is invisible even if you know the URL.
- **The dedicated registration page is available only when** `is_upcoming` **and**
  `registration_open` are both set. Event promotion pages link to
  `/events/<slug>/register` and contain no embedded form. The countdown uses the
  event's Date detail and counts to midnight in Pakistan time, since event start
  times are not a structured field. Unknown dates hide the countdown; elapsed
  dates show a message without changing the staff-controlled registration state.
- **Attendee uploads** land in `registration_uploads` as `pending`. Approving a
  photo copies a row into `event_gallery`; approving a PDF copies it into
  `event_materials` as a `document`. Rejecting removes it from both again.
- **`audit_log`** records every admin action (who, what, when, from where).
  Append-only in practice — nothing in the app updates or deletes it.
- Adding a column later: put it in `schema.sql` *and* add a
  `db_ensure_column(...)` line in `db_migrate()`. `CREATE TABLE IF NOT EXISTS`
  will not alter an existing table.

The committed snapshot contains seven events. The original production import
has already run — use `cli.php import:json` only on a fresh database. Live event
content belongs to MySQL; refresh the fallback with `export:json` after content
changes, then copy it back to `src/data/events.json` and commit it.

---

## 5. Working on it

### Local

```bash
npm ci
npm run dev               # static React preview at http://localhost:5173
```

That is the static preview — Node plus installed npm dependencies, no database.
Vite updates JSX/CSS edits automatically with React Fast Refresh. To run the full stack
locally (PHP, MySQL, the portal), follow **[LOCAL-SETUP.md](LOCAL-SETUP.md)**:

```bash
cd dist && php -S localhost:8000 router-dev.php
```

`router-dev.php` reproduces the live `.htaccess` routing, which PHP's built-in
server otherwise ignores.

Every build deletes and recreates local `dist/`, including any test
uploads there. Keep source edits in `src/` or `public/assets/` and back up
local uploads before rebuilding if you need to preserve them.

### Deploying

Deployment is: build, then copy `dist/` over `public_html/`.

```bash
npm run build
tar -C dist -cf - . | ssh -i ~/.ssh/hcos_deploy -p 65002 \
  u203446351@92.112.189.240 'cd ~/domains/hcosservices.com/public_html && tar -xf -'
```

For a single file, `scp` it to the same path. After deploying, verify:

```bash
for p in / /about /services /events /events/hr-seasons /careers /newsroom \
         /newsroom/the-rules-have-changed /contact /api/visits; do
  printf "%-40s " "$p"; curl -s -o /dev/null -w "%{http_code}\n" "https://hcosservices.com$p"
done
```

All should be `200`. See [DEPLOYMENT.md](DEPLOYMENT.md) for the full procedure
and rollback guidance. A whole-directory comparison also includes server-owned
uploads and runtime files, so it is not a build parity check.

**`uploads/` is server-owned.** It holds everything staff and attendees upload
and is *not* in the build. Never delete it, never let a deploy wipe it — the
`tar -xf` above adds and replaces files but does not remove any, which is why
it is written that way.

### Server commands

```bash
ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240
cd ~/domains/hcosservices.com/public_html
PHP=/opt/alt/php82/usr/bin/php        # the CLI PHP; the web runs 8.3

$PHP _app/cli.php status              # config, DB, uploads, counts
$PHP _app/cli.php migrate             # apply schema changes (safe to re-run)
$PHP _app/cli.php admin:create        # add an admin user
$PHP _app/cli.php admin:password      # reset one
```

Error log: `~/domains/hcosservices.com/private/logs/app.log`.

---

## 6. Rules that must not be broken

These are not style preferences. Each one is load-bearing.

1. **Every value that reaches HTML goes through `h()`** (or `esc_js()` in the
   build-parity renderers). No exceptions, including data that came from the
   admin — an admin account is not a licence to inject script.
2. **Every SQL statement is a prepared statement.** No string interpolation into
   SQL, ever. Table names in helpers like `renumber()` are internal constants,
   never user input.
3. **Every admin POST goes through CSRF.** `admin_dispatch()` calls
   `csrf_check()` for you; don't add a route that bypasses it, and don't make a
   state-changing GET.
4. **Uploaded images are re-encoded, never stored as received.** That is what
   strips metadata and any payload hidden in the original. PDFs are checked for
   `/JavaScript`, `/Launch`, `/EmbeddedFile`, `/OpenAction` and refused if found.
   The type is sniffed with `finfo` — never trusted from the filename.
5. **`uploads/` must keep its `.htaccess`.** It forbids script execution. If it
   is lost, an uploaded file could be executed.
6. **Secrets stay in `private/`.** Not in the repo, not in `public_html`, not in
   a chat message, not in a zip. If one leaks, rotate it in hPanel immediately.
7. **Passwords are hashed by the app** (Argon2id). Never write to
   `admin_users.password_hash` by hand or through phpMyAdmin.
8. **The public site must survive a database outage.** Keep the `if (!db())`
   fallbacks working; don't make a public page that throws when MySQL is down.

---

## 7. Gotchas this project has already hit

Save yourself the afternoon:

- **PHP eats the newline after `?>`.** The build adds one back when it writes a
  view, so PHP output stays byte-identical to the static build. If you change
  `renderPhpView()`, re-run the parity check in §8.
- **Browsers cache 301s.** An `.htaccess` edit that redirects `/x` → `/x/` and
  then back again leaves visitors in a permanent loop that no server fix clears
  from *their* cache. Trailing-slash URLs now **serve** the page rather than
  redirecting, deliberately. Don't reintroduce a redirect pair.
- **Assets are cached for a year** (`immutable`). `build.js` appends a content
  hash — `styles.css?v=b515c40ea9` — so a changed file gets a new URL. Never
  hand-edit a file in `public_html/assets` or returning visitors keep the old one.
- **A failed React bundle can leave reveal sections hidden.** Vite checks
  frontend syntax and imports; `build.js` also checks the standalone admin
  script. Keep those checks and deploy matching HTML, boot data and bundles.
- **Hostinger's MySQL drops idle connections after ~20 seconds.** Don't open a
  connection and then wait for user input. `cli.php admin:create` asks for
  everything first and connects last, for this reason.
- **`shell_exec`, `exec`, `system`, `popen` are disabled** on this host.
  `proc_open` works (that is how hidden password input is done).
- **The CLI is PHP 7.3 by default** — always call `/opt/alt/php82/usr/bin/php`
  explicitly, or `declare(strict_types=1)` and `never` return types will fail.

---

## 8. Checks before you call something done

The homepage popup reads published upcoming events from the same repository as
the cards. `src/lib/event-dates.js` applies the date cutoff in Pakistan time;
see [ADMIN-PORTAL.md](ADMIN-PORTAL.md#homepage-event-popup) for staff controls.
Run `npm test` from the repository root: date checks plus browser-DOM hydration of public
pages, popup dismissal, navigation, form transport and PHP-slot escaping.
Tests rebuild `dist/` in both modes and finish with the PHP build. They simulate
PHP slot substitution and mock network requests; they do not execute PHP,
validate the real database, or test email delivery.

```bash
npm ci
npm run build                      # validates JSX, scripts and placeholders
npm test
```

**Parity check** (only needed if you touched `build.js`, `render.php` or
`pages.php`): build both modes and confirm PHP output matches the static build
byte-for-byte for equivalent data. There is no `reports/` folder or committed
parity test in this checkout. Build each mode in a separate temporary checkout
(both otherwise overwrite `dist/`), render the PHP pages against the same
snapshot, and compare the corresponding HTML. This is a manual check.

**After any change to uploads, auth or the admin**, re-run the end-to-end pass:
register → share link → upload (valid, oversized, PDF-with-JavaScript,
fake image) → approve → check the public gallery → reject → delete. Every one of
those cases was verified working on 21 Sep 2026; keep it that way.

---

## 9. Open items

Maintain pending client and infrastructure decisions in
[PRE-LAUNCH-CHECKLIST.md](PRE-LAUNCH-CHECKLIST.md). Access transfer and operational
risks belong in [HANDOVER-TO-SHAFAY.md](HANDOVER-TO-SHAFAY.md). Update those lists
when decisions are confirmed rather than keeping another copy here.

---

## 10. Who to ask

- **Content, design intent, client decisions** — Alizah Khan (ITG, Generative Media)
- **Hosting, DNS, mailboxes** — whoever holds the Hostinger account
- **Code and schema define implemented behavior.** Update this guide and any
  affected task-specific guide in the same commit when behavior changes.
