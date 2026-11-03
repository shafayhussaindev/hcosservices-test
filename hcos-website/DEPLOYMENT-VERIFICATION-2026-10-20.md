# HCOS deployment verification — 20 October 2026

## PDF opening-view false rejection — 22 October 2026

The shared PDF validator rejected all `/OpenAction` entries, including explicit
page/zoom destination arrays. It now excludes strictly matched page-reference
destination arrays from that keyword check, preserving the original PDF bytes.
Other opening actions, JavaScript, launch actions, additional actions and
embedded files remain blocked by the existing checks. No PDF content is
rewritten and no upload limits or authentication controls changed.

`npm run test:backend` passed with multipart upload fixtures for plain PDFs,
Fit/XYZ/FitH opening destinations, byte-for-byte stored content, script and
attachment rejection, and opening actions outside the allowed format. Only
`_app/lib/uploads.php` was deployed after backup and hosting PHP lint. Installed
SHA-256 matched `5cf6bce0dbea72d6152991b06d38b02a96be2912e99db3479eea47f14829caa9`.
Live event page returned 200 and dashboard returned its expected 303 sign-in
redirect. Frontend tests/build were not repeated for this PHP-only change.
The user's actual agenda PDF was not provided, so the exact rejection trigger
and upload of that particular file remain unverified. No event content changed.

Previous validator backup:
`~/domains/hcosservices.com/private/backups/pdf-opening-view-20261022/uploads.php`.

## Attendee participant price — 22 October 2026

Added a required participant price select under Attendee details: Corporate
Sector Rs:3000 per participant and For Students Rs:1500 per Participant, with
the exact disclaimer: These options are subject to verification before payment
can be accepted. The API accepts only corporate/student, stores the selection,
preserves saved choices on public resubmissions, and includes the selection in
attendee/admin emails, the registrations dashboard and CSV export. No payment
collection was added.

Added nullable `registrations.price_option` through the existing migration
helper; existing registration count was preserved. Older records display Not
supplied. Frontend tests, backend integration fixtures and production build
passed, covering both saved choices, invalid choices, duplicate preservation,
dashboard and CSV output. The local development fixture was updated; that
separate suite was not rerun. Hosting PHP lint and all six installed file
hashes matched. Live HTTP checks confirmed field placement, exact options and
disclaimer, and rejection of missing/invalid choices. No successful live
registration or email delivery was tested.

Archive SHA-256:
`9c5c1bc8a8bfdd3ac1cf45f5551c52f0fc6b81276b5334b9fc59e47bbbab9bb8`.
Previous files and pre-migration registrations schema:
`~/domains/hcosservices.com/private/backups/price-option-20261022/`.
For rollback, restore the backed-up files and retain the nullable column.

## Professional Information heading — 22 October 2026

Changed the shared registration section heading from Professional profile to
Professional Information. Published only the fragment and confirmed the exact
heading in live HTTP output. Tests were not repeated for this text-only change.
Previous fragment backup:
`~/domains/hcosservices.com/private/backups/professional-information-20261022/registration.html`.

## Required organization and designation — 22 October 2026

Organization and Designation now have required markers and HTML required
attributes, with matching API validation rejecting empty and whitespace-only
values. Frontend tests, backend fixtures and production build passed. Updated
the local development submission fixture to supply all required contact and
professional fields; the full local development suite was not rerun.
Deployed only the registration fragment and API after backing up both files.
Hosting PHP syntax and installed hashes passed. Live HTTP checks confirmed
all three fields (Phone, Organization, Designation) are required and each
rejects missing and whitespace-only values without successful submissions.
Email delivery was not tested.

Archive SHA-256:
`d9422f0442af3d6c981464ccdeada99e25f5671263af583ec5f9a7e736d1606b`.
Previous files backup:
`~/domains/hcosservices.com/private/backups/required-profile-20261022/registration-files.tar.gz`.

## Required registration phone — 22 October 2026

