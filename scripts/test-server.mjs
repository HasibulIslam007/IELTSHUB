import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
mkdirSync(`${root}.local`, { recursive: true });
const directory = mkdtempSync(`${root}.local/e2e-`);
writeFileSync(`${directory}/database.sqlite`, '');
const env = {
  ...process.env, APP_ENV: 'local', APP_URL: 'http://127.0.0.1:8001',
  DB_CONNECTION: 'sqlite', DB_DATABASE: `${directory}/database.sqlite`, DB_URL: '',
  SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:8001', HUB_FRONTEND_DEV: 'false',
  SESSION_DRIVER: 'database', CACHE_STORE: 'array', MAIL_ENABLED: 'false',
  QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
};
for (const args of [
  ['migrate', '--no-interaction'], ['db:seed', '--no-interaction'],
  ['hub:dev-accounts', `--credentials=${directory}/accounts.json`, '--no-interaction'],
]) {
  const result = spawnSync('php', ['artisan', ...args], { cwd: `${root}backend`, env, stdio: 'inherit' });
  if (result.status !== 0) process.exit(result.status ?? 1);
}
writeFileSync(`${root}.local/e2e-credentials-path`, `${directory}/accounts.json`);
const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8001', '--tries=1', '--no-interaction'], {
  cwd: `${root}backend`, env, stdio: 'inherit',
});
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
server.on('exit', code => { process.exitCode = code ?? 0; });
