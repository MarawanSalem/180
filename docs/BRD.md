# Business Requirements Document (BRD)
## Instructor Revenue & Payout Ledger System

---

## 1. Project Background & Core Mission
The platform operates as a multi-instructor subscription Learning Management System (LMS). Students pay upfront for platform access across three tiers:
* **Monthly** (1 month)
* **3-Month** (Quarterly)
* **Annual** (12 months)

A single subscription gives students unrestricted access to courses created by multiple independent instructors. The platform retains a configured commission (platform cut), while the remainder is divided among the instructors whose content was consumed.

**Core Mission:** Build a zero-loss, audit-compliant financial core that governs revenue recognition, accurate profit splits, and resilient automated payouts capable of handling 500,000 active subscriptions and tens of millions of records without double-paying or leaking funds during network, infrastructure, or worker failures.

---

## 2. Revenue Recognition Rules (Accrual Accounting)

### 2.1 Upfront Cash vs. Deferred Revenue
* **Constraint:** Students pay 100% upfront on day one.
* **Accounting Model:** Subscription payments cannot be treated as immediately earned platform income or instructor revenue. Cash collected upfront is booked as **Deferred Revenue (Liability)**.
* **Amortization Windows:**
  * **Monthly Plan:** Amortized across 1 discrete recognition period (30 days).
  * **3-Month Plan:** Amortized across 3 equal recognition periods (~30 days each).
  * **Annual Plan:** Amortized across 12 equal recognition periods (~30 days each).

### 2.2 Recognition Trigger
* Revenue is recognized incrementally at the end of each discrete period (`subscription_periods`).
* The allocated amount for that specific window is distributed strictly to the instructors whose courses were viewed during that exact period, calculated against platform consumption weights.

---

## 3. Allocation Formula & The Penny-Perfect Invariant

### 3.1 Platform Fee Deduction
The platform cut is defined in basis points (platform_fee_bps, where 100 bps = 1.00%, and 3000 bps = 30.00%).

  Platform Cut Cents = floor( Period Gross Cents * Platform Fee BPS / 10000 )
  Distributable Pool Cents = Period Gross Cents - Platform Cut Cents

### 3.2 The Hamilton-Hare (Largest Remainder) Method
When dividing money among multiple instructors, standard integer division creates fractional cent remainders.

The system enforces the **Hamilton-Hare Method**:
1. For each instructor i with consumption weight w_i, calculate their exact unrounded share.
2. Assign the integer floor as base pay: B_i = floor(S_i).
3. Compute the fractional remainder: R_i = S_i - B_i.
4. Determine remaining pennies: P = Distributable Pool - sum(B_i).
5. Sort instructors by R_i descending. Allocate 1 cent to each of the top P instructors.
6. **Strict Invariant:** sum(Allocated Instructor Shares) + Platform Cut = Total Period Gross.

---

## 4. Payout Rules, Settlement & Fault-Tolerance

### 4.1 Eligibility & Thresholds
* Instructors qualify for a payout only when their net payable balance reaches or exceeds **$20.00 (2,000 cents)**.
* Balances below this threshold remain in the ledger until future cycles accumulate sufficient funds.

### 4.2 Append-Only Ledger Model
* **No Mutable Balances:** No table contains a mutable balance column.
* Credits are booked as positive integers; debits and withholdings are booked as negative integers.

### 4.3 Two-Phase Settlement & Gateway Lifecycles
1. **Phase 1 (Reserve Hold):** Book a negative ledger entry (payout_withhold) under pessimistic lock.
2. **Phase 2 (Gateway Dispatch):** External gateway API is called outside the database transaction.
   * **Success:** Status → PAID; hold → payout_settled.
   * **Permanent Failure:** Status → FAILED; adjustment credit restores balance.
   * **Gateway Timeout:** Status → UNCERTAIN. Async reconciliation job verifies state.

---

## 5. Non-Functional Requirements (NFRs)
* **Zero Floating-Point Drift:** Every currency column is a BIGINT (cents).
* **Storage-Level Idempotency:** Deterministic idempotency_key on every transaction.
* **Concurrency Protection:** Redis lock + row-level DB lock + unique DB constraints.
* **High Scale:** Support 500,000 active subscriptions and tens of millions of ledger records.
