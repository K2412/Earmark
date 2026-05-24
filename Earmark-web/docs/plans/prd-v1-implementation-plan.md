# PRD v1.0 Implementation Plan — Household Budget App

Status: Draft plan for review  
Plan file: `Earmark-web/docs/plans/prd-v1-implementation-plan.md`  
Primary PRD source: `../prd.md` from the `Earmark-web` project root  
Architecture source: `docs/architecture-design-patterns.md`  
Execution rule: do not deploy; prepare deploy artifacts only  
Testing rule: use vertical TDD cycles; no horizontal “all tests first” pass  
Doc-only verification for this plan: read the written Markdown file; no app test run required

---

## 1. Locked Decisions From Preflight

1. This is a single comprehensive implementation roadmap plus Beads-style task breakdown.
2. The target file is `docs/plans/prd-v1-implementation-plan.md`.
3. v1.0 remains focused on PRD MVP scope.
4. v1.1+ items are explicitly deferred into a parking lot.
5. Hosting target is self-hosted Docker Compose on a home/server box.
6. v1.0 has no automated SQLite backup work.
7. Initial reporting categories use an optional default seed; bucket seeding must always create the protected `Unassigned Funds` system bucket.
8. Currency and display locale are locked to CAD cents and `en-CA` formatting.
9. v1.0 invites are copy-paste invite URLs, with no transactional email.
10. Docker deployment should require `tmpfs` for PHP upload temp files.
11. Existing Teams scaffolding should be fully removed before finance features.
12. The replacement domain model is the PRD household model, not Teams.
13. Authentication target is Fortify email/password, self-registration disabled, first-user command, invite links.
14. Finance routes live under `/household`.
15. Root redirects authenticated users to `/household/dashboard`.
16. Finance tables do not include `household_id` in v1.0.
17. The first release-level tracer bullet is: user can sign in, create an account/category/bucket, and enter a categorized + bucket-attributed transaction.
18. Milestone order follows the PRD build order with early risk spikes for BudgetService math and parser API contract.
19. Beads are represented as plain Markdown backlog tables.
20. No human approval gates are required except dependency-approval checkpoints.
21. Do not deploy.
22. Prepare Docker Compose artifacts but stop before deployment.
23. The Python parser service lives inside `Earmark-web/parser/`.
24. Installed package versions are preferred over PRD version text; call out drift.
25. The plan is intentionally detailed and self-contained.
26. Include schema implementation checkpoints.
27. Playwright is deferred to v1.1 parking lot.
28. v1.0 verification uses Pest for PHP behavior plus frontend static checks where frontend changes exist.
29. For doc-only plan creation, no app test run is required.
30. Every implementation milestone must re-read `docs/architecture-design-patterns.md` before work starts.
31. Local starter data can be reset during Team removal.
32. Adding `lorisleiva/laravel-actions` requires a dependency-approval checkpoint.
33. Categories are for reporting/classification; buckets/goals are independent envelope-budget containers.
34. Imported/manual transaction rows can be tagged with both a reporting category and a budget bucket/goal.
35. Unsplit transactions store category/bucket attribution directly on `transactions`; split transactions use child split rows with their own category/bucket attribution.
36. A transaction may be split by user action into multiple child rows; each child row has exactly one category and one bucket.
37. There is a built-in explicit system bucket named `Unassigned Funds`.
38. Income/positive inflows default into `Unassigned Funds`; users then assign/move money to buckets/goals.
39. Expenses can post even if the chosen bucket goes negative; the UI must clearly flag negative/needs-attention buckets.
40. Buckets/goals all have an end date concept; open-ended buckets use a far-future date such as `9999-12-31`.
41. Every active bucket has a monthly obligation amount.
42. Missed monthly obligations roll forward until funded.
43. Extra funding can prepay future obligations and/or increase spendable bucket available.
44. Editing a bucket obligation amount applies to the current and future months only; historical months keep their prior obligation amount.
45. Archiving a bucket stops future obligations and keeps history visible.
46. Archiving a bucket with a positive balance sweeps the surplus to `Unassigned Funds`.
47. A bucket with a negative balance cannot be archived until the deficit is covered.
48. The current category-only PRD budget model is superseded by this category + bucket/goal model for v1.0 planning.

---

## 2. Source-of-Truth Hierarchy

When sources disagree, use this order:

1. `docs/architecture-design-patterns.md`
   - Thin singular controllers.
   - `FormRequest` validation and authorization.
   - Domain services for reusable logic.
   - Laravel Actions for bespoke, multi-stage, reusable operations after dependency approval.
   - Inertia pages with server-provided props.
   - Svelte 5 runes and typed `$props()`.
   - State classes in `.svelte.ts` for complex client state.
   - No module-level state singletons.
2. Installed project dependencies in `composer.json`, `composer.lock`, `package.json`, and lock files.
3. `../prd.md` product requirements.
4. Existing app conventions after Teams scaffolding is removed.

Version drift to record in implementation notes:

- The PRD references Laravel latest stable/PHP 8.3+, while the app currently targets Laravel 13 and PHP `^8.3`.
- The PRD references Inertia/Svelte starter language, while the installed app has Fortify, Passkeys, Wayfinder, and Inertia/Svelte package versions already present.
- `lorisleiva/laravel-actions` is required by architecture/PRD patterns but is not currently installed; dependency approval is required before using package-specific `AsAction` behavior.
- Playwright is in the PRD DoD but deferred from v1.0 by decision.

---

## 3. Global Architecture Constraints

Every milestone must begin by re-reading `docs/architecture-design-patterns.md` and applying these constraints:

### Backend

1. Controllers are singular and thin.
2. Controller methods should delegate to services/actions and return an Inertia response or redirect.
3. Parameter order is:
   1. `FormRequest`
   2. route-bound models
   3. action/service
4. Every non-trivial write endpoint has a dedicated `FormRequest`.
5. Authorization lives in `FormRequest::authorize()`.
6. Validation lives in `FormRequest::rules()` and `after()` when needed.
7. Business logic lives in domain services under `app/Services/{Domain}`.
8. Bespoke multi-stage operations use action classes after Laravel Actions dependency approval.
9. Avoid raw DB access unless a query cannot be expressed cleanly through Eloquent/query builder.
10. Prevent N+1 queries with eager loading.
11. Money is always integer cents.
12. Transaction dates are `date` columns, not datetimes.
13. Prefer ULIDs for domain models.

### Inertia Bridge

1. Laravel owns routing.
2. Inertia renders Svelte pages with server props.
3. Do not create a REST API unless explicitly required by PRD behavior.
4. For frontend route references, use Wayfinder route/action functions instead of hardcoded URLs when implementation reaches Svelte route wiring.
5. Shared server data flows through `HandleInertiaRequests`.

### Frontend

1. Use Svelte 5 runes.
2. Use typed `$props()`.
3. Use Inertia forms/navigation, not standalone fetch/axios for normal app flows.
4. Complex UI state belongs in `.svelte.ts` classes with an interface first.
5. Never destructure state classes.
6. Never export module-level state instances.
7. Use Svelte context for shared client state.
8. Use the project’s existing UI primitives consistently.
9. Money formatting goes through a shared `Money` component/helper, never inline `.toFixed()`.

---

## 4. v1.0 Release Boundary

### Included in v1.0

1. Team removal and household-only foundation.
2. Fortify email/password auth with disabled public registration.
3. First-user creation command.
4. Copy-paste invite links for the second household user.
5. Core database schema.
6. Accounts CRUD.
7. Categories CRUD for reporting/classification.
8. Buckets/goals CRUD for envelope budgeting.
9. Built-in `Unassigned Funds` system bucket.
10. Transaction CRUD with category + bucket attribution.
11. User-triggered transaction splitting into child lines, each with its own category + bucket.
12. Transfers between accounts.
13. Budget plan page, bucket obligation schedule, assignment/move endpoints, and underfunding signals.
14. Bucket available math with carryover, rolled-forward obligations, and negative-bucket visibility.
15. Payee rules and suggestion endpoint.
16. Reconciliation matched-only flow.
17. Python parser service skeleton and parser API contract.
18. PDF upload, parse, stage, review, split, attribute, and promote flow.
19. Dashboard with fixed layout, including negative/underfunded buckets.
20. Docker Compose artifacts for app + parser with tmpfs upload temp config.
21. Pest behavior coverage for backend flows.
22. Frontend static checks for Svelte/TypeScript changes.

### Excluded from v1.0

