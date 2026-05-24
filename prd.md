# Household Budget App — Product Requirements Document
**Stack: Laravel + Inertia.js + Svelte 5**

## 1. Context

A self-hosted YNAB replacement for a single household (two adults, fully merged finances, steady salary income). The user has used YNAB extensively and is leaving because of:

- Tedious categorization workflow
- Painful reconciliation
- Pricing
- Can't see what they want at a glance
- Plaid/Teller don't work with their bank (PDF-only statement exports)

The system implements YNAB's "every dollar a job" philosophy with full control over the data model, UI, and ingestion. Built for one household — no multi-tenancy.

## 2. Goals

- Zero-based budgeting: every dollar must be assigned to a category before being spent.
- Two-user shared access (operator + co-pilot, both with full edit rights).
- Faster transaction entry than YNAB: aggressive auto-categorization via a payee rule engine.
- Reconciliation that surfaces discrepancies instead of hiding them.
- A dashboard the household can customize (v1.1 — v1.0 is fixed).
- PDF statement parsing as the primary ingestion path. **PDFs are never persisted by application code** — processed in-memory and discarded.
- Self-hostable via a single `docker compose up`.

## 3. Non-goals (v1)

- Multi-household / multi-tenant. Hardcoded to one family.
- Investment / net worth tracking.
- Mobile native app. Responsive web only.
- Real-time bank sync (Plaid/Teller/MX).
- Loan amortization, debt payoff planning.
- Reporting beyond dashboard + per-category history.
- Multi-currency. Single currency (CAD).

## 4. Users

Two authenticated adults. Both have full read/write to all data. No role distinction in the system — "operator" vs "co-pilot" is a household convention, not an enforced permission. Audit columns (`created_by_user_id`, `updated_by_user_id`) record who did what.

## 5. Tech stack

| Layer | Choice |
|------|--------|
| Backend framework | Laravel (latest stable, PHP 8.3+) |
| Frontend framework | Svelte 5 with runes (`$state`, `$derived`, `$effect`), TypeScript |
| Bridge | Inertia.js — Laravel owns routing; Svelte renders pages with server props |
| Auth | Laravel Breeze with the Inertia-Svelte starter; invite-only registration |
| Database | SQLite (single-household, self-hosted) |
| ORM | Eloquent |
| Actions library | `lorisleiva/laravel-actions` for bespoke operations |
| Form/validation | Laravel `FormRequest` on the server; `useForm` from `@inertiajs/svelte` on the client |
| PDF parsing | Separate Python service (FastAPI + pdfplumber, Docling fallback) called over HTTP |
| Styling | Tailwind CSS v4 |
| Component primitives | shadcn-svelte (pick one and stay consistent) |
| Testing | Pest (PHP) for backend, Playwright for critical e2e flows |
| Deployment | Docker Compose: app container (PHP-FPM + nginx or FrankenPHP) + parser container (Python) |

**Hard rules:**

- All money stored as **integer cents** (`bigInteger`), never floats. Convert at the display boundary.
- All transaction dates stored as `date` columns. Local calendar dates, no times.
- Use Svelte 5 runes — never legacy `$:` or `let` reactivity.
- Type-safe boundaries: Eloquent models → controller-provided Inertia props → Svelte `$props()` with explicit TS types.
- Never destructure state classes — snapshots break reactivity.
- Never export module-level state instances — leaks across SSR. Use Svelte context.

## 6. Backend structure

### 6.1 Controllers — thin, singular

Singular names: `AccountController`, `TransactionController`. Body = delegate + return.

Parameter order: `FormRequest` → route-bound models → `Action`/`Service`. Always declare return types. Always use `$request->validated()`.

```php
class TransactionController extends Controller
{
    public function index(TransactionService $service): \Inertia\Response
    {
        return inertia('transactions/Index', [
            'transactions' => $service->paginatedForCurrentMonth(),
            'accounts' => $service->accountOptions(),
            'categories' => $service->categoryOptions(),
        ]);
    }

    public function store(StoreTransactionRequest $request, CreateTransaction $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user());
        return redirect()->route('transactions.index');
    }
}
```

### 6.2 FormRequests — one per non-trivial endpoint

Authorization + validation live here. Naming: `Store{Model}Request`, `Update{Model}Request`, or verb-based (`ReconcileAccountRequest`, `UploadStatementRequest`).

