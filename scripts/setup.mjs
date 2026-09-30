import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync, closeSync, openSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const backend = `${root}backend/`;
function run(command, args, cwd = backend) {
  const result = spawnSync(command, args, { cwd, stdio: 'inherit' });
  if (result.status !== 0) process.exit(result.status ?? 1);
}
if (!existsSync(`${backend}.env`)) copyFileSync(`${backend}.env.example`, `${backend}.env`);
run('composer', ['install', '--no-interaction', '--prefer-dist']);
const config = readFileSync(`${backend}.env`, 'utf8');
if (!/^APP_KEY=.+/m.test(config)) run('php', ['artisan', 'key:generate', '--no-interaction']);
if (/^DB_CONNECTION=sqlite$/m.test(config) && !existsSync(`${backend}database/database.sqlite`)) {
  closeSync(openSync(`${backend}database/database.sqlite`, 'a'));
}
run('php', ['artisan', 'migrate', '--no-interaction']);
run('php', ['artisan', 'db:seed', '--no-interaction']);
run('php', ['artisan', 'hub:dev-accounts', '--no-interaction']);
run('npm', ['run', 'build'], root);
console.log('Ready. Run npm start, or npm run dev for live frontend changes.');