1. Production deployment.
2. Automated SQLite backups.
3. Playwright project dependency and Playwright test suite.
4. Transactional email for invites.
5. Dashboard customization.
6. Goal progress dashboard card.
7. Discrepancy-accepted reconciliation path.
8. Bank-specific PDF parsers.
9. CSV import.
10. Scheduled stale-upload cleanup command.
11. Category history charts.
12. Year-over-year comparison.
13. Recurring transaction templates.
14. Multi-household/multi-tenant support.
15. Multi-currency support.
16. Investment/net-worth tracking.

---

## 4A. Category + Bucket/Goal Domain Model

This section supersedes the PRD's earlier category-only budget model.

### Reporting categories

Categories classify what the transaction was for:

- `Groceries`
- `Home Repair`
- `Restaurant`
- `Income`
- `Fees`
- `Medical`

Categories power spending reports and search/filtering. They do not hold money.

### Buckets/goals

Buckets/goals are envelopes of money:

- `Unassigned Funds`
- `Car Maintenance`
- `New Roof`
- `New Oven`
- `Fun Money`
- `Household Items`

Buckets hold available balances, monthly obligations, target amounts, target dates, and underfunding state.

### Transaction attribution

Every effective transaction line can have:

1. a reporting category, and
2. a bucket/goal attribution.

Examples:

- Paycheque: category `Income`, bucket `Unassigned Funds`, amount `+300000` cents.
- Mechanic payment: category `Auto Repair`, bucket `Car Maintenance`, amount `-45000` cents.
- Oven purchase: category `Appliance`, bucket `New Oven`, amount `-120000` cents.
- Amazon mixed purchase: parent transaction `-4000` cents split into:
  - `-2000` category `Household`, bucket `Household Items`
  - `-2000` category `Personal`, bucket `Fun Money`

### Split rule

The system keeps the imported/bank transaction as the reconciliation parent. Splits are child rows. Each child has exactly one category and one bucket. The child amounts must sum to the parent amount.

### Obligation rule

Every active bucket has a monthly obligation amount and a target date. Buckets without a meaningful end date use a far-future date such as `9999-12-31`.

- Missed monthly obligations roll forward.
- Extra funding can prepay future obligations.
- Editing obligation parameters applies to the current and future months only.
- Historical obligation expectations are not recalculated.

### Archive rule

Archive is the chosen term for disabling a bucket/goal.

- Archived buckets stop future obligations.
- Archived buckets remain visible in history.
- Positive available balance is swept to `Unassigned Funds` during archive.
- Negative buckets cannot be archived until the deficit is covered.

### Negative bucket rule

Expenses are real and can post even when they make a bucket negative. The system must clearly flag negative buckets instead of blocking the transaction or hiding the issue.

---

## 5. Target Route Shape

All finance routes live under `/household` and require auth.

Auth/settings routes can remain outside `/household` where appropriate.

Target route concepts:

```php
Route::get('/', HomeRedirectController::class)->name('home');

Route::middleware(['auth'])->prefix('household')->name('household.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/plan/{yearMonth?}', [PlanController::class, 'show'])
        ->where('yearMonth', '\\d{4}-\\d{2}')
        ->name('plan.show');
    Route::patch('/plan/assignment', [PlanController::class, 'updateAssignment'])
        ->name('plan.assignment.update');
    Route::post('/plan/move', [PlanController::class, 'moveMoney'])
        ->name('plan.move');
    Route::post('/plan/obligations/recalculate', [PlanController::class, 'recalculateObligations'])
        ->name('plan.obligations.recalculate');

    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class); // reporting taxonomy
    Route::resource('buckets', BucketController::class); // envelope goals/buckets
    Route::resource('transactions', TransactionController::class);
    Route::resource('rules', PayeeRuleController::class);

    Route::post('/transactions/{transaction}/split', [TransactionSplitController::class, 'store'])
        ->name('transactions.split.store');
    Route::put('/transactions/{transaction}/split', [TransactionSplitController::class, 'update'])
        ->name('transactions.split.update');
    Route::delete('/transactions/{transaction}/split', [TransactionSplitController::class, 'destroy'])
        ->name('transactions.split.destroy');

    Route::post('/transactions/transfer', [TransferController::class, 'store'])
        ->name('transfers.store');
    Route::get('/payee-rules/suggest', [PayeeRuleController::class, 'suggest'])
        ->name('rules.suggest');

    Route::get('/reconcile', [ReconcileController::class, 'index'])->name('reconcile.index');
    Route::get('/reconcile/{account}', [ReconcileController::class, 'show'])->name('reconcile.show');
    Route::post('/reconcile/{account}', [ReconcileController::class, 'store'])->name('reconcile.store');
    Route::post('/reconcile/{reconciliation}/unlock', [ReconcileController::class, 'unlock'])->name('reconcile.unlock');

    Route::get('/import', [StatementImportController::class, 'index'])->name('import.index');
    Route::get('/import/new', [StatementImportController::class, 'create'])->name('import.create');
    Route::post('/import', [StatementImportController::class, 'store'])->name('import.store');
    Route::get('/import/{statementUpload}/review', [StatementImportController::class, 'review'])->name('import.review');
    Route::post('/import/{statementUpload}/promote', [StatementImportController::class, 'promote'])->name('import.promote');
    Route::delete('/import/{statementUpload}', [StatementImportController::class, 'destroy'])->name('import.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/invites', [InviteController::class, 'store'])->name('invites.store');
});

Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.show');
Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])->name('invites.accept');
```

Implementation may adjust names to match Wayfinder and existing conventions, but the `/household` prefix is intentional.

---

## 6. Database Schema Checkpoints

All domain tables use ULIDs unless the table is inherited from Laravel auth defaults.

### 6.1 users

Use Laravel/Fortify user table with project-required fields.

Implementation checkpoints:

- Preserve email/password login.
- Disable public registration routes/UI.
- Add audit compatibility for `created_by_user_id`, `updated_by_user_id`, `uploaded_by_user_id`, and related columns.
- Ensure first-user command can create the initial account.

### 6.2 accounts

Fields:

- `id` ULID primary key
- `name` string
- `type` enum: `chequing`, `savings`, `credit_card`, `cash`, `investment`, `other`
- `starting_balance` big integer cents, default `0`
- `starting_balance_date` date
- `archived` boolean default `false`
- `sort_order` unsigned integer default `0`
- timestamps

Checkpoints:

- Indexes sufficient for account lists and reconciliation.
- Factory supports common account types.
- Archived accounts are excluded from default selectors unless explicitly needed.

### 6.3 categories

Categories are the reporting/classification taxonomy. They answer “what kind of thing was bought or earned?” They do **not** own envelope balances.

Fields:

- `id` ULID primary key
- `name` unique string
- `type` enum: `income`, `housing`, `transportation`, `food`, `household`, `personal`, `health`, `debt`, `savings`, `fees`, `other`; exact enum can be refined during implementation
- `sort_order` unsigned integer default `0`
- `archived` boolean default `false`
- `notes` nullable string
- timestamps

Checkpoints:

- Optional default category seeder.
- Income categories classify positive inflows for reporting only.
- Archived categories are hidden from default selectors.
- Category reports can answer “how much did we spend on groceries/home repair/etc.” independently from bucket funding.

### 6.4 buckets

Buckets/goals are the envelope-budget containers. They answer “which pot of money funded this?” Examples: `Unassigned Funds`, `Car Maintenance`, `New Roof`, `Fun Money`, `New Oven`.

Fields:

- `id` ULID primary key
- `name` unique string
- `kind` enum: `system`, `goal`, `ongoing`; implementation may collapse `goal`/`ongoing` if the UI only needs labels
- `monthly_obligation` big integer cents default `0`
- `target_amount` nullable big integer cents
- `target_date` date, required; open-ended buckets use `9999-12-31`
- `archived` boolean default `false`
- `archived_at` nullable timestamp
- `sort_order` unsigned integer default `0`
- `notes` nullable string
- timestamps

System bucket checkpoint:

- A built-in `Unassigned Funds` bucket exists.
- It cannot be deleted.
- It should not be archived.
- Positive income defaults into this bucket.
- Swept surplus from archived buckets returns to this bucket.

Goal/obligation checkpoints:

- Every active bucket has a monthly obligation amount, even if that amount is `0`.
- All buckets have a target date. Open-ended buckets use a far-future date.
- Missed monthly obligations roll forward until funded.
- Extra funding can prepay future obligations and/or increase bucket available.
- After a bucket's target date passes, the UI must signal underfunding if obligations remain unmet.
- Archived buckets stop future obligations but remain visible in history.
- A bucket with positive available balance can be archived only by sweeping the surplus to `Unassigned Funds`.
- A bucket with negative available balance cannot be archived until covered.

### 6.5 bucket_obligation_versions

Bucket obligation edits are effective for the current and future months only. Historical months keep the obligation amount that was active then.

Fields:

