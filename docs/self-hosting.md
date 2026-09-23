# Self-hosting Earmark

Earmark is self-hosted and open source ([MIT](../LICENSE)). Your household's
financial data lives only on the server you run it on — there is no hosted
Earmark service, no telemetry, and no bank-sync provider in the core product.

## Install

```bash
git clone https://github.com/K2412/Earmark.git
cd Earmark
composer setup            # installs deps, copies .env, generates key, migrates, builds assets
php artisan earmark:create-first-user \
    --email=you@example.com --name="Your Name" --password='your-secret'
composer run dev
```

Open http://localhost:8000 and sign in. Registration is invite-only; later members
join from Household settings.

## Upgrade

```bash
git pull
composer install --no-dev
php artisan migrate            # forward-only, additive migrations
npm ci && npm run build
php artisan optimize:clear
```

Review the release notes before upgrading. Migrations are additive and safe to run
on a backed-up database.

## Health check

- `php artisan about` — framework, PHP, cache, and database status.
- `php artisan migrate:status` — confirms the schema is current.

## Backup

Two independent options, both without a proprietary service:

1. **Database backup** — copy the SQLite file (`database/database.sqlite`) or run
   `mysqldump` / `pg_dump` for other drivers. This is the full, byte-level backup.
2. **Portable export** — Household → Accounts → **Data & backup** → *Export backup*.
   Produces a versioned JSON manifest (`earmark-backup.json`) plus human-readable
   per-table rows. Secrets (users, auth) are never included.

## Restore

- **Database restore** — stop the app, replace the database file / import the dump,
  restart.
- **Portable restore** — Household → Accounts → **Data & backup** → *Restore backup*
  and upload a previously exported `earmark-backup.json`. This replaces the current
  household's financial data and reproduces its accounts, transactions, buckets,
  categories, positions, goals, and scenarios with fresh identifiers.

## Privacy and deletion

- No household financial data leaves your server by default.
- **Delete all data** — Household → Accounts → **Data & backup** → *Delete all
  financial data* (owners only) removes the household's accounts, transactions,
  budgets, positions, goals, and scenarios. Export first if you want a copy.
- To remove everything, delete the database and the application directory.

## Recovery

- Keep periodic database backups (a cron copying the SQLite file, or a scheduled
  dump) plus an occasional portable export.
- To recover: reinstall (see **Install**), restore the database backup, run
  `php artisan migrate` to apply any newer migrations, and rebuild assets.
