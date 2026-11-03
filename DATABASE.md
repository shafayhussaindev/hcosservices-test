# Database reference

MySQL on Hostinger. Database `u203446351_hcos`, user `u203446351_mali`,
`localhost`. Created and updated by `php _app/cli.php migrate`, which applies
`_app/schema.sql` — that file is the source of truth, this one explains it.

The original handover records live verification on 20 October 2026. The table
reference below follows the committed schema; live access and counts must be
checked separately. Start with [README.md](README.md) for the documentation map.

Command examples here use Bash (Git Bash/WSL locally, or the Linux SSH shell).

---

## 1. Getting to it

**Four access routes; the portal supports only its built-in operations:**

| Route | Use it for | How |
|---|---|---|
| **Event Setup** (`/event-setup/`) | Everything day to day. Content, registrations, moderation. | Browser. No SQL. |
| **`cli.php` over SSH** | Migrations, snapshot export, admin accounts, health check. | See below. |
| **`mysql` client over SSH** | Ad-hoc queries, dumps, restores. | See below. |
| phpMyAdmin | A GUI for the same thing. Hostinger's phpMyAdmin accepts the database username and password directly — hPanel access to the wider hosting account is not required. | hPanel → Databases, or the direct `auth-dbNNNN.hstgr.io` URL |

```bash
# CLI tools
ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240
cd ~/domains/hcosservices.com/public_html
PHP=/opt/alt/php82/usr/bin/php
$PHP _app/cli.php status

# Direct SQL — the password is read from the private file, never typed
CFG=~/domains/hcosservices.com/private
mysql -u u203446351_mali -p"$(cat $CFG/db.pass)" u203446351_hcos
```

Credentials live in `private/config.php` and `private/db.pass`, mode 600,
outside the web root. Anyone with SSH to this account can read them — SSH
access *is* database access.

---

## 2. The tables

Fourteen tables in three groups: six content, three personal-data and five
operations tables.

### Content — what the public sees

```
events ─┬─ event_details     the "Event details" box (label/value rows)
        ├─ event_people      speakers, panelists, guests
        ├─ event_orgs        sponsors and partners
        ├─ event_materials   brochure, agenda pages, posters, documents
        └─ event_gallery     approved photos
```

**`events`** — one row per event.

| Column | Notes |
|---|---|
| `slug` | unique; the URL is `/events/<slug>`. Changing it changes the public link. |
| `title`, `teaser`, `intro` | teaser is the card text, intro the opening paragraph |
| `hero_image`, `hero_alt` | banner. Path relative to the web root, no leading slash |
| `status` | `draft` · `published` · `archived`. **Only `published` is public.** |
| `is_upcoming` | shows the *Upcoming* badge |
| `registration_open` | registration form appears only when **both** this and `is_upcoming` are set |
| `attendee_uploads` | lets registrants share photos/documents through their private link |
| `sort_order` | lower first; ties broken by newest id |

**`event_details`** — free label/value rows (`Date`, `Venue`, `Call or Whats
App`, `Email`…), ordered by `sort_order`. The renderer turns an `Email` label
into a `mailto:` link and `Call`/`WhatsApp`/`Contact` into `tel:`, so the label
wording matters.

**`event_people`** — `role_group` is the heading they appear under (*Keynote
Speakers*, *Panelist*…). Every field is editable in the admin, which is the
point: a misspelt name is a ten-second fix, not a developer task.

**`event_orgs`** — `kind` is `sponsor` or `partner`; `group_label` is the tier
(*Platinum Sponsor*, *In Collaboration with*) and groups the logos on the page.

**`event_materials`** — `kind` is `brochure` (at most one per event — it becomes
the catalogue card), or `agenda` / `poster` / `document`. `mime` decides how it
renders: a PDF gets a document tile, an image is shown inline.

**`event_gallery`** — approved photos only. `registration_id` is set when the
photo came from an attendee, so credit and provenance are not lost.

### People — personal data, treat accordingly

**`registrations`**

| Column | Notes |
|---|---|
| `name`, `organization`, `designation`, `email`, `phone` | as submitted |
| `email` | may be empty for imported historical records |
| `status` | `new` · `confirmed` · `attended` · `cancelled` |
| `upload_token` | 64 hex chars, unique. **This is a secret** — it authenticates `/events/<slug>/share/<token>`. Share the link only with its intended attendee; never put it in logs, shared documents or tickets. |
| `ip`, `user_agent` | kept for abuse investigation |
| `note` | provenance for imported rows |

One registration per email per event: a repeat submission re-sends the same
link instead of creating a duplicate.

**`registration_uploads`** — what attendees share. `status` is `pending` until
an admin acts. Approving copies a row into `event_gallery` (photo) or
`event_materials` as a `document` (PDF); rejecting removes it from both again.
`file` points into `uploads/`, which is **not** in the repository and cannot be
regenerated.

**`contact_messages`** — the contact form. `status` is `new` · `read` ·
`replied`.

### Operations