- `id` ULID primary key
- `bucket_id` foreign ULID, cascade delete
- `monthly_obligation` big integer cents
- `target_amount` nullable big integer cents
- `target_date` date
- `effective_year` unsigned small integer
- `effective_month` unsigned tiny integer, 1–12
- `created_by_user_id` foreign key to users
- timestamps
- index: `bucket_id`, `effective_year`, `effective_month`

Checkpoints:

- Editing a bucket in May changes May and future months.
- January–April keep the previous obligation parameters.
- BudgetService can determine the applicable obligation version for a bucket/month.
- This table prevents recalculating all historical rollover when a user changes a bucket later.

### 6.6 bucket_assignments

Assignments move money from `Unassigned Funds` or another bucket into a bucket for a given month. They represent the user intentionally funding envelopes.

Fields:

- `id` ULID primary key
- `from_bucket_id` nullable foreign ULID; null only for system/bootstrap adjustments
- `to_bucket_id` foreign ULID
- `year` unsigned small integer
- `month` unsigned tiny integer, 1–12
- `amount` big integer cents, positive
- `memo` nullable string
- `created_by_user_id` foreign key to users
- timestamps
- index: `from_bucket_id`, `year`, `month`
- index: `to_bucket_id`, `year`, `month`

Checkpoints:

- Income initially increases `Unassigned Funds` through positive transaction attribution.
- User assignment from Unassigned to a bucket creates an auditable movement.
- Moving money between buckets is represented by a single movement row or equivalent atomic pair.
- Assignments can fund current or future months.
- Extra assignment can prepay future obligations.

### 6.7 transactions

Fields:

- `id` ULID primary key
- `date` date
- `account_id` foreign ULID
- `payee` string
- `category_id` nullable foreign ULID for unsplit transactions
- `bucket_id` nullable foreign ULID for unsplit transactions; defaults to `Unassigned Funds` for income when omitted
- `amount` signed big integer cents
- `memo` nullable string
- `is_split` boolean default `false`
- `cleared` boolean default `false`
- `reconciled` boolean default `false`
- `transfer_pair_id` nullable ULID indexed
- `source` enum: `manual`, `imported_pdf`, `imported_csv`; v1.0 uses manual/imported_pdf
- `import_batch_id` nullable ULID indexed
- `created_by_user_id` foreign key to users
- timestamps
- index: `account_id`, `date`
- index: `category_id`, `date`
- index: `bucket_id`, `date`

Checkpoints:

- Outflows are negative.
- Inflows are positive.
- Positive inflows default to `Unassigned Funds` unless explicitly attributed otherwise by a future workflow.
- Negative expenses can make a bucket negative; they still post, and the bucket is flagged as needing attention.
- Unsplit transactions keep reporting category and bucket directly on the transaction row.
- Split transactions set `is_split = true`; their effective reporting/bucket attribution comes from child split rows.
- The sum of split child amounts must equal the parent transaction amount.
- Transfers have `category_id = null`, `bucket_id = null`, and do not affect bucket available.
- Reconciled transactions are protected from accidental edits.
- Imported transactions link to statement upload through `import_batch_id`.

### 6.8 transaction_splits

Fields:

- `id` ULID primary key
- `transaction_id` foreign ULID, cascade delete
- `category_id` nullable foreign ULID
- `bucket_id` foreign ULID
- `amount` signed big integer cents
- `memo` nullable string
- timestamps
- index: `transaction_id`
- index: `category_id`
- index: `bucket_id`

Checkpoints:

- Split rows are only used when the user marks a transaction as split.
- Each split row has exactly one bucket.
- Split row signs follow the parent transaction sign.
- Example: `$40 Amazon` can split into `$20 Household Items` bucket and `$20 Fun Money` bucket.
- Reconciliation remains tied to the parent bank transaction, not the child splits.
- Budget/report calculations read an “effective transaction lines” abstraction: unsplit transactions as one line, split transactions as their child lines.

### 6.9 payee_rules

Fields:

- `id` ULID primary key
- `pattern` string
- `category_id` nullable foreign ULID
- `bucket_id` nullable foreign ULID
- `priority` unsigned integer default `100`
- `auto_apply` boolean default `true`
- timestamps

Checkpoints:

- Rules are evaluated by priority.
- Suggestion endpoint returns auto-apply category and/or bucket suggestion chip data.
- A rule can suggest only a reporting category, only a bucket, or both.
- Existing rules are never silently changed by manual category/bucket selection.

### 6.10 reconciliations

Fields:

- `id` ULID primary key
- `account_id` foreign ULID
- `statement_date` date
- `statement_balance` big integer cents
- `calculated_balance` big integer cents
- `status` enum: v1.0 uses `matched`; `discrepancy_accepted` parked for v1.1
- `discrepancy_amount` big integer cents default `0`
- `notes` nullable string
- `reconciled_by_user_id` foreign key to users
- `reconciled_at` timestamp
- timestamps

Checkpoints:

- Matched reconciliation marks cleared transactions through statement date as reconciled.
- Unmatched reconciliation shows discrepancy but cannot be accepted in v1.0.
- Unlock requires explicit UI confirmation.

### 6.11 statement_uploads

Fields:

- `id` ULID primary key
- `account_id` foreign ULID
- `original_filename` string
- `file_sha256` string length 64 unique
- `file_size_bytes` unsigned big integer
- `status` enum: `parsing`, `parsed`, `failed`, `imported`
- `parsed_transaction_count` unsigned integer default `0`
- `imported_transaction_count` unsigned integer default `0`
- `error_message` nullable text
- `uploaded_by_user_id` foreign key to users
- `uploaded_at` timestamp
- timestamps

Checkpoints:

- No PDF bytes are stored.
- Duplicate hash blocks re-import.
- Parser failures are visible to the user.
- `parsed` uploads remain reviewable.

### 6.12 staged_transactions

Fields:

- `id` ULID primary key
- `statement_upload_id` foreign ULID, cascade delete
- `date` date
- `payee` string
- `raw_payee` string
- `amount` signed big integer cents
- `suggested_category_id` nullable foreign ULID
- `suggested_bucket_id` nullable foreign ULID
- `final_category_id` nullable foreign ULID
- `final_bucket_id` nullable foreign ULID
- `accept` boolean default `true`
- `is_split` boolean default `false`
- `transaction_id` nullable foreign ULID
- timestamps

Checkpoints:

- Possible duplicates default to `accept = false`.
- Suggested category and bucket are prefilled when auto-apply rules match.
- Review UI can split a staged row before promotion.
- Split staged rows must sum to the parent staged transaction amount.
- Promotion is idempotent and does not double-create transactions.

### 6.13 staged_transaction_splits

Fields:

- `id` ULID primary key
- `staged_transaction_id` foreign ULID, cascade delete
- `category_id` nullable foreign ULID
- `bucket_id` foreign ULID
- `amount` signed big integer cents
- `memo` nullable string
- timestamps

Checkpoints:

- Used only when an imported staged row is split during review.
- Promoting a split staged row creates one parent transaction and matching `transaction_splits`.
- The parent transaction remains the reconciliation anchor for the imported bank row.

### 6.14 invites

Fields:

- `id` ULID primary key
- `email` string
- `token` string length 64 unique
- `expires_at` timestamp
- `accepted_at` nullable timestamp
- `invited_by_user_id` foreign key to users
- timestamps

Checkpoints:

- Invite links are copy-paste only.
- Token expiry is enforced.
- Accepted invites cannot be reused.
- Accepted user becomes a full household member.

---

## 7. Target Service and Action Layout

Re-read `docs/architecture-design-patterns.md` before creating any of these.

### Services

```text
app/Services/
  Account/AccountService.php
  Category/CategoryService.php
  Bucket/BucketService.php
  Budget/BudgetService.php
  Transaction/TransactionService.php
  Transaction/EffectiveTransactionLineService.php
  PayeeRule/PayeeRuleService.php
  Reconciliation/ReconciliationService.php
  StatementImport/StatementImportService.php
  Household/HouseholdService.php
```

Service responsibilities:

- `AccountService`: account lists, balances, options, archive behavior.
- `CategoryService`: reporting category groups, options, archived behavior, optional default seed support.
- `BucketService`: bucket/goal options, obligation versions, archive/sweep rules, `Unassigned Funds` invariants.
- `BudgetService`: bucket obligations, assigned/funded amounts, spending, available, carryover, underfunding, monthly plan payloads.
- `TransactionService`: transaction lists, filters, account/category/bucket options, split state, edit guards.
- `EffectiveTransactionLineService`: query abstraction that exposes unsplit transactions and split child rows as one effective line stream for budget/report calculations.
- `PayeeRuleService`: category/bucket rule matching, priority handling, suggestion data.
- `ReconciliationService`: calculated balance, discrepancy display, ledger through statement date.
- `StatementImportService`: review payload, duplicate detection helpers, staged row transforms.
- `HouseholdService`: first-user/household access assumptions without a household table.

