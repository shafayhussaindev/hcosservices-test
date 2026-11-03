import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createServer } from 'vite';
import { JSDOM } from 'jsdom';
import initializeInteractions from '../src/lib/interactions.js';

const server = await createServer({ server: { middlewareMode: true }, logLevel: 'silent' });
try {
  assert.equal(server.config.server.proxy['/uploads'].target, fs.existsSync('.cache/local-backend.env') ? 'http://127.0.0.1:8000' : 'https://hcosservices.com');
  assert.equal(server.config.server.proxy['^/event-setup(?:/|\\?|$)'].target, 'http://127.0.0.1:8000');
  assert.equal(server.config.server.proxy['^/api/'].target, 'http://127.0.0.1:8000');
  assert(server.config.server.fs.deny.includes('**/.cache/**'), 'Local database files and credentials must not be web-served');
  const template = fs.readFileSync('index.html', 'utf8');
  for (const [route, page] of [['/', 'index'], ['/about', 'about'], ['/events/hr-seasons', 'event-detail'], ['/events/eco-textile-conference-3-0-karachi-edition/register', 'event-registration'], ['/missing', '404']]) {
    const html = await server.transformIndexHtml(route, template);
    const dom = new JSDOM(html);
    const data = JSON.parse(dom.window.document.getElementById('react-page-data').textContent);
    assert.equal(data.page, page);
    assert(html.includes('/@vite/client'));
    assert(html.includes('/@react-refresh'));
    assert(!html.includes('{{'));
    dom.window.close();
  }
  const app = await server.transformRequest('/src/App.jsx');
  assert(app.code.includes('react-refresh'), 'React Fast Refresh should transform JSX');
  const entry = await server.transformRequest('/src/main.jsx');
  assert(entry.code.includes('createRoot'));
} finally { await server.close(); }

// Fast Refresh re-runs effects: the previous handlers must be removed first.
const dom = new JSDOM('<header id="nav"></header><button id="navToggle"></button><div id="navPanel"></div>');
const previousWindow = globalThis.window;
const previousDocument = globalThis.document;
globalThis.window = dom.window;
globalThis.document = dom.window.document;
dom.window.matchMedia = () => ({ matches: true });
try {
  const cleanup = initializeInteractions();
  cleanup();
  const nextCleanup = initializeInteractions();
  const button = document.getElementById('navToggle');
  button.click();
  assert.equal(button.getAttribute('aria-expanded'), 'true', 'Only one menu handler should remain');
  nextCleanup();
  assert(!document.body.classList.contains('nav-open'));
} finally {
  if (previousWindow === undefined) delete globalThis.window;
  else globalThis.window = previousWindow;
  if (previousDocument === undefined) delete globalThis.document;
  else globalThis.document = previousDocument;
  dom.window.close();
}
console.log('Vite route data, React Fast Refresh and interaction cleanup checks passed.');
