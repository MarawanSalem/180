# System Architecture, Concurrency Controls & Database ERD

---

## 1. Database Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS ||--o{ SUBSCRIPTIONS : "purchases"
    USERS ||--o{ INSTRUCTOR_LEDGER_ENTRIES : "owns"
    USERS ||--o{ PAYOUTS : "receives"

    SUBSCRIPTIONS ||--|{ SUBSCRIPTION_PERIODS : "amortizes into"
    SUBSCRIPTION_PERIODS ||--o{ INSTRUCTOR_LEDGER_ENTRIES : "yields earnings"

    PAYOUT_BATCHES ||--o{ PAYOUTS : "groups"
    PAYOUTS ||--o{ INSTRUCTOR_LEDGER_ENTRIES : "creates holds/settlements"

    USERS {
        bigint id PK
        string name
        string email
        boolean is_instructor
        timestamp created_at
        timestamp updated_at
    }

    SUBSCRIPTIONS {
        bigint id PK
        bigint user_id FK
        enum plan_tier "monthly, three_month, annual"
        unsigned_bigint price_cents
        unsigned_bigint platform_fee_bps
        timestamp starts_at
        timestamp ends_at
        string status "active, canceled, refunded, upgraded"
        timestamp created_at
        timestamp updated_at
    }

    SUBSCRIPTION_PERIODS {
        bigint id PK
        bigint subscription_id FK
        unsigned_int period_number
        unsigned_bigint period_amount_cents
        date period_start
        date period_end
        string status "pending, distributed, refunded, voided"
        timestamp created_at
        timestamp updated_at
    }

    PAYOUT_BATCHES {
        bigint id PK
        uuid batch_uuid UK
        string status "processing, completed, partially_failed"
        unsigned_bigint total_cents
        timestamp created_at
        timestamp updated_at
    }

    PAYOUTS {
        bigint id PK
        bigint payout_batch_id FK
        bigint instructor_id FK
        unsigned_bigint amount_cents
        string status "pending, processing, paid, failed, uncertain"
        string idempotency_key UK
        string provider_tx_id
        string failure_reason
        unsigned_int attempts
        timestamp created_at
        timestamp updated_at
    }

    INSTRUCTOR_LEDGER_ENTRIES {
        bigint id PK
        bigint instructor_id FK
        bigint payout_id FK "nullable"
        bigint subscription_period_id FK "nullable"
        enum type "earning, payout_settled, payout_withhold, clawback, adjustment"
        bigint amount_cents "signed: positive credit, negative debit"
        string idempotency_key UK
        json metadata
        timestamp created_at
    }
```

---

## 2. Table Indexing & Database Integrity Rules

### `instructor_ledger_entries`
* `PRIMARY KEY (id)`
* `UNIQUE INDEX uq_ledger_idempotency (idempotency_key)` — prevents duplicate allocations/holds.
* `INDEX idx_instructor_type (instructor_id, type)` — accelerates balance `SUM(amount_cents)`.
* `INDEX idx_period_id (subscription_period_id)` — speeds up period-level auditing.

### `payouts`
* `PRIMARY KEY (id)`
* `UNIQUE INDEX uq_payout_idempotency (idempotency_key)` — one payout record per batch per instructor.
* `INDEX idx_instructor_status (instructor_id, status)` — verify existing active/pending payouts.
* `INDEX idx_reconciliation (status, updated_at)` — locate `uncertain` payouts older than 5 minutes.

### `subscription_periods`
* `UNIQUE INDEX uq_sub_period (subscription_id, period_number)` — enforces correct chronological accrual.

---

## 3. Concurrency Protection Layers

```
Layer 1: Distributed Redis Cache Lock (Command Entrypoint)
  │── Command: Cache::lock('payouts_execution_lock', 7200)
  └── Eliminates overlapping execution across multiple web/cron instances.
       │
Layer 2: Deterministic Chunk Streaming
  │── User::where('is_instructor', true)->chunkById(250)
  └── Eliminates memory leaks and cursor drift across 500k records.
       │
Layer 3: Row-Level Pessimistic Locking (Within DB Transaction)
  │── User::where('id', $instructorId)->lockForUpdate()
  └── Serializes balance calculations per instructor; blocks competing transactions.
       │
Layer 4: Two-Phase Balance Withholding (Append-Only Ledger)
  │── Books negative 'payout_withhold' entry before touching network
  └── Instantly zeros out available balance, preventing race conditions.
       │
Layer 5: Decoupled Gateway Network Call (Outside DB Transaction)
  │── MockPaymentProvider::executePayout()
  └── DB transaction committed before HTTP call; prevents connection pool exhaustion.
       │
Layer 6: Unique Database Constraints (InnoDB Engine Layer)
  └── Physical defense against double-inserts if application-level checks are breached.
```

---

## 4. Payout Finite State Machine

```mermaid
stateDiagram-v2
    [*] --> PENDING : Batch Dispatched
    PENDING --> PROCESSING : Balance >= $20 (Withholding Booked under Lock)
    PENDING --> FAILED : Balance < $20 Threshold

    PROCESSING --> ExternalGateway : Call executePayout() [Outside DB Tx]

    ExternalGateway --> PAID : 200 OK (Hold converted to payout_settled)
    ExternalGateway --> FAILED : 4xx/5xx Permanent Error (Adjustment rollback booked)
    ExternalGateway --> UNCERTAIN : Timeout / 504 / Dropped Socket

    UNCERTAIN --> ReconcileWorker : verifyStatus() polled every 10 mins
    ReconcileWorker --> PAID : Gateway Confirms Execution
    ReconcileWorker --> FAILED : Gateway Confirms Non-Existence

    PAID --> [*]
    FAILED --> [*]
```