### Actions after dependency approval

`lorisleiva/laravel-actions` must be approved before package-specific usage.

Target action concepts:

```text
app/Actions/
  Household/
    GenerateInviteLink.php
    AcceptInvite.php
  Transaction/
    CreateTransaction.php
    TransferBetweenAccounts.php
    SplitTransaction.php
    UnsplitTransaction.php
  Budget/
    AssignToBucket.php
    MoveMoneyBetweenBuckets.php
    ArchiveBucket.php
    RecalculateBucketObligations.php
  Reconciliation/
    ReconcileAccount.php
  Statement/
    UploadAndStageStatement.php
    PromoteStagedTransactions.php
    Pipeline/
      StatementImportContext.php
      HashAndCheckDuplicate.php
      RecordStatementUpload.php
      ForwardToParser.php
      StageParsedTransactions.php
      ApplyPayeeRuleSuggestions.php
```

If dependency approval is not granted, implement these as plain invokable application classes with the same public interfaces and defer package traits.

---

## 8. Vertical TDD Strategy

Do not write all tests first.

Each milestone uses this loop:

1. Pick one observable behavior.
2. Write one failing Pest test through a public interface.
3. Implement the minimum code to pass.
4. Run the targeted test with `php artisan test --compact --filter={name} --no-interaction` where applicable.
5. Repeat for the next behavior.
6. Refactor only when green.
7. Run `vendor/bin/pint --dirty --format agent` after PHP changes.
8. Run frontend static checks for frontend changes:
   - `npm run types:check`
   - `npm run lint:check`
   - `npm run build` near release/milestone completion or when Vite integration changes

Test philosophy:

- Test behavior, not private implementation.
- Use feature tests for web/domain flows.
- Use factories instead of hand-built database rows where possible.
- Mock only external boundaries, especially the parser HTTP service.
- Avoid testing internal service method structure unless the service is itself a stable public boundary for complex domain behavior.

Release-level tracer bullet:

> User can sign in, create an account, create a reporting category, create a bucket, and enter a categorized + bucket-attributed transaction.

This proves auth, household access, schema, routes, controllers, validation, services/actions, Inertia redirects, category classification, bucket attribution, and money sign handling.

---

## 9. Milestone Roadmap

### Milestone 0 — Foundation Cleanup: Remove Teams and Establish Household Auth

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Replace starter Team concepts with PRD household-only access.
- Reset local starter data if needed.
- Keep Fortify email/password auth.
- Disable public registration.
- Add first-user command.
- Prepare copy-paste invite foundation.

Candidate behavior tests:

1. Guest cannot access `/household/dashboard`.
2. Authenticated user can access `/household/dashboard` without a team URL segment.
3. Public registration is unavailable.
4. First-user command creates an initial household user.

Implementation notes:

- Remove Team URL prefix and `EnsureTeamMembership` middleware from finance route flow.
- Remove Team pages/components/routes after checking references.
- Remove Team models, policies, notifications, rules, support classes, migrations, and shared Inertia props where no longer needed.
- Keep auth/profile/security features only as compatible with the chosen Fortify scope.
- Define a single `is-household-member` authorization gate equivalent to authenticated user for v1.0.
- Add or adjust `/household/dashboard` route.
- Root route redirects authenticated users to `/household/dashboard`.
- Ensure Wayfinder generation remains valid after routes change.

Definition of done:

- No finance route requires `current_team`.
- No Team UI appears in navigation.
- Tests prove household auth access.
- Existing starter data reset is acceptable.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| FND-1 Remove Team route/middleware dependency | Task | None | `/household/dashboard` works for authenticated users without `current_team` | Pest feature test | Re-read architecture doc first |
| FND-2 Remove Team UI and shared props | Task | FND-1 | Navigation no longer exposes Teams | Frontend static checks | Avoid module-level state |
| FND-3 Disable public registration | Task | FND-1 | Register page/route unavailable | Pest feature test | Fortify/auth skill required during implementation |
| FND-4 Add first-user command | Task | FND-3 | Command creates initial user once | Pest command test | Use `--no-interaction` for artisan commands |
| FND-5 Add household-member gate | Task | FND-1 | FormRequests can authorize via gate | Pest feature test | v1 gate is authenticated user |

---

### Milestone 1 — Dependency and Architecture Baseline

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Resolve dependency checkpoint for Laravel Actions.
- Establish generated-code/tooling conventions before domain work.
- Capture no-Playwright v1.0 decision.

Dependency checkpoint:

- Ask for explicit approval before adding `lorisleiva/laravel-actions`.
- If approved, install and use `AsAction` for target actions.
- If not approved, use plain invokable classes with stable `handle()` methods.

Candidate behavior tests:

1. Application boots after Team removal.
2. Household dashboard route returns an Inertia response for authenticated users.
3. Wayfinder route generation/static imports remain coherent after route changes.

Implementation notes:

- Do not add Playwright in v1.0.
- Keep Pest as automated behavior test runner.
- Use npm static checks for frontend changes.
- Prefer installed package versions over PRD version text.

Definition of done:

- Dependency decision is recorded.
- Codebase has a stable architecture baseline.
- No deployment occurs.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| ARC-1 Resolve Laravel Actions dependency | Approval checkpoint | FND milestone | Approved install or documented plain-class fallback | Composer state / note | Only dependency approval gate |
| ARC-2 Confirm route/tooling baseline | Task | FND milestone | App boots, route list is coherent | Targeted Pest + route inspection | No deploy |
| ARC-3 Record v1.0 test policy | Task | None | Pest + static checks documented in plan/README if needed | Review | Playwright parking lot |

---

### Milestone 2 — Core Schema, Models, Factories, and Seeders

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Create full v1.0 domain schema from PRD.
- Add Eloquent models with ULIDs.
- Add factories.
- Add optional default category seeder.

Candidate behavior tests:

1. Migrations create accounts, categories, buckets, bucket obligation versions, bucket assignments, transactions, transaction splits, rules, reconciliations, statement uploads, staged transactions, staged splits, and invites.
2. Factories can create valid account/category/bucket/transaction records.
3. Optional category and bucket seeders create defaults, including `Unassigned Funds`, without duplicates.
4. Money values persist as integer cents.
5. Effective transaction line queries can represent unsplit and split transactions consistently.

Implementation notes:

- Use artisan generators where possible.
- Keep all money columns as `bigInteger`.
- Keep all transaction dates as `date`.
- Add indexes required by `BudgetService`, effective transaction line calculations, and reconciliation.
- Avoid `household_id`.
- Include audit foreign keys to users.
- Handle enum choices consistently, preferably with PHP enums if the codebase pattern supports it.

Definition of done:

- Fresh migrate succeeds.
- Factories support future tests.
- Schema supports PRD workflows.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| SCH-1 Create finance migrations | Task | FND milestone | Tables and indexes match schema checkpoints | Migration test | No household_id |
| SCH-2 Add Eloquent models | Task | SCH-1 | Models use ULIDs and relationships | Pest model/factory test | Relationship return types |
| SCH-3 Add factories | Task | SCH-2 | Factories create valid records | Pest factory test | Use fake data conventions |
| SCH-4 Add optional category and bucket seeders | Task | SCH-2 | Seeders are idempotent and create `Unassigned Funds` | Pest/seed test | Optional default categories plus required system bucket |
| SCH-5 Add transaction split schema/factories | Task | SCH-2 | Split rows can be created and sum to parent amount | Pest factory/model test | Reconciliation stays on parent transaction |
| SCH-6 Remove obsolete Team migrations safely | Task | FND milestone | Fresh local DB no longer needs Teams | Fresh migration | Local data reset accepted |

---

### Milestone 3 — Accounts, Reporting Categories, and Buckets CRUD

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Build first end-to-end finance CRUD slices.
- Establish controller/FormRequest/service/Svelte page patterns.
- Support the release-level tracer bullet setup.

Candidate behavior tests:

1. Household member can create an account with a starting balance.
2. Household member can create a reporting category.
3. Household member can create a bucket with monthly obligation, target amount, and target date.
4. Editing a bucket applies obligation changes to the current and future months only.
5. Archiving a positive bucket sweeps surplus to `Unassigned Funds`.
6. Archiving a negative bucket is blocked until covered.
7. Archived accounts/categories/buckets disappear from default selectors.
8. Invalid money/category/bucket data returns validation errors.

Implementation notes:

