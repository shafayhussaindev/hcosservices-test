import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { decodeHTML } from 'entities';

const backend = 'http://127.0.0.1:8000';

// The PHP-compatible build helpers also supply snapshot content for Vite dev.
function previewPages() {
  let pages;
  const refresh = () => {
    execFileSync(process.execPath, ['scripts/build.cjs', '--data-only'], { stdio: 'pipe' });
    pages = JSON.parse(readFileSync('.cache/pages.json', 'utf8'));
  };
  const escape = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  return {
    name: 'hcos-preview-pages',
    apply: 'serve',
    configureServer(server) {
      refresh();
      server.watcher.add(['src/data', 'scripts/build.cjs', 'server/_app/views/fragments/registration.html']);
      server.watcher.on('change', file => {
        const normalized = file.replace(/\\/g, '/');
        if (/\/src\/data\/|\/scripts\/build\.cjs$|\/server\/.*registration\.html$/.test(normalized)) {
          try {
            refresh();
            server.ws.send({ type: 'full-reload' });
          } catch (error) {
            server.ws.send({ type: 'error', err: { message: error.message, stack: error.stack } });
          }
        }
      });
      server.middlewares.use('/api/visits', (_req, res, next) => {
        if (existsSync('.cache/local-backend.env')) return next();
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify({ count: 21945, live: false }));
      });
    },
    transformIndexHtml: {
      order: 'pre',
      async handler(html, context) {
        const route = new URL(context.originalUrl || context.path, 'http://localhost').pathname.replace(/\.html$|\/$/g, '') || '/';
        let { meta } = pages[route] || pages['/404'];
        let data = (pages[route] || pages['/404']).data;
        if (existsSync('.cache/local-backend.env') && /^\/(?:$|contact$|events(?:\/|$))/.test(route)) {
          try {
            const response = await fetch(backend + route, { signal: AbortSignal.timeout(3000) });
            const page = await response.text();
            if (response.status === 404) {
              data = pages['/404'].data;
              meta = pages['/404'].meta;
            }
            const boot = /<script type="application\/json" id="react-page-data">([\s\S]*?)<\/script>/.exec(page);
            if (response.ok && boot) {
              data = JSON.parse(boot[1]);
              const title = /<title>([\s\S]*?)<\/title>/.exec(page);
              meta = { ...meta, title: title ? decodeHTML(title[1]) : meta.title, nav: data.nav };
            }
          } catch { /* The React snapshot preview remains available without PHP. */ }
        }
        return {
          html: html.replace(/<title>.*?<\/title>/, '<title>' + escape(meta.title) + '</title>')
            .replace(/content="HCOS Services — success through people\."/, 'content="' + escape(meta.desc) + '"')
            .replace('<body>', '<body data-page="' + escape(meta.nav) + '">'),
          tags: [{ tag: 'script', attrs: { id: 'react-page-data', type: 'application/json' },
            children: JSON.stringify(data).replace(/</g, '\\u003c'), injectTo: 'body-prepend' }]
        };
      }
    }
  };
}

export default defineConfig({
  plugins: [react(), previewPages()],
  server: {
    // Uploaded event banners are server-owned and are not stored in this repo.
    proxy: {
      '^/event-setup(?:/|\\?|$)': { target: backend },
      '^/api/': { target: backend },
      '/uploads': { target: existsSync('.cache/local-backend.env') ? backend : 'https://hcosservices.com', changeOrigin: true }
    },
    watch: { ignored: ['**/dist/**', '**/.cache/**'] },
    fs: { deny: ['.env', '.env.*', '*.{crt,pem}', '**/.git/**', '**/.cache/**', '**/private/**', '**/server/**'] }
  },
  build: {
    emptyOutDir: true,
    manifest: true,
    rolldownOptions: {
      input: 'src/main.jsx',
      output: { entryFileNames: 'assets/react/[name]-[hash].js' }
    }
  }
});
