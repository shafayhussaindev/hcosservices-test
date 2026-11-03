import { spawn, execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, openSync, closeSync, readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import { randomBytes } from 'node:crypto';
import { pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const local = path.resolve('.cache/local');
const privateDir = path.join(local, 'private');
const root = path.join(local, 'public_html');
const php = process.env.PHP_BINARY || path.resolve('.cache/php-runtime/php.exe');
const bin = path.resolve('.cache/mariadb-11.4.10-winx64/bin');
const marker = '.cache/local-backend.env';
const phpArgs = ['-d', 'extension_dir=' + path.join(path.dirname(php), 'ext')];
for (const extension of ['mbstring', 'gd', 'fileinfo', 'pdo_mysql']) phpArgs.push('-d', 'extension=' + extension);

async function listening(port) {
  return new Promise(resolve => {
    const socket = net.createConnection({ host: '127.0.0.1', port });
    socket.once('connect', () => { socket.destroy(); resolve(true); });
    socket.once('error', () => resolve(false));
  });
}

export async function startBackend() {
  if (!existsSync(php) || !existsSync(path.join(bin, 'mariadbd.exe'))) {
    throw new Error('Local PHP/MariaDB runtimes are missing. See LOCAL-DEVELOPMENT.md.');
  }
  if (await listening(8000) || await listening(3307)) throw new Error('Local backend ports 8000 or 3307 are in use. Stop the other local backend first.');
  mkdirSync(privateDir, { recursive: true });
  const stateFile = path.join(privateDir, 'state.json');
  if (!existsSync(stateFile)) {
    writeFileSync(stateFile, JSON.stringify({ rootPassword: randomBytes(24).toString('hex'), dbPassword: randomBytes(24).toString('hex'),
      appKey: randomBytes(32).toString('hex'), adminPassword: 'Local9!' + randomBytes(16).toString('hex') }), { mode: 0o600 });
  }
  const state = JSON.parse(readFileSync(stateFile, 'utf8'));
  const dataDir = path.join(local, 'db');
  if (!existsSync(path.join(dataDir, 'mysql'))) {
    try {
      execFileSync(path.join(bin, 'mariadb-install-db.exe'), ['--datadir=' + dataDir, '--password=' + state.rootPassword, '--port=3307'], { windowsHide: true, stdio: 'pipe' });
    } catch { throw new Error('Local database initialization failed; no production database was contacted.'); }
  }
  // Build the PHP views once; the browser still runs React through Vite + HMR.
  execFileSync(process.execPath, ['scripts/build.cjs'], { stdio: 'inherit' });
  cpSync('server', root, { recursive: true, filter: source => path.resolve(source) !== path.resolve('server/uploads') });
  cpSync('dist/_app/views/pages', path.join(root, '_app/views/pages'), { recursive: true });
  mkdirSync(path.join(root, '_app/data'), { recursive: true });
  cpSync('src/data/events.json', path.join(root, '_app/data/events.json'));
  mkdirSync(path.join(root, 'uploads'), { recursive: true });
  writeFileSync(path.join(privateDir, 'login.txt'), 'Local dashboard only\nUser ID: local.admin\nPassword: ' + state.adminPassword + '\n', { mode: 0o600 });
  const options = path.join(privateDir, 'mysql.cnf');
  writeFileSync(options, '[client]\nhost=127.0.0.1\nport=3307\nuser=root\npassword=' + state.rootPassword + '\n', { mode: 0o600 });
  const log = openSync(path.join(privateDir, 'backend.log'), 'a');
  const database = spawn(path.join(bin, 'mariadbd.exe'), ['--no-defaults', '--basedir=' + path.dirname(bin), '--datadir=' + dataDir,
    '--port=3307', '--bind-address=127.0.0.1', '--skip-name-resolve', '--console'], { windowsHide: true, stdio: ['ignore', log, log] });
  let server;
  const stop = () => {
    if (existsSync(marker)) unlinkSync(marker);
    server?.kill();
    if (database.exitCode === null) {
      try { execFileSync(path.join(bin, 'mariadb-admin.exe'), ['--defaults-extra-file=' + options, 'shutdown'], { windowsHide: true, stdio: 'pipe', timeout: 10000 }); }
      catch { database.kill(); }
    }
    closeSync(log);
  };
  try {
    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
      if (database.exitCode !== null) break;
      if (await listening(3307)) { ready = true; break; }
      await delay(200);
    }
    if (!ready) throw new Error('Local MariaDB did not start. See .cache/local/private/backend.log.');
    execFileSync(php, [...phpArgs, 'scripts/dev-init.php', root], { windowsHide: true, stdio: 'inherit' });
    server = spawn(php, [...phpArgs, '-S', '127.0.0.1:8000', '-t', root, 'scripts/dev-router.php'], {
      windowsHide: true, stdio: ['ignore', log, log], env: { ...process.env, HCOS_DEV_ROOT: root }
    });
    ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
      if (server.exitCode !== null) break;
      if (await listening(8000)) { ready = true; break; }
      await delay(100);
    }
    if (!ready) throw new Error('Local PHP did not start. See .cache/local/private/backend.log.');
    writeFileSync(marker, 'native-local-backend\n');
    console.log('Local PHP + MariaDB ready. Dashboard credentials: .cache/local/private/login.txt');
    console.log('Test email is captured in .cache/local/private/mailbox.log; production is not used.');
    return stop;
  } catch (error) { stop(); throw error; }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  const stop = await startBackend();
  for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, () => { stop(); process.exit(0); });
}