```php
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('is-household-member') ?? false;
    }

    public function rules(): array
    {
        return [
            'date'        => ['required', 'date_format:Y-m-d'],
            'account_id'  => ['required', 'ulid', 'exists:accounts,id'],
            'payee'       => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'ulid', 'exists:categories,id'],
            'amount'      => ['required', 'integer'], // signed cents
            'memo'        => ['nullable', 'string', 'max:1000'],
            'cleared'     => ['boolean'],
        ];
    }
}
```

### 6.3 Services — domain logic by area

```
app/Services/
  Account/AccountService.php
  Category/CategoryService.php
  Budget/BudgetService.php              ← owns Available balance math
  Transaction/TransactionService.php
  PayeeRule/PayeeRuleService.php
  Reconciliation/ReconciliationService.php
  StatementImport/StatementImportService.php  ← orchestrates the PDF flow via Actions
```

The hard piece — the **running Available balance math** — lives in `BudgetService::availableForCategory(string $categoryId, int $year, int $month): int`. Single SQL query per category, or one batch query per month for the whole list. Don't denormalize; compute on read. Add covering indexes on `category_assignments(category_id, year, month)` and `transactions(category_id, date)`.

### 6.4 Actions — for bespoke, multi-stage, or runnable-elsewhere operations

```
app/Actions/
  Statement/
    UploadAndStageStatement.php           ← orchestrator, runs the Pipeline
    PromoteStagedTransactions.php
    Pipeline/
      StatementImportContext.php
      HashAndCheckDuplicate.php
      RecordStatementUpload.php
      ForwardToParser.php
      StageParsedTransactions.php
      ApplyPayeeRuleSuggestions.php
  Transaction/
    CreateTransaction.php
    TransferBetweenAccounts.php           ← creates linked transaction pair atomically
  Budget/
    MoveMoneyBetweenCategories.php
    AssignToCategory.php
  Reconciliation/
    ReconcileAccount.php                  ← matches + locks transactions ≤ statement_date
  Household/
    GenerateInviteLink.php
    AcceptInvite.php
```

**The PDF import is a Pipeline inside an Action**, wrapped in `DB::transaction` so any failure leaves no orphan rows:

```php
class UploadAndStageStatement
{
    use AsAction;

    public function handle(UploadedFile $file, string $accountId, User $user): StatementUpload
    {
        return DB::transaction(function () use ($file, $accountId, $user) {
            return app(Pipeline::class)
                ->send(new StatementImportContext(
                    bytes: file_get_contents($file->getRealPath()),
                    originalFilename: $file->getClientOriginalName(),
                    sizeBytes: $file->getSize(),
                    accountId: $accountId,
                    user: $user,
                ))
                ->through([
                    HashAndCheckDuplicate::class,
                    RecordStatementUpload::class,
                    ForwardToParser::class,
                    StageParsedTransactions::class,
                    ApplyPayeeRuleSuggestions::class,
                ])
                ->thenReturn()
                ->statementUpload;
        });
    }
}
```

Each pipeline class is a tight invokable; the context object carries state between stages and is discarded when the Action returns.

## 7. Data model

Money is integer cents. ULIDs preferred over auto-increment IDs (`HasUlids` trait on every model).

### `users` (Laravel default + Breeze)

Standard.

### `accounts`

```php
Schema::create('accounts', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('name');
    $table->enum('type', ['chequing', 'savings', 'credit_card', 'cash', 'investment', 'other']);
    $table->bigInteger('starting_balance')->default(0);
    $table->date('starting_balance_date');
    $table->boolean('archived')->default(false);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### `categories`

```php
Schema::create('categories', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('name')->unique();
    $table->enum('type', ['monthly_bill', 'true_expense', 'discretionary', 'goal', 'income']);
    $table->bigInteger('monthly_target')->nullable();
    $table->bigInteger('annual_target')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('archived')->default(false);
    $table->string('notes')->nullable();
    $table->timestamps();
});
```

### `category_assignments`

```php
Schema::create('category_assignments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('category_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month'); // 1–12
    $table->bigInteger('amount');
    $table->foreignId('updated_by_user_id')->constrained('users');
    $table->timestamps();
    $table->unique(['category_id', 'year', 'month']);
});
```

### `transactions`

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->date('date');
    $table->foreignUlid('account_id')->constrained();
    $table->string('payee');
    $table->foreignUlid('category_id')->nullable()->constrained();
    $table->bigInteger('amount'); // signed cents
    $table->string('memo')->nullable();
    $table->boolean('cleared')->default(false);
    $table->boolean('reconciled')->default(false);
    $table->ulid('transfer_pair_id')->nullable()->index();
    $table->enum('source', ['manual', 'imported_pdf', 'imported_csv'])->default('manual');
    $table->ulid('import_batch_id')->nullable()->index();
    $table->foreignId('created_by_user_id')->constrained('users');
    $table->timestamps();
    $table->index(['account_id', 'date']);
    $table->index(['category_id', 'date']);
});
```

