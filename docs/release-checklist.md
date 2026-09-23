# Earmark release checklist

Parity target: **K2412/planning#1243** — *"reach Canadian open-source household-finance
parity without bank sync."* This checklist maps each of the epic's 23 user stories to the
shipped capability and the automated evidence that proves it, or records an explicit,
deliberate limitation.

**The product promise:** your household data, your server, Canadian rules, and calculations
you can inspect.

Run the full evidence suite with:

```bash
php artisan test --compact
```

## Story coverage

| # | User story | Status | Where it lives | Evidence |
|---|------------|--------|----------------|----------|
| 1 | Open-source licence + operational docs | ✅ Shipped | `LICENSE` (MIT), `README.md`, `docs/self-hosting.md` (install, upgrade, health, backup, restore, deletion, recovery) | Docs reviewed at release |
| 2 | Create/edit/archive/reorder/reconcile accounts | ✅ Shipped | `AccountController`, `ReconciliationController` | `Household/AccountLifecycleTest`, `Household/ReconciliationTest`, browser `AccountLifecycleTest`, `ReconcileTest` |
| 3 | Fast manual transaction entry as first-class | ✅ Shipped | `TransactionController@store` | `Household/TransactionRegisterTest`, browser `TransactionRegisterTest` |
| 4 | Upload statements (CSV + OFX/QFX/QBO/QIF) | ✅ Shipped | `ImportController` (`store`, `storeStructured`); `CsvImportNormalizer`, `OfxImportNormalizer`, `QifImportNormalizer` | `Household/ImportPageTest`, `ImportReviewTest`, `StructuredImportTest`, `Services/OfxImportNormalizerTest`, `Services/QifImportNormalizerTest`, browser `ImportPageTest`, `StructuredImportTest` |
| 5 | Local PDF/image scan into draft transactions | ⏸️ Deferred | — | See limitation **L1** below |
| 6 | Review/correct/split/accept/reject imported rows | ✅ Shipped | `ImportController@review/updateStaged/promote` (staging → atomic promotion) | `Household/ImportReviewTest`, browser `ImportReviewTest` |
| 7 | Duplicate import/transaction prevention (no silent discards) | ✅ Shipped | `DuplicateDetector` — content fingerprint, plus exact bank-id (OFX `FITID`) matching when present | `Household/ImportReviewTest`, `StructuredImportTest`, `LedgerIntegrityTest` |
| 8 | Search/filter/edit/delete/clear/review/split/audit transactions | ✅ Shipped | `TransactionController`, `TransactionActivityLogger` | `Household/TransactionRegisterTest`, `LedgerIntegrityTest`, browser `TransactionRegisterTest` |
| 9 | Understandable payee rules with previews | ✅ Shipped | `PayeeRuleController`, `PayeeRuleService` | `Household/PayeeRuleManagementTest`, browser `RulesPageTest` |
| 10 | Transfers & reconciliations preserve balances | ✅ Shipped | `TransferController`, `ReconciliationController` | `Household/TransfersPageTest`, `ReconciliationTest`, `LedgerIntegrityTest` |
| 11 | Fund buckets, roll forward, plan non-monthly obligations | ✅ Shipped | `PlanController`, `BudgetService` | `Household/BudgetPlanTest`, `PlanPageTest`, browser `PlanBudgetTest` |
| 12 | Recurring bills/subscriptions/income surfaced | ✅ Shipped | `RecurringController` | `Household/RecurringTest`, browser `RecurringTest` |
| 13 | Save-up / pay-down goals linked to accounts & buckets | ✅ Shipped | `GoalController` | `Household/GoalTest`, browser `GoalsPageTest` |
| 14 | Cash-flow/spending/income/budget/net-worth reports + saved filters + export | ✅ Shipped | `ReportController` (incl. `@export`) | `Household/ReportingTest`, browser `ReportsPageTest` |
| 15 | Historical net worth keeping investable / home equity / future-home fund / liabilities distinct | ✅ Shipped | `NetWorthController`, `SnapshotService` | `Household/NetWorthHistoryTest`, `NetWorthPageTest`, browser `NetWorthPageTest` |
| 16 | TFSA/RRSP/FHSA/RESP/taxable with ownership & contribution context | ✅ Shipped | `RegisteredController` | `Household/RegisteredProgrammeTest`, browser `RegisteredPageTest` |
| 17 | HBP withdrawals & required repayments tracked separately | ✅ Shipped | `RegisteredController` (HBP obligation versioning) | `Household/RegisteredProgrammeTest` |
| 18 | Versioned Canadian limits / CPP-OAS / inflation / tax inputs with source dates & overrides | ✅ Shipped | `AssumptionController`, `CanadianAssumptionCatalogue` | `Household/AssumptionCatalogueTest`, browser `AssumptionsPageTest` |
| 19 | Scenarios (children, home, career, windfalls, contributions, drawdown, benefits) | ✅ Shipped | `ScenarioController`, `ScenarioService` | `Household/ScenarioProjectionTest`, browser `ScenariosPageTest` |
| 20 | Manually maintained/imported holdings, allocation, contributions, performance | ✅ Shipped | `HoldingController` | `Household/InvestmentPortfolioTest`, browser `HoldingsPageTest` |
| 21 | Shared views & review assignments (incl. read-only advisor) without shadow copies | ✅ Shipped | `MemberController`, `EnsureAdvisorReadOnly` middleware, `HouseholdRole` | `Household/CollaborationTest`, `MembersPageTest`, browser `CollaborationTest`, `MembersPageTest` |
| 22 | Complete export / backup / restore / deletion | ✅ Shipped | `PortabilityController`, `PortabilityService`, `docs/self-hosting.md` | `Household/PortabilityTest`, browser `PortabilityPageTest` |
| 23 | Responsive full critical workflow, installable PWA | ✅ Shipped | Responsive Svelte pages, `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, configurable overview (`DashboardController`) | `PwaTest`, `DashboardTest`, browser `OverviewPageTest` |

## Documented limitations

### L1 — Local PDF/image statement scanning is deferred (Story 5)

Deferred by explicit decision (planning task **#1248** left unbuilt for this release). CSV
import is the supported bulk-entry path; manual entry remains first-class for everything a CSV
does not cover.

- **Contract already in place:** the statement-upload → staged-transaction → atomic-promotion
  lifecycle (Stories 4, 6, 7) is the same pipeline a future extractor will emit into. Adding
  PDF/image extraction is additive — it produces the existing normalized draft-row contract and
  never writes ledger rows directly.
- **Privacy stance unchanged:** per the epic's implementation decisions, statement/receipt bytes
  are not durably stored by default; only fingerprints, provenance, parser version, and accepted
  structured data persist. A local (browser-side) extractor preserves that stance; any attachment
  retention is a separate, explicit future decision.

### PWA scope (Story 23)

The service worker (`public/sw.js`) is deliberately **network-first with no financial-data
caching**. It caches only the offline shell (`public/offline.html`); household data is never
written to the cache, so the installed app makes no unexpected network requests and never serves
stale financial figures. When offline, navigations fall back to a plain "you're offline" page
rather than showing cached balances. This is enforced by `PwaTest` ("the service worker never
caches financial data").

## Out of scope (by epic decision)

Live bank sync / aggregation is intentionally excluded — no Plaid, MX, Flinks, Mastercard Data
Connect, or provider-shaped schema. Imports and scans accelerate manual entry; they never
replace it.
