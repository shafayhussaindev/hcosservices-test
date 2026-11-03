# Ownership handover — HCOS website

Alizah Khan → Shafay (Engineering Intern, ITG Technologies)
Prepared 20 October 2026.

This is the original ownership record. Counts, access status, incidents and
decisions below were reported at handover and have not been independently
rechecked during the documentation review. Leave sign-off and access boxes
unchanged until the people responsible verify them.

For the documentation map, see [README.md](README.md). Commands marked `bash`
require Git Bash/WSL locally or the Linux SSH shell, not PowerShell.

**Frontend migration:** the public pages now use React/JavaScript. Install Node
22.13+ (22.x) or Node 24+ and run `npm ci` in the repository root before the build commands
in this historical checklist. Current source locations and preview commands
are in [README.md](README.md); the PHP admin and server access are unchanged.

From the date both parties sign off below, **Shafay owns this project**: the
code, the server, the database, deployments and day-to-day operation. This
document is the transfer itself — the inventory, what has to change hands, what
to do first, and what stays with Alizah.

For *how the system works*, read **[DEVELOPER-HANDOVER.md](DEVELOPER-HANDOVER.md)**
first. This document assumes you have. Two companions go with it:
**[DATABASE.md](DATABASE.md)** (tables, access, migrations, backups) and
**[ADMIN-PORTAL.md](ADMIN-PORTAL.md)** (Event Setup, screen by screen).

---

## 1. Scope — what you are taking on

| You own | You do not own |
|---|---|
| The codebase and this repository | Client copy and content decisions |
| Deployments to `hcosservices.com` | Brand/design direction |
| The MySQL database and its backups | Whether an event is published (staff decide; you make it possible) |
| Server configuration, `.htaccess`, PHP | The Hostinger billing account |
| The Event Setup admin and its user accounts | DNS ownership for the domain |
| Security: patches, access, uploads, incidents | Client relationship |

If a request is about *what the site says*, it goes to Alizah. If it is about
*what the site does*, it is yours.

---

## 2. Access inventory

Tick each line as it is actually in your hands and tested.

