# Feature Checklist — Instructor Revenue & Payout Ledger System

> **Status Legend:** `[ ]` Not started · `[/]` In progress · `[x]` Complete

---

## Phase 1: Database Foundation

- [x] **1.1** Modify `users` table — add `is_instructor` boolean column
- [x] **1.2** Create `subscriptions` table with plan tiers, price_cents, platform_fee_bps, status, starts_at, ends_at
- [x] **1.3** Create `subscription_periods` table with period_number, period_amount_cents, period_start, period_end, status + unique index `(subscription_id, period_number)`
- [x] **1.4** Create `payout_batches` table with batch_uuid (UK), status, total_cents
- [x] **1.5** Create `payouts` table with instructor_id, amount_cents, status FSM, idempotency_key (UK), provider_tx_id, failure_reason, attempts
- [x] **1.6** Create `instructor_ledger_entries` table with signed BIGINT amount_cents, type enum, idempotency_key (UK), nullable payout_id FK, nullable subscription_period_id FK, metadata JSON
- [x] **1.7** Apply all compound indexes per SYSTEM_ARCHITECTURE_AND_ERD.md (idx_instructor_type, idx_period_id, idx_reconciliation, idx_instructor_status)

---

## Phase 2: Eloquent Models

- [x] **2.1** `User` model — `is_instructor` cast, `subscriptions()`, `ledgerEntries()`, `payouts()` relationships
- [x] **2.2** `Subscription` model — `plan_tier` enum cast, `periods()` relationship, scopes: `active()`, `upgraded()`
- [x] **2.3** `SubscriptionPeriod` model — `status` enum cast, `ledgerEntries()` relationship, scopes: `pending()`, `distributed()`
- [x] **2.4** `PayoutBatch` model — `payouts()` relationship, `status` enum cast
- [x] **2.5** `Payout` model — `instructor()`, `batch()`, `ledgerEntries()` relationships, `status` enum cast, `attempts` increment helpers
- [x] **2.6** `InstructorLedgerEntry` model — `type` enum cast, `instructor()`, `payout()`, `subscriptionPeriod()` relationships, `amount_cents` integer cast

---

## Phase 3: Revenue Recognition Service

- [x] **3.1** `RevenueAllocationService::allocatePeriod(SubscriptionPeriod $period, Collection $consumptionWeights)` — accepts weighted instructor consumption data
- [x] **3.2** Platform fee deduction logic (floor division in basis points)
- [x] **3.3** Hamilton-Hare (Largest Remainder) algorithm for distributing the pool among instructors
- [x] **3.4** Penny-perfect invariant assertion: `sum(instructor shares) + platform_cut == period_gross`
- [x] **3.5** Bulk-insert `InstructorLedgerEntry` records (type: `earning`) with deterministic idempotency keys
- [x] **3.6** Mark `SubscriptionPeriod` status as `distributed` after successful allocation
- [x] **3.7** Unit test: `test_remainder_allocation_zero_penny_leak`

---

## Phase 4: Ledger Service

- [x] **4.1** `LedgerService::getBalance(int $instructorId): int` — `SUM(amount_cents)` with no mutable state
- [x] **4.2** `LedgerService::bookEntry(array $attributes): InstructorLedgerEntry` — insert with idempotency_key conflict protection
- [x] **4.3** `LedgerService::bookWithhold(Payout $payout): InstructorLedgerEntry` — negative entry under pessimistic lock
- [x] **4.4** `LedgerService::settleHold(Payout $payout): void` — convert withhold to `payout_settled` (same amount / key; no second debit)
- [x] **4.5** `LedgerService::releaseHold(Payout $payout): void` — book offsetting `adjustment` credit on failure

---

## Phase 5: Payout Processing

- [x] **5.1** `RunScheduledPayoutsCommand` artisan command — Redis distributed lock (`payouts_execution_lock`, 7200s TTL)
- [x] **5.2** Stream instructors via `chunkById(250)` to prevent memory exhaustion
- [x] **5.3** Create `PayoutBatch` record before dispatching jobs
- [x] **5.4** Dispatch `ProcessInstructorPayoutJob` per eligible instructor
- [x] **5.5** `ProcessInstructorPayoutJob` — idempotent `firstOrCreate` on payout record
- [x] **5.6** Row-level pessimistic lock on instructor record during balance calculation
- [x] **5.7** Threshold check: skip if balance < 2,000 cents, mark payout FAILED
- [x] **5.8** Phase 1: Book `payout_withhold` ledger entry inside DB transaction
- [x] **5.9** Phase 2: Call `MockPaymentProvider::executePayout()` outside DB transaction
- [x] **5.10** On gateway success: mark payout `PAID`, convert withhold to `payout_settled`
- [x] **5.11** On gateway `4xx/5xx`: mark payout `FAILED`, book `adjustment` credit, set `failure_reason`
- [x] **5.12** On `GatewayTimeoutException`: mark payout `UNCERTAIN`, leave withhold intact
- [x] **5.13** Increment `attempts` counter on each processing attempt
- [x] **5.14** Unit test: `test_running_payout_job_twice_never_double_pays`
- [x] **5.15** Unit test: `test_timeout_sets_status_to_uncertain_without_leaking_money`