### `payee_rules`

```php
Schema::create('payee_rules', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('pattern');
    $table->foreignUlid('category_id')->constrained();
    $table->unsignedInteger('priority')->default(100);
    $table->boolean('auto_apply')->default(true);
    $table->timestamps();
});
```

### `reconciliations`

```php
Schema::create('reconciliations', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('account_id')->constrained();
    $table->date('statement_date');
    $table->bigInteger('statement_balance');
    $table->bigInteger('calculated_balance');
    $table->enum('status', ['matched', 'discrepancy_accepted']);
    $table->bigInteger('discrepancy_amount')->default(0);
    $table->string('notes')->nullable();
    $table->foreignId('reconciled_by_user_id')->constrained('users');
    $table->timestamp('reconciled_at');
    $table->timestamps();
});
```

### `statement_uploads` (metadata only — PDFs never persisted)

```php
Schema::create('statement_uploads', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('account_id')->constrained();
    $table->string('original_filename');
    $table->string('file_sha256', 64)->unique();
    $table->unsignedBigInteger('file_size_bytes');
    $table->enum('status', ['parsing', 'parsed', 'failed', 'imported']);
    $table->unsignedInteger('parsed_transaction_count')->default(0);
    $table->unsignedInteger('imported_transaction_count')->default(0);
    $table->text('error_message')->nullable();
    $table->foreignId('uploaded_by_user_id')->constrained('users');
    $table->timestamp('uploaded_at');
    $table->timestamps();
});
```

### `staged_transactions`

```php
Schema::create('staged_transactions', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('statement_upload_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->string('payee');
    $table->string('raw_payee');
    $table->bigInteger('amount');
    $table->foreignUlid('suggested_category_id')->nullable()->constrained('categories');
    $table->foreignUlid('final_category_id')->nullable()->constrained('categories');
    $table->boolean('accept')->default(true);
    $table->foreignUlid('transaction_id')->nullable()->constrained();
    $table->timestamps();
});
```

### `invites`

```php
Schema::create('invites', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('email');
    $table->string('token', 64)->unique();
    $table->timestamp('expires_at');
    $table->timestamp('accepted_at')->nullable();
    $table->foreignId('invited_by_user_id')->constrained('users');
    $table->timestamps();
});
```

## 8. Core workflows

### 8.1 First-time setup

CLI seed creates the first user. They land on `/dashboard`, see an empty state, and are walked through: add accounts → add categories → optionally seed payee rules.

### 8.2 Monthly plan

`/plan/{yearMonth?}` shows the current month by default. Month param format: `2026-05`.

Top of page (computed in `BudgetService`):
- `income_this_month` — sum of positive transaction amounts in income-typed categories for the month.
- `total_assigned` — sum of assignments for the month.
- `to_be_budgeted` — prior carryover + this month's income − this month's assigned. Must be ≥ 0. If user over-assigns, show a clear red banner but don't block.

Categories grouped by `type` (Income → Monthly Bills → True Expenses → Discretionary → Goals). Collapsible groups. Drag-to-reorder within a group.

Per row: name, monthly target (greyed), assigned (inline-editable), spent (computed), available (computed running balance across all months).

**"Auto-fill targets"** button: assigns `monthly_target` to every category that has no assignment yet this month.

**Move money:** drag from one category's Available to another, or context menu → "Move money to…". Creates two offsetting `category_assignments` adjustments atomically via the `MoveMoneyBetweenCategories` Action.

### 8.3 Available balance math

For category `C` as of month `M`:

```
available(C, M) =
    SUM(category_assignments.amount WHERE category_id = C AND (year, month) ≤ (M.year, M.month))
  + SUM(transactions.amount         WHERE category_id = C AND date ≤ last_day_of_month(M))
```

(Amounts are signed; outflows are negative, so adding reduces the balance.)

Implemented in `BudgetService`. Don't denormalize.

### 8.4 Transaction entry

Three paths:
- Inline keyboard-driven add row at top of `/transactions`.
- Quick Add floating button → side drawer.
- PDF upload (see 8.5).

Required: date, account, payee, amount. Optional: category, memo, cleared.

On payee blur, the form (Svelte `$effect` in `TransactionEntryState`) calls a small server endpoint to check `payee_rules`. `auto_apply=true` rule → category auto-set. Otherwise, suggestion chip.

If user picks a category contradicting an existing rule for that payee, prompt: "Update rule for [pattern] → [new category]?" Never silently update.

