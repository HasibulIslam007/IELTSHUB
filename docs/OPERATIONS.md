# Operations

## Local services

Run `npm run setup` once after configuring `backend/.env`, then `npm start` for built assets or `npm run dev` for Vite hot updates. These commands start PHP on port 8000, the database queue worker and the scheduler. PostgreSQL must already be running.

For this existing macOS checkout, the project-owned PostgreSQL cluster is `.local/postgres` and its socket directory is `.local/socket`:

```sh
pg_ctl -D "$PWD/.local/postgres" status
pg_ctl -D "$PWD/.local/postgres" -l "$PWD/.local/postgres.log" start
```

Use the same PostgreSQL major version that initialized the cluster (currently 16). A fresh installation can use an independently managed PostgreSQL instance; update `.env` to match. Do not run `initdb` over an existing cluster. SQLite is supported for a portable local preview and isolated tests, but PostgreSQL is the intended production database.

## Production release

Keep the repository layout intact: Laravel reads `shared/seo.json` and serves `shared/openapi.yaml` from the sibling directory. The web server document root must be **`backend/public`**, never the repository root. Do not expose `.env`, `.local`, private storage, source files or development credentials.

1. Back up the database and private recording storage.
2. Configure environment variables before booting PHP: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, a persistent `APP_KEY`, PostgreSQL, queue/cache/session database drivers, and `SANCTUM_STATEFUL_DOMAINS` containing the real host. Set `SESSION_SECURE_COOKIE=true`. Keep `HUB_FRONTEND_DEV=false`.
3. Install and build the exact locked dependencies:

   ```sh
   composer install --working-dir=backend --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci
   npm run build
   cd backend
   php artisan migrate --force --no-interaction
   php artisan optimize
   php artisan queue:restart
   ```

4. Run PHP-FPM behind HTTPS Nginx/Apache. Route non-file requests to `public/index.php`. Keep `storage` and `bootstrap/cache` writable. The Node/PHP development runners are not production process managers.
5. Supervise the queue worker and install the scheduler cron entry below. Check `/up`, a learner login, a saved attempt, private audio and the admin panel after each release.

Production content and administrator accounts should be created deliberately. Do not run demo seeding or the local account generator in production. `hub:dev-accounts` refuses environments other than local/testing. For the first administrator, use a controlled interactive application console, set `role=admin` explicitly, use a randomly generated password and send it through your normal secure onboarding process; avoid putting passwords in shell history.

## Queue and schedule

Run under your process manager, from `backend/`:

```sh
php artisan queue:work --tries=3 --timeout=90 --sleep=1
```

Install a minute-by-minute cron entry using the actual deployment path:

```cron
* * * * * cd /srv/ielts-hub/backend && php artisan schedule:run >> /var/log/ielts-scheduler.log 2>&1
```

The scheduler finalizes expired active attempts every minute and prunes expired recording files daily. Request-time deadline checks also finalize late attempts. Use `php artisan schedule:list` and `php artisan queue:failed` to inspect operations. Fix the underlying problem before retrying failed jobs. Published feedback creates database notifications; optional email requires a configured transport.

## Private files and recording retention

Development recordings and demo audio use Laravel's private `local` disk. Do not create a public symlink to `storage/app/private`. Authorization is checked in addition to the signed URL: a recording may be played by its owner, assigned reviewer or an administrator. Signed recording URLs last five minutes; retained files are inaccessible after expiry.

For private S3-compatible production storage, configure `FILESYSTEM_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and, where needed, `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT`. Deny public bucket access. The AWS filesystem adapter is installed. Recording responses are proxied through authorized Laravel routes; CSP does not require public access to the bucket. Configure upstream/PHP upload limits to allow the application limit of 25 MB plus multipart overhead. Admin-authored media currently uses the local disk and needs persistent storage across releases.

`RECORDING_RETENTION_DAYS` defaults to 90. Run `php artisan hub:prune-recordings` to delete expired recording files; metadata is retained. The current command revisits expired metadata on later runs. Attempt mutation history is retained indefinitely; no mutation-pruning policy is implemented. Add a policy before large-scale operation without deleting keys needed by active attempts.

The seeded WAV files use macOS synthesized voice and are demonstration assets. Review their platform license before distributing them in production. Exercises and band conversions require editorial review and calibration before production use.

## Account administration

Local development accounts:

```sh
cd backend
php artisan hub:dev-accounts
```

The private credential file contains passwords only for newly created accounts. Existing passwords are never reset. The optional `--credentials=/absolute/private/path.json` is used by isolated browser tests; its parent directory must already exist.

Students request deletion from Settings by confirming their password. Administrators review any retention requirements and run:

```sh
php artisan hub:delete-account USER_ID
```

The command requires an existing deletion request, removes private recordings, tokens, sessions and notifications, and deletes the user with cascading student data. Backups follow your separately defined retention policy. This is an irreversible operation; verify the user ID before running it.

Teacher accounts access `/admin` but only their assigned reviews. Administrators manage content, publish versions, assign teachers and grant/revoke entitlements. Existing attempts always keep their original immutable content version. Never edit published snapshot rows directly.

## Mail and payments

Configure an actual Laravel mail transport and sender before enabling `MAIL_ENABLED=true`. With mail disabled, reset and verification endpoints return 503 instead of claiming delivery. Log transport is for local debugging only. No recurring reminder job exists yet.

Checkout and webhook endpoints deliberately return 503; no payment processor is integrated. Do not accept payment or grant access based on those stubs. Admin-managed entitlement grants work independently. AI assessment is disabled; writing and speaking grades are human rubric assessments.

## Security and search metadata

The application sends CSP, `X-Frame-Options: DENY`, `nosniff`, a strict-origin referrer policy, and a permissions policy that allows same-origin microphones and denies camera/geolocation. HTTPS responses also receive one-year HSTS. Configure trusted proxies correctly when TLS terminates upstream; the application must recognize the original request as HTTPS.

The learner policy uses a per-response script nonce and same-origin assets. Development alone allows the fixed local Vite origin. Filament/Livewire routes use `unsafe-inline` and `unsafe-eval` for current Alpine/Filament compatibility; this is a documented CSP limitation, not a claim of a fully strict admin policy. Inline styles remain allowed for dynamic layouts. Static files served directly by Nginx/Apache bypass Laravel middleware; configure corresponding headers at the web server if required.

Titles, descriptions, canonical links and Open Graph/Twitter metadata render in the initial HTML and update during React navigation. `/sitemap.xml` includes public pages and published tests; `/robots.txt` points to it. Private pages use noindex metadata/headers. `APP_URL` is the canonical origin. Metadata is rendered server-side; the full React page content still requires JavaScript.

## Backups, logs and rollback

Back up PostgreSQL, private local/S3 files and the persistent `APP_KEY`. Protect backups like production data and test restoration. Keep the prior release's built assets and code for rollback. Review migrations before applying them; reverting code does not automatically reverse a schema/data change.

Use `backend/storage/logs/laravel.log`, your process-manager logs and `php artisan queue:failed` for diagnosis. Do not expose log files publicly. For an autosave 409, preserve/download the browser draft and load the latest server version; never bypass revision checks. A 419 indicates session/CSRF origin or cookie configuration, not a reason to disable CSRF.

`npm test` checks build/PHP behavior; `npm run test:e2e` covers browser flows using a separate disposable database. Browser reports and credentials are ignored by Git. GitHub Actions uploads only test reports, not `.local` databases or private credential files.
