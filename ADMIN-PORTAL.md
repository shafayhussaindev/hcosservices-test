# Event Setup — the admin portal

**https://hcosservices.com/event-setup/**

Nothing on the public site links here. That is deliberate: no login button, no
footer link, no mention in the sitemap or `robots.txt`. Staff reach it by the
address, which should be bookmarked rather than circulated. Do not add a link.

This document covers what the portal does, screen by screen, and how to run it.
For local testing, follow [LOCAL-SETUP.md](LOCAL-SETUP.md). Server commands
marked `bash` require Git Bash/WSL or the Linux SSH shell. Historical test dates
below record the original handover checks, not a new verification.
For the data behind it see [DATABASE.md](DATABASE.md); for the code see
[DEVELOPER-HANDOVER.md](DEVELOPER-HANDOVER.md).

---

## 1. Accounts

Accounts are created from the server, never through a sign-up page — there is
no public registration and there is no password reset by email.

```bash
ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'cd ~/domains/hcosservices.com/public_html && /opt/alt/php82/usr/bin/php _app/cli.php admin:create'
```

It asks for a user ID, display name, email (optional) and a password — at least
12 characters with upper case, lower case and a number. Input is hidden when
the terminal supports it; on Windows the CLI warns that it is visible. Use
`admin:password` to reset one.

A user changes their own password in the portal under **My account**. To
disable someone without losing their history, set `active = 0` in
`admin_users` rather than deleting the row — the audit log references it.

**Keep at least two accounts.** There is no recovery path if the only one is
locked out, short of SSH.

### What protects it

| | |
|---|---|
| Passwords | Argon2id, re-hashed on login if the algorithm changes |
| Brute force | 5 failed attempts in 15 minutes, counted per IP **and** per username, blocks the rest of the window |
| Sessions | Own cookie name, `HttpOnly` + `Secure` + `SameSite=Strict`, stored outside the web root, bound to the browser's user agent, 60-minute idle timeout and a 12-hour hard limit |
| Forms | A CSRF token on every state-changing action; a request without it gets 419 |
| Headers | Strict CSP, `noindex`, `no-store`, `X-Frame-Options: DENY`, no referrer |
| Record | Every action written to `audit_log` with user, time and IP |

---

## 2. The screens

### Dashboard
Six tiles — published events, upcoming, registrations, uploads to review, new
messages, site visits — and the eight most recent registrations. Each tile is a
link to the list behind it.

### Events
Every event with its status, registration count and how many uploads are
waiting. **+ New event** creates a draft.

Deleting an event removes its speakers, logos, materials, gallery **and all its
registrations**, and deletes the files it owns from `uploads/`. The confirmation
says so. There is no undo.

### The event editor — seven tabs

**1 · Catalogue** — title, web address (slug), teaser, intro, banner image,
position, and three switches:

- *Upcoming event* — shows the badge and moves it to the front
- *Registration open* — enables the dedicated registration page and Register Now links (only works together with *Upcoming*)
- *Let registered attendees share photos and documents* — enables their private upload link

Also the **catalogue / brochure** file: a PDF or an image, which becomes the
card at the top of the event page. Changing the slug changes the public URL —
any link already shared will break.

**2 · Registration & details** — the *Event details* box as label/value rows
(Date, Venue, Call or Whats App, Email…). Add, reorder, remove. Empty rows are
ignored. The label wording matters: `Email` becomes a mail link, `Call` or
`Whats App` becomes a phone link.

**3 · Speakers & panel** — add a person with a group, name, designation,
organization and photo. Every existing person is an editable row: correct a
spelling, fix a designation, replace a photo, reorder, delete. **Save** is per
row. People are grouped on the public page by their group label, in this order.

**4 · Sponsors & partners** — logo, name, tier label, optional website. Grouped by
tier on the public page, sponsors and partners ordered separately.

**5 · Agenda & documents** — upload agenda pages, posters or documents, several at
once. Images and PDFs both work; a PDF appears as a document tile.

**6 · Photo gallery** — upload many photos at once, caption them, reorder, delete.
Photos approved from attendee uploads appear here automatically.

**7 · Registrations** — everyone registered for this event, with CSV export.

### Registrations
All registrations, or filtered by event. Set each one to *New*, *Confirmed*,
*Attended* or *Cancelled*. **Download CSV** gives a spreadsheet — the export
neutralises leading `=`, `+`, `-` and `@` so a crafted name cannot run as a
formula in Excel.

