# AI Usage & Engineering Ownership Record

---

## 1. Tooling & Declaration
* **Tools Consulted:** Gemini, GitHub Copilot.
* **Usage Objective:** Rapid prototyping, scaffolding migrations, stress-testing distributed race conditions, and drafting unit test fixtures.

---

## 2. Collaborative Workflow & Prompts

### Phase 1: Accounting Model Selection
* **AI Initial Proposal:** Immediate cash allocation upon student subscription purchase.
* **Human Engineering Override:** Rejected. Enforced **Accrual Deferred Revenue Amortization** model where earnings unlock periodically through `subscription_periods`.

### Phase 2: Gateway Fault-Tolerance
* **AI Initial Proposal:** Standard queue retries with exponential backoff on exceptions.
* **Human Engineering Override:** Rejected. Retrying blind on network timeouts causes duplicate bank transfers. Enforced a **Tri-State Lifecycle** (`PAID`, `FAILED`, `UNCERTAIN`) backed by an asynchronous reconciliation worker.

### Phase 3: Remainder Math
* **Human Engineering Directive:** Mandated the **Hamilton-Hare (Largest Remainder) Method** to eliminate penny leakage.
* **AI Contribution:** Generated the sorting and distribution logic to allocate base integer values and distribute remainder cents by rank.

---

## 3. Ownership Matrix

| Area | Authorship | Description |
|:---|:---|:---|
| **Accrual Accounting Architecture** | Human | Designed deferred revenue recognition and period partitioning. |
| **Database Schema & Indexes** | Collaborative | AI scaffolded migrations; Human added signed BIGINT amounts and compound unique idempotency indexes. |
| **Two-Phase Payout Job** | Human | Designed hold reservation, decoupled network I/O, and settlement pipeline. |
| **Hamilton-Hare Algorithm** | Collaborative | Human specified mathematical rules; AI drafted implementation. |
| **Mock Provider State Machine** | Collaborative | Deterministic hash-based simulation of network timeouts. |
| **Filament Admin Dashboard** | AI | Finance resources, dashboard charts, Spatie Users/Roles management via services. |
| **Pessimistic Locking & Isolation** | Human | Configured row-level locks and transaction boundaries. |

---

## 4. Key Differentiators
1. **Append-Only Immutable Ledger:** Eliminates mutable balance columns, preventing lost updates and race conditions.
2. **Decoupled Gateway Execution:** DB transactions committed prior to network I/O, protecting connection pools from exhaustion.
3. **Multi-Layered Concurrency Defense:** Redis locks + DB row-level locking + physical DB unique constraints.
