/* ==========================================================================
   HCOS React site builder
   Renders src/*.jsx and page components with the data in
   src/data/*.json into flat route files under dist/. Run: `npm run build`.
   ========================================================================== */

const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('node:url');
const metadata = require('../src/data/page-metadata.json');

const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'src');
const OUT = path.join(ROOT, 'dist');
// Public origin used for <link rel="canonical">, sitemap.xml and robots.txt.
// Override per environment: `SITE_URL=https://staging.example.com npm run build`
const SITE_URL = (process.env.SITE_URL || 'https://hcosservices.com').replace(/\/$/, '');
// Default output is the PHP site (Hostinger): home, events, event pages, contact
// and the attendee share page render from MySQL at request time through the
// code in server/. `node scripts/build.js --static` instead writes a
// static site (no admin, no database) for a plain host or local preview.
const DATA_ONLY = process.argv.includes('--data-only');
const PHP_MODE = !DATA_ONLY && !process.argv.includes('--static');
const previewPages = {};

const read = (...p) => fs.readFileSync(path.join(...p), 'utf8');
const json = (name) => JSON.parse(read(SRC, 'data', name));
const exists = (...p) => fs.existsSync(path.join(...p));

const site = json('site.json');
const services = json('services.json');
const events = json('events.json');
const news = json('news.json');
const testimonials = json('testimonials.json');
const partners = json('partners.json');
const jobs = json('jobs.json');

const shell = read(ROOT, 'server', '_app', 'views', 'document.html');
const registrationTpl = read(ROOT, 'server', '_app', 'views', 'fragments', 'registration.html');
let renderReact, reactScript;

/* ---------- helpers ------------------------------------------------------- */

// Replace every {{KEY}} occurrence. Values are inserted literally, so `$&`
// and friends in the data can never be read as replacement patterns.
function fill(template, vars) {
  return Object.entries(vars).reduce(
    (out, [key, value]) => out.split(`{{${key}}}`).join(value ?? ''),
    template
  );
}

const esc = (s) =>
  String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const slugify = (s) => String(s).toLowerCase().replace(/&amp;|&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const ARROW =
  '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">' +
  '<path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" stroke-width="1.6" ' +
  'stroke-linecap="round" stroke-linejoin="round"/></svg>';
const ARROW_SM = ARROW.replace('width="15" height="15"', 'width="14" height="14"');

// Real photographs for an event live in assets/events/<slug>/photos/. Anything
// dropped in there is picked up on the next build — no JSON edit needed.
function eventPhotos(slug) {
  const dir = path.join(ROOT, 'public', 'assets', 'events', slug, 'photos');
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir)
    .filter((f) => /\.(webp|jpe?g|png)$/i.test(f))
    .sort()
    .map((f) => `assets/events/${slug}/photos/${f}`);
}

/* ---------- shared chrome ------------------------------------------------- */

const SOCIAL_ICONS = {
  Facebook:
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.12-2.41-.12-2.39 0-4.02 1.46-4.02 4.13V9.9H7.5V13h2.77v8h3.23Z"/></svg>',
  Instagram:
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3.9" stroke="currentColor" stroke-width="1.7"/><circle cx="16.9" cy="7.1" r="1.1" fill="currentColor"/></svg>',
  Linkedin:
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.94 8.4H3.9V21h3.04V8.4ZM5.42 3a1.77 1.77 0 1 0 0 3.54 1.77 1.77 0 0 0 0-3.54ZM20.1 13.6c0-3.2-1.71-4.7-4-4.7-1.84 0-2.66 1.02-3.12 1.73V8.4H9.94c.04.86 0 12.6 0 12.6h3.04v-7.04c0-.27.02-.55.1-.74.22-.55.72-1.11 1.56-1.11 1.1 0 1.54.84 1.54 2.07V21h3.04l-.12-7.4Z"/></svg>',
  WhatsApp:
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2a9.8 9.8 0 0 0-8.4 14.8L2.3 21.8l4.9-1.3A9.8 9.8 0 1 0 12 2.2Zm0 17.9a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8.1 8.1 0 1 1 12 20.1Zm4.4-6c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5l.4-.4.2-.4c.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1.1 2.7c.1.2 1.8 2.8 4.5 3.9 1.6.7 2.2.7 2.9.6.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1l-.6-.2Z"/></svg>'
};