- Controllers: `AccountController`, `CategoryController`, `BucketController`.
- Requests: `StoreAccountRequest`, `UpdateAccountRequest`, `StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreBucketRequest`, `UpdateBucketRequest`, `ArchiveBucketRequest`.
- Services: `AccountService`, `CategoryService`, `BucketService`.
- Pages under `resources/js/pages/household/accounts` or another convention chosen during implementation, but route names stay under `/household`.
- Use existing UI primitives.
- Add `MoneyInput` and `Money` component/helper early if needed.
- Use Wayfinder for frontend route references.

Definition of done:

- Account/category/bucket creation works through public web interface.
- `Unassigned Funds` system bucket exists and is protected.
- Bucket obligation versioning and archive rules are covered.
- Validation and authorization are covered by FormRequests.
- Frontend compiles.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| CRUD-1 Account CRUD backend | Task | SCH milestone | Store/update/list/archive accounts | Pest feature tests | Thin singular controller |
| CRUD-2 Category CRUD backend | Task | SCH milestone | Store/update/list/archive reporting categories | Pest feature tests | Reporting taxonomy only |
| CRUD-3 Bucket CRUD backend | Task | SCH milestone | Store/update/archive buckets and protect `Unassigned Funds` | Pest feature tests | Envelope goals/buckets |
| CRUD-4 Bucket obligation versioning | Task | CRUD-3 | Current/future edits do not rewrite history | Pest feature tests | Critical domain rule |
| CRUD-5 Bucket archive/sweep rules | Task | CRUD-3 | Positive surplus sweeps; negative archive blocked | Pest feature tests | Uses Unassigned Funds |
| CRUD-6 Money display/input primitives | Task | CRUD-1 | Cents parse/format consistently | Unit/Pest or TS static checks | CAD en-CA only |
| CRUD-7 Account/category/bucket Svelte pages | Task | CRUD-1, CRUD-2, CRUD-3 | Pages render and submit forms | Static checks | Svelte 5 runes |
| CRUD-8 Selector option services | Task | CRUD-1, CRUD-2, CRUD-3 | Archived hidden by default | Pest feature/service test | Used by later milestones |

---

### Milestone 4 — Transactions CRUD and Release Tracer Bullet

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Complete the first release-level tracer bullet.
- Add manual categorized transaction entry.
- Establish transaction sign conventions.

Tracer behavior:

> User can sign in, create an account, create a reporting category, create a bucket, and enter a categorized + bucket-attributed transaction.

Candidate behavior tests:

1. Household member can create a categorized manual outflow transaction attributed to a bucket.
2. Household member can create income as a positive transaction that defaults to `Unassigned Funds`.
3. Transaction validation rejects missing date/account/payee/amount.
4. User can mark a transaction as split and assign child amounts to different categories/buckets.
5. Split child amounts must sum to the parent transaction amount.
6. Negative expenses can make a bucket negative and still post.
7. Reconciled transactions cannot be edited without unlock behavior later.

Implementation notes:

- Controller: `TransactionController`.
- Requests: `StoreTransactionRequest`, `UpdateTransactionRequest`, maybe `DeleteTransactionRequest` if authorization is non-trivial.
- Service: `TransactionService` plus `EffectiveTransactionLineService`.
- Action/class: `CreateTransaction`, `SplitTransaction`, `UnsplitTransaction`.
- Pages: transaction index with inline add row and/or side drawer.
- Pessimistic submission for transactions.
- Use `source = manual` by default.
- `created_by_user_id` set from authenticated user.
- Income defaults to `Unassigned Funds` unless later explicitly moved by assignment.
- Unsplit transactions store category/bucket directly.
- Split transactions keep reconciliation identity on the parent row and store category/bucket on child rows.
- Do not implement PDF import here.

Definition of done:

- Release-level tracer bullet is green.
- Transactions can be manually entered and listed.
- Category and bucket attribution are captured.
- Split transactions are supported without breaking reconciliation identity.
- Money signs are clear and tested.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| TXN-1 Transaction backend create/list | Task | CRUD milestone | Create/list manual transactions with category + bucket | Pest feature test | Public behavior only |
| TXN-2 Income defaults to Unassigned | Task | TXN-1 | Positive income goes to `Unassigned Funds` | Pest feature test | Key bucket rule |
| TXN-3 Transaction validation/authorization | Task | TXN-1 | Invalid data returns errors | Pest feature test | FormRequest owns auth |
| TXN-4 Split transaction backend | Task | TXN-1 | Child split rows sum to parent and carry category/bucket | Pest feature test | One bucket per child |
| TXN-5 Effective transaction line service | Task | TXN-4 | Unsplit and split transactions produce consistent lines | Pest test | Deep module candidate |
| TXN-6 Transaction UI entry/split flow | Task | TXN-1, TXN-4 | Inline or quick-add flow submits and can split | Static checks | Keyboard-first later refinement |
| TXN-7 Release tracer test | Task | TXN-1, CRUD milestone | Sign in → account/category/bucket → transaction | Pest feature test | First release-level tracer |
| TXN-8 Edit/delete guards baseline | Task | TXN-1 | Reconciled guard path exists | Pest feature test | Full unlock later |

---

### Milestone 5 — Bucket BudgetService Math Risk Spike and Plan Page

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Prove the hardest envelope-budget math early.
- Build monthly plan page and bucket assignment/move endpoints.
- Compute bucket available, obligation due, underfunding, and carryover on read without denormalizing.

Core concepts:

```text
bucket_available(B, M) =
    SUM(effective_transaction_lines.amount WHERE bucket_id = B AND date <= last_day_of_month(M))
  + SUM(bucket_assignments.amount WHERE to_bucket_id = B AND (year, month) <= M)
  - SUM(bucket_assignments.amount WHERE from_bucket_id = B AND (year, month) <= M)
```

```text
obligation_due(B, M) =
    SUM(monthly_obligation_for(B, each active month from bucket start through M))
  - SUM(assignments that funded those obligations through M)
```

Notes:

- `Unassigned Funds` available comes from income and is reduced by assignments out.
- A bucket may go negative when expenses exceed available; this is allowed and must be flagged.
- Missed obligations roll forward.
- Extra funding can prepay future obligations.
- Current/future obligation edits use `bucket_obligation_versions`; historical months do not change.
- Archived buckets stop generating future obligations.

Candidate behavior tests:

1. Income posts to `Unassigned Funds`, then an assignment funds a bucket.
2. Expense attributed to a bucket draws down that bucket available.
3. Expense can make a bucket negative and the plan flags it as needing attention.
4. Missed monthly obligations roll forward to the next month.
5. Extra funding can prepay a future obligation.
6. Editing monthly obligation in May changes May and future months, not January–April.
7. Batch plan payload computes bucket available/obligation state without N+1 query behavior.

Implementation notes:

- Service: `BudgetService` backed by `EffectiveTransactionLineService` and `BucketService`.
- Controller: `PlanController`.
- Requests: `UpdateBucketAssignmentRequest`, `MoveMoneyRequest`, `RecalculateBucketObligationsRequest` if needed.
- Actions/classes: `AssignToBucket`, `MoveMoneyBetweenBuckets`, `RecalculateBucketObligations`.
- Page: `/household/plan/{yearMonth?}`.
- Month format: `YYYY-MM`.
- Group buckets by active/underfunded/archived or by configurable bucket group during implementation.
- Auto-fill obligations funds buckets from `Unassigned Funds` where possible; if not enough unassigned money exists, UI shows remaining obligations as underfunded.
- Use optimistic updates for assignments/moves where safe.
- Consider a state class `plan-state.svelte.ts` only when component script would otherwise become complex.

Definition of done:

- Bucket carryover, obligation rollover, and negative-bucket behavior are covered by tests.
- Plan page renders month summary, unassigned funds, bucket available, due/underfunded state, and negative-bucket warnings.
- Assignments update through FormRequests and services/actions.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| BUD-1 Bucket available math | Risk spike/task | SCH, TXN | Income, assignment, expense, carryover pass | Pest tests | Deep module candidate |
| BUD-2 Obligation rollover math | Risk spike/task | BUD-1 | Missed obligations roll forward | Pest tests | Core new decision |
| BUD-3 Future prepayment math | Task | BUD-2 | Extra funding reduces future obligation pressure | Pest tests | Supports surplus usage |
| BUD-4 Obligation versioning behavior | Task | CRUD-4, BUD-2 | Current/future edits do not rewrite history | Pest tests | Critical for bucket edits |
| BUD-5 Plan show endpoint | Task | BUD-1 | Monthly bucket plan props render | Pest feature test | Inertia response |
| BUD-6 Bucket assignment/move endpoints | Task | BUD-1 | Assign/move money between buckets | Pest feature test | Uses Unassigned Funds |
| BUD-7 Auto-fill obligations | Task | BUD-6 | Available unassigned funds can fill obligations | Pest feature test | Underfunded state if short |
| BUD-8 Plan Svelte page/state | Task | BUD-5, BUD-6 | UI compiles and uses Inertia | Static checks | Wayfinder route refs |

