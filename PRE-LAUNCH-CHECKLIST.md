# Open items

This file keeps its original pre-launch name so existing links still work.
Use it as the shared list of pending content and infrastructure decisions.
The entries below come from the handover; confirm their status before acting,
and move an item to Done only after it is resolved and verified.

The site is live and the admin is in use. Nothing here blocks anything that is
already running — these are decisions and content still owed, plus a few things
worth keeping an eye on.

**Who** — *Client* = HCOS · *Alizah* = ITG · *Dev* = whoever maintains the code.
Ownership and access tasks are in [HANDOVER-TO-SHAFAY.md](HANDOVER-TO-SHAFAY.md).

---

## Waiting on the client

### 1. LinkedIn URL is wrong
`site.json` carries `linkedin.com/company/morgan-stanleyl/` — applied exactly as
the client supplied it, but it is **Morgan Stanley's page**. Appears in the
footer of every page and on Contact. Fix: one value in
`src/data/site.json` (`social` and `contactSocial`), rebuild, deploy.

### 2. "Employees Portal" button goes nowhere
Links to `#`. Set the real URL in `site.json` → `employeesPortal`.

### 3. Testimonials
The section is built and sits between Our Clients and Latest News, **hidden
until it has content**. Real quotes go in `src/data/testimonials.json`
(format in [testimonials.README.md](src/data/testimonials.README.md)).
**Nothing is to be invented here.**

### 4. Is Eco-Textile 3.0 still "upcoming"?
It carries the *Upcoming* badge and the registration form, but its banner,
agenda and article all say **4 July 2026**, which has passed. If it has
happened, untick *Upcoming event* in Event Setup → the badge and the
registration form disappear.

### 5. Two Company Values are identical
On `/about`, **Ethical** and **Effectiveness** carry the same sentence, copied
from the old site. Needs the intended Effectiveness wording.

### 6. Three unused event folders
The client's Drive has **Fire Safety, HR Breakfast and Sessi Event** photos.
These are not events on the site. Say if they should be added — they can now be
created directly in Event Setup.

### 7. Old registrations
Registrations on the old site went to a **Google Form**
(`forms.gle/XfheJtBhAjAVGpYb7`). Only one record survived in the WordPress
backup; it is in the database, marked in its `note` field as imported with no
email. If the client exports the form's response sheet as CSV, the rest can be
imported.

---

## Waiting on Alizah / infra

### 8. Mailboxes
Registrations and contact messages are sent **to** `info@hcosservices.com`
**from** `noreply@hcosservices.com`. Confirm both exist in hPanel → Emails —
if the sender does not exist on the domain, mail fails SPF and may be dropped.

### 9. The old WordPress install
The backup shows it was **hacked**: ~35 spam articles (casino/forex, several
languages) published 25 June – 2 July 2026 alongside the four real ones. DNS
now points at the new site. If that install still exists anywhere, decommission
it.

### 10. Analytics
None installed. A production tag would go in `server/_app/views/document.html`.

---

## Done — recorded at handover

These are historical completion notes, not a fresh test of the live site.

- Site rebuilt as a multi-page site and deployed to `hcosservices.com`
- All six client corrections from *Website Correction.pdf* applied
- Partner logos, all four newsroom articles, event recaps, speakers, sponsor
  logos, posters and agendas recovered from the WordPress backup
- 65 event photos and the Eco-Textile 3.0 material (28 speakers, 8 logos,
  5-page agenda) from the client's Drive
- Company introduction video self-hosted, transcoded to H.264
- Contact page rebuilt to the old layout, with opening hours and WhatsApp
- **Visitor counter is live**, continuing from the old site's 21,945
- **Both forms deliver** — contact and event registration — into the database
  and by email, with no third-party form service
- **Event Setup admin** live: events (catalogue, details, speakers, sponsors,
  agenda, gallery), registrations with CSV export, attendee upload moderation,
  message inbox, audit log
- **Attendee flow** live: register → private link by email → upload photos and
  documents → admin approves before anything is public
- Uploads capped at 3 MB per image and compressed automatically; PDFs carrying
  scripts are refused