const socialLinks = (list) => list
  .map((s) => {
    // Unresolved links must not look clickable or mislead assistive tech.
    const rel = s.url === '#' ? ' aria-disabled="true" title="Link not set yet"' : ' target="_blank" rel="noopener"';
    return `<a href="${s.url}"${rel}>\n            ${SOCIAL_ICONS[s.name] || ''}\n            ${s.name}\n          </a>`;
  })
  .join('\n          ');

/* ---------- form delivery (contact + event registration) ------------------
   One provider config in site.json feeds every form. Empty endpoint = forms
   show a not-connected notice with a mailto fallback instead of failing. */

const formVars = () => ({
  FORM_ENDPOINT: site.form?.endpoint || '',
  FORM_FALLBACK_EMAIL: site.form?.notifyEmail || site.email,
  FORM_HIDDEN: Object.entries(site.form?.hidden || {})
    .map(([k, v]) => `<input type="hidden" name="${esc(k)}" value="${esc(v)}">`)
    .join('\n    ')
});

/* ---------- generated fragments ------------------------------------------- */

function eventCards(list, delays = [0, 90, 180]) {
  return list
    .map((e, i) => `
      <a class="event-card reveal" style="--d:${delays[i % delays.length]}ms" href="/events/${e.slug}">
        <div class="event-card__media">
          <img src="/${e.image}" alt="${esc(e.alt)}" loading="lazy" decoding="async">
        </div>
        <div class="event-card__body">
          ${e.upcoming ? '<span class="event-card__flag">Upcoming</span>' : ''}
          <h3 class="event-card__title">${e.title}</h3>
          <p class="event-card__desc">${e.teaser}</p>
          <span class="event-card__link">View More
            ${ARROW_SM}
          </span>
        </div>
      </a>`)
    .join('\n');
}

function serviceCards(list, delays = [0, 90, 180]) {
  return list
    .map((s, i) => `
      <a class="service reveal" style="--d:${delays[i % delays.length]}ms" href="/services#${s.slug}">
        <div class="service__media"><img src="/${s.image}" alt="${esc(s.alt)}" loading="lazy" decoding="async" width="900" height="619"></div>
        <div class="service__scrim"></div>
        <div class="service__body">
          <span class="service__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">${s.icon}</svg>
          </span>
          <h3 class="service__title">${s.title}</h3>
          <p class="service__desc">${s.short}</p>
          <span class="service__cta">Read More
            ${ARROW_SM}
          </span>
        </div>
      </a>`)
    .join('\n');
}

function serviceRows(list) {
  return list
    .map((s, i) => `
      <article class="service-row reveal" id="${s.slug}">
        <div class="service-row__media reveal-clip">
          <img src="/${s.image}" alt="${esc(s.alt)}" loading="lazy" decoding="async" width="900" height="619">
        </div>
        <div class="service-row__body">
          <span class="service-row__index">${String(i + 1).padStart(2, '0')}</span>
          <span class="service-row__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">${s.icon}</svg>
          </span>
          <h2 class="service-row__title">${s.title}</h2>
          <p class="service-row__text">${s.full}</p>
        </div>
      </article>`)
    .join('\n');
}

// Whole section, or nothing. An empty list must never leave an orphan heading.
function testimonialsSection(list) {
  if (!Array.isArray(list) || list.length === 0) return '';
  const cards = list
    .map((t, i) => {
      const meta = [t.role, t.company].filter(Boolean).map(esc).join(', ');
      const logo = t.logo
        ? `<img class="testimonial__logo" src="/${esc(t.logo)}" alt="${esc(t.company || '')}" loading="lazy" decoding="async">`
        : '';
      return `
      <figure class="testimonial reveal" style="--d:${(i % 3) * 90}ms">
        <span class="testimonial__mark" aria-hidden="true">&ldquo;</span>
        <blockquote class="testimonial__quote">${esc(t.quote)}</blockquote>
        <figcaption class="testimonial__by">
          ${logo}
          <span>
            <strong class="testimonial__name">${esc(t.name)}</strong>
            ${meta ? `<span class="testimonial__meta">${meta}</span>` : ''}
          </span>
        </figcaption>
      </figure>`;
    })
    .join('\n');
  return `
<section class="section" id="testimonials">
  <div class="container">
    <div class="section-head section-head--center">
      <h2 class="section-title reveal">WHAT OUR CLIENTS SAY</h2>
      <div class="section-rule reveal" style="--d:80ms"></div>
    </div>
    <div class="testimonials__grid">
      ${cards}
    </div>
  </div>
</section>`;
}