If user enters a new payee + picks a category with no matching rule, offer to create one (default opt-in).

Transfers go through `/transactions/transfer` and the `TransferBetweenAccounts` Action. Creates two linked `transaction` rows with `transfer_pair_id` and `category_id = null`. Transfers do not affect category Available.

### 8.5 PDF statement import (ephemeral)

**The PDF is never written to disk by application code.**

Caveat to be honest about: PHP's `UploadedFile` mechanism buffers the body to `php.ini`'s `upload_tmp_dir` for the duration of the request; PHP cleans it up at request end. To eliminate even this brief disk touch, mount `upload_tmp_dir` on `tmpfs`. Covered in deployment notes (§12.6).

Flow:

1. User uploads PDF via `/import` (multipart form).
2. `UploadStatementRequest` validates: required, file, `mimes:pdf`, `max:10240` KB, and an `after()` rule checking magic bytes `%PDF-`.
3. Controller delegates to `UploadAndStageStatement` Action (Pipeline from §6.4).
4. Pipeline stages run inside `DB::transaction`:
   - **HashAndCheckDuplicate** — SHA-256 of bytes. If hash exists in `statement_uploads`, abort with "Already imported on [date]."
   - **RecordStatementUpload** — insert row with `status = parsing`.
   - **ForwardToParser** — HTTP POST bytes to Python service with shared-secret header and 60s timeout. Set `ctx->bytes = ''` after to release.
   - **StageParsedTransactions** — insert `staged_transactions` rows from parser response.
   - **ApplyPayeeRuleSuggestions** — populate `suggested_category_id` on each staged row.
5. Redirect to `/import/{uploadId}/review`.
6. Review table: each row editable, checkbox to accept. Default: all accepted, suggestion pre-filled. Possible duplicates (matching `(account, date, amount, normalized_payee)` within ±3 days of an existing transaction) get a "Possible duplicate" badge and default to NOT accepted.
7. User submits → `PromoteStagedTransactions` Action wraps a transaction, inserts real `transactions` for accepted rows, sets `staged_transactions.transaction_id`, updates `statement_uploads.status = imported`.

**Failure modes:**
- Parser error → `statement_uploads.status = failed`, `error_message` set. User sees error.
- User abandons review → upload stays `parsed`. Reachable later from `/import` list.
- Stale-upload cleanup: `php artisan statements:purge-stale --days=30` deletes `parsed` uploads older than N days and their staged rows.
- Re-process needed → delete upload + its transactions via UI affordance (with confirmation), then re-upload.

**Tradeoff being accepted:** if a parser bug emerges later, you cannot re-run the parser on the original file. Document in README.

### 8.6 Reconciliation

1. User picks an account at `/reconcile/{accountId}`, enters statement date and closing balance via `StoreReconciliationRequest`.
2. `ReconciliationService` computes:
   `calculated = starting_balance + SUM(transactions WHERE account=X AND cleared=true AND date ≤ statement_date)`.
3. If equal → one-click confirm. `ReconcileAccount` Action: mark those transactions `reconciled=true`, insert `reconciliations` row with `status = matched`.
4. If not equal → side-by-side ledger view through statement_date. User can mark missing transactions as cleared, add missing transactions, or accept the discrepancy with a note (v1.1).
5. Once matched, transactions on or before `statement_date` for this account become read-only. Editing requires explicit unlock (UI confirm).

### 8.7 Dashboard

Cards on `/dashboard`:
- **To Be Budgeted (this month)** — big, prominent.
- **Total cash on hand** — sum of cleared balances across accounts.
- **Overspent categories** — list with one-click "cover from another category."
- **Top 5 spending categories this month.**
- **Accounts needing reconciliation** — latest reconciliation >35 days old or non-zero discrepancy.

Goal progress card → v1.1. Dashboard customization → v1.1.

## 9. Routes

`routes/web.php` — `Route::resource` where it fits, single routes for bespoke ops.