---

### Milestone 6 — Payee Rules and Transaction Suggestions

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Speed up transaction entry.
- Add rule engine for payee category/bucket suggestions.

Candidate behavior tests:

1. Matching auto-apply rule returns category and/or bucket suggestions for a payee.
2. Higher-priority rule wins over lower-priority rule.
3. New payee/category/bucket entry can create a rule only when user opts in.
4. Existing rules are not silently changed by contradictory category or bucket selection.

Implementation notes:

- Controller: `PayeeRuleController`.
- Requests: `StorePayeeRuleRequest`, `UpdatePayeeRuleRequest`, `SuggestPayeeRuleRequest` if needed.
- Service: `PayeeRuleService`.
- Suggest endpoint under `/household/payee-rules/suggest`.
- Transaction entry state may call suggestion endpoint on payee blur.
- Rules can suggest category only, bucket only, or both.
- Keep suggestions small and server-derived.

Definition of done:

- Manual transaction form can receive category and bucket suggestions.
- Rule CRUD exists enough for v1.0.
- No silent mutation of user rules.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| RULE-1 Rule CRUD backend | Task | SCH, CRUD | Create/update/list rules | Pest feature tests | Thin controller |
| RULE-2 Rule matching service | Task | RULE-1 | Priority and auto_apply work for category/bucket | Pest tests | Public service boundary ok |
| RULE-3 Suggest endpoint | Task | RULE-2 | Payee returns category/bucket suggestion data | Pest feature test | Small endpoint |
| RULE-4 Transaction UI suggestion integration | Task | TXN, RULE-3 | Category/bucket can auto-fill/suggest | Static checks | Svelte state class if complex |
| RULE-5 Opt-in rule creation prompt path | Task | RULE-4 | No silent rule updates | Pest + static checks | UI can be minimal |

---

### Milestone 7 — Transfers Between Accounts

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Support transfers that do not affect bucket available.
- Create linked transaction pairs atomically.

Candidate behavior tests:

1. Transfer creates two transactions with equal/opposite amounts.
2. Transfer rows share a `transfer_pair_id`.
3. Transfer transactions have `category_id = null` and `bucket_id = null`.
4. Transfer does not affect bucket available balance.

Implementation notes:

- Controller: `TransferController`.
- Request: `StoreTransferRequest`.
- Action/class: `TransferBetweenAccounts`.
- Use DB transaction.
- Validate from/to accounts differ.
- Keep transfer UI simple; can be a side panel or dedicated flow.

Definition of done:

- Transfers are atomic and tested.
- Budget math excludes transfers naturally via null bucket.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| TRF-1 Transfer action/backend | Task | TXN | Linked pair created atomically | Pest feature test | DB transaction |
| TRF-2 Transfer validation | Task | TRF-1 | Same-account/invalid amount rejected | Pest feature test | FormRequest |
| TRF-3 Transfer UI | Task | TRF-1 | User can submit transfer | Static checks | Pessimistic submit |
| TRF-4 Budget exclusion test | Task | TRF-1, BUD | Transfers do not affect bucket available | Pest test | Protects math |

---

### Milestone 8 — Reconciliation Matched-Only Flow

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Let users reconcile cleared transactions to a statement date and balance.
- Surface discrepancies without accepting them in v1.0.
- Lock reconciled transactions against accidental edits.

Candidate behavior tests:

1. Matched reconciliation marks cleared transactions on/before statement date as reconciled.
2. Uncleared transactions are not reconciled.
3. Transactions after statement date are not reconciled.
4. Unmatched reconciliation shows discrepancy and does not create accepted reconciliation in v1.0.

Implementation notes:

- Controller: `ReconcileController`.
- Request: `StoreReconciliationRequest`, `UnlockReconciliationRequest` if needed.
- Service: `ReconciliationService`.
- Action/class: `ReconcileAccount`.
- Calculated balance:
  `starting_balance + SUM(cleared transactions for account where date <= statement_date)`.
- Editing reconciled transactions requires explicit unlock path.
- Discrepancy acceptance goes to parking lot.

Definition of done:

- Matched reconciliation is reliable and tested.
- Discrepancies are visible but not accepted.
- Reconciled transactions are protected.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| REC-1 Reconciliation calculation service | Task | SCH, TXN | Calculated balance correct | Pest tests | Deep module candidate |
| REC-2 Matched reconciliation action | Task | REC-1 | Marks eligible rows reconciled | Pest feature test | Atomic write |
| REC-3 Discrepancy display path | Task | REC-1 | No accepted discrepancy in v1.0 | Pest feature test | v1.1 parking lot |
| REC-4 Reconciliation UI | Task | REC-2 | Show/account flow compiles | Static checks | Side-by-side ledger if needed |
| REC-5 Unlock guard baseline | Task | REC-2 | Explicit unlock required | Pest feature test | UI confirm minimal |

---

### Milestone 9 — Parser Service Contract and Skeleton

Architecture precondition: re-read `docs/architecture-design-patterns.md` for app boundaries even though parser is Python.

Purpose:

- Create FastAPI parser service under `parser/`.
- Define stable `/parse` contract before Laravel import code depends on it.
- Keep parser stateless and file-persistence-free beyond request lifetime.

Candidate behavior tests/checks:

1. Parser endpoint accepts a PDF upload and shared-secret header.
2. Parser endpoint rejects missing/invalid secret.
3. Parser returns normalized transaction JSON shape.
4. Parser does not write uploaded PDFs to project storage.

Implementation notes:

- Directory: `Earmark-web/parser/`.
- Endpoint: `POST /parse`.
- Input: multipart PDF.
- Output:

```json
{
  "status": "ok",
  "transactions": [
    {
      "date": "2026-04-15",
      "payee": "LOBLAWS #1234",
      "amount_cents": -12743,
      "raw_text": "..."
    }
  ],
  "warnings": []
}
```

- Primary parser: `pdfplumber`.
- Fallback: `docling` when needed, but bank-specific parsers are deferred.
- Shared secret header protects endpoint.
- No database.
- No durable file persistence.

Definition of done:

- Laravel can mock or call a stable parser contract.
- Parser can run locally with uvicorn.
- Docker Compose can wire it later.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| PAR-1 Parser service skeleton | Task | None | FastAPI app runs locally | Parser test/check | Lives in `parser/` |
| PAR-2 Shared-secret auth | Task | PAR-1 | Invalid secret rejected | Parser test | App-only caller |
| PAR-3 Parse response contract | Risk spike/task | PAR-1 | Stable normalized JSON | Parser test | Keep generic parser |
| PAR-4 No file persistence check | Task | PAR-1 | No project PDF writes | Test/check | Mirrors privacy rule |
| PAR-5 Laravel parser client contract | Task | PAR-3 | App can fake parser response | Pest with HTTP fake | Before import pipeline |

---

### Milestone 10 — PDF Statement Upload, Stage, Review, Promote

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Implement ephemeral PDF import flow.
- Store metadata and staged rows only.
- Never persist PDF bytes by application code.

Candidate behavior tests:

1. Uploading a valid PDF hashes bytes, creates statement upload metadata, calls parser, and stages transactions.
2. Re-uploading the same PDF hash is rejected as duplicate.
3. Parser failure marks statement upload failed and shows an error.
4. Review can attribute each staged row to a reporting category and bucket.
5. Review can split a staged row into child amounts, each with one category and one bucket.
6. Promoting accepted staged rows creates real transactions exactly once, including split children where present.

Implementation notes:

- Request: `UploadStatementRequest` with file validation and `%PDF` magic-byte `after()` rule.
- Controller: `StatementImportController`.
- Action/class: `UploadAndStageStatement`.
- Pipeline classes:
  - `StatementImportContext`
  - `HashAndCheckDuplicate`
  - `RecordStatementUpload`
  - `ForwardToParser`
  - `StageParsedTransactions`
  - `ApplyPayeeRuleSuggestions`
- Action/class: `PromoteStagedTransactions`.
- Request: `ImportStagedTransactionsRequest`.
- Use `Http::fake()` in tests for parser boundary.
- Do not call `$file->store()`, `$file->move()`, or `Storage::put()` for PDFs.
- Do not log PDF contents, raw payees, or amounts at info level.
- Set bytes to empty string after parser forwarding.
- Duplicate detection for staged rows uses account/date/amount/normalized payee within ±3 days and defaults possible duplicates to not accepted.
- Imported income defaults to `Unassigned Funds` unless review explicitly attributes otherwise.
- Imported expenses may be bucketed into a negative bucket; the row can still promote, and plan/dashboard must flag the deficit.
- Review splitting preserves the original bank row as one parent transaction for reconciliation.

