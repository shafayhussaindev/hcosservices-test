# Local React development

Run from the repository root:

```powershell
npm run dev -- --port 5174
```

Open http://localhost:5174. This starts the native local database, the PHP
backend and Vite with React Fast Refresh. Do not open HTML files directly.
Ctrl+C stops the development services, shutting the database down cleanly.
No Docker or system-wide database service is used.

## Dashboard and test data

Open http://localhost:5174/event-setup/ or click Employees Portal. Sign in
with `local.admin` and the password in `.cache/local/private/login.txt`.
The local account is separate from the production account.

The local MariaDB database uses port 3307; PHP uses port 8000. Both bind only
to `127.0.0.1`. Vite proxies dashboard/API requests to PHP and uses local
database content for Home, Events, Contact and attendee sharing pages.
The frontend continues to load through Vite, including new event URLs.

The first run imports public event content from `src/data/events.json`.
Existing live registrations, staff accounts and messages are not copied.
Dashboard edits persist locally and appear on the next frontend page load.
They do not edit the JSON seed or the production website.

All local registration/contact emails are captured in
`.cache/local/private/mailbox.log`; none are sent to real inboxes. Uploads
and private headshots stay under `.cache/local/`. Missing public seed banners
are loaded from the live website; no local form submissions go there.

Local data and credentials live in the ignored `.cache/local/` folder.
Keep that folder if you want to retain test data. Vite blocks HTTP access to
the cache, and the PHP server serves only its separate public directory.

React edits use Fast Refresh. PHP source changes are copied into the local
backend on its next request. Restart `npm run dev` after changes to the build
scripts, document template, package configuration or PHP runtime settings.

## Verification

With the app running on port 5174:

```powershell
npm run test:local
```

This tests actual local login, admin screens, event publishing, React data,
registration, private headshots, CSV export and captured email. It creates a
temporary local event and registration, then deletes them. The test refuses
non-localhost origins. For another Vite port, set `HCOS_DEV_ORIGIN` first.

`npm test` runs frontend checks; `npm run test:backend` runs the separate PHP
registration fixture tests.

## Native runtime setup on another Windows machine

Install Node.js matching `package.json` and run `npm install`. The current
machine already has these local runtimes:

- PHP: `.cache/php-runtime/php.exe` with mbstring, GD, Fileinfo and PDO MySQL.
  `PHP_BINARY` can point to another PHP installation with an `ext/` folder.
- MariaDB: `.cache/mariadb-11.4.10-winx64/bin/mariadbd.exe`, extracted from
  the [official Windows archive](https://archive.mariadb.org/mariadb-11.4.10/winx64-packages/).
  The downloaded ZIP was checked against the publisher's SHA-256 checksum.

The launcher initializes a database inside this project, generates local
credentials, applies the existing schema/importer and creates the local
administrator. It does not install a Windows service or use another MySQL
instance. No additional npm dependency was added.