function newsCards(list) {
  return list
    .map((n, i) => `
      <article class="news-card reveal" style="--d:${(i % 3) * 90}ms">
        <a class="news-card__media reveal-clip" href="/newsroom/${n.slug}">
          <img src="/${n.image}" alt="${esc(n.alt)}" loading="lazy" decoding="async">
        </a>
        <p class="article__meta"><time datetime="${n.dateTime}">${n.date}</time> · ${n.meta}</p>
        <h2 class="news-card__title"><a href="/newsroom/${n.slug}">${esc(n.title)}</a></h2>
        <p class="article__excerpt">${esc(n.teaser)}</p>
        <a class="link-arrow" href="/newsroom/${n.slug}">Read More
          ${ARROW}
        </a>
      </article>`)
    .join('\n');
}

const partnerLogos = (list) => `
      <div class="partners__row">
        ${list.map((p, i) => `<img class="partners__logo reveal" style="--d:${i * 80}ms" src="/${p.logo}" alt="${esc(p.name)}" loading="lazy" decoding="async">`).join('\n        ')}
      </div>`;

/* ---------- event page pieces ---------------------------------------------- */

const DETAIL_LABELS = { Theme: 'Theme', Date: 'Date', Time: 'Time', Venue: 'Venue', 'Call or Whats App': 'Call / WhatsApp', Email: 'Email', Contact: 'Contact' };

function eventDetails(e) {
  const rows = Object.entries(e.details || {}).filter(([, v]) => v);
  if (!rows.length) return '';
  const cell = (k, v) => {
    if (/email/i.test(k)) return `<a href="mailto:${esc(v)}">${esc(v)}</a>`;
    if (/call|whats|contact/i.test(k)) return `<a href="tel:${esc(v.replace(/[^\d+]/g, ''))}">${esc(v)}</a>`;
    return esc(v);
  };
  return `
        <div class="event-side reveal">
          <h2 class="event-side__title">Event details</h2>
          <dl class="event-dl">
            ${rows.map(([k, v]) => `<div><dt>${esc(DETAIL_LABELS[k] || k)}</dt><dd>${cell(k, v)}</dd></div>`).join('\n            ')}
          </dl>
        </div>`;
}

function logoGroups(title, list) {
  if (!list?.length) return '';
  const groups = new Map();
  for (const l of list) { const g = l.group || ''; if (!groups.has(g)) groups.set(g, []); groups.get(g).push(l); }
  return `
        <div class="event-side reveal">
          <h2 class="event-side__title">${title}</h2>
          ${[...groups].map(([g, items]) => `
          ${g ? `<h3 class="event-side__sub">${esc(g)}</h3>` : ''}
          <div class="logo-row">
            ${items.map((l) => `<img src="/${l.image}" alt="${esc(l.name || g || title)}" loading="lazy" decoding="async">`).join('\n            ')}
          </div>`).join('')}
        </div>`;
}

function eventPeople(e) {
  if (!e.people?.length) return '';
  const groups = new Map();
  for (const p of e.people) { const g = p.role || 'Speakers'; if (!groups.has(g)) groups.set(g, []); groups.get(g).push(p); }
  const initials = (n) => n.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  return `
        <section class="event-block reveal">
          <h2 class="event-block__title">Speakers &amp; panel</h2>
          ${[...groups].map(([g, items]) => `
          <h3 class="event-block__sub">${esc(g)}</h3>
          <div class="people-grid">
            ${items.map((p) => `
            <figure class="person">
              ${p.photo ? `<img class="person__photo" src="/${p.photo}" alt="${esc(p.name)}" loading="lazy" decoding="async">` : `<span class="person__photo person__photo--initials" aria-hidden="true">${esc(initials(p.name))}</span>`}
              <figcaption>
                <strong class="person__name">${esc(p.name)}</strong>
                ${p.designation ? `<span class="person__role">${esc(p.designation)}</span>` : ''}
                ${p.organization ? `<span class="person__org">${esc(p.organization)}</span>` : ''}
              </figcaption>
            </figure>`).join('')}
          </div>`).join('')}
        </section>`;
}