```php
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Plan
    Route::get('/plan/{yearMonth?}', [PlanController::class, 'show'])
        ->where('yearMonth', '\d{4}-\d{2}')
        ->name('plan.show');
    Route::patch('/plan/assignment', [PlanController::class, 'updateAssignment'])
        ->name('plan.assignment.update');
    Route::post('/plan/move', [PlanController::class, 'moveMoney'])
        ->name('plan.move');

    // CRUD
    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('transactions', TransactionController::class);
    Route::resource('rules', PayeeRuleController::class);

    // Bespoke
    Route::post('/transactions/transfer', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/payee-rules/suggest', [PayeeRuleController::class, 'suggest'])->name('rules.suggest');

    // Reconciliation
    Route::get('/reconcile', [ReconcileController::class, 'index'])->name('reconcile.index');
    Route::get('/reconcile/{account}', [ReconcileController::class, 'show'])->name('reconcile.show');
    Route::post('/reconcile/{account}', [ReconcileController::class, 'store'])->name('reconcile.store');
    Route::post('/reconcile/{reconciliation}/unlock', [ReconcileController::class, 'unlock'])->name('reconcile.unlock');

    // Import
    Route::get('/import', [StatementImportController::class, 'index'])->name('import.index');
    Route::get('/import/new', [StatementImportController::class, 'create'])->name('import.create');
    Route::post('/import', [StatementImportController::class, 'store'])->name('import.store');
    Route::get('/import/{statementUpload}/review', [StatementImportController::class, 'review'])->name('import.review');
    Route::post('/import/{statementUpload}/promote', [StatementImportController::class, 'promote'])->name('import.promote');
    Route::delete('/import/{statementUpload}', [StatementImportController::class, 'destroy'])->name('import.destroy');

    // Settings + invites
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/invites', [InviteController::class, 'store'])->name('invites.store');
});

Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.show');
Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])->name('invites.accept');
```

## 10. Frontend structure

Per stack conventions. Lowercase subdirs in `pages/`, PascalCase files. State classes in `resources/js/lib/states/` (kebab-case).

```
resources/js/
  pages/
    Dashboard.svelte
    plan/Show.svelte
    accounts/Index.svelte
    accounts/Show.svelte
    categories/Index.svelte
    categories/Show.svelte
    transactions/Index.svelte
    transactions/Transfer.svelte
    rules/Index.svelte
    reconcile/Index.svelte
    reconcile/Show.svelte
    import/Index.svelte
    import/Create.svelte
    import/Review.svelte
    settings/Index.svelte
    auth/Login.svelte
    auth/AcceptInvite.svelte
  components/
    Money.svelte                 ← formats signed cents
    MoneyInput.svelte
    CategoryPicker.svelte
    AccountBadge.svelte
    DataTable.svelte
    SidePanel.svelte
    Toast.svelte
  layouts/
    AppLayout.svelte
  lib/
    states/
      plan-state.svelte.ts
      transaction-entry-state.svelte.ts
      import-review-state.svelte.ts
      reconcile-state.svelte.ts
      toast-state.svelte.ts        ← global, via context
      command-palette-state.svelte.ts
    types/
      index.ts                     ← shared TS types matching Inertia props
    money.ts                       ← formatCents, parseCentsInput
```

### 10.1 State classes — pattern

Every state class starts with an interface declaring its public surface. `$state` for reactive fields, `$derived` for computed, `$effect` sparingly for external side effects.

```ts
// resources/js/lib/states/plan-state.svelte.ts
import type { PlanCategory } from '$lib/types';
import { router } from '@inertiajs/svelte';

interface IPlanState {
  categories: PlanCategory[];
  income: number;
  totalAssigned: number;       // derived
  toBeBudgeted: number;        // derived
  hasDeficit: boolean;         // derived
  updateAssignment: (categoryId: string, amount: number) => void;
  moveMoney: (fromId: string, toId: string, amount: number) => void;
}

export class PlanState implements IPlanState {
  categories = $state<PlanCategory[]>([]);
  income = $state(0);
  totalAssigned = $derived(
    this.categories.reduce((sum, c) => sum + c.assigned, 0)
  );
  toBeBudgeted = $derived(this.income - this.totalAssigned);
  hasDeficit = $derived(this.toBeBudgeted < 0);

  constructor(initial: { categories: PlanCategory[]; income: number }) {
    this.categories = initial.categories;
    this.income = initial.income;
  }

  updateAssignment(categoryId: string, amount: number) {
    const cat = this.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    cat.assigned = amount;
    router.patch('/plan/assignment', { category_id: categoryId, amount }, {
      preserveScroll: true,
      preserveState: true,
    });
  }

  moveMoney(fromId: string, toId: string, amount: number) {
    router.post('/plan/move', { from_id: fromId, to_id: toId, amount }, {
      preserveScroll: true,
    });
  }
}
```

```svelte
<!-- pages/plan/Show.svelte -->
<script lang="ts">
  import { PlanState } from '$lib/states/plan-state.svelte';
  import type { PlanCategory } from '$lib/types';

  let { categories, income, month }: {
    categories: PlanCategory[];
    income: number;
    month: string;
  } = $props();

  const plan = new PlanState({ categories, income });
</script>
```

