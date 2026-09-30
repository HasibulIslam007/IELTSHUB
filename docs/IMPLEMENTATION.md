# Implementation checklist

- [x] Inspect repository, runtime and official compatibility/timing sources.
- [ ] Scaffold Laravel 13, Sanctum, Filament 5, React/TypeScript/Vite/Tailwind and PostgreSQL.
- [ ] Persistent content/versioning, validation, deterministic marking and secure attempts.
- [ ] Authentication, permissions, private media, assessment and access services.
- [ ] Original demonstration content and audio with asset manifest.
- [ ] Public pages, student dashboard/library, four skill experiences and review/progress.
- [ ] Filament authoring, review assignment, assessment, imports and business management.
- [ ] OpenAPI, operations guides, CI and secure development account creation.
- [ ] Automated tests, production build and desktop/mobile browser verification.

Architecture: one same-origin Laravel application serves a separately organized React frontend. PostgreSQL owns durable state. All examination and assessment rules live in shared backend services used by both versioned API controllers and Filament. Published JSON content snapshots are immutable; attempts retain the snapshot and scoring configuration. Browser authentication uses server sessions and CSRF; scoped Sanctum tokens are reserved for mobile clients. Database queues handle notification work. Production recording storage is private S3-compatible storage. Providers remain disabled until configured.

External prerequisites: no payment country/provider assumed. No fabricated deliveries or AI assessments. Demonstration content must be labelled unvalidated; no estimated band for uncalibrated exercises.