Phone number now shows a required marker and has the HTML required attribute.
The registration API rejects empty, whitespace-only and malformed phone values.
Frontend tests, backend fixture tests and the production build passed. Backend
tests required loopback access outside the sandbox. Only `api/register.php` and
`_app/views/fragments/registration.html` were deployed; hosting PHP syntax and
installed SHA-256 checks passed. Live HTTP checks confirmed the required field
and rejection of missing and whitespace-only phone values after the anti-bot
delay. No successful live submission or email delivery was tested.

Archive SHA-256:
`ee297d95640fee4c4a06978533deae969f10d3e4192c66bbd51fb931eeba1414`.
Previous files backup:
`~/domains/hcosservices.com/private/backups/required-phone-20261022/registration-files.tar.gz`.

## Popup on every home-page visit — 21 October 2026

Removed the session-storage seen flag so the event popup opens after the
existing two-second delay on every home-page load. Closing dismisses the
current popup. Tests cover repeat visits with the old stored flag, alongside
existing carousel, registration link and reduced-motion behavior. `npm test`
and production build passed. Server syntax and release checks passed, and the
live browser confirmed closing the popup, visiting About, then returning Home
reopens it in the same tab. Backend tests were not repeated for this frontend
change; no database records or configuration changed.

Production entry: `assets/react/main-HuRs50x1.js`. Archive SHA-256:
`e1d6d8d86bc1b3c6613dd752e8aab37a6208c6da098fdfeacfe8b9b83afa8ae0`.
Pre-update backup:
`~/domains/hcosservices.com/private/backups/popup-every-visit-20261021-114015/frontend.tar.gz`.

## Registration purpose text — 21 October 2026

Replaced the sentence beside Submit registration with the user's exact text:
"Your details will only be used for this event only, and for registration purposes".
Only the shared registration fragment was deployed. Live HTTP output confirmed
the replacement. Tests were not repeated for this text-only change.
Previous fragment backup:
`~/domains/hcosservices.com/private/backups/registration-text-20261021-073848/registration.html`.

## Registration header — 21 October 2026

Removed the HCOS event registration badge, changed the heading to Register
yourself, and increased the event name from 15px to a responsive 20–26px.
Published the fragment, CSS and generated pages with refreshed CSS references.
`npm test`, production build, server syntax checks and release checks passed.
The live browser confirmed the heading, larger event name and removed badge;
the three gender choices were also rechecked. No live form was submitted.
Backend tests were not repeated for this text/CSS-only change.

Archive SHA-256:
`f71974cb45f2eaf28c1c9dbc2ca90275448d08545809511c19ce01cb4b0b2cd5`.
Pre-update backup:
`~/domains/hcosservices.com/private/backups/registration-heading-20261021-073555/registration-heading.tar.gz`.

## Gender options — 21 October 2026

Published exactly Male, Female and Other for registration, with matching
backend validation. Removed options are rejected for new submissions. Existing
stored values are preserved and translated for dashboard, CSV and email display.
`npm test` and `npm run test:backend` passed, including exact choices, removed
value rejection, accepted values and legacy display labels. Server PHP syntax,
live form options, dashboard rendering and access restrictions passed checks.
The live browser accessibility tree confirmed all three options. No live
registration was submitted and no database records were changed.

Archive SHA-256:
`7f5ed61df3ac24384adb5d108821b862b55cf961aaac78b2fac4461a6d0d4e7c`.
Pre-update backup:
`~/domains/hcosservices.com/private/backups/gender-options-20261021-060952/registration-files.tar.gz`.

## Popup registration button — 21 October 2026

Published a highlighted Register now button next to View event details. It
links to the selected event's `#register` section and appears only when that
event has registration open. A brief pulse respects reduced-motion settings;
buttons wrap on narrow screens. Popup data includes the registration flag in
both PHP and static builds. No event flags or database records were changed.

`npm test` and the production build passed. Production PHP syntax and release
checks passed; archive hashes were verified. Browser checks confirmed the
button, layout without horizontal scrolling, and navigation to the live form.
No live registration was submitted; mobile hardware was not tested.