function eventMaterials(e) {
  if (!e.materials?.length) return '';
  return `
        <section class="event-block reveal">
          <h2 class="event-block__title">Poster &amp; agenda</h2>
          <div class="materials">
            ${e.materials.map((m) => `<a class="materials__item" href="/${m}" target="_blank" rel="noopener"><img src="/${m}" alt="${esc(e.title)} — poster or agenda" loading="lazy" decoding="async"></a>`).join('\n            ')}
          </div>
        </section>`;
}

function eventGallery(e) {
  const photos = eventPhotos(e.slug);
  if (!photos.length) {
    return `
        <aside class="placeholder reveal">
          <span class="placeholder__badge">Coming soon</span>
          <h2 class="placeholder__title">Event photo gallery coming soon</h2>
          <p class="placeholder__text">Photographs from this event are being added. To publish them, drop the image files into <code>assets/events/${e.slug}/photos/</code> and rebuild.</p>
        </aside>`;
  }
  return `
        <section class="event-block reveal">
          <h2 class="event-block__title">Photo gallery</h2>
          <div class="gallery">
            ${photos.map((p, i) => `<a class="gallery__item" href="/${p}" target="_blank" rel="noopener"><img src="/${p}" alt="${esc(e.title)} — photo ${i + 1}" loading="lazy" decoding="async"></a>`).join('\n            ')}
          </div>
        </section>`;
}

const registrationBlock = (e) =>
  e.upcoming && (e.registration_open ?? true) ? fill(registrationTpl, { ...formVars(), EVENT_TITLE: esc(e.title) }) : '';

/* ---------- careers ---------------------------------------------------------- */

function jobsSection(list) {
  if (!list.length) {
    return `<div class="empty-state reveal" style="--d:120ms">
      <span class="empty-state__icon" aria-hidden="true">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><rect x="3" y="7.2" width="18" height="12.6" rx="2.4" stroke="currentColor" stroke-width="1.7"/><path d="M8.6 7.2V5.6a2 2 0 0 1 2-2h2.8a2 2 0 0 1 2 2v1.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M3 12.4h18" stroke="currentColor" stroke-width="1.7"/></svg>
      </span>
      <h2 class="empty-state__title">No current openings</h2>
      <p class="empty-state__text">We do not have any roles advertised right now. Please check back — this page is updated whenever a position opens.</p>
      <p class="empty-state__text">If you would like to be considered for future roles, send us your CV and a short note about the kind of work you are looking for.</p>
      <a class="btn btn--primary" href="mailto:${site.email}?subject=Speculative%20application">Send a speculative application
        ${ARROW}
      </a>
      <p class="empty-state__note">Applications go to <a href="mailto:${site.email}">${site.email}</a></p>
    </div>`;
  }
  return `<div class="jobs reveal" style="--d:120ms">
      <h2 class="jobs__title">Open positions <span class="jobs__count">${list.length}</span></h2>
      ${list.map((j) => `
      <a class="job-card" href="/careers/${j.slug}">
        <div>
          <h3 class="job-card__title">${esc(j.title)}</h3>
          <p class="job-card__summary">${esc(j.summary)}</p>
          <ul class="job-meta">${[j.location, j.type, j.salary].filter(Boolean).map((m) => `<li>${esc(m)}</li>`).join('')}</ul>
        </div>
        <span class="service__arrow">${ARROW_SM}</span>
      </a>`).join('')}
    </div>`;
}

function jobBody(j) {
  return (j.sections || []).map((s) => {
    const body = s.items
      ? `<ul>${s.items.map((i) => `<li>${esc(i)}</li>`).join('')}</ul>`
      : (s.paragraphs || []).map((p) => `<p>${esc(p)}</p>`).join('');
    return `<h2>${esc(s.heading)}</h2>${body}`;
  }).join('\n');
}

/* ---------- page assembly -------------------------------------------------- */


// Assets are served with a one-year immutable cache, so any change to CSS/JS
// must change the URL too. A content hash in the query string does exactly that.
// esbuild validates the React frontend; also check the standalone admin script.
for (const js of ['server/event-setup/admin.js']) {
  try { new Function(fs.readFileSync(path.join(ROOT, js), 'utf8')); }
  catch (e) { throw new Error(`${js} has a syntax error: ${e.message}`); }
}

