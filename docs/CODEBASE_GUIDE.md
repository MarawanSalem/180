# Codebase Guide & Real-World Failure Scenarios

---

## 1. Directory Structure

```text
app/
├── Console/Commands/
│   └── RunScheduledPayoutsCommand.php           # Distributed locked payout initiator
├── DTOs/{Feature}/                              # RegisterDTO, CreateSubscriptionDTO, …
├── Enums/                                       # PlanTier, PayoutStatus, RoleName, …
├── Exceptions/                                  # DomainException + feature exceptions
├── Filament/
│   ├── Pages/Dashboard.php                      # Stats + charts home
│   ├── Widgets/                                 # FinanceStatsOverview, payout/sub/ledger charts
│   └── Resources/
│       ├── InstructorBalances|Payouts|PayoutBatches|Subscriptions/
│       └── Users/ + Roles/                      # Spatie user + role/permission admin
├── Http/
│   ├── Controllers/Api/V1/{Feature}/
│   ├── Requests/Api/V1/{Feature}/
│   ├── Resources/Api/V1/{Feature}/
│   └── Routes/Api/V1/                           # {Feature}Routes::register()
├── Jobs/
│   ├── ProcessInstructorPayoutJob.php           # Idempotent 2-phase worker
│   └── ReconcileUncertainPayoutsJob.php         # Automated gateway status resolver
├── Models/
│   ├── InstructorLedgerEntry.php                # Append-only balance records
│   ├── Payout.php
│   ├── PayoutBatch.php
│   ├── Subscription.php
│   ├── SubscriptionPeriod.php
│   └── User.php                                 # HasRoles (Spatie)
├── Policies/                                    # User, Role, Subscription, Period, Payout, Batch
├── Repositories/{Interfaces,Eloquent}/
├── Services/
│   ├── AuthService.php                          # Sanctum token issue / revoke
│   ├── AdminDashboardService.php                # Filament dashboard aggregates
│   ├── LedgerService.php                        # Balance + withhold/settle/release
│   ├── PayoutService.php                        # Batch cycle + two-phase payout
│   ├── PayoutBatchService.php
│   ├── RevenueAllocationService.php             # Hamilton-Hare allocation
│   ├── SubscriptionService.php
│   ├── SubscriptionUpgradeService.php
│   ├── UserService.php
│   ├── UserAdminService.php                     # Filament user/role sync (shared rule sets)
│   └── Payment/
│       ├── GatewayResult.php
│       ├── GatewayTimeoutException.php
│       └── MockPaymentProvider.php
└── Support/
    ├── ApiResponse.php
    ├── Authorization/{PermissionName,RolePermissionMap}.php
    └── Pagination/PaginatedPayload.php
```

Portable API layering rules: `docs/reusable-laravel-api-guidelines.md`.

---

## 2. Failure Scenarios & Edge Cases

### Scenario A: Payment Gateway Drops Connection After Moving Money
* **Problem:** Gateway moves funds but connection drops before HTTP 200 reaches the app.
* **Flawed Response:** Marking as failed and releasing hold → double payout on next run.
* **Our Implementation:**
  1. Gateway wrapper catches the timeout and throws `GatewayTimeoutException`.
  2. Job marks payout status as `UNCERTAIN`.
  3. `payout_withhold` entry remains in the ledger; instructor balance stays zeroed.
  4. Job does not retry automatically.
  5. `ReconcileUncertainPayoutsJob` queries `MockPaymentProvider::verifyStatus($idempotencyKey)`.
  6. Provider returns confirmed state; payout transitions to `PAID`; ledger converts the existing withhold row's type to `payout_settled` (same amount and idempotency key — no second debit).

### Scenario B: Background Worker Crashes Mid-Execution
* **Problem:** Server crashes after booking ledger hold, before dispatching payment.
* **Our Implementation:**
  1. Queue worker picks job back up upon restart.
  2. `ProcessInstructorPayoutJob` uses `Payout::firstOrCreate(['idempotency_key' => ...])`.
  3. If payout is already `processing`, job recognizes the withholding entry is recorded.
  4. Skips re-locking/re-holding; resumes directly at the gateway execution stage.

### Scenario C: Overlapping Schedulers / Manual Re-trigger
* **Problem:** Ops engineer runs `php artisan payouts:run` while scheduler is already executing.
* **Our Implementation:**
  1. Command initiates atomic Redis lock: `Cache::lock('payouts_execution_lock', 7200)`.
  2. Second command terminates safely without dispatching redundant jobs.

### Scenario D: The Uneven Penny Distribution
* **Problem:** $100.00 pool (10,000 cents) split equally among 3 instructors.
* **Our Implementation:**
  1. `RevenueAllocationService` assigns base shares of 3,333 cents each (total: 9,999).
  2. 1 cent remains unallocated.
  3. Fractional remainders ranked (0.333... each).
  4. Instructor 1 receives the single remaining penny.
  5. Final: 3,334 / 3,333 / 3,333 cents. Sum = 10,000. Zero drift.

---

## 3. Automated Test Verification

Run the test suite:
```bash
php artisan test --compact
php artisan test --filter=PayoutConcurrencyAndResilienceTest
```

| Test Case | What It Proves |
|---|---|
| `test_running_payout_job_twice_never_double_pays` | Retried jobs don't double-pay or duplicate ledger entries. |
| `test_timeout_sets_status_to_uncertain_without_leaking_money` | Timeouts lock payout to `UNCERTAIN` without leaking cash. |
| `test_reconciliation_resolves_uncertain_payout_correctly` | Async reconciliation safely settles uncertain records to `PAID`. |
| `test_remainder_allocation_zero_penny_leak` | Largest Remainder Method maintains zero penny leakage. |
| Auth / instructor authz feature tests | Envelope, Spatie permission gates on ledger & instructor index |
