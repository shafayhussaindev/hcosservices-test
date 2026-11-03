import assert from 'node:assert/strict';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import { JSDOM } from 'jsdom';
import { decodeHTML } from 'entities';

const origin = process.env.HCOS_DEV_ORIGIN || 'http://127.0.0.1:5174';
assert(['127.0.0.1', 'localhost'].includes(new URL(origin).hostname), 'These tests may only use the local development server');
const state = JSON.parse(fs.readFileSync('.cache/local/private/state.json', 'utf8'));
const cookies = new Map();
async function request(route, body, authenticated = true) {
  const headers = { 'User-Agent': 'HCOS-local-integration-test' };
  if (authenticated) headers.Cookie = [...cookies].map(([key, value]) => key + '=' + value).join('; ');
  const response = await fetch(origin + route, { method: body ? 'POST' : 'GET', headers, body, redirect: 'manual' });
  if (authenticated) for (const cookie of response.headers.getSetCookie()) {
    const pair = cookie.split(';')[0]; const separator = pair.indexOf('=');
    cookies.set(pair.slice(0, separator), pair.slice(separator + 1));
  }
  return response;
}
const token = html => new JSDOM(html).window.document.querySelector('input[name="_token"]').value;
const pageData = html => JSON.parse(new JSDOM(html).window.document.getElementById('react-page-data').textContent);
const admin = '/event-setup/?r=';
let eventId;
let csrf;
try {
  const entry = await request('/event-setup/');
  assert.equal(entry.status, 303);
  assert(entry.headers.get('location').includes('r=login'));
  const login = await request(admin + 'login');
  const loginHtml = await login.text();
  assert(loginHtml.includes('Sign in to manage events'));
  const stylesheet = new JSDOM(loginHtml).window.document.querySelector('link[href^="/event-setup/admin.css"]');
  for (const [url, type, source] of [
    [stylesheet.getAttribute('href'), 'text/css', 'server/event-setup/admin.css'],
    ['/event-setup/admin.js?v=test', '(?:text|application)/javascript', 'server/event-setup/admin.js']
  ]) {
    const asset = await request(url);
    assert.equal(asset.status, 200, url);
    assert.match(asset.headers.get('content-type') || '', new RegExp('^' + type + '(?:;|$)'), url);
    assert.equal(await asset.text(), fs.readFileSync(source, 'utf8'), url);
  }
  csrf = token(loginHtml);
  assert.equal((await request(admin + 'login', new URLSearchParams({ _token: csrf, username: 'local.admin', password: state.adminPassword }))).status, 303);
  for (const screen of ['dashboard', 'events', 'registrations', 'moderation', 'messages', 'account']) {
    const response = await request(admin + screen);
    assert.equal(response.status, 200, screen);
    const html = await response.text();
    assert(!html.includes('Something went wrong'), screen);
    csrf = token(html);
  }
  console.log('Local dashboard routing, real login and all six admin screens passed.');
  const created = await request(admin + 'event-new', new URLSearchParams({ _token: csrf }));
  assert.equal(created.status, 303);
  eventId = new URL(created.headers.get('location'), origin).searchParams.get('id');
  assert(eventId);
  const slug = 'local-verification-' + Date.now();
  const post = async (route, values) => request(admin + route, new URLSearchParams({ _token: csrf, event_id: eventId, ...values }));
  assert.equal((await post('event-save', { title: 'Local verification event', slug, teaser: 'Local test only', intro: 'Local test only', is_upcoming: '1', registration_open: '1', sort_order: '-1' })).status, 303);
  const details = new URLSearchParams({ _token: csrf, event_id: eventId });
  details.append('label[]', 'Date'); details.append('value[]', '2099-12-05');
  assert.equal((await request(admin + 'details-save', details)).status, 303);
  assert.equal((await post('event-status', { status: 'published' })).status, 303);
  const homeHtml = await (await request('/')).text();
  assert(homeHtml.includes('/@vite/client') && homeHtml.includes('/@react-refresh'));
  const popup = JSON.parse(decodeHTML(pageData(homeHtml).values.POPUP_EVENTS));
  assert(popup.some(event => event.slug === slug), 'Local dashboard changes must appear in the React popup data');
  const eventHtml = await (await request('/events/' + slug)).text();
  assert(eventHtml.includes('<title>Local verification event'));
  const data = pageData(eventHtml);
  assert.equal(data.page, 'event-detail');
  assert.equal(data.values.REGISTRATION, '', 'Event promotion must not contain the form');
  const registrationHtml = await (await request('/events/' + slug + '/register')).text();
  const registrationData = pageData(registrationHtml);
  assert.equal(registrationData.page, 'event-registration');
  const form = new JSDOM(registrationData.values.REGISTRATION).window.document.querySelector('form');
  assert(form && form.querySelector('[name="headshot"][required]'));
  await delay(3100);
  const registration = new FormData();
  for (const input of form.querySelectorAll('input[type="hidden"]')) registration.set(input.name, input.value);
  registration.set('name', 'Local verification attendee');
  registration.set('email', 'local-check@example.invalid');
  registration.set('phone', '+92 300 1234567');
  registration.set('organization', 'Local verification company');
  registration.set('designation', 'Verification attendee');
  registration.set('linkedin_url', 'https://www.linkedin.com/in/local-verification');
  registration.set('gender', 'other');
  registration.set('price_option', 'corporate');
  const photo = execFileSync('.cache/php-runtime/php.exe', ['-d', 'extension_dir=.cache/php-runtime/ext', '-d', 'extension=gd', '-r', 'imagepng(imagecreatetruecolor(64,64));']);
  registration.set('headshot', new Blob([photo], { type: 'image/png' }), 'local-check.png');
  const saved = await request('/api/register', registration, false);
  assert.equal(saved.status, 200, await saved.clone().text());
  const result = await saved.json();
  assert(result.ok && result.redirect.startsWith('/events/' + slug + '/share/'));
  assert.equal(pageData(await (await request(result.redirect)).text()).page, 'share');
  const registrationsHtml = await (await request(admin + 'registrations&event=' + eventId)).text();
  const headshotPath = new JSDOM(registrationsHtml).window.document.querySelector('a[href*="reg-headshot"]').getAttribute('href');
  assert.equal((await request(headshotPath, undefined, false)).status, 303);
  const headshot = await request(headshotPath);
  assert.equal(headshot.status, 200);
  assert.equal(headshot.headers.get('content-type'), 'image/webp');
  const csv = await (await request(admin + 'reg-export&event=' + eventId)).text();
  assert(csv.includes('Local verification attendee') && csv.includes('LinkedIn,Gender,Headshot'));
  assert(fs.readFileSync('.cache/local/private/mailbox.log', 'utf8').includes('local-check@example.invalid'));
  assert.equal((await request('/events/' + slug + '/register/')).status, 200, 'Trailing-slash registration route works');
  assert.equal((await post('event-save', { title: 'Local verification event', slug, teaser: 'Local test only', intro: 'Local test only', is_upcoming: '1', sort_order: '-1' })).status, 303);
  assert.equal((await fetch('http://127.0.0.1:8000/events/' + slug + '/register')).status, 404, 'Closed registrations cannot open the PHP form');
  assert.equal(pageData(await (await request('/events/' + slug + '/register')).text()).page, '404', 'Vite must respect the closed PHP route');
  const closed = pageData(await (await request('/events/' + slug)).text());
  assert.equal(closed.values.REGISTRATION_URL, '');
  assert(closed.values.EVENT_ACTION.includes('Registration is closed'));
  for (const blocked of ['/.cache/local/private/state.json', '/.cache/local/db/my.ini']) {
    const response = await request(blocked);
    assert([403, 404].includes(response.status), 'Local private files must be blocked');
  }
  console.log('Local event publishing, React/Vite data refresh, registration, private headshots, CSV and captured email passed.');
} finally {
  if (eventId && csrf) assert.equal((await request(admin + 'event-status', new URLSearchParams({ _token: csrf, event_id: eventId, status: 'delete' }))).status, 303);
  if (csrf) await request(admin + 'logout', new URLSearchParams({ _token: csrf }));
}
