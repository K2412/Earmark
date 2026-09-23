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

## Privacy

Earmark is self-hosted. Your household's financial data stays on the server you
run it on. By default it is **not** sent to Earmark's maintainers or to any
third-party service — there is no telemetry, no hosted account, and no bank-sync
provider in the core product.

## Documentation

- [CONTEXT.md](CONTEXT.md) — household language
- [docs/architecture-svelte.md](docs/architecture-svelte.md) — Laravel, Inertia, and Svelte layering
- [docs/adr](docs/adr) — household-owned balance sheet; server-owned projection math
- [design-patterns/patterns.md](design-patterns/patterns.md) — interface patterns

## Contributing and security

- [CONTRIBUTING.md](CONTRIBUTING.md) — setup, workflow, and support boundaries
- [SECURITY.md](SECURITY.md) — how to report a vulnerability privately

## Licence

Earmark is open source under the [MIT Licence](LICENSE).