Definition of done:

- PDF bytes are not persisted by app code.
- Metadata and staged rows are persisted.
- Review page can edit category/bucket, split rows, and promote accepted rows.
- Tests verify no app storage file was created for the PDF.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| IMP-1 Upload FormRequest/controller | Task | SCH, PAR | Valid PDF accepted, invalid rejected | Pest feature test | Magic bytes check |
| IMP-2 Import pipeline/action | Task | IMP-1 | Hash, metadata, parser, staging work | Pest with HTTP fake | DB transaction |
| IMP-3 Duplicate statement protection | Task | IMP-2 | Same hash rejected | Pest feature test | User-friendly error |
| IMP-4 Review payload/service | Task | IMP-2 | Staged rows render with category/bucket options | Pest feature test | Inertia props |
| IMP-5 Staged row splitting | Task | IMP-4 | Split staged child amounts sum to parent | Pest feature test | One bucket per child |
| IMP-6 Promote staged transactions | Task | IMP-4, IMP-5 | Accepted rows create transactions/splits once | Pest feature test | Idempotent retry |
| IMP-7 Import Svelte pages | Task | IMP-4, IMP-6 | Upload/review pages compile and support attribution/splits | Static checks | Inertia forms |
| IMP-8 PDF non-persistence verification | Task | IMP-2 | No storage PDF/DB BLOB bytes | Pest feature test | tmpfs still deploy config |

---

### Milestone 11 — Dashboard Fixed Layout

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Build v1.0 fixed dashboard.
- Summarize household budget health.

Cards:

1. Unassigned Funds available this month.
2. Total cash on hand.
3. Negative/needs-attention buckets.
4. Underfunded bucket obligations.
5. Top 5 spending categories this month.
6. Accounts needing reconciliation.

Candidate behavior tests:

1. Dashboard shows Unassigned Funds available from BudgetService.
2. Dashboard shows cleared cash across accounts.
3. Dashboard lists negative buckets.
4. Dashboard lists underfunded obligations.
5. Dashboard flags accounts needing reconciliation when latest reconciliation is older than 35 days or has discrepancy.

Implementation notes:

- Controller: `DashboardController` or invokable.
- Service may compose `BudgetService`, `AccountService`, `ReconciliationService`.
- Fixed layout only.
- Dashboard customization is v1.1.
- Separate decorative goal-progress card is v1.1, but v1.0 must still show bucket funding/underfunding state because buckets are core.

Definition of done:

- `/household/dashboard` gives at-a-glance financial state.
- No customization features appear.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| DSH-1 Dashboard data service | Task | BUD, REC | Unassigned, negative buckets, underfunded obligations, and reconciliation cards computed | Pest tests | Avoid N+1 |
| DSH-2 Dashboard Inertia endpoint | Task | DSH-1 | Props returned to page | Pest feature test | Thin controller |
| DSH-3 Dashboard Svelte page | Task | DSH-2 | Fixed cards compile | Static checks | Tailwind/UI primitives |
| DSH-4 Empty states | Task | DSH-3 | Empty household gives useful CTAs | Static checks/review | No blank pages |

---

### Milestone 12 — Settings and Copy-Paste Invites

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Allow inviting the second household user.
- Keep both users as full editors.
- No transactional email.

Candidate behavior tests:

1. Authenticated user can create an invite link for an email.
2. Expired invite cannot be accepted.
3. Accepted invite creates a user and marks invite accepted.
4. Accepted invite cannot be reused.

Implementation notes:

- Controller: `InviteController`, `SettingsController`.
- Requests: `StoreInviteRequest`, `AcceptInviteRequest`.
- Actions/classes: `GenerateInviteLink`, `AcceptInvite`.
- Token is random and stored hashed if desired; PRD says token string length 64 unique.
- v1.0 returns URL for manual copy.
- No roles.
- All accepted users are household members.

Definition of done:

- Two users can sign in concurrently and see shared household data after refresh.
- No email provider is required.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| INV-1 Invite generation backend | Task | FND, SCH | Link generated and stored | Pest feature test | Copy-paste URL |
| INV-2 Invite acceptance backend | Task | INV-1 | User created and invite accepted | Pest feature test | No role distinction |
| INV-3 Expiry/reuse guards | Task | INV-2 | Expired/reused blocked | Pest feature test | Security critical |
| INV-4 Settings invite UI | Task | INV-1 | Link displayed for copy | Static checks | No email |
| INV-5 Two-user shared data smoke behavior | Task | INV-2, core CRUD | Both users see same data | Pest feature test | Shared household model |

---

### Milestone 13 — Docker Compose and Local Release Readiness, No Deploy

Architecture precondition: re-read `docs/architecture-design-patterns.md` for app/container boundaries.

Purpose:

- Prepare self-hostable Docker Compose artifacts.
- Wire Laravel app and parser container.
- Require tmpfs for PHP upload temp files.
- Stop before deployment.

Candidate behavior checks:

1. Docker Compose config includes app and parser services.
2. App service config mounts upload temp path as tmpfs.
3. Parser URL/secret are configurable through config files/env consumed by Laravel config.
4. Local compose can be smoke-tested, but production deployment is not performed.

Implementation notes:

- Do not use `env()` outside config files.
- Add parser config under `config/services.php` or equivalent.
- App service tmpfs:

```yaml
services:
  app:
    tmpfs:
      - /tmp:size=128M
    environment:
      PHP_INI_UPLOAD_TMP_DIR: /tmp
```

- SQLite volume must be explicit.
- No automated backups in v1.0.
- No deployment commands to a server.

Definition of done:

- Compose artifacts are ready for local/self-host use.
- tmpfs privacy requirement is documented in config/comments or README if docs are requested separately.
- No actual deployment has occurred.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| OPS-1 Parser/app env config | Task | PAR, IMP | Laravel reads parser URL/secret from config | Pest/config check | No direct env outside config |
| OPS-2 Docker Compose app service | Task | Core app | App service defined | Compose config check | No deploy |
| OPS-3 Docker Compose parser service | Task | PAR | Parser service defined | Compose config check | Same repo parser dir |
| OPS-4 tmpfs upload temp config | Task | OPS-2 | tmpfs configured for uploads | Config review/check | Required v1.0 DoD |
| OPS-5 Local release checklist | Task | All milestones | Commands/checks listed | Review | Do not deploy |

---

### Milestone 14 — v1.0 Hardening and Release Readiness

Architecture precondition: re-read `docs/architecture-design-patterns.md`.

Purpose:

- Close gaps against PRD v1.0 DoD.
- Ensure automated behavior coverage is meaningful.
- Verify no accidental v1.1 creep.

Candidate behavior tests/checks:

1. Every dollar of income starts in `Unassigned Funds` and, once assigned, appears in the correct bucket available balance.
2. Bucket carryover and obligation rollover across months work.
3. Reconciliation prevents accidental edits without unlock.
4. PDF upload flow does not persist PDF bytes by application code.
5. Split transactions preserve the parent reconciliation row while child lines drive category/bucket reporting.

Implementation notes:

- Run targeted Pest tests during each milestone.
- Run full relevant Pest suite near release readiness.
- Run Pint after PHP changes.
- Run frontend static checks after frontend changes.
- Run build near release readiness.
- Do not add Playwright.
- Do not deploy.

Definition of done:

- v1.0 acceptance criteria pass except explicitly deferred Playwright and deployment execution.
- Parking lot is clear.
- No production server has been touched.

Beads:

| Bead | Type | Dependencies | Acceptance | Verification | Notes |
|---|---|---|---|---|---|
| HARD-1 PRD DoD audit | Task | All app milestones | Included/excluded list reconciled | Review | Note Playwright deferral |
| HARD-2 Money/reconciliation regression pass | Task | BUD, REC | Critical tests pass | Pest filters/suite | Behavior tests only |
| HARD-3 PDF privacy regression pass | Task | IMP, OPS | Non-persistence verified | Pest tests | tmpfs config exists |
| HARD-4 Frontend static pass | Task | Frontend milestones | Types/lint/build pass | npm checks | No browser tests |
| HARD-5 No-deploy confirmation | Task | OPS | Plan stops before deploy | Review | User said do not deploy |

---

## 10. Parking Lot for v1.1+

These are explicitly not v1.0 tasks.