Deleting a registration also deletes that person's uploaded files, from both
the database and the disk.

### Homepage event popup

The homepage shows an event announcement after two seconds, with a blurred
backdrop and a close button. Escape also closes it. It appears on each homepage
visit; closing it does not hide future visits.

The popup uses the first published, upcoming event in the public catalogue
order whose end date has not passed. Untick **Upcoming event** or unpublish the
event to remove it. In **Registration & details**, set a **Date** row using
`05-December-2026`, `5 Dec 2026` or `2026-12-05`. For a multi-day event, add an
**End date** row in the same format. The popup stops at midnight after that date
in Pakistan time (Asia/Karachi); without End date it uses Date. Missing, invalid
or TBA dates are skipped. This date check controls only the popup; it does not
change registration status or the Upcoming badge.

The popup uses the event banner and links to its detail page. On the live PHP
site, portal edits are picked up on the next homepage visit. Refresh the JSON
snapshot after changes so the database fallback and static preview match.

### Uploads to review
Photos and documents shared by registered attendees. Everything arrives
**pending** and is invisible to the public until approved.

- **Approve** — a photo joins the event's gallery; a PDF joins its documents
- **Reject** — removed from the public page again if it had been approved
- **Delete** — removes the file from disk permanently

Agree a turnaround with the events team. Contributors are told their upload is
under review; if nobody looks, they are left wondering.

### Messages
The contact form inbox — `new`, `read`, `replied`. Opening a message marks it
read. Replying is a normal mail client link; the portal does not send mail.
Each message is also emailed to `info@hcosservices.com` when it arrives.

### My account
Change your own password. Nothing else — there is no user management screen by
design; accounts are a server-side operation.

---

## 3. How a visitor experiences it

1. An upcoming event with registration open shows a **Register Now** link to
   `/events/<slug>/register`. The event page contains the banner, countdown,
   event details, programme and sponsor/partner logos; the form is on its own page.
2. Submitting stores the registration, notifies HCOS, and redirects the visitor
   to a **private link** — `/events/<slug>/share/<64-character token>`. The
   attendee email includes this link when attendee uploads are enabled.
3. That page confirms the registration and, if attendee uploads are enabled,
   lets them upload photos and documents: JPG, PNG or WebP up to **3 MB** each
   (compressed automatically), PDF up to 10 MB, 20 files at a time, 100 per
   registration.
4. Everything lands in **Uploads to review**. Nothing is public until approved.

The link is the only authentication, so treat the token as a secret — don't
paste it into a shared document or a ticket.

### What uploads are checked for

Every image is **re-encoded to WebP** rather than stored as received, which
strips metadata and anything hidden in the original. Types are sniffed from
content, never from the filename — a `.jpg` that is really PHP is refused.
PDFs must start with `%PDF-` and are rejected if they contain JavaScript,
launch actions, embedded files or auto-run actions. The uploads folder forbids
script execution.

All of this was verified end to end on 21 September 2026 with deliberately
hostile files.

---

## 4. If something goes wrong

| Symptom | What to do |
|---|---|
| "Too many attempts" | Wait 15 minutes, or clear the lockout: `DELETE FROM login_attempts WHERE username = '…' AND succeeded = 0;` |
| Signed out constantly | Idle timeout is 60 minutes and the session is bound to the browser — a different browser or profile means a new login |
| "Your session expired" on save | The CSRF token is missing or no longer matches the session. Reload the page and redo the action; nothing was saved |
| "The database is not configured" | `cli.php status` — usually `private/db.pass` has wrong contents |
| An upload is refused | The message says why: too large, wrong type, or a PDF carrying scripts. These are deliberate, not bugs |
| A change doesn't show publicly | Check the event is **published**, not a draft. Then hard-refresh — HTML is not cached, but an old page may be open in the tab |

Error log: `~/domains/hcosservices.com/private/logs/app.log`.

---

## 5. For whoever maintains the code

Single entry point: `event-setup/index.php` → `_app/admin/router.php`, which
maps `?r=<route>` to a controller. Every route except `login` requires a live
session; every POST is CSRF-checked before the controller runs. Controllers are
`_app/admin/ctl_*.php`; shared UI helpers are in `_app/admin/layout.php`.

Adding a screen: write the controller, add one line to the `match` in the
router, add it to the sidebar list in `layout.php`. Do not add a route that
skips `csrf_check()`, and do not make a state-changing GET — the dispatcher's
guarantees are the security model.