Production entry: `assets/react/main-BtwVQGgD.js`. Final CSS/view archive SHA-256:
`dbbe9ed01ad50b2fb09cf4a47399aa321e2f88da05b8913a70adaef48f54cd93`.
Pre-update backup:
`~/domains/hcosservices.com/private/backups/popup-register-20261021-060015/frontend.tar.gz`.

## Registration details update — 21 October 2026

Published the shared registration table update: contact details now include
email, phone and LinkedIn together; headshots use small clickable thumbnails;
uploaded filenames and review statuses expand in a dropdown; registration date
and editable status remain visible. The admin stylesheet URL now includes its
modification time to refresh cached CSS.

Only `ctl_registrations.php`, admin `layout.php`, and `admin.css` were deployed.
The release archive SHA-256 was verified before installation, PHP 8.2 syntax
checks passed on hosting, and all three installed file hashes matched local
sources. `npm run test:backend` passed before deployment.

Read-only production checks confirmed database connectivity, table rendering
for the populated event, homepage and sign-in availability, the refreshed CSS
reference, and unauthenticated restrictions on registrations and headshots.
The existing live records did not exercise thumbnails or uploaded-file lists;
these are covered by local integration fixtures. No browser visual check or
live form submission was performed. No database or attendee records changed.

Backup of the three previous files:
`~/domains/hcosservices.com/private/backups/registration-view-20261021-054706/admin-files.tar.gz`.

## Latest popup presentation update

Both popup slides now share a responsive fixed frame, with banners fitted
without cropping. Play/pause controls were removed and autoplay is four seconds.
Arrow/dot navigation keeps autoplay enabled. Hover and event-link keyboard
focus pause temporarily; reduced-motion preferences disable automatic rotation.
Long content scrolls within the details area while the frame stays unchanged.

`npm test` passed, including timing, navigation, hover/focus, reduced-motion and
timer cleanup checks. The live browser showed both events and no playback
button; each slide measured 608 × 672 pixels at the tested desktop viewport.
Mobile hardware was not separately tested. No backend code or data changed,
so backend tests were not repeated.

Production entry: `assets/react/main-DFrfiyZw.js`. Uploaded archive SHA-256:
`a85f4c11091840d15b02179a4d8e9c7bdf8853bf2aaf7ef8e92f4e15ab1bd004`.
Server release checks passed after publication. Pre-update backup:
`~/domains/hcosservices.com/private/backups/hcos-20261020-224212/`.

## Latest popup update — dashboard control

At the user's request, published events marked Upcoming now appear in the
popup regardless of their dates. Dates are display-only; removing an event
from the popup requires clearing its Upcoming checkbox in the dashboard.
Both Karachi and Lahore were verified in the live two-slide carousel.
`npm test` passed, including past-date inclusion and carousel interaction tests.

Production entry: `assets/react/main--yG6L6C4.js`.
The frontend archive passed SHA-256 verification before publication; live
release checks confirmed the bundle and two published Upcoming events.
No event records, dates, flags, backend code or database schema were changed.
Backend tests were not repeated for this frontend-only update.

Pre-update backup:
`~/domains/hcosservices.com/private/backups/hcos-20261020-222515/`.

## Earlier production release

The latest production build was deployed again at the user's request, including
the multi-event React popup carousel. Its production entry is
`assets/react/main-DU1g9jFo.js`. Frontend and backend fixture tests passed;
the uploaded archive passed SHA-256 verification and server-side PHP syntax
checks before publication. All 363 live page, asset, dashboard, authentication
and export checks passed afterward. The temporary verification account was
removed, and the live popup was also checked in a browser.

Fresh pre-release backup:
`~/domains/hcosservices.com/private/backups/hcos-20261020-214503/`.
Website files, the database and record counts were verified. Live uploads,
private configuration and the refreshed live event snapshot were preserved.
Native development runtimes, local database files, test accounts and cached
test content were excluded. The live database and real mail configuration
remain in use.