| # | Access | Where it comes from | How you verify it | Done |
|---|---|---|---|---|
| 1 | **GitHub repository** (private) | Alizah adds you as collaborator | `git clone` succeeds, `git push` to a test branch works | ☐ |
| 2 | **SSH to the server** | You generate a key, Alizah/host installs the public half | `ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 'whoami'` returns the user | ☐ |
| 3 | **Event Setup admin account** | You create your own with `cli.php admin:create` | You can sign in at `/event-setup/` and see the dashboard | ☐ |
| 4 | **hPanel (Hostinger)** | Account holder invites you or shares access | You can reach Databases, Emails, File Manager | ☐ |
| 5 | **Database password** | Rotate it in hPanel yourself at handover; write the new one to `private/db.pass` | `cli.php status` says *connected* | ☐ |
| 6 | **DNS for hcosservices.com** | Account holder — read access is enough day to day | You can see the A record pointing at the host | ☐ |
| 7 | **Mailboxes** `info@` and `noreply@` | hPanel → Emails | A test registration arrives in `info@` | ☐ |
| 8 | **Client asset archive** (Drive: event photos, speaker headshots, agendas) | Alizah shares the folder | You can open it | ☐ |
| 9 | **Old WordPress backup** (`.rar`, ~980 MB, on Alizah's machine) | Copy it, or agree it is archived and you don't need it | You know where it is | ☐ |
| 10 | ~~Vercel project `hcos-homepage`~~ | — | **Deleted 20 Oct 2026.** Nothing to hand over. | ☑ |

**Credentials rule.** Nothing above is handed over by message or chat. SSH uses
your own keypair. The database password is rotated at handover and typed
straight into the server. Admin passwords are set by you and known only to you.

---

## 3. Your first day

In this order:

```bash
git clone <repo-url> hcos-website && cd hcos-website
npm run dev    # http://localhost:5173
```

That is the static preview, which needs nothing but Node. To run the full
stack locally — PHP, MySQL and the portal — follow [LOCAL-SETUP.md](LOCAL-SETUP.md).

Click through every page locally. Then:

1. **Read** `DEVELOPER-HANDOVER.md` end to end — especially §6 (rules that must
   not be broken) and §7 (gotchas already hit) — then `DATABASE.md` and
   `ADMIN-PORTAL.md`.
2. **Get SSH working** (inventory item 2) and run:
   ```bash
   ssh -t -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 'cd ~/domains/hcosservices.com/public_html && /opt/alt/php82/usr/bin/php _app/cli.php status'
   ```
3. **Create your admin account** and sign in to `/event-setup/`. Look at an
   event's seven tabs so you know what staff see.
4. **Take a database backup before you change anything** — see §5. Do this on
   day one, not day three.
5. **Make one trivial, reversible change** end to end (a word in a static page),
   deploy it, verify it live, and confirm you can roll it back. Do this while
   someone is still available to ask. Do not let your first deploy be an urgent
   one.

---

## 4. State as of handover (20 Oct 2026)

The original handover recorded these server counts; they are a dated snapshot,
not current monitoring:

| | |
|---|---|
| Events | **7** published (one, *Eco-Textile 3.0 Karachi Edition*, is marked upcoming with registration open) |
| Speakers / panelists | 102 |
| Sponsor & partner logos | 35 |
| Agenda pages, posters, documents | 17 |
| Gallery photos | 65 |
| Registrations | 1 (the record recovered from the old site) |
| Contact messages | 0 |
| Attendee uploads | 0 pending |
| Admin accounts | 1 — `mali` (Muhammad Ali) |
| Visitor counter | 22,307 and counting (seeded from the old site's 21,945) |
| Audit log | 41 entries |
| Web root | 36 MB · `uploads/` 1.3 MB, 9 files |

Staff have been creating events through the admin since September, so **the
database is the source of truth, not the repo**. See the warning in §6.

---

## 5. Recurring responsibilities

**Back up the database.** There is no automated backup from this project today.
Hostinger takes its own account-level backups; do not rely on them alone. At
minimum, before any schema change, and on a schedule you set:

```bash
ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'mysqldump -u u203446351_mali -p"$(cat ~/domains/hcosservices.com/private/db.pass)" u203446351_hcos' \
  | gzip > hcos-db-$(date +%F).sql.gz
```

Keep the dumps off the server. They contain registrant names and email
addresses — treat them as personal data, not as build artefacts.

**Also back up `uploads/`** — it is not in the repo and cannot be rebuilt:

```bash
ssh -i ~/.ssh/hcos_deploy -p 65002 u203446351@92.112.189.240 \
  'tar -C ~/domains/hcosservices.com/public_html -cf - uploads' | gzip > hcos-uploads-$(date +%F).tar.gz
```

**Watch the moderation queue.** Attendee photos sit as *pending* and are
invisible until approved. If nobody looks, contributors are left wondering why
nothing appeared. Agree a turnaround with the events team.

**Check the error log** after anything unusual:
`~/domains/hcosservices.com/private/logs/app.log`.

**Keep the docs true.** If you change how something works, change
`DEVELOPER-HANDOVER.md` in the same commit. That file is the reason this
handover is short.

---

## 6. Risks and watch items

**Keep the offline fallback fresh.** If MySQL is unreachable, every public page
renders from `_app/data/events.json`. That snapshot is no longer written by
hand — refresh it from the database with:

```bash
cd ~/domains/hcosservices.com/public_html && /opt/alt/php82/usr/bin/php _app/cli.php export:json
```

Then copy the file back into the repository (`src/data/events.json`) and
commit it, so the two do not drift. **Run this after any significant content
change**, or the site would show out-of-date events during an outage rather
than an error. It was last exported on 20 Oct 2026: 7 events, 102 people, 65
photos, verified to render identically to the live pages with the database
switched off.

**Only one admin account was recorded at handover.** Verify the current
accounts and create yours on day one so there are at least two.

**A transient database outage was logged on 17 Oct** (`No such file or
directory` — the MySQL socket was briefly unavailable, a host-side blip). The
site stayed up on the fallback. Worth knowing the pattern if it recurs.

**The database password has been through a chat message.** Rotate it at
handover (§2 item 5) so the credential you operate with has never left hPanel
and the server.

**The old WordPress install was compromised** — ~35 spam articles published
25 June – 2 July 2026. DNS now points at the new site. If that install still
exists anywhere, it should be decommissioned, not just unlinked.

**Trailing-slash URLs must keep serving, not redirecting.** A redirect pair
once left visitors in a cached loop that no server-side fix could clear. See
`DEVELOPER-HANDOVER.md` §7.

---

## 7. Things to decide at handover

Settled on 20 October 2026:

| Decision | Agreed |
|---|---|
| The legacy **Vercel project** `hcos-homepage` — a public static copy, already out of date (6 events against 7 live) | **Deleted, 20 Oct 2026.** The URL no longer resolves; the repo references were removed in the same commit. |
| **Alizah's SSH key** on the server | **Remove after Shafay's first successful deploy.** Delete that line from `~/.ssh/authorized_keys`; it is labelled `hcos-deploy@itg-alizah`. |
| **Alizah's admin access** | Same milestone as the SSH key. |
| **Out-of-hours failures** | **Shafay, best effort. There is no formal on-call.** The events team should know this: an overnight outage may not be fixed until the morning. |
| **`htaccess.backup-2026-09-21`** in the domain folder | Delete once you are confident in the current config. Still outstanding. |

---

## 8. Open items inherited

Content and client decisions carry over unchanged — they are listed in
[PRE-LAUNCH-CHECKLIST.md](PRE-LAUNCH-CHECKLIST.md). The short version: the
LinkedIn URL is wrong, the Employees Portal button goes nowhere, testimonials
are empty (**nothing is to be invented**), two About-page values duplicate each
other, and the bulk of the old registrations are still in a Google Form the
client has not exported.

None of these are yours to decide. They are yours to implement once the client
answers.

---

## 9. Sign-off

| | Name | Date |
|---|---|---|
| Handed over by | Alizah Khan | |
| Received by | Shafay | |
| Access items 1–10 verified | | |
| First deploy completed by Shafay | | |
