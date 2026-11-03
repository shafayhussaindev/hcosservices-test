import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { JSDOM, VirtualConsole } from 'jsdom';

const root = fileURLToPath(new URL('..', import.meta.url));
const build = flag => execFileSync(process.execPath, ['scripts/build.cjs', ...flag], { cwd: root, stdio: 'pipe' });
const read = file => fs.readFileSync(path.join(root, 'dist', file), 'utf8');
const wait = () => new Promise(resolve => setTimeout(resolve, 80));
const escaped = value => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

async function browser(html, route, check, storage = {}, reducedMotion = true) {
  const errors = [];
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', error => errors.push(error.message));
  virtualConsole.on('error', (...args) => errors.push(args.join(' ')));
  const dom = new JSDOM(html, { url: 'https://hcosservices.com' + route, runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole });
  const { window } = dom;
  for (const [key, value] of Object.entries(storage)) window.sessionStorage.setItem(key, value);
  const posts = [], timers = [];
  const intervals = new Map();
  const intervalDelays = new Map();
  const resizeObservers = new Set();
  window.ResizeObserver = class {
    constructor(callback) { this.callback = callback; }
    observe() { resizeObservers.add(this.callback); }
    disconnect() { resizeObservers.delete(this.callback); }
  };
  let intervalId = 0;
  window.setInterval = (fn, ms) => { intervals.set(++intervalId, fn); intervalDelays.set(intervalId, ms); return intervalId; };
  window.clearInterval = id => intervals.delete(id);
  window.addEventListener('error', event => errors.push(event.message));
  window.matchMedia = () => ({ matches: reducedMotion });
  window.Date.now = () => Date.parse('2026-10-20T12:00:00+05:00');
  window.HTMLElement.prototype.scrollIntoView = () => {};
  window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); this.querySelector('button').focus(); };
  window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new window.Event('close')); };
  const nativeTimeout = window.setTimeout.bind(window);
  window.setTimeout = (fn, ms, ...args) => ms === 2000 ? (timers.push(fn), timers.length) : nativeTimeout(fn, ms, ...args);
  window.fetch = async (url, options = {}) => {
    if (options.body) posts.push({ url, data: options.body });
    return { ok: true, json: async () => url === '/api/visits' ? { count: 21945, live: false } : { ok: true } };
  };
  try {
    const script = window.document.querySelector('script[type="module"]').getAttribute('src');
    assert.match(script, /^\/assets\/react\/main-[\w-]+\.js$/);
    window.eval('"use strict";\n' + read(script.slice(1)));
    await wait();
    assert.deepEqual(errors, [], route + ': initial browser or hydration errors');
    await check({ window, document: window.document, posts, timers, intervals, intervalDelays, resizeObservers });
    await wait();
    assert.deepEqual(errors, [], route + ': browser or hydration errors');
    for (let i = 0; i < window.sessionStorage.length; i++) {
      const key = window.sessionStorage.key(i);
      storage[key] = window.sessionStorage.getItem(key);
    }
  } finally { window.close(); }
}