At that deployment, the client date filter admitted only one event. Carousel
controls required two events passing that filter, which the latest update removes.
No production event dates or flags were changed to create a carousel demo.
The user will test successful live submissions and inbox delivery themselves;
no registration/contact test emails were sent during this release.

## Published changes

The current React/Vite frontend and PHP backend were published to
https://hcosservices.com with the user's explicit approval. The Employees
Portal link now opens `/event-setup/` in desktop and mobile navigation.

Live verification found a disconnected homepage enquiry form. It now uses
the existing PHP contact endpoint and signed anti-bot fields, requires an
email and message, and displays submission feedback. The shared form handler
combines first and last names for the backend's `name` field.

## Database and backup

The migration added nullable `linkedin_url`, `gender`, and `headshot` columns
to `registrations`. New submissions require all three through application
validation. Existing records remain valid. Migration was rerun successfully.

Private backup on the hosting account:

`~/domains/hcosservices.com/private/backups/hcos-20261020-203947/`

It contains `website.tar.gz` and `database.sql`, both checked as nonempty and
restricted to the hosting account. Existing event, registration, attendee
upload, message and staff row counts were preserved. Existing public uploads
and private configuration were retained. The live event snapshot was
refreshed before publication and preserved during both release uploads.

## Verification completed

- `npm test`: passed event-date, React hydration, popup, navigation, forms,
  Vite and interaction-cleanup checks, including the homepage-form fix.
- `npm run test:backend`: passed PHP integration tests using SQLite fixtures.
  The earlier failure was caused by restricted loopback connectivity; the
  same tests passed with local-server access enabled.
- Production build: passed. PHP source passed syntax checks on the server's
  PHP 8.2 runtime before publication.
- 363 live HTTP checks passed: public pages and referenced assets, connected
  contact forms, registration fields, database-backed visitor counter,
  authenticated staff login/logout and all six dashboard screens,
  event-specific lists and CSV exports, CSRF enforcement, and restrictions
  on application internals and unauthenticated headshot access.
- Six further live checks passed: invalid timestamps, deceptive LinkedIn
  domains, missing gender, missing headshots and invalid contact emails were
  rejected, with no registrations or messages created by these requests.
- A temporary verification administrator was created for real HTTP login
  tests, then removed. Existing staff credentials were unchanged. Login and
  export audit records were retained.
- Browser checks confirmed the mobile Employees Portal link opens admin
  sign-in, the new event form blocks empty submissions, and the homepage
  popup displays the complete live banner with the blurred backdrop and
  closes using its cross button.

## Still pending

Successful live registration/contact submissions and end-to-end email
delivery need a user-approved recipient and permission for the clearly
labelled test notification to the configured HCOS inbox. Neither was sent
as part of the checks above. Local fixture tests cover successful registration,
private image processing/access, event isolation and cleanup, but do not
prove delivery to a real inbox.

## Rollback

Restore website files from the private website archive if application rollback
is required, while preserving any new uploads received since the backup.
The added nullable database columns are compatible with the previous code;
leave them in place during an application rollback. Do not restore the whole
database over newer attendee records without reviewing the changes first.

## Registration success popup — 28 October 2026

Published the registration confirmation dialog with the message: Thank you for
registering. Our Delegate Acquisition Team will reach out to you shortly.
The popup opens after successful registration and the existing redirect waits
until dismissal. npm test and the production build passed. Deployed the new
React bundle, shared registration fragment, popup-only CSS additions, manifest
and refreshed bundle/CSS references in 14 live frontend pages. Existing live
page content was retained. Installed file hashes were checked by the installer.
Live HTTP checks confirmed the message, bundle handler and versioned styles.
No live registration was submitted and email delivery was not tested.

Production entry: assets/react/main-p7Jgo3Mu.js.
Private pre-update backup:
~/domains/hcosservices.com/private/backups/registration-popup-20261028-105517/
