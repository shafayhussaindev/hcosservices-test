# HCOS Website

The website for HCOS Services — **https://hcosservices.com** — plus the private
admin that staff use to manage events, registrations and uploads.

## Where to start

| What you need | Read |
|---|---|
| Understand the project and its code | [Developer handover](DEVELOPER-HANDOVER.md) |
| Take ownership and verify your access | [Handover to Shafay](HANDOVER-TO-SHAFAY.md) |
| Run PHP, MySQL and the admin locally | [Local setup](LOCAL-SETUP.md) |
| Manage events, registrations and uploads | [Admin portal](ADMIN-PORTAL.md) |
| Understand tables, migrations and backups | [Database reference](DATABASE.md) |
| Deploy a change or rebuild the server | [Deployment](DEPLOYMENT.md) |
| Find pending client decisions | [Open items](PRE-LAUNCH-CHECKLIST.md) |
| Add jobs or testimonials | [Jobs format](src/data/jobs.README.md), [testimonials format](src/data/testimonials.README.md) |

For a first read: this README → developer handover → local setup. Shafay should
also work through the ownership checklist before changing the live server.

## How the pieces fit together

`src/` + `public/assets/` → `npm run build` → `dist/` → server `public_html/`.

Staff edit event content in the admin, which saves to MySQL. The JSON event
snapshot is a fallback when the database cannot connect; it is refreshed
manually. Uploaded files live separately in `uploads/` and need their own backup.
Private configuration lives outside the web root.

The code and schema describe implemented behavior. Live counts, access and
hosting details in the handover documents are historical records, not a fresh
server check. Keep pending decisions in the open-items checklist and update the
relevant guide whenever behavior changes.

---

## What it is

A built site: `scripts/build.cjs` composes the source in `src/` plus
`public/assets/` into `dist/`, and `dist/` is what the web server serves.

- **Static pages** — About, Services, Careers, Newsroom (+ articles), 404
- **PHP-rendered from MySQL** — Home, Events, every event page, Contact, and the
  attendee upload page
- **Admin** — `/event-setup/` (no link from the public site; open it directly)

Event pages promote the event with a banner, a countdown to event day in Pakistan
time, details, programme and sponsor/partner logos. Open registrations use a
dedicated `/events/<slug>/register` page; the event page and homepage popup link
there. The existing `#register` anchor leads to the event's registration callout.

Stack: React with JavaScript/JSX for the public frontend, existing CSS, and
PHP 8.3 + MySQL on Hostinger shared hosting. Vite bundles React locally;
the server does not need Node or npm. Use Node 22.13+ (22.x) or Node 24+ locally.
The Event Setup admin remains PHP.

React renders the initial HTML during the build and hydrates it in the browser.
PHP still supplies live event content, form tokens and attendee pages. Existing
URLs and `/api/` endpoints are preserved; navigation uses normal page links.

---

## Folder map

```text
hcos-website/
├── src/
│   ├── components/
│   ├── pages/
│   ├── data/
│   ├── lib/
│   ├── App.jsx
│   └── main.jsx
├── public/assets/
├── server/
├── server-config/
├── scripts/
├── tests/
├── index.html
├── vite.config.js
├── package.json
└── package-lock.json
```

`node_modules/`, `.cache/` and `dist/` are installed/generated files, not source.
The remaining backend HTML files are active registration and upload-directory
templates; generated HTML in `dist/` is part of the deployable React build.

| Path | What it is |
|---|---|
| `src/pages/` | Public page components in `.jsx`; edit these for layout and copy |
| `src/App.jsx`, `src/main.jsx` | React application and browser entry point |
| `src/components/` | Navigation, footer, event popup and trusted HTML bridge |
| `src/lib/` | Shared interaction handlers and event date logic |
| `server/` | The PHP application (`_app/`, `api/`, `event-setup/`, entry points) |
| `src/data/` | Content as JSON — site details, events snapshot, news, services |
| `public/assets/` | Images, the company video and existing CSS; public URLs stay under `/assets/` |
| `index.html` | Standard Vite development entry loading `src/main.jsx` |
| `vite.config.js` | React plugin, Fast Refresh and PHP-compatible bundle settings |
| `server/_app/views/document.html` | Production HTML document with PHP/React boot data |
| `scripts/` | Build, server renderer, preview server and link checker |
| `tests/` | Date logic, hydration and form transport checks |
| `dist/` | Build output — generated, not in version control |
| `server-config/` | `.htaccess` (what the live server uses) and an Nginx equivalent |
| `DEVELOPER-HANDOVER.md` | Architecture, code responsibilities and maintenance rules |
| `HANDOVER-TO-SHAFAY.md` | Ownership transfer — access checklist, first day, backups, risks |
| `LOCAL-SETUP.md` | Run the whole site, database and portal on your own machine |
| `DATABASE.md` | The database — every table, access routes, migrations, backups, queries |
| `ADMIN-PORTAL.md` | Event Setup — accounts, every screen, the attendee flow, troubleshooting |
| `DEPLOYMENT.md` | Deploy and server setup, step by step |
| `PRE-LAUNCH-CHECKLIST.md` | Items still waiting on a client or client decision |

---

## Quick start

Run from the repository root; these commands also work in PowerShell:

```text
npm ci                    # install the locked dependencies
npm run dev               # Vite + React Fast Refresh at localhost:5173
```

That is the quick static preview. To run the **real thing** — PHP, the database
and the admin portal — follow [LOCAL-SETUP.md](LOCAL-SETUP.md).

To build what actually gets deployed:

Still from the repository root:

```text
npm run build             # writes dist/ including the PHP application
npm test                  # date, hydration, navigation, popup and form checks
```

Every build replaces `dist/`, including local test uploads. Edit source,
and back up any local uploads you want to keep before rebuilding.
Vite updates the development preview automatically when JSX, CSS or content
changes. It uses the event snapshot and does not execute PHP. To preview a
production static bundle, run `npm run build:static`, then `npm run preview`.
`npm run build` produces the PHP deployment; Hostinger runs it without Node.
`npm test` also rebuilds `dist/` and finishes with the PHP build.

Deploying, server commands and verification are in [DEPLOYMENT.md](DEPLOYMENT.md).

---

## Ground rules

- **No invented content.** No fabricated testimonials, statistics, client logos
  or quotes. If something is missing, it stays empty and goes on the open-items
  list.
- **Client copy is not rewritten** without being asked.
- **Secrets never enter this repository.** Database credentials and the app key
  live in `private/config.php` and `private/db.pass` on the server only.

---

Built by Alizah Khan, ITG Technologies.
