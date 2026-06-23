# Earmark

A self-hosted, single-household YNAB replacement. Zero-based budgeting, PDF statement ingestion, payee rule engine, two-user shared access — no Plaid required, no monthly fee.

Two parallel implementations live in this repo:

| Project | Stack | Role |
|---|---|---|
| `Earmark/` | Laravel 13 + Livewire 4 + Flux UI + Alpine.js | Primary target |
| `Earmark-web/` | Laravel + Inertia.js + Svelte 5 + TypeScript | Reference implementation |
| `Earmark-parser/` | Python (FastAPI + pdfplumber + Docling) | PDF parser used by `Earmark-web`. Replaced in `Earmark/` by browser-side [`@llamaindex/liteparse`](https://www.llamaindex.ai/blog/liteparse-v2-0-runs-everywhere) |

The same product, the same domain model — exercised against two stacks to compare and refine the Livewire architecture.

## Repo Layout

```
.
├── Earmark/                 # Livewire reimplementation (active)
├── Earmark-web/             # Inertia + Svelte reference implementation
├── Earmark-parser/          # Python PDF parser (Earmark-web only)
├── docs/                    # Top-level docs (architecture, PRD)
├── docker-compose.yml       # Multi-service dev/prod compose
├── prd.md                   # Product requirements
├── AGENTS.MD                # Agent collaboration guide
├── CLAUDE.md                # Claude Code project instructions
└── .beads/                  # Local issue tracker
```

## Getting Started

### Earmark (Livewire 4)

```bash
cd Earmark
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run dev          # one terminal
php artisan serve    # another terminal
```

Open `http://localhost:8000`. First-time registration auto-creates a single household and attaches the new user as Owner.

### Earmark-web (Inertia + Svelte)

```bash
cd Earmark-web
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run dev          # one terminal
php artisan serve    # another terminal
```

### Earmark-parser (Python service, optional)

```bash
cd Earmark-parser
pip install -e .
uvicorn earmark_parser.app:app --reload --port 8001
```

The parser service is only required when running `Earmark-web` end-to-end. `Earmark/` parses PDFs in the browser via `@llamaindex/liteparse` — no Python service required.

### Full stack via Docker Compose

```bash
docker compose up
```

## Architecture Highlights

- **Money** is stored as integer cents (`bigInteger`). Never floats.
- **Single household** — both apps lock to one household per user; no multi-tenancy slug routing.
- **PDF statements are never persisted to disk** — parsed in-memory and discarded. Only the parsed transactions are kept.
- **Livewire stack**: thin full-page components under `resources/views/pages/` (`pages::` namespace, `⚡` SFC convention), Form objects own validation, Policies own authorization, domain services + Laravel Actions for logic, Alpine.js for ephemeral client state, Flux UI for primitives.
- **Inertia stack**: thin singular controllers + FormRequests, Svelte 5 runes (`$state`, `$derived`, `$effect`), Inertia owns the bridge.

## Documentation

- [`prd.md`](prd.md) — product requirements
- [`Earmark/docs/architecture-livewire.md`](Earmark/docs/architecture-livewire.md) — Livewire stack architecture
- [`Earmark-web/docs/plans/prd-v1-implementation-plan.md`](Earmark-web/docs/plans/prd-v1-implementation-plan.md) — implementation plan
- [`Earmark-web/docs/architecture-design.md`](Earmark-web/docs/architecture-design.md) — Inertia stack architecture

## Issue Tracking

This repo uses [beads](https://github.com/gastownhall/beads) (`bd`) for local issue tracking. From any directory inside the repo:

```bash
bd ready             # show issues ready to work
bd show <id>         # view issue details
bd stats             # database snapshot
```

## Status

Early in development. The Livewire reimplementation is being built feature-by-feature against the PRD, mirroring the Inertia version's scope. The schema, auth flow, household model, and Fortify wiring are the current focus.
