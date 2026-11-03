/* Link + asset audit for the built site. Run: node scripts/check-links.js */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SKIP_DIRS = ['assets', 'src', 'node_modules', '.vercel', '.git'];
const files = [];

(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory()) {
      if (SKIP_DIRS.includes(e.name)) continue;
      walk(path.join(dir, e.name));
    } else if (e.name.endsWith('.html')) {
      files.push(path.join(dir, e.name));
    }
  }
})(path.join(__dirname, '..', 'dist'));

const toRoute = (f) => {
  const rel = '/' + path.relative(path.join(__dirname, '..', 'dist'), f).split(path.sep).join('/').replace(/\.html$/, '');
  return rel === '/index' ? '/' : rel;
};

const routes = new Set(files.map(toRoute));

const broken = [];
const placeholders = [];
const external = [];
const mailtel = [];

for (const f of files) {
  const rel = path.relative(path.join(__dirname, '..', 'dist'), f).split(path.sep).join('/');
  const html = fs.readFileSync(f, 'utf8');

  for (const m of html.matchAll(/(?:href|src|srcset)="([^"]+)"/g)) {
    const u = m[1].split(',')[0].trim().split(' ')[0];
    if (!u) continue;

    if (/^(https?:)/.test(u)) { external.push(u); continue; }
    if (/^(mailto:|tel:)/.test(u)) { mailtel.push(u); continue; }
    if (/^data:/.test(u)) continue;

    if (u === '#') { placeholders.push(rel); continue; }
    if (u.startsWith('#')) continue;

    if (u.startsWith('/assets/')) {
      if (!fs.existsSync(path.join(__dirname, '..', 'dist', u))) broken.push(`${rel}  ->  MISSING ASSET  ${u}`);
      continue;
    }

    const clean = (u.split('#')[0].replace(/\/$/, '')) || '/';
    if (!routes.has(clean)) broken.push(`${rel}  ->  BROKEN ROUTE  ${u}`);
  }
}

const uniq = (a) => [...new Set(a)];

console.log(`Routes built (${routes.size}):`);
[...routes].sort().forEach((r) => console.log('  ' + r));

console.log(`\nBroken internal links / missing assets: ${uniq(broken).length}`);
uniq(broken).forEach((b) => console.log('  ' + b));

console.log(`\nPages still containing a bare "#" link: ${uniq(placeholders).length}`);
uniq(placeholders).forEach((p) => console.log('  ' + p));

const hosts = uniq(external.map((u) => { try { return new URL(u).host; } catch { return u; } }));
console.log(`\nExternal hosts referenced: ${hosts.join(', ')}`);
console.log(`mailto/tel targets: ${uniq(mailtel).join(', ')}`);