**Hard rule:** never destructure `plan.something` — snapshots break reactivity. Access via the instance.

### 10.2 Shared client state via context

For toasts and any other cross-page client state:

```ts
// resources/js/lib/states/toast-state.svelte.ts
import { setContext, getContext } from 'svelte';

const KEY = Symbol('toast');

interface IToastState {
  messages: { id: string; text: string; type: 'success' | 'error' }[];
  open: (text: string, type?: 'success' | 'error') => void;
  dismiss: (id: string) => void;
}

export class ToastState implements IToastState {
  messages = $state<{ id: string; text: string; type: 'success' | 'error' }[]>([]);

  open(text: string, type: 'success' | 'error' = 'success') {
    this.messages.push({ id: crypto.randomUUID(), text, type });
  }

  dismiss(id: string) {
    this.messages = this.messages.filter((m) => m.id !== id);
  }
}

export const setToastState = () => setContext(KEY, new ToastState());
export const getToastState = () => getContext<ToastState>(KEY);
```

Call `setToastState()` in `AppLayout.svelte`, `getToastState()` underneath. **Never** export a module-level instance.

Inertia flash messages flow in via `HandleInertiaRequests` and the layout pumps them into the toast state on prop change.

## 11. Python parser service (separate component)

- FastAPI, single endpoint `POST /parse` accepting multipart PDF.
- Returns:
  ```json
  {
    "status": "ok",
    "transactions": [
      {"date": "2026-04-15", "payee": "LOBLAWS #1234", "amount_cents": -12743, "raw_text": "..."}
    ],
    "warnings": []
  }
  ```
- Primary parser: `pdfplumber` with date+amount row heuristic.
- Fallback: `docling` when pdfplumber returns < 3 rows.
- Bank-specific parsers under `parsers/`, dispatched by detecting bank name in first-page text.
- Authenticated by shared secret header. Only the Laravel app calls it.
- Stateless: no DB, no file persistence beyond the request lifetime.

## 12. Reference implementation: upload flow

### 12.1 FormRequest

```php
// app/Http/Requests/UploadStatementRequest.php
class UploadStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'ulid', 'exists:accounts,id'],
            'statement'  => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('statement');
                if (!$file) return;

                $handle = fopen($file->getRealPath(), 'rb');
                $magic = fread($handle, 4);
                fclose($handle);

                if ($magic !== '%PDF') {
                    $validator->errors()->add('statement', 'File is not a valid PDF.');
                }
            },
        ];
    }
}
```

### 12.2 Controller

```php
// app/Http/Controllers/StatementImportController.php
class StatementImportController extends Controller
{
    public function store(UploadStatementRequest $request, UploadAndStageStatement $action): RedirectResponse
    {
        try {
            $upload = $action->handle(
                $request->file('statement'),
                $request->validated('account_id'),
                $request->user(),
            );
        } catch (DuplicateStatementException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        } catch (ParserException $e) {
            return back()->withErrors(['statement' => "Parser failed: {$e->getMessage()}"]);
        }

        return redirect()->route('import.review', $upload);
    }

    public function review(StatementUpload $statementUpload, StatementImportService $service): \Inertia\Response
    {
        return inertia('import/Review', [
            'upload' => $statementUpload,
            'staged' => $service->stagedForReview($statementUpload),
            'categories' => $service->categoryOptions(),
        ]);
    }

    public function promote(StatementUpload $statementUpload, ImportStagedTransactionsRequest $request, PromoteStagedTransactions $action): RedirectResponse
    {
        $action->handle($statementUpload, $request->validated('rows'), $request->user());
        return redirect()->route('transactions.index')->with('success', 'Transactions imported.');
    }
}
```

### 12.3 Pipeline stages

```php
// app/Actions/Statement/Pipeline/StatementImportContext.php
class StatementImportContext
{
    public ?StatementUpload $statementUpload = null;
    public string $hash = '';
    public array $parsedTransactions = [];

    public function __construct(
        public string $bytes,
        public string $originalFilename,
        public int $sizeBytes,
        public string $accountId,
        public User $user,
    ) {}
}
```

```php
// app/Actions/Statement/Pipeline/HashAndCheckDuplicate.php
class HashAndCheckDuplicate
{
    public function __invoke(StatementImportContext $ctx, Closure $next): mixed
    {
        $ctx->hash = hash('sha256', $ctx->bytes);

        $existing = StatementUpload::where('file_sha256', $ctx->hash)->first();
        if ($existing) {
            throw new DuplicateStatementException(
                "Already imported on {$existing->uploaded_at->format('Y-m-d')}."
            );
        }

        return $next($ctx);
    }
}
```

