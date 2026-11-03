import { spawn } from 'node:child_process';
import { startBackend } from './dev-backend.js';

const stop = await startBackend();
const vite = spawn(process.execPath, ['node_modules/vite/bin/vite.js', ...process.argv.slice(2)], { stdio: 'inherit', windowsHide: true });
let stopped = false;
const cleanup = () => { if (!stopped) { stopped = true; vite.kill(); stop(); } };
for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, () => { cleanup(); process.exit(0); });
vite.once('exit', code => { cleanup(); process.exit(code ?? 1); });