| Bead | Future Version | Description | Dependency Notes |
|---|---:|---|---|
| FUT-1 Playwright critical e2e suite | v1.1 | Add Playwright for login, transaction, PDF import, reconcile | Requires dependency approval |
| FUT-2 Discrepancy-accepted reconciliation | v1.1 | Allow accepting discrepancy with note | Builds on REC milestone |
| FUT-3 Dashboard customization | v1.1 | Add user dashboard preferences | Requires new table/preferences UI |
| FUT-4 Goal progress dashboard card | v1.1 | Show goal category progress | Builds on categories/targets |
| FUT-5 CSV import | v1.1 | Add CSV import alongside PDF | Reuse staged transaction flow |
| FUT-6 Scheduled stale-upload cleanup | v1.1 | `statements:purge-stale --days=30` | Builds on imports |
| FUT-7 Transactional invite email | v1.1 | Send invite through mail provider | Requires mail dependency/config approval |
| FUT-8 Bank-specific PDF parsers | v1.2+ | Add parser dispatch by bank | Builds on parser contract |
| FUT-9 Category history charts | v1.2+ | Add reporting visualizations | Requires charting decision |
| FUT-10 Year-over-year comparison | v1.2+ | Add historical comparison reports | Builds on budget history |
| FUT-11 Recurring transaction templates | v1.2+ | Add scheduled/manual recurring entries | Requires recurrence model |
| FUT-12 Automated SQLite backups | Later | Add encrypted off-site/local rotation | User chose no automated backups v1.0 |
| FUT-13 Production deployment | Later | Deploy to home/server target | Explicitly forbidden for current task |

---

## 11. Cross-Cutting Risks and Mitigations

### Risk: Team removal touches many starter files

Mitigation:

- Do it first.
- Reset local data.
- Use focused tests for auth and `/household/dashboard`.
- Remove references incrementally while green.

### Risk: Available balance math becomes slow or wrong

Mitigation:

- Treat `BudgetService` as a deep module.
- Write behavior tests for income to Unassigned, bucket assignment, same-month spend, carryover, obligation rollover, negative buckets, split transactions, and transfer exclusion.
- Use batch queries for plan page.
- Add covering indexes for buckets, assignments, effective transaction lines, and dates.

### Risk: PDF privacy promise is accidentally broken

Mitigation:

- Never call storage/move APIs for PDFs.
- Add tests proving no file appears under app storage.
- Store metadata only.
- Clear in-memory bytes after parser call.
- Require Docker tmpfs for upload temp directory.

### Risk: Parser integration blocks Laravel work

Mitigation:

- Define parser contract before full parser accuracy.
- Use Laravel HTTP fakes for app import tests.
- Keep parser service stateless.
- Defer bank-specific parsing.

### Risk: Dependency drift from PRD

Mitigation:

- Prefer installed dependencies.
- Gate dependency changes.
- Record deviations in implementation notes.

### Risk: UI grows complex too early

Mitigation:

- Start with minimal pages that exercise behavior.
- Extract Svelte state classes only when scripts become complex.
- Follow Svelte 5 runes and no-destructuring rule.
- Use existing UI primitives.

---

## 12. Implementation Rules Per Milestone

Before any milestone:

1. Re-read `docs/architecture-design-patterns.md`.
2. Inspect sibling files for current conventions.
3. Confirm no dependency change is being made without explicit approval.
4. Pick one behavior test as the tracer for that milestone.
5. Write one failing test.
6. Implement minimum code.
7. Run targeted test.
8. Refactor only when green.

After PHP changes:

1. Run targeted Pest test: `php artisan test --compact --filter={name} --no-interaction`.
2. Run `vendor/bin/pint --dirty --format agent`.

After frontend changes:

1. Run `npm run types:check`.
2. Run `npm run lint:check`.
3. Run `npm run build` near milestone/release completion or when Vite integration changes.

Never:

1. Deploy.
2. Add dependencies without approval.
3. Persist PDF bytes through app code.
4. Use floats for money.
5. Add `household_id` to v1.0 finance tables.
6. Bring Teams back as a finance model.
7. Add Playwright in v1.0.
8. Use legacy Svelte reactivity.
9. Put business logic in controllers.
10. Skip FormRequests for non-trivial endpoints.
11. Collapse reporting categories and budget buckets into the same model.
12. Hide negative bucket balances instead of surfacing them.

---

## 13. v1.0 Acceptance Checklist

Functional:

- [ ] First user can be created by command.
- [ ] Public registration is disabled.
- [ ] Authenticated household user can access `/household/dashboard`.
- [ ] Second user can be invited through copy-paste link.
- [ ] Both users have full edit rights.
- [ ] User can create accounts.
- [ ] User can create reporting categories.
- [ ] User can create buckets/goals with monthly obligation, target amount, and target date.
- [ ] `Unassigned Funds` system bucket exists and is protected.
- [ ] User can assign/move money from Unassigned to buckets for current or future months.
- [ ] User can enter manual transactions with category + bucket attribution.
- [ ] User can split a transaction into child rows, each with its own category + bucket.
- [ ] User can transfer between accounts.
- [ ] User can upload PDF and stage transactions.
- [ ] User can review, categorize, bucket, split, and promote staged transactions.
- [ ] User can reconcile matched account statements.
- [ ] Dashboard shows fixed v1.0 cards.

Accounting:

- [ ] All money is integer cents.
- [ ] Outflows are negative.
- [ ] Inflows are positive.
- [ ] Transaction dates are date-only.
- [ ] Bucket available includes prior assignments/moves and effective transaction lines through month end.
- [ ] Positive income defaults to `Unassigned Funds`.
- [ ] Expenses draw down the attributed bucket.
- [ ] Expenses may make buckets negative, and those buckets are clearly flagged.
- [ ] Transfers do not affect bucket available.
- [ ] Bucket carryover works across months.
- [ ] Missed monthly obligations roll forward.
- [ ] Extra funding can prepay future obligations.
- [ ] Editing bucket obligations affects current/future months only.
- [ ] Archived positive buckets sweep surplus to Unassigned.
- [ ] Negative buckets cannot be archived until covered.
- [ ] Reconciled transactions are protected from accidental edits.

Privacy/import:

- [ ] PDFs are never persisted by application code.
- [ ] Statement upload stores metadata only.
- [ ] Duplicate PDF hash is rejected.
- [ ] Parser errors are visible.
- [ ] Docker app service uses tmpfs for upload temp path.

Architecture:

- [ ] Controllers are thin and singular.
- [ ] FormRequests own validation and authorization.
- [ ] Services own domain logic.
- [ ] Actions/plain action classes own bespoke workflows.
- [ ] Inertia pages receive typed props.
- [ ] Svelte uses runes.
- [ ] Shared client state uses context, not module singletons.
- [ ] Wayfinder is used for frontend route/action references.

Verification:

- [ ] Targeted Pest tests pass for changed behavior.
- [ ] Pint runs after PHP changes.
- [ ] Frontend static checks pass after frontend changes.
- [ ] No Playwright requirement in v1.0.
- [ ] No production deployment performed.

---

## 14. First Three Execution Slices After Plan Approval

These are the recommended first implementation slices, not to be executed until explicitly requested.

### Slice 1 — Team Removal Tracer

Behavior:

> Authenticated user can access `/household/dashboard` without a team segment.

Expected red-green path:

1. Write feature test for authenticated dashboard access.
2. Remove/adjust route prefix and middleware.
3. Make dashboard route return Inertia response.
4. Run targeted test.
5. Remove Team UI references only after route behavior is green.

### Slice 2 — First User Command

Behavior:

> Operator can create the first household user from CLI when no users exist.

Expected red-green path:

1. Write command test.
2. Implement command with email/password/name input or options.
3. Guard duplicate first-user creation.
4. Run targeted test.

### Slice 3 — Account, Category, and Bucket Creation

Behavior:

> Household member can create an account, create a reporting category, and create a bucket with a current-month obligation.

Expected red-green path:

1. Write account creation feature test.
2. Create account migration/model/factory as needed.
3. Create Account FormRequest/controller/service.
4. Get account creation green.
5. Repeat the red-green loop for reporting category creation.
6. Repeat the red-green loop for bucket creation, including `Unassigned Funds` protection and current/future obligation version creation.
7. Run targeted tests and Pint after PHP changes.

---

## 15. Review Questions for the Next Conversation

Use these to discuss the plan before execution:

1. Should any v1.0 milestone be split further before Beads import?
2. Should any parked v1.1 item move into v1.0 despite the current decision?
3. Should `lorisleiva/laravel-actions` be approved before Milestone 1?
4. Should passkeys/2FA remain in the auth surface or be removed as non-PRD extras?
5. Should the optional category seed use a generic YNAB-like set or a custom household list?
6. Should Docker Compose be built before or after PDF import implementation?
7. Should parser accuracy be intentionally minimal in v1.0, or should generic pdfplumber/docling quality be a release blocker?

---

End of plan.
