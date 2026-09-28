# Senior Bonus: Mid-Term Plan Upgrades & Prorated Accounting

---

## 1. The Scenario
A student purchases a **Monthly Subscription ($30.00)** on Day 1. On **Day 10**, the student upgrades to an **Annual Subscription ($300.00)**.

---

## 2. Mathematical & Proration Formulation

### Step 1: Amortize the Active Plan
* **Monthly Term:** 30 days ($30.00 total value).
* **Daily Accrual Rate:** $30.00 / 30 = $1.00/day.
* **Consumed Value (Days 1–10):** 10 days × $1.00 = $10.00 (Recognized Revenue).
* **Unearned Deferred Credit (Days 11–30):** 20 days × $1.00 = $20.00 (Unearned Credit).

### Step 2: Calculate the Upgrade Invoice
* **New Annual Subscription Cost:** $300.00.
* **Applied Credit from Unused Monthly Term:** -$20.00.
* **Net Cash Collected on Day 10:** **$280.00**.

---

## 3. Database & Ledger Operations

```
[Day 1: Initial Purchase ($30.00)]
└─ Monthly Subscription created.
└─ Period 1 created: $30.00 (Days 1–30, status: pending).

[Day 10: Upgrade Triggered]
│
├─ 1. Prorate Active Period:
│      Update Period 1 amount from 3000 cents to 1000 cents.
│      Allocate Period 1 ($10.00) immediately to instructors consumed during Days 1–10.
│      Set Period 1 status to 'distributed'.
│
├─ 2. Void Remaining Term:
│      Mark original Monthly Subscription as 'upgraded'.
│      Any future pending periods under this subscription are marked 'voided'.
│
├─ 3. Book Ledger Credit:
│      Record student invoice adjustment: $280.00 cash + $20.00 transferred credit.
│
└─ 4. Initialize New Amortization Schedule:
       Create new Annual Subscription ($300.00 total recognized value).
       Generate 12 discrete monthly periods of $25.00 (2,500 cents) each, starting from Day 10.
```

---

## 4. Invariant Protection Rules

1. **Immutable History:** Never modify or delete historical earning entries. Post offsetting `clawback` or `adjustment` entries for corrections.
2. **Instructor Fairness:** Instructors whose content was consumed during the first 10 days receive their full proportional cut of the consumed $10.00.
3. **Zero Liability Mismatch:** The platform's deferred revenue liability remains balanced:
   * Cash Collected ($280.00) + Transferred Credit ($20.00) = Total Deferred Value ($300.00).