const crypto = require('crypto');
const assetVersion = (rel) =>
  crypto.createHash('md5').update(fs.readFileSync(path.join(ROOT, 'public', rel))).digest('hex').slice(0, 10);
const VERSIONED_SHELL = ['assets/css/styles.css', 'assets/css/pages.css', 'assets/css/corrections.css']
  .reduce((s, rel) => s.split(`"/${rel}"`).join(`"/${rel}?v=${assetVersion(rel)}"`), shell);

function composePage(meta, data, boot) {
  return fill(VERSIONED_SHELL, {
    TITLE: meta.title, DESC: meta.desc, CANONICAL: meta.canonical || SITE_URL + meta.route,
    PAGE: meta.nav, PRELOAD: meta.preload || '',
    CONTENT: renderReact(data), REACT_BOOT: boot, REACT_SCRIPT: reactScript
  });
}

const serialize = data => JSON.stringify(data).replace(/</g, '\\u003c');

function renderPage(meta) {
  const data = { page: meta.component, nav: meta.nav, values: meta.values || {} };
  const route = meta.route;
  if (DATA_ONLY) { previewPages[route] = { meta, data }; return route === '/' ? 'index.html' : route.slice(1) + '.html'; }
  const outFile = route === '/' ? 'index.html' : `${route.replace(/^\//, '')}.html`;
  const dest = path.join(OUT, outFile);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, composePage(meta, data, serialize(data)));
  return outFile;
}

// PHP view: everything static is resolved here at build time; the listed
// dynamic keys become `<?= $v['KEY'] ?>` and are filled by _app/lib/pages.php
// on every request. Any other leftover {{PLACEHOLDER}} is a build error.
function renderPhpView(name, meta, dynamicKeys) {
  const values = { ...meta.values, ...Object.fromEntries(dynamicKeys.map(key => [key, '{{' + key + '}}'])) };
  const data = { page: meta.component, nav: meta.nav, values };
  let html = composePage(meta, data, 'REACT_BOOT_DATA');
  // PHP drops the one newline that directly follows `?>`; add one back so the
  // rendered HTML is byte-identical to the static build.
  for (const key of dynamicKeys) html = html.split(`{{${key}}}`).join(`<?= $v['${key}'] ?? '' ?>
`);
  const base = Buffer.from(JSON.stringify({ ...data, values: meta.values || {} })).toString('base64');
  const boot = "<?= json_encode(array_replace_recursive(json_decode(base64_decode('" + base + "'), true), ['values' => $v]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?>";
  html = html.replace('REACT_BOOT_DATA', boot);
  const leftover = html.match(/{{[A-Z_]+}}/g);
  if (leftover) throw new Error(`Unresolved placeholders in PHP view ${name}: ${[...new Set(leftover)].join(', ')}`);
  if (html.includes('<?php')) throw new Error(`Unexpected PHP block in ${name}`);
  const dest = path.join(OUT, '_app', 'views', 'pages', `${name}.php`);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, html);
  return `_app/views/pages/${name}.php`;
}

function loadPage(relPath) {
  const component = relPath.replace(/\.html$/, '');
  if (!metadata[component]) throw new Error('Missing React page: ' + component);
  return { meta: { ...metadata[component], component } };
}

/* ---------- run ------------------------------------------------------------ */

