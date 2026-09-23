# Contributing to Earmark

Thanks for your interest in Earmark. It is a self-hostable, Canada-first
household finance and wealth-planning app. Contributions are welcome under the
terms below.

## Licence

Earmark is released under the [MIT Licence](LICENSE). By contributing, you agree
that your contributions are licensed under the same terms. There is no separate
contributor licence agreement (CLA).

## Getting set up

See the [README](README.md) for the full setup. In short:

```bash
composer setup
php artisan earmark:create-first-user \
    --email=you@example.com \
    --name="Your Name" \
    --password='your-secret'
composer run dev
```

## Making a change

1. Fork and branch from `main`.
2. Keep changes focused; add or update tests for every behavioural change.
3. Run the checks below and make sure they pass.
4. Open a pull request describing the change and the reasoning.

```bash
vendor/bin/pint            # PHP formatting
php artisan test           # Pest feature + unit tests
npm run check              # Svelte / TypeScript checks
npm run build              # front-end build
```

Money is stored as integer minor units (cents), all authoritative financial
math lives on the server, and every read and write is scoped to a household.
Please preserve those invariants.

## Reporting security issues

Do **not** open a public issue for security problems. See [SECURITY.md](SECURITY.md)
for private disclosure.

## Support boundaries

Earmark is maintained on a best-effort, volunteer basis. To set expectations,
the maintainers do **not** promise:

- a service-level agreement, guaranteed response time, or scheduled releases;
- a hosted or managed version of Earmark — it is self-hosted software;
- financial, tax, legal, or investment advice — Earmark's outputs are planning
  estimates, not professional advice;
- backwards-compatibility for undocumented internals; or
- support for live bank/brokerage sync, which is deliberately out of scope.

Bug reports, feature discussion, and pull requests are still very welcome.