```php
// app/Actions/Statement/Pipeline/RecordStatementUpload.php
class RecordStatementUpload
{
    public function __invoke(StatementImportContext $ctx, Closure $next): mixed
    {
        $ctx->statementUpload = StatementUpload::create([
            'account_id' => $ctx->accountId,
            'original_filename' => $ctx->originalFilename,
            'file_sha256' => $ctx->hash,
            'file_size_bytes' => $ctx->sizeBytes,
            'status' => 'parsing',
            'uploaded_by_user_id' => $ctx->user->id,
            'uploaded_at' => now(),
        ]);

        return $next($ctx);
    }
}
```

```php
// app/Actions/Statement/Pipeline/ForwardToParser.php
class ForwardToParser
{
    public function __invoke(StatementImportContext $ctx, Closure $next): mixed
    {
        $response = Http::timeout(60)
            ->withHeaders(['x-parser-secret' => config('services.parser.secret')])
            ->attach('file', $ctx->bytes, $ctx->originalFilename, ['Content-Type' => 'application/pdf'])
            ->post(config('services.parser.url') . '/parse');

        if (!$response->successful()) {
            $ctx->statementUpload->update([
                'status' => 'failed',
                'error_message' => "Parser returned {$response->status()}",
            ]);
            throw new ParserException("Parser returned {$response->status()}");
        }

        $ctx->parsedTransactions = $response->json('transactions', []);

        // Release bytes — no longer needed in this request
        $ctx->bytes = '';

        return $next($ctx);
    }
}
```

### 12.4 Svelte upload page

```svelte
<!-- resources/js/pages/import/Create.svelte -->
<script lang="ts">
  import { useForm } from '@inertiajs/svelte';
  import type { Account } from '$lib/types';

  let { accounts }: { accounts: Account[] } = $props();

  const form = useForm({
    account_id: '',
    statement: null as File | null,
  });

  function submit(e: Event) {
    e.preventDefault();
    $form.post('/import', { forceFormData: true });
  }
</script>

<h1>Import statement</h1>

<form onsubmit={submit}>
  <label>
    Account
    <select bind:value={$form.account_id} required>
      <option value="">—</option>
      {#each accounts as account}
        <option value={account.id}>{account.name}</option>
      {/each}
    </select>
    {#if $form.errors.account_id}<p class="error">{$form.errors.account_id}</p>{/if}
  </label>

  <label>
    PDF
    <input
      type="file"
      accept="application/pdf"
      onchange={(e) => $form.statement = e.currentTarget.files?.[0] ?? null}
      required
    />
    {#if $form.errors.statement}<p class="error">{$form.errors.statement}</p>{/if}
  </label>

  <button type="submit" disabled={$form.processing}>
    {$form.processing ? 'Parsing…' : 'Upload'}
  </button>
</form>
```

### 12.5 What the agent MUST NOT do

