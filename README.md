# Earmark

A self-hosted, single-household YNAB replacement. Zero-based budgeting, browser-side PDF statement parsing, payee rule engine, two-user shared access — no Plaid required, no monthly fee.

## Stack

- **Laravel 13** + **PHP 8.4** backend
- **Livewire 4** thin full-page components (`pages::` namespace, `⚡` SFC convention)
- **Flux UI v2** component primitives
- **Alpine.js** for ephemeral client state
- **`lorisleiva/laravel-actions`** for bespoke domain operations
- **`@llamaindex/liteparse-wasm`** for browser-side PDF parsing (no Python sidecar)
- **Tailwind CSS v4**, **Pest 4** tests, **Laravel Pint** formatter

## Repo Layout

```
.
├── Earmark/                 # The application (Livewire)
│   ├── app/                 # Models, Actions, Services, Policies, Livewire Forms, Middleware
│   ├── resources/
│   │   ├── views/pages/     # Full-page Livewire SFCs (pages:: namespace)
│   │   ├── views/components/  # Shared Blade + Livewire components
│   │   └── js/alpine/       # Alpine.data() definitions (incl. liteparse)
│   ├── database/            # Migrations, factories, seeders
│   ├── routes/              # web.php, settings.php, console.php
│   ├── tests/               # Pest tests (Feature, Unit)
│   ├── Dockerfile + docker-compose.yml + docker/entrypoint.sh
│   └── docs/architecture-livewire.md
├── prd.md                   # Product requirements
├── AGENTS.MD                # Agent collaboration guide
├── CLAUDE.md                # Claude Code project instructions
└── .beads/                  # Local issue tracker
```

## Getting Started

```bash
cd Earmark
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=BucketSeeder

# Bootstrap the first owner (registration is invite-only)
php artisan earmark:create-first-user \
    --email=you@example.com \
    --name="Your Name" \
    --password='your-secret'

npm run dev          # one terminal
php artisan serve    # another terminal
```

Open `http://localhost:8000`, log in, and start adding accounts/categories/buckets at `/household/dashboard`.

### Docker

```bash
cd Earmark
docker compose up
```

Single `app` service. PDF parsing runs in the browser via `@llamaindex/liteparse-wasm` — no parser sidecar.

## Architecture Highlights

- **Money** is stored as integer cents (`bigInteger`). Never floats.
- **Single household per user** — locked at the app layer via `EnsureValidInvite` middleware; the schema still uses a `household_members` pivot in case multi-household ever becomes a requirement.
- **PDF statements are never persisted to disk** — parsed in-memory in the browser; only the parsed transactions are kept.
- **Thin Livewire components**: routing entry points delegate to Form objects (validation), Policies (authorization), domain Services (queries/orchestration), and Laravel Actions (bespoke operations). Logic does not accumulate in the page SFC.
- **Alpine vs Livewire**: ephemeral / presentational state → Alpine; persisted / validated / server-meaningful → Livewire; bridged via `wire:model` or direct `$wire` access when an interaction is both.
- **Registration is invite-only**. Bootstrap the first user with the `earmark:create-first-user` CLI; everyone else joins via a copy-paste invite URL (`/register?invite=<code>`) generated from `/household/members`.

## Documentation

- [`prd.md`](prd.md) — product requirements (zero-based budgeting, PDF ingestion, household model)
- [`Earmark/docs/architecture-livewire.md`](Earmark/docs/architecture-livewire.md) — Livewire stack architecture, do's and don'ts

## Issue Tracking

This repo uses [beads](https://github.com/gastownhall/beads) (`bd`) for local issue tracking. From any directory inside the repo:

```bash
bd ready             # show issues ready to work
bd show <id>         # view issue details
bd stats             # database snapshot
```

## Testing

```bash
cd Earmark
php artisan test --compact          # all tests
php artisan test --filter=BudgetServiceTest
vendor/bin/pint --dirty             # format dirty files
```