---

## Phase 6: Payment Gateway Mock

- [x] **6.1** `GatewayResult` value object — `status`, `provider_tx_id`, `message`
- [x] **6.2** `GatewayTimeoutException` custom exception class
- [x] **6.3** `MockPaymentProvider::executePayout(string $idempotencyKey, int $amountCents): GatewayResult` — tri-state (success/failure/timeout) based on deterministic hash
- [x] **6.4** `MockPaymentProvider::verifyStatus(string $idempotencyKey): GatewayResult` — reconciliation lookup
- [x] **6.5** Configurable simulation probabilities (env-driven: success %, failure %, timeout %)

---

## Phase 7: Reconciliation Worker

- [x] **7.1** `ReconcileUncertainPayoutsJob` — query `uncertain` payouts older than 5 minutes
- [x] **7.2** Call `MockPaymentProvider::verifyStatus()` per uncertain payout
- [x] **7.3** On confirmed paid: mark payout `PAID`, convert withhold to `payout_settled`
- [x] **7.4** On confirmed non-existent: mark payout `FAILED`, book `adjustment` credit
- [x] **7.5** Schedule reconciliation job to run every 10 minutes via Laravel scheduler (`payouts:run` daily at 02:00)
- [x] **7.6** Unit test: `test_reconciliation_resolves_uncertain_payout_correctly`

---

## Phase 8: Senior Bonus — Mid-Term Plan Upgrades

- [x] **8.1** `SubscriptionUpgradeService::upgrade(Subscription $old, string $newTier, int $newPriceCents): Subscription`
- [x] **8.2** Calculate daily accrual rate and consumed days for the active period
- [x] **8.3** Prorate and immediately distribute the consumed period (update amount, set status `distributed`)
- [x] **8.4** Mark remaining pending periods as `voided`
- [x] **8.5** Mark old subscription as `upgraded`
- [x] **8.6** Calculate credit (unearned active + voided pending) for the new invoice
- [x] **8.7** Create new subscription at full new-plan recognized value (credit applied on invoice math)
- [x] **8.8** Generate all new amortization periods for the new plan
- [x] **8.9** Invariant check: `credit_applied + net_cash == new_plan_total_value`
- [x] **8.10** Unit test: prorated upgrade preserves zero ledger drift

---

## Phase 9: Admin Dashboard (Filament)

- [x] **9.1** Install Filament package (v4)
- [x] **9.2** `InstructorBalanceResource` — list view with computed balance per instructor
- [x] **9.3** Ledger entry detail view with type badge, amount, idempotency_key, metadata
- [x] **9.4** Payout history view per instructor (status badges, provider_tx_id, attempts)
- [x] **9.5** PayoutBatch list view with total_cents and completion status
- [x] **9.6** Filter by payout status (pending, paid, failed, uncertain)
- [x] **9.7** Subscription periods panel — amortization schedule per subscription
- [x] **9.8** Dashboard home — finance stats + payout/subscription/ledger charts (`AdminDashboardService`)
- [x] **9.9** User management — `UserResource` create/edit via `UserAdminService` (roles + direct permissions)
- [x] **9.10** Roles & permissions — `RoleResource` sync via `UserAdminService` (Spatie; no create/delete of roles)

---

## Phase 10: Quality & NFRs

- [x] **10.1** Ensure ALL currency columns are `BIGINT` (zero float columns)
- [x] **10.2** Ensure every ledger row has a unique `idempotency_key` — validated by DB unique index
- [x] **10.3** Feature test: concurrent payout dispatch does not produce duplicate ledger entries
- [ ] **10.4** Performance test: `chunkById(250)` over 500k instructor records stays under 512MB RAM
- [x] **10.5** Verify all migrations include `down()` rollbacks
- [x] **10.6** Add PHPDoc blocks and inline comments to all service classes
- [x] **10.7** Spatie Permission catalog (`PermissionName` / `RolePermissionMap`) + `RolesAndPermissionsSeeder`
- [x] **10.8** Policies use Spatie permissions; FormRequests authorize via policies; Filament gates on same constants