- Never call `$file->store()`, `$file->move()`, `Storage::put()`, or any method that persists the PDF beyond PHP's automatic tmp-file lifecycle.
- Never log PDF contents, raw payee strings, or amounts at info level. Debug only, gated by env flag.
- Never retain `$ctx->bytes` after `ForwardToParser` runs (it's set to `''` so PHP can GC it).
- Never put bytes in a queue, cache, or session.

### 12.6 PHP config for true zero-disk

PHP buffers uploads to `upload_tmp_dir`. To eliminate even this brief disk touch, mount it on `tmpfs`:

```yaml
# docker-compose.yaml (app service excerpt)
services:
  app:
    tmpfs:
      - /tmp:size=128M
    environment:
      PHP_INI_UPLOAD_TMP_DIR: /tmp
```

Document this in the deploy README. It's the only piece of "zero disk" that requires container config rather than app code.

## 13. UI principles

- **Information density over whitespace.** Inline editing wherever possible. Inspired by spreadsheets, not marketing pages.
- **Keyboard-first transaction entry.** Tab through fields, Enter to save, arrow keys for table nav. Tested in Playwright.
- **No modals for routine actions.** Use side panels (drawer) so users can reference the underlying list while editing.
- **Money formatting via the `<Money>` component only.** Never inline `.toFixed()`. Outflows in default text, inflows in green, negatives (overspent) in red. Always two decimals, currency symbol on leftmost column.
- **Optimistic updates** for assignments and category edits (`router.patch(..., { preserveScroll: true, preserveState: true })`). Pessimistic for transactions, transfers, reconciliation.
- **Empty states tell you what to do next.** No empty pages without a clear CTA.

## 14. Auth & access control

- **Laravel Breeze with the Inertia-Svelte starter.** Email + password. No OAuth.
- All non-auth routes require `auth` middleware.
- **Self-registration disabled.** First user via `php artisan app:create-first-user`. Second user via signed invite link from `/settings`:
  - User clicks "Invite partner" → enters email → server creates `invites` row with random token, 7-day expiry, returns the URL.
  - v1.0 has no transactional email; the inviter copies the URL and sends it manually.
  - Recipient opens `/invites/{token}` → sets password → account is created and linked.
- Sessions: Laravel default file/cookie driver. 30-day "remember me" by default.
- Authorization: a single Gate `is-household-member` defined in `AuthServiceProvider`, used in every `FormRequest::authorize()`. In v1 it's "user is authenticated" — but defining it now means future row-level checks are a one-file refactor, not all FormRequests.

## 15. Phasing

**v1.0 — MVP:**
Everything in §6–§14 minus:
- Dashboard customization (fixed layout in v1.0).
- Goal progress card on dashboard.
- Discrepancy-accepted reconciliation path (v1.0 = matched or no reconciliation).
- Bank-specific PDF parsers (generic pdfplumber + docling only).
- CSV import.
- Transactional email for invites.

**v1.1:**
- Discrepancy reconciliation flow.
- Dashboard customization (`dashboard_preferences` table per user).
- Goal progress card.
- CSV import alongside PDF.
- Scheduled stale-upload cleanup.
- Email-sent invites (Mailgun or Postmark).

**v1.2+:**
- Bank-specific PDF parsers as quirks emerge.
- Category history charts.
- Year-over-year comparison.
- Recurring transaction templates.

## 16. Open questions for the user

Agent should ask these at the start, not block on them:

1. **Hosting target:** self-hosted on a home server (Docker Compose) or hosted (Laravel Forge, fly.io, Sevalla)? Affects deploy scripts.
2. **Backup strategy for SQLite:** encrypted S3? Local rotation? Nothing automated?
3. **Initial category seed:** ship with a default set or start empty?
4. **Currency & locale:** confirm CAD with `en-CA` formatting.
5. **Email infra:** confirm v1.0 has no transactional email (copy-paste invite URLs). Acceptable, or add it now?
6. **`tmpfs` for uploads:** does the deployment host support `tmpfs` mounts? If not, accept the brief PHP tmp-file disk touch and document it.

## 17. Definition of done for v1.0

- Two users can sign in concurrently and see each other's edits after refresh.
- A user can: create accounts, create categories, assign money for a month, enter transactions manually, transfer between accounts, upload a PDF and import its transactions, reconcile an account against a statement, view the dashboard.
- Every dollar of income, once assigned, appears in the correct category's Available balance.
- All money operations are idempotent on retry.
- Carryover across months works: $50 assigned in January and $30 spent leaves $20 Available in February with no user action.
- Reconciliation correctly flags transactions as reconciled and prevents accidental edits without explicit unlock.
- **PDFs are never persisted by application code.** Verified by automated test: upload a PDF, assert no file in `storage/`, no DB BLOB contains the bytes. `tmpfs` config documented for true zero-disk operators.
- App runs locally via `php artisan serve` + `python -m uvicorn parser:app` and via a single `docker compose up`.
- Critical flows have Playwright coverage: login, create transaction, upload PDF + import, reconcile.

---

**Agent build order:**
1. Project scaffold via Laravel installer with the Inertia-Svelte Breeze starter. Disable public registration. Add `app:create-first-user` command.
2. Migrations + Eloquent models for the full §7 schema. Factories + a dev seeder.
3. Accounts + Categories CRUD: singular controller, `Store`/`Update` FormRequests, services, Svelte index/show pages.
4. Transactions CRUD with keyboard-driven inline entry. `TransactionService` + `CreateTransaction` Action.
5. Plan page + assignment endpoints + `BudgetService` Available math. `PlanState` class on the frontend.
6. Payee rules + auto-suggestion endpoint on payee blur.
7. Transfers via `TransferBetweenAccounts` Action.
8. Reconciliation flow + `ReconcileAccount` Action.
9. PDF parser service (Python) and Docker Compose wiring.
10. Statement import: FormRequest, `UploadAndStageStatement` Pipeline Action, Review page, `PromoteStagedTransactions` Action.
11. Dashboard.
12. Invites + Settings.
13. Playwright happy-path coverage + README.

Confirm each milestone manually (and with a smoke test) before moving on. Ask §16 questions before step 1.