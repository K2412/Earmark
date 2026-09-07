# Earmark

Household envelope budget and net worth for two adults. Money is stored as integer cents. Registration is invite-only.

## Stack

- Laravel 13 on PHP 8.4
- Inertia v3 and Svelte 5
- Tailwind CSS 4
- SQLite
- Pest 5, including browser tests

## Getting started

```bash
composer setup
php artisan earmark:create-first-user \
    --email=you@example.com \
    --name="Your Name" \
    --password='your-secret'
composer run dev
```

`composer setup` installs PHP and Node dependencies, copies `.env`, generates the app key, runs migrations, and builds front-end assets.

Open [http://localhost:8000](http://localhost:8000) and sign in. Later members join from Household settings via an invite URL (`/register?invite=<code>`).

## Tests

```bash
php artisan test
```

Browser tests need built Vite assets and Playwright Chromium:

```bash
npm run build
npx playwright install chromium
php artisan test tests/Browser/Household
```

## Documentation

- [CONTEXT.md](CONTEXT.md) — household language
- [docs/architecture-svelte.md](docs/architecture-svelte.md) — Laravel, Inertia, and Svelte layering
- [docs/adr](docs/adr) — household-owned balance sheet; server-owned projection math
- [design-patterns/patterns.md](design-patterns/patterns.md) — interface patterns
