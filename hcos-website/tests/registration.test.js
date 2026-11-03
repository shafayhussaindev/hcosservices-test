import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { createHmac } from 'node:crypto';
import { setTimeout as delay } from 'node:timers/promises';
import { JSDOM } from 'jsdom';

const php = process.env.PHP_BINARY || (process.platform === 'win32' ? path.resolve('.cache/php-runtime/php.exe') : 'php');
assert.deepEqual(JSON.parse(execFileSync(php, ['-r', "require 'server/_app/lib/registrations.php'; echo json_encode(array_map('registration_gender_label', ['male', 'female', 'other', 'man', 'woman', 'non_binary', 'prefer_not_to_say', null]));"], { encoding: 'utf8' })), ['Male', 'Female', 'Other', 'Male', 'Female', 'Other', 'Not supplied', 'Not supplied']);
const testDir = fs.mkdtempSync(path.resolve('.cache/registration-test-'));
const socket = net.createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const args = [];
if (process.platform === 'win32') {
  args.push('-d', 'extension_dir=' + path.join(path.dirname(php), 'ext'));
  for (const extension of ['mbstring', 'gd', 'fileinfo', 'pdo_sqlite']) args.push('-d', 'extension=' + extension);
}
for (const file of fs.readdirSync('server', { recursive: true }).filter(file => file.endsWith('.php'))) {
  execFileSync(php, ['-l', path.join('server', file)], { stdio: 'pipe' });
}
const child = spawn(php, [...args, '-S', `127.0.0.1:${port}`, 'tests/fixtures/registration-router.php'], { env: { ...process.env, HCOS_TEST_DIR: testDir }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let log = '';
child.stderr.on('data', chunk => { log += chunk; });
const origin = `http://127.0.0.1:${port}`;
const state = async () => (await fetch(origin + '/state')).json();
try {
  let ready = false;
  for (let i = 0; i < 60; i++) {
    try { await state(); ready = true; break; } catch { await delay(100); }
  }
  assert(ready, 'PHP test server did not start: ' + log);
  const pdf = catalogEntry => {
    const objects = [`<< /Type /Catalog /Pages 2 0 R ${catalogEntry} >>`, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>'];
    let body = '%PDF-1.4\n';
    const offsets = [0];
    for (const [index, object] of objects.entries()) {
      offsets.push(Buffer.byteLength(body));
      body += `${index + 1} 0 obj\n${object}\nendobj\n`;
    }
    const xref = Buffer.byteLength(body);
    body += `xref\n0 4\n0000000000 65535 f \n${offsets.slice(1).map(offset => String(offset).padStart(10, '0') + ' 00000 n \n').join('')}trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    return body;
  };
  for (const entry of ['', '/OpenAction [3 0 R /Fit]', '/OpenAction [3 0 R /XYZ null null 0]', '/OpenAction [3 0 R /FitH 842]']) {
    const body = pdf(entry);
    const form = new FormData();
    form.set('file', new Blob([body], { type: 'application/pdf' }), 'agenda.pdf');
    const response = await fetch(origin + '/test-pdf', { method: 'POST', body: form });
    assert.equal(response.status, 200, entry);
    const saved = (await response.json()).file;
    assert.equal(saved.mime, 'application/pdf');
    assert.equal(fs.readFileSync(path.join(testDir, saved.path), 'utf8'), body, 'PDF content must be preserved');
  }
  for (const entry of ['/OpenAction << /S /JavaScript /JS (app.alert(1)) >>', '/OpenAction << /S /URI /URI (https://example.com) >>', '/OpenAction 4 0 R', '/OpenAction [3 0 R /XYZ /JS (bad)]', '/OpenAction [3 0 R /Fit] /JavaScript 4 0 R', '/Launch 4 0 R', '/EmbeddedFile 4 0 R', '/AA << >>']) {
    const form = new FormData();
    form.set('file', new Blob([pdf(entry)], { type: 'application/pdf' }), 'unsafe.pdf');
    assert.equal((await fetch(origin + '/test-pdf', { method: 'POST', body: form })).status, 422, entry);
  }
  const photo = await (await fetch(origin + '/photo')).arrayBuffer();
  const submit = async (overrides = {}, blob = new Blob([photo], { type: 'image/png' })) => {
    const timestamp = String(Math.floor(Date.now() / 1000) - 5);
    const form = new FormData();
    const values = { event_slug: 'first-event', name: 'Test Visitor', email: 'visitor@example.com', phone: '+92 300 1234567', organization: 'Test company', designation: 'Test role', linkedin_url: 'https://www.linkedin.com/in/test-visitor', gender: 'other', price_option: 'corporate', stamp: timestamp + '.' + createHmac('sha256', 'test-key').update('stamp|' + timestamp).digest('hex'), ...overrides };
    for (const [key, value] of Object.entries(values)) form.set(key, value);
    if (blob) form.set('headshot', blob, 'headshot.png');
    return fetch(origin + '/api/register', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
  };
  for (const phone of ['', '   ', 'invalid']) {
    const response = await submit({ phone });
    assert.equal(response.status, 422);
    assert.equal((await response.json()).error, 'Please enter a valid phone number.');
  }
  for (const field of ['organization', 'designation']) {
    for (const value of ['', '   ']) {
      const response = await submit({ [field]: value });
      assert.equal(response.status, 422);
      assert.equal((await response.json()).error, `Please enter your ${field}.`);
    }
  }
  for (const price_option of ['', '   ', '1500', 'invalid']) {
    const response = await submit({ price_option });
    assert.equal(response.status, 422);
    assert.equal((await response.json()).error, 'Please select a participant price option.');
  }
  assert.equal((await submit({ linkedin_url: 'https://linkedin.com.bad.test/in/visitor' })).status, 422);
  assert.equal((await submit({ linkedin_url: 'http://www.linkedin.com/in/visitor' })).status, 422);
  assert.equal((await submit({ gender: '' })).status, 422);
  for (const gender of ['man', 'woman', 'non_binary', 'prefer_not_to_say']) assert.equal((await submit({ gender })).status, 422);
  assert.equal((await submit({}, null)).status, 422);
  assert.equal((await submit({}, new Blob(['<?php echo "bad";'], { type: 'image/png' }))).status, 422);
  assert.equal((await submit({}, new Blob([new Uint8Array(3145729)], { type: 'image/png' }))).status, 422);
  assert.equal((await state()).photos.length, 0);
  const first = await submit({ gender: 'male' });
  assert.equal(first.status, 200, await first.text());
  assert.equal((await submit({ event_slug: 'second-event', name: 'Second attendee', gender: 'female', price_option: 'student' })).status, 200);
  let saved = await state();
  assert.equal(saved.registrations.length, 2, 'The same email can register for distinct events');
  assert.deepEqual(saved.registrations.map(row => row.event_id), [1, 2]);
  assert.deepEqual(saved.registrations.map(row => row.gender), ['male', 'female']);
  assert.deepEqual(saved.registrations.map(row => row.price_option), ['corporate', 'student']);
  assert.equal(saved.photos.length, 2);
  assert(saved.registrations[0].headshot.startsWith('headshots/event-1/'));
  assert(saved.registrations[1].headshot.startsWith('headshots/event-2/'));
  assert.equal((await submit({ name: 'Replacement name', price_option: 'student' })).status, 200);
  saved = await state();
  assert.equal(saved.registrations.length, 2);
  assert.equal(saved.registrations[0].name, 'Test Visitor', 'An unauthenticated repeat must not overwrite a saved profile');
  assert.equal(saved.registrations[0].price_option, 'corporate', 'An unauthenticated repeat must not overwrite the saved price option');
  assert.equal(saved.photos.length, 2, 'A duplicate must not orphan a second headshot');
  const failedInsert = await submit({ email: 'fail@example.com' });
  assert.equal(failedInsert.status, 500, await failedInsert.text());
  assert.equal((await state()).photos.length, 2, 'A failed insert must remove its headshot');

  const headshotUrl = origin + '/event-setup/?r=reg-headshot&id=1';
  assert.equal((await fetch(headshotUrl, { redirect: 'manual' })).status, 303, 'Headshots require a staff session');
  const login = await fetch(origin + '/test-login');
  const headers = { Cookie: login.headers.get('set-cookie').split(';')[0] };
  const headshot = await fetch(headshotUrl, { headers });
  assert.equal(headshot.status, 200);
  assert.equal(headshot.headers.get('content-type'), 'image/webp');
  assert(headshot.headers.get('cache-control').includes('no-store'));
  const overview = await (await fetch(origin + '/event-setup/?r=registrations', { headers })).text();
  assert(overview.includes('Registrations by event'));
  const eventList = await (await fetch(origin + '/event-setup/?r=registrations&event=1', { headers })).text();
  assert(eventList.includes('Test Visitor') && !eventList.includes('Second attendee'));
  const document = new JSDOM(eventList).window.document;
  const cells = document.querySelectorAll('tbody tr:first-child td');
  assert(cells[0].textContent.includes('Test Visitor'));
  assert(cells[0].textContent.includes('Corporate Sector Rs:3000 per participant'));
  assert.equal(cells[1].querySelector('a[href^="tel:"]').textContent, '+92 300 1234567');
  assert.equal(cells[1].querySelector('a[target="_blank"]').href, 'https://www.linkedin.com/in/test-visitor');
  assert.equal(cells[2].querySelector('img').getAttribute('src'), '/event-setup/?r=reg-headshot&id=1');
  assert.equal(cells[2].querySelector('img').alt, 'Headshot of Test Visitor');
  assert.equal(cells[3].querySelector('option[selected]').textContent, 'New');
  assert.equal(cells[4].querySelectorAll('details li').length, 2);
  assert(cells[4].textContent.includes('Agenda <final>.pdf') && cells[4].textContent.includes('approved'));
  assert(!eventList.includes('Other event.pdf'));
  assert(cells[5].textContent.trim(), 'Registration date is visible');
  const secondList = await (await fetch(origin + '/event-setup/?r=registrations&event=2', { headers })).text();
  assert(secondList.includes('Other event.pdf') && !secondList.includes('Approved document.pdf'));
  const dashboard = await (await fetch(origin + '/event-setup/?r=dashboard', { headers })).text();
  assert(dashboard.includes('Registration activity by event') && !dashboard.includes('Test Visitor'));
  assert.equal((await fetch(origin + '/event-setup/?r=reg-export', { headers, redirect: 'manual' })).status, 303);
  const csv = await (await fetch(origin + '/event-setup/?r=reg-export&event=1', { headers })).text();
  assert(csv.includes('Test Visitor') && !csv.includes('Second attendee'));
  assert(csv.includes('Participant price') && csv.includes('Corporate Sector Rs:3000 per participant'));
  const remove = async (route, values) => fetch(origin + '/event-setup/?r=' + route, { method: 'POST', headers, body: new URLSearchParams({ _token: 'test-csrf', ...values }), redirect: 'manual' });
  assert.equal((await remove('reg-delete', { id: '1' })).status, 303);
  assert.equal((await state()).photos.length, 1);
  assert.equal((await remove('event-status', { event_id: '2', status: 'delete' })).status, 303);
  assert.equal((await state()).photos.length, 0);
  console.log('PHP registration/upload validation, private headshots, event isolation, CSV and deletion checks passed (SQLite fixtures).');
} finally {
  child.kill();
  await new Promise(resolve => child.once('exit', resolve));
  assert(path.resolve(testDir).startsWith(path.resolve('.cache') + path.sep));
  fs.rmSync(testDir, { recursive: true, force: true });
}