| Table | Purpose |
|---|---|
| `admin_users` | `password_hash` is Argon2id, written **only** by the app. Never set it by hand. `active = 0` disables an account without deleting its history. |
| `login_attempts` | every attempt, success or not. Five failures in 15 minutes — per IP **and** per username — locks further attempts for the rest of the window. Safe to prune; clearing it clears a lockout. |
| `audit_log` | who did what, when, from where. Treat as append-only: nothing in the app updates or deletes it, and nothing should. |
| `rate_limits` | fixed-window counters for the public endpoints. Safe to truncate. |
| `visits` | one row (`id = 1`) holding the footer counter. Seeded at 21,945 — the number the old WordPress site displayed when it was retired. |

---

## 3. Changing the schema

Never with `ALTER TABLE` typed into phpMyAdmin. That leaves the live database
and the repository disagreeing, and the next person has no way to know.

1. Edit `_app/schema.sql` — the shape a fresh database should have.
2. **Also** add a line to `db_migrate()` in `_app/lib/db.php`:
   ```php
   db_ensure_column('registrations', 'note', 'VARCHAR(255) NULL AFTER user_agent');
   ```
   `CREATE TABLE IF NOT EXISTS` does nothing to a table that already exists, so
   without this the column appears on fresh installs only.
3. Back up (section 4).
4. Deploy, then run `php _app/cli.php migrate`. It is idempotent — safe to
   re-run, safe if it was already applied.
5. Commit both files together.

---

## 4. Backups

**There is no automated backup from this project.** Hostinger takes
account-level backups; do not rely on them alone.

```bash
# Database — store the dump securely; it contains personal data
ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'mysqldump -u u203446351_mali -p"$(cat ~/domains/hcosservices.com/private/db.pass)" u203446351_hcos' \
  | gzip > hcos-db-$(date +%F).sql.gz

# Uploads — not in the repository, cannot be rebuilt
ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'tar -C ~/domains/hcosservices.com/public_html -cf - uploads' | gzip > hcos-uploads-$(date +%F).tar.gz
```

Take both **before any schema change**, and on whatever schedule you set.

**Restoring:**

```bash
gunzip -c hcos-db-2026-10-20.sql.gz | ssh -i ~/.ssh/hcos_deploy -p 65002 \
  u203446351@92.112.189.240 'mysql -u u203446351_mali -p"$(cat ~/domains/hcosservices.com/private/db.pass)" u203446351_hcos'
```

A restore overwrites current data. Take a fresh dump first, even when the
current state looks broken — you may need it to recover something the backup
predates.

**Dumps contain personal data** — names, emails, phone numbers, IP addresses.
They are not build artefacts. Keep them off the server, out of the repository
and off shared drives.

---

## 5. After content changes: refresh the snapshot

The public site falls back to `_app/data/events.json` whenever MySQL is
unreachable. It does not update itself.

```bash
ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'cd ~/domains/hcosservices.com/public_html && /opt/alt/php82/usr/bin/php _app/cli.php export:json'
```

Then copy the file back into the repository (`src/data/events.json`) and
commit it. Skip this and an outage shows an out-of-date Events page rather than
an error — which is worse, because nobody notices.

---

## 6. Queries worth having

```sql
-- Registrations for an event, newest first
SELECT r.name, r.organization, r.email, r.phone, r.status, r.created_at
FROM registrations r JOIN events e ON e.id = r.event_id
WHERE e.slug = 'eco-textile-conference-3-0-karachi-edition'
ORDER BY r.created_at DESC;

-- Anything waiting for moderation
SELECT u.id, u.original_name, u.kind, r.name AS from_who, e.title
FROM registration_uploads u
JOIN registrations r ON r.id = u.registration_id
JOIN events e ON e.id = u.event_id
WHERE u.status = 'pending';

-- What changed in the admin this week, and who did it
SELECT a.created_at, au.username, a.action, a.entity, a.entity_id
FROM audit_log a LEFT JOIN admin_users au ON au.id = a.admin_id
WHERE a.created_at > NOW() - INTERVAL 7 DAY ORDER BY a.id DESC;

-- Content per event, to spot an event that was never finished
SELECT e.slug, e.status,
  (SELECT COUNT(*) FROM event_people p WHERE p.event_id = e.id)   AS people,
  (SELECT COUNT(*) FROM event_gallery g WHERE g.event_id = e.id)  AS photos,
  (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id)  AS registrations
FROM events e ORDER BY e.sort_order, e.id;

-- Clear a lockout after someone mistyped their password five times
DELETE FROM login_attempts WHERE username = 'someone' AND succeeded = 0;
```

---

## 7. Things not to do

- **Don't write `admin_users.password_hash` by hand.** Use
  `cli.php admin:password`, or *My account* in the portal.
- **Don't delete from `audit_log`.** It is the only record of who changed what.
- **Don't expose `upload_token`.** It is the only thing protecting an
  attendee's private page.
- **Don't `DELETE FROM registrations`** to tidy up — it cascades to their
  uploads and orphans the files in `uploads/`. Use the admin, which removes
  both.
- **Don't edit schema in phpMyAdmin.** Section 3.
- **Don't drop `rate_limits` or `login_attempts` tables** — truncating is fine,
  dropping breaks the throttles until the next `migrate`.
