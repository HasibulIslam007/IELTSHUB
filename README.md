# IELTS Practice Hub

A same-origin IELTS practice application with a React learner interface and a Laravel/Filament administration workspace.

The platform includes all four skills, 10 original demonstration tests, immutable published content, autosaved attempts, timed skill mocks, deterministic objective marking, private speaking recordings, human writing/speaking assessment, a mistake notebook and progress tracking. Demo exercises are not professionally calibrated and do not produce official IELTS scores.

## Requirements

- PHP 8.5 and Composer 2; PHP extensions: PDO PostgreSQL, PDO SQLite (tests), intl, mbstring, XML/DOM, fileinfo and zip.
- Node.js 22.12+ and npm.
- PostgreSQL 16+ for the main application. The existing local environment uses `127.0.0.1:55432`, database `ielts_hub`.
- Chromium for the optional Playwright suite; install with the command below.

Versions are pinned by `backend/composer.lock` and `package-lock.json`. Laravel currently resolves to 13.34 and Filament to 5.9.

## Install and run

```sh
npm ci
cp backend/.env.example backend/.env
```

Keep an existing `.env` instead of replacing it. Configure its PostgreSQL connection:

```dotenv
APP_NAME="IELTS Practice Hub"
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=ielts_hub
DB_USERNAME=your_local_postgres_role
DB_PASSWORD=
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000
```

Create the database first with `createdb -h 127.0.0.1 -p 55432 ielts_hub`. For a portable preview, the example environment defaults to SQLite; setup creates its database file automatically.

```sh
npm run setup
npm start
```

Setup installs locked PHP dependencies, generates an application key only when missing, migrates, seeds demo content, creates local development accounts and builds the frontend. It does not reset existing application data or passwords.

- Learner app: **http://127.0.0.1:8000**
- Admin and teacher workspace: **http://127.0.0.1:8000/admin**
- API specification: **http://127.0.0.1:8000/api-docs**

`npm start` runs the built application, queue worker and scheduler. `npm run dev` runs those services plus Vite with live frontend updates. Open port 8000 for both modes; port 5173 serves development assets only. Ctrl+C stops the services started by the runner. Start PostgreSQL separately.

The first `hub:dev-accounts` invocation writes random passwords to `backend/storage/app/private/development-accounts.json` (mode 0600). Accounts are `student@ielts.local`, `teacher@ielts.local`, and `admin@ielts.local`. The command never changes an existing password. Keep the credential file private; it is ignored by Git.

## Checks

```sh
npm test
PLAYWRIGHT_BROWSERS_PATH=.local/browsers npx playwright install chromium
npm run test:e2e
# or run the complete suite:
npm run check
```

`npm test` runs TypeScript checking, the production Vite build and PHPUnit. PHPUnit uses an in-memory SQLite database. Playwright starts its own server on port 8001 and creates a new SQLite database and accounts under `.local/e2e-*`; it does not reset the main PostgreSQL database. Desktop and iPhone 13-sized Chromium tests cover registration, reading autosave/recovery, listening playback, speaking uploads, admin pages and published teacher feedback. This is viewport emulation, not a Safari/iOS device test.

GitHub Actions runs backend/build and browser jobs on pushes and pull requests. Browser reports are retained as workflow artifacts.

## Project layout

| Path | Purpose |
| --- | --- |
| `backend/app/Services` | Access, content publication, attempts, marking and assessment rules |
| `backend/app/Filament` | Content authoring, teacher reviews and business administration |
| `backend/database/content` | Original demo exercises, transcripts and synthesized WAV audio |
| `frontend/src` | React routes, exam workspace and local draft recovery |
| `shared` | TypeScript contracts, OpenAPI and shared route metadata |
| `scripts` | Setup, local service runners and browser tests |

See [OPERATIONS.md](docs/OPERATIONS.md) for deployment, workers, private storage, retention, backups and account deletion. [IMPLEMENTATION.md](docs/IMPLEMENTATION.md) records the delivered scope and remaining external integrations.

## Integration status

Payments and AI assessment are disabled. Email is disabled until a real mail transport and `MAIL_ENABLED=true` are configured. Reminder preferences are stored, but automated study reminders are not implemented. An administrator can grant premium access manually. These limitations are shown in the application; it does not pretend to charge, send mail or generate AI grades.