// Reproduce only the two PHP expressions emitted by the build, without pretending
// to run PHP or exercise its database/authentication code.
function phpView(name, values) {
  return read('_app/views/pages/' + name + '.php')
    .replace(/<\?= \$v\['([A-Z_]+)'\] \?\? '' \?>\r?\n/g, (_, key) => values[key] || '')
    .replace(/<\?= json_encode\(array_replace_recursive\(json_decode\(base64_decode\('([^']+)'\)[\s\S]*?\?>/g, (_, base) => {
      const data = JSON.parse(Buffer.from(base, 'base64').toString());
      data.values = { ...data.values, ...values };
      return JSON.stringify(data).replace(/</g, '\\u003c');
    });
}

(async () => {
  build(['--static']);
  const popupSession = { 'hcos-event-popup-seen': '1' };
  const routes = ['index.html', 'about.html', 'services.html', 'events.html', 'careers.html', 'newsroom.html', 'contact.html', '404.html',
    'events/hr-seasons.html', 'newsroom/the-rules-have-changed.html'];
  for (const file of routes) {
    const html = read(file);
    assert(!/\{\{[A-Z_]+\}\}/.test(html), file + ': unresolved slot');
    await browser(html, '/' + file, async ({ document, timers, window }) => {
      assert(document.querySelector('main'));
      assert(document.querySelector('footer'));
      document.getElementById('navToggle').click();
      assert.equal(document.getElementById('navToggle').getAttribute('aria-expanded'), 'true');
      document.getElementById('navToggle').click();
      if (file === 'index.html') {
        assert.equal(timers.length, 1);
        assert(!document.querySelector('dialog[open]'));
        timers[0](); await wait();
        assert(document.querySelector('dialog[open]'));
        const banner = document.querySelector('.event-popup__image');
        assert(banner, 'The popup should include the event banner');
        const events = JSON.parse(fs.readFileSync(path.join(root, 'src/data/events.json'), 'utf8'));
        assert.equal(banner.getAttribute('src'), '/' + events.find(event => event.upcoming).image);
        document.querySelector('.event-popup__close').click(); await wait();
        assert(!document.querySelector('dialog[open]'));
        assert(!document.body.classList.contains('event-popup-open'));
      }
    }, file === 'index.html' ? popupSession : {});
  }
  assert.equal(popupSession['hcos-event-popup-seen'], '1');
  for (let visit = 0; visit < 2; visit++) await browser(read('index.html'), '/', async ({ document, timers }) => {
    timers[0](); await wait();
    assert(document.querySelector('dialog[open]'), 'Every home page visit must reopen the popup, including sessions with the old seen flag');
    document.querySelector('.event-popup__close').click(); await wait();
    assert(!document.querySelector('dialog[open]'), 'Closing the popup dismisses it for the current page visit');
  }, popupSession);
  build([]);
  const contact = phpView('contact', { FORM_ENDPOINT: '/api/contact', FORM_FALLBACK_EMAIL: 'info@hcosservices.com', FORM_HIDDEN: '<input type="hidden" name="stamp" value="test-stamp">' });
  await browser(contact, '/contact', async ({ document, window, posts }) => {
    document.getElementById('firstName').value = 'Test';
    document.getElementById('lastName').value = 'Visitor';
    for (const input of document.querySelectorAll('[required]')) input.value = input.type === 'email' ? 'test@example.com' : 'Test visitor';
    document.querySelector('form[data-endpoint]').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await wait();
    assert.equal(posts[0].url, '/api/contact');
    assert.equal(posts[0].data.get('stamp'), 'test-stamp');
    assert.equal(posts[0].data.get('name'), 'Test Visitor');
  });
  const registration = fs.readFileSync(path.join(root, 'server/_app/views/fragments/registration.html'), 'utf8')
    .replace(/\{\{([A-Z_]+)\}\}/g, (_, key) => ({ EVENT_TITLE: 'Test event', FORM_ENDPOINT: '/api/register',
      FORM_FALLBACK_EMAIL: 'info@hcosservices.com', FORM_HIDDEN: '<input type="hidden" name="event_slug" value="test"><input type="hidden" name="stamp" value="test-stamp">' })[key] || '');
  const promotion = phpView('event-detail', { PAGE_TITLE: 'Event — HCOS', PAGE_DESC: 'Description', CANONICAL: 'https://hcosservices.com/events/test',
    TITLE: escaped('Test <event> & details'), EYEBROW: 'Event', IMAGE: '/assets/img/hero.webp', ALT: 'Event image', INTRO: 'Introduction',
    COUNTDOWN_DATE: '05-December-2026', EVENT_ACTION: '<h2>Join us</h2><a href="/events/test/register">Register Now</a>',
    BROCHURE: '', DETAILS: '<p>Details</p>', SPONSORS: '', PARTNERS: '', PEOPLE: '', MATERIALS: '', GALLERY: '', MORE_CARDS: '' });
  await browser(promotion, '/events/test', async ({ document, intervals, window }) => {
    assert.equal(document.querySelector('h1').textContent, 'Test <event> & details');
    assert.equal(document.querySelector('#register a').getAttribute('href'), '/events/test/register');
    assert(!document.getElementById('registerForm'), 'Promotion page has no embedded registration form');
    assert(!document.querySelector('.event-countdown').hidden);
    assert(intervals.size > 0, 'Countdown updates after hydration');
    window.Date.now = () => Date.parse('2026-12-05T00:00:01+05:00');
    for (const tick of intervals.values()) tick();
    await wait();
    assert.equal(document.querySelector('.event-countdown__caption').textContent, 'The event date has arrived.');
  });
  const event = phpView('event-registration', { PAGE_TITLE: 'Event — HCOS', PAGE_DESC: 'Description', CANONICAL: 'https://hcosservices.com/events/test/register',
    TITLE: escaped('Test <event> & details'), EYEBROW: 'Event', IMAGE: '/assets/img/hero.webp', ALT: 'Event image', INTRO: 'Introduction',
    EVENT_URL: '/events/test', BROCHURE: '', REGISTRATION: registration, DETAILS: '<p>Details</p>', SPONSORS: '', PARTNERS: '', PEOPLE: '', MATERIALS: '', GALLERY: '', MORE_CARDS: '' });
  assert(!event.includes('<?'));
  await browser(event, '/events/test/register', async ({ document, window, posts }) => {
    assert.equal(document.querySelector('h1').textContent, 'REGISTRATION FORM');
    assert.equal(document.querySelector('.page-hero__deck').textContent, 'Test <event> & details');
    const form = document.getElementById('registerForm');
    const confirmation = document.querySelector('.registration-success');
    assert(!confirmation.open, 'Confirmation stays hidden before submission');
    const fetch = window.fetch;
    window.fetch = async (url, options) => {
      const response = await fetch(url, options);
      return url === '/api/register' ? { ok: true, json: async () => ({ ok: true, redirect: '/events/test/register#confirmed' }) } : response;
    };
    const submit = () => form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    for (const name of ['phone', 'organization', 'designation', 'linkedin_url', 'gender', 'price_option', 'headshot']) assert(form.elements.namedItem(name).required);
    assert.deepEqual([...form.elements.namedItem('price_option').options].filter(option => option.value).map(option => [option.value, option.text]), [['corporate', 'Corporate Sector Rs:3000 per participant'], ['student', 'For Students Rs:1500 per Participant']]);
    assert.equal(document.getElementById('regPriceHelp').textContent, 'These options are subject to verification before payment can be accepted');
    assert.deepEqual([...form.elements.namedItem('gender').options].filter(option => option.value).map(option => [option.value, option.text]), [['male', 'Male'], ['female', 'Female'], ['other', 'Other']]);
    submit(); await wait();
    assert.equal(posts.length, 0, 'Empty required profile fields must block submission');
    assert(!confirmation.open, 'Invalid registration must not show confirmation');
    for (const input of document.querySelectorAll('[required]')) {
      if (input.type === 'file') continue;
      input.value = input.type === 'email' ? 'test@example.com' : input.type === 'url' ? 'https://www.linkedin.com/in/test-visitor' : input.name === 'price_option' ? 'corporate' : input.tagName === 'SELECT' ? 'other' : 'Test visitor';
    }
    submit(); await wait();
    assert.equal(posts.length, 0, 'A headshot is required');
    const photo = form.elements.namedItem('headshot');
    Object.defineProperty(photo, 'files', { configurable: true, value: [new window.File(['photo'], 'headshot.jpg', { type: 'image/jpeg' })] });
    const linkedin = form.elements.namedItem('linkedin_url');
    linkedin.value = 'https://linkedin.com.attacker.test/in/test';
    submit(); await wait();
    assert.equal(posts.length, 0, 'An unrelated domain must not be accepted as LinkedIn');
    linkedin.value = 'https://www.linkedin.com/in/test-visitor';
    Object.defineProperty(photo, 'files', { configurable: true, value: [new window.File(['bad'], 'script.svg', { type: 'image/svg+xml' })] });
    submit(); await wait();
    assert.equal(posts.length, 0, 'Non-photo uploads must be rejected');
    Object.defineProperty(photo, 'files', { configurable: true, value: [new window.File(['photo'], 'headshot.jpg', { type: 'image/jpeg' })] });
    submit();
    await wait();
    assert.equal(posts[0].url, '/api/register');
    assert.equal(posts[0].data.get('event_slug'), 'test');
    assert.equal(posts[0].data.get('linkedin_url'), 'https://www.linkedin.com/in/test-visitor');
    assert.equal(posts[0].data.get('gender'), 'other');
    assert(confirmation.open, 'Successful registration opens the confirmation popup');
    assert.equal(confirmation.querySelector('h2').textContent, 'Thank you for registering.');
    assert.equal(confirmation.querySelector('p').textContent, 'Our Delegate Acquisition Team will reach out to you shortly.');
    assert.equal(document.activeElement, confirmation.querySelector('button'));
    assert.equal(window.location.hash, '', 'Redirect waits for the popup to close');
    assert.equal(confirmation.querySelector('form').method, 'dialog', 'Close button uses native dialog dismissal');
    confirmation.close(); await wait();
    assert(!confirmation.open);
    assert.equal(window.location.hash, '#confirmed', 'Closing continues to the existing confirmation page');
  });
  const home = phpView('home', { FORM_ENDPOINT: '/api/contact', FORM_FALLBACK_EMAIL: 'info@hcosservices.com',
    FORM_HIDDEN: '<input type="hidden" name="stamp" value="home-stamp">', EVENT_CARDS: '<p>Live events</p>', POPUP_EVENTS: escaped(JSON.stringify([
    { slug: 'live-event', title: 'Live event', date: '2026-12-05', image: 'assets/img/hero.webp' }
  ])) });
  await browser(home, '/', async ({ document, timers, window, posts }) => {
    timers[0](); await wait();
    assert.equal(document.querySelector('#eventPopupTitle').textContent, 'Live event');
    assert(!document.querySelector('.event-popup__controls'), 'One event needs no carousel controls');
    assert(!document.querySelector('.event-popup__register'), 'Do not advertise registration when it is not open');
    const form = document.querySelector('form[data-endpoint]');
    document.getElementById('email').value = 'test@example.com';
    document.getElementById('message').value = 'Homepage enquiry';
    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await wait();
    assert.equal(posts[0].url, '/api/contact');
    assert.equal(posts[0].data.get('stamp'), 'home-stamp');
  });
  const carouselHome = phpView('home', { EVENT_CARDS: '', FORM_ENDPOINT: '', FORM_FALLBACK_EMAIL: '', FORM_HIDDEN: '',
    POPUP_EVENTS: escaped(JSON.stringify([
      { slug: 'first-event', title: 'First event', date: '2026-12-05', image: 'assets/img/hero.webp', registrationOpen: true },
      { slug: 'second-event', title: 'Second event', date: '2025-01-01', image: 'assets/img/event-eco3-lahore.jpg', registrationOpen: true }
    ])) });
  await browser(carouselHome, '/', async ({ document, timers, window, intervals, intervalDelays }) => {
    const title = () => document.getElementById('eventPopupTitle').textContent;
    const button = label => document.querySelector(`button[aria-label="${label}"]`);
    const tick = async () => { for (const callback of [...intervals.values()]) callback(); await wait(); };
    timers[0](); await wait();
    assert.equal(title(), 'First event');
    assert.equal(document.querySelector('.event-popup__register').getAttribute('href'), '/events/first-event/register');
    assert.equal(document.querySelectorAll('.event-popup__dot').length, 2, 'Both Upcoming events must become slides, including the past-dated event');
    await tick();
    assert.equal(title(), 'Second event', 'Autoplay advances the event');
    assert.equal(document.querySelector('.event-popup__register').getAttribute('href'), '/events/second-event/register');
    assert.equal(document.querySelector('.event-popup__image').getAttribute('src'), '/assets/img/event-eco3-lahore.jpg');
    assert.equal(document.querySelector('.event-popup__link').getAttribute('href'), '/events/second-event');
    assert(document.body.classList.contains('event-popup-open'), 'Slide changes must not unlock background scrolling');
    await tick();
    assert.equal(title(), 'First event', 'Autoplay loops back to the first event');
    const popup = document.getElementById('eventPopup');
    popup.dispatchEvent(new window.MouseEvent('mouseover', { bubbles: true })); await wait();
    await tick();
    assert.equal(title(), 'First event', 'Hover pauses rotation');
    popup.dispatchEvent(new window.MouseEvent('mouseout', { bubbles: true })); await wait();
    await tick();
    assert.equal(title(), 'Second event', 'Rotation resumes after hover');
    assert(!button('Pause event carousel') && !button('Play event carousel'), 'Playback buttons must be absent');
    assert([...intervalDelays.values()].includes(4000), 'Autoplay must use four seconds');
    button('Next event').click(); await wait();
    assert.equal(title(), 'First event', 'Next wraps to the first event');
    document.querySelector('.event-popup__image').dispatchEvent(new window.Event('error')); await wait();
    assert(!document.querySelector('.event-popup__image'));
    button('Previous event').click(); await wait();
    assert.equal(title(), 'Second event', 'Previous wraps to the last event');
    assert(document.querySelector('.event-popup__image'), 'One failed banner must not hide another event banner');
    document.querySelector('.event-popup__dot').click(); await wait();
    assert.equal(title(), 'First event', 'Dots select an event');
    Object.defineProperty(document, 'hidden', { configurable: true, value: true });
    await tick();
    assert.equal(title(), 'First event', 'Hidden tabs must not advance');
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    await tick();
    assert.equal(title(), 'Second event');
    document.querySelector('.event-popup__register').focus(); await wait();
    await tick();
    assert.equal(title(), 'Second event', 'Keyboard focus pauses rotation');
    document.querySelector('.event-popup__register').blur(); await wait();
    await tick();
    assert.equal(title(), 'First event', 'Rotation resumes when the link loses focus');
    const running = intervals.size;
    document.querySelector('.event-popup__close').click(); await wait();
    assert.equal(intervals.size, running - 1, 'Closing must clear the carousel timer');
    assert(!document.querySelector('dialog[open]'));
    assert(!document.body.classList.contains('event-popup-open'));
  }, {}, false);
  await browser(carouselHome, '/', async ({ document, timers, intervals }) => {
    timers[0](); await wait();
    assert(!document.querySelector('.event-popup__play'), 'Reduced motion also has no playback button');
    assert.equal(intervals.size, 0);
    document.querySelector('button[aria-label="Next event"]').click(); await wait();
    assert.equal(document.getElementById('eventPopupTitle').textContent, 'Second event');
  });
  const share = phpView('share', { SHARE_TITLE: 'You are registered', SHARE_EVENT: 'test',
    SHARE_BODY: '<form data-endpoint="/api/upload"><input type="hidden" name="token" value="test-token"><button type="submit">Upload</button><p class="form__notice" hidden></p></form>' });
  await browser(share, '/events/test/share/test-token', async ({ document, window, posts }) => {
    document.querySelector('form[data-endpoint]').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await wait();
    assert.equal(posts[0].url, '/api/upload');
    assert.equal(posts[0].data.get('token'), 'test-token');
  });
  console.log('React hydration, navigation, popup, PHP-slot escaping and form transport checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