async function buildSite() {
if (!DATA_ONLY) {
  const { build } = await import('vite');
  if (!process.argv.includes('--skip-client')) await build();
  await build({ configFile: false, root: ROOT, publicDir: false, logLevel: 'silent',
    build: { ssr: 'scripts/render.jsx', outDir: '.cache/ssr', emptyOutDir: true,
      rolldownOptions: { output: { entryFileNames: 'render.mjs' } } } });
  process.env.NODE_ENV = 'production';
  renderReact = (await import(pathToFileURL(path.join(ROOT, '.cache/ssr/render.mjs')).href)).render;
  const manifest = JSON.parse(read(OUT, '.vite', 'manifest.json'));
  reactScript = '/' + manifest['src/main.jsx'].file;
}

const written = [];   // static route files
const phpViews = [];  // PHP-rendered routes (listed in the sitemap, not written as .html)

// Pages that are fully static in both modes.
const staticPages = [
  ['about.html', { PARTNER_LOGOS: partnerLogos(partners) }],
  ['services.html', { SERVICE_ROWS: serviceRows(services) }],
  ['careers.html', { JOBS: jobsSection(jobs) }],
  ['newsroom.html', { NEWS_CARDS: newsCards(news) }],
  ['404.html', {}]
];
// Pages whose event data (or form fields) is filled at request time in PHP mode.
const dynamicPages = [
  ['index.html', 'home', { SERVICE_CARDS: serviceCards(services), NEWS_CARDS: newsCards(news.slice(0, 2)), TESTIMONIALS: testimonialsSection(testimonials) }, { ...formVars(), EVENT_CARDS: eventCards(events), POPUP_EVENTS: esc(JSON.stringify(events.filter(e => e.upcoming).map(e => ({ slug: e.slug, title: e.title, image: e.image, alt: e.alt, date: e.details?.Date || '', endDate: e.details?.['End date'] || '', registrationOpen: e.registration_open ?? !!e.upcoming })))) }],
  ['events.html', 'events', {}, { EVENT_CARDS: eventCards(events) }],
  ['contact.html', 'contact', { CONTACT_SOCIAL: socialLinks(site.contactSocial || site.social) }, formVars()]
];

for (const [file, vars] of staticPages) {
  const { meta } = loadPage(file);
  written.push(renderPage({ ...meta, values: vars }));
}

for (const [file, view, staticVars, dynamicVars] of dynamicPages) {
  const { meta } = loadPage(file);
  if (PHP_MODE) {
    renderPhpView(view, { ...meta, values: staticVars }, Object.keys(dynamicVars));
    phpViews.push(meta.route);
  } else {
    written.push(renderPage({ ...meta, values: { ...staticVars, ...dynamicVars } }));
  }
}

// Event detail pages.
const EVENT_DYNAMIC = ['PAGE_TITLE', 'PAGE_DESC', 'CANONICAL', 'TITLE', 'EYEBROW', 'IMAGE', 'ALT', 'INTRO', 'BROCHURE', 'REGISTRATION', 'DETAILS', 'SPONSORS', 'PARTNERS', 'PEOPLE', 'MATERIALS', 'GALLERY', 'MORE_CARDS', 'EVENT_URL', 'REGISTRATION_URL', 'COUNTDOWN_DATE', 'EVENT_ACTION'];
if (PHP_MODE) {
  renderPhpView('event-detail', { route: '/events/x', title: '{{PAGE_TITLE}}', desc: '{{PAGE_DESC}}', canonical: '{{CANONICAL}}', nav: 'events', component: 'event-detail' }, EVENT_DYNAMIC);
  renderPhpView('event-registration', { route: '/events/x/register', title: '{{PAGE_TITLE}}', desc: '{{PAGE_DESC}}', canonical: '{{CANONICAL}}', nav: 'events', component: 'event-registration' }, EVENT_DYNAMIC);
  events.forEach((e) => phpViews.push(`/events/${e.slug}`));

  // Attendee share page (only exists with a database).
  const share = loadPage('share.html');
  renderPhpView('share', share.meta, share.meta.dynamic || []);
} else {
  events.forEach((e, i) => {
    const others = events.filter((_, j) => j !== i).slice(0, 3);
    const values = {
      TITLE: e.title,
      EVENT_URL: `/events/${e.slug}`,
      REGISTRATION_URL: e.upcoming && (e.registration_open ?? true) ? `/events/${e.slug}/register` : '',
      EVENT_ACTION: e.upcoming && (e.registration_open ?? true)
        ? `<h2>Join us at the event</h2><p>Complete your registration on the next page. The HCOS events team will contact you with confirmation and joining instructions.</p><a class="btn btn--primary" href="/events/${e.slug}/register">Register Now ${ARROW}</a>`
        : '<p>Registration is closed for this event.</p>',
      COUNTDOWN_DATE: e.upcoming ? esc(e.details?.Date || '') : '',
      EYEBROW: e.upcoming ? 'Upcoming event' : 'Event',
      IMAGE: '/' + e.image,
      ALT: esc(e.alt),
      INTRO: esc(e.intro || e.teaser),
      BROCHURE: '',
      REGISTRATION: '',
      DETAILS: eventDetails(e),
      SPONSORS: logoGroups('Sponsors', e.sponsors),
      PARTNERS: logoGroups('Partners', e.partners),
      PEOPLE: eventPeople(e),
      MATERIALS: eventMaterials(e),
      GALLERY: eventGallery(e),
      MORE_CARDS: eventCards(others)
    };
    written.push(renderPage({ route: `/events/${e.slug}`, title: `${e.title} — HCOS`, desc: (e.intro || e.teaser).replace(/"/g, "'").slice(0, 180), nav: 'events', component: 'event-detail', values }));
    if (values.REGISTRATION_URL) written.push(renderPage({ route: values.REGISTRATION_URL, title: `Registration: ${e.title} — HCOS`, desc: `Register for ${e.title}.`, nav: 'events', component: 'event-registration', values: { ...values, REGISTRATION: registrationBlock(e) } }));
  });
}

// Server-side code + the data it needs, PHP mode only.
if (PHP_MODE) {
  fs.cpSync(path.join(ROOT, 'server'), OUT, { recursive: true });
  fs.mkdirSync(path.join(OUT, '_app', 'data'), { recursive: true });
  fs.copyFileSync(path.join(SRC, 'data', 'events.json'), path.join(OUT, '_app', 'data', 'events.json'));
}

// Newsroom detail pages.
news.forEach((n, i) => {
  const others = news.filter((_, j) => j !== i).slice(0, 3);
  const values = {
    TITLE: esc(n.title),
    IMAGE: '/' + n.image,
    ALT: esc(n.alt),
    DATE: n.date,
    DATETIME: n.dateTime,
    META: n.meta,
    BODY: n.body || `<p class="lead">${esc(n.teaser)}</p>`,
    MORE_CARDS: others.length ? newsCards(others) : '<p class="lead">No other articles yet.</p>'
  };
  written.push(renderPage({ route: `/newsroom/${n.slug}`, title: `${n.title} — HCOS`, desc: n.teaser.replace(/"/g, "'").slice(0, 180), nav: 'newsroom', component: 'news-detail', values }));
});

// Job detail pages.
jobs.forEach((j) => {
  const email = j.applyEmail || site.email;
  const values = {
    TITLE: esc(j.title),
    META_ITEMS: [j.location, j.type, j.salary].filter(Boolean).map((m) => `<li>${esc(m)}</li>`).join(''),
    BODY: jobBody(j),
    APPLY_EMAIL: email,
    APPLY_SUBJECT: encodeURIComponent('Application: ' + j.title)
  };
  written.push(renderPage({ route: `/careers/${j.slug}`, title: `${j.title} — Careers — HCOS`, desc: j.summary, nav: 'careers', component: 'job-detail', values }));
});

if (DATA_ONLY) {
  fs.mkdirSync(path.join(ROOT, '.cache'), { recursive: true });
  fs.writeFileSync(path.join(ROOT, '.cache/pages.json'), JSON.stringify(previewPages));
  return;
}

/* ---------- sitemap + robots --------------------------------------------- */

const routes = [...phpViews, ...written.map((f) => (f === 'index.html' ? '/' : '/' + f.replace(/\.html$/, '')))]
  .filter((r) => r !== '/404' && r !== '/events/share')
  .sort((a, b) => (a === '/' ? -1 : b === '/' ? 1 : a.localeCompare(b)));

const today = new Date().toISOString().slice(0, 10);
const sitemap =
  '<?xml version="1.0" encoding="UTF-8"?>\n' +
  '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
  routes.map((r) => {
    const depth = r === '/' ? 0 : r.split('/').length - 1;
    const priority = r === '/' ? '1.0' : depth === 1 ? '0.8' : '0.6';
    return `  <url>\n    <loc>${SITE_URL}${r}</loc>\n    <lastmod>${today}</lastmod>\n    <priority>${priority}</priority>\n  </url>`;
  }).join('\n') +
  '\n</urlset>\n';

fs.writeFileSync(path.join(OUT, 'sitemap.xml'), sitemap);
fs.writeFileSync(path.join(OUT, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${SITE_URL}/sitemap.xml\n`);

console.log(`Built ${written.length} static pages${PHP_MODE ? ` + ${phpViews.length} PHP-rendered routes` : ''} for ${SITE_URL}:`);
written.forEach((f) => console.log('  ' + f));
if (PHP_MODE) console.log('  PHP: ' + phpViews.join(', '));
console.log('  sitemap.xml (' + routes.length + ' URLs)');
console.log('  robots.txt');

}

buildSite().catch(error => { console.error(error); process.exitCode = 1; });
