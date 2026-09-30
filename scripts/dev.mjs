import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const backend = fileURLToPath(new URL('../backend/', import.meta.url));
const built = process.argv.includes('--built');
if (!existsSync(`${backend}.env`) || !existsSync(`${backend}vendor/autoload.php`)) {
  console.error('Run npm install and npm run setup first.');
  process.exit(1);
}
if (built && !existsSync(`${backend}public/build/manifest.json`)) {
  const result = spawnSync('npm', ['run', 'build'], { cwd: root, stdio: 'inherit' });
  if (result.status !== 0) process.exit(result.status ?? 1);
}

const children = [];
let stopping = false;
function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) {
    try { process.kill(-child.pid, 'SIGTERM'); } catch { child.kill('SIGTERM'); }
  }
  process.exitCode = code;
}
function start(command, args, cwd, env = {}) {
  const child = spawn(command, args, {
    cwd, stdio: 'inherit', detached: process.platform !== 'win32',
    env: { ...process.env, ...env },
  });
  children.push(child);
  child.on('error', error => { console.error(error.message); stop(1); });
  child.on('exit', code => { if (!stopping) stop(code || 1); });
}
process.on('SIGINT', () => stop());
process.on('SIGTERM', () => stop());
if (!built) start('node', ['node_modules/vite/bin/vite.js'], root);
start('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1', '--no-interaction'], backend, { HUB_FRONTEND_DEV: built ? 'false' : 'true' });
start('php', ['artisan', 'queue:work', '--tries=3', '--timeout=90', '--sleep=1', '--no-interaction'], backend);
start('php', ['artisan', 'schedule:work', '--no-interaction'], backend);
console.log('\nIELTS Practice Hub: http://127.0.0.1:8000\nAdmin workspace: http://127.0.0.1:8000/admin\nCtrl+C stops all application services.\n');
