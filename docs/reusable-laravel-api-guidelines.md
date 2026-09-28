# Reusable Laravel API guidelines

Portable architecture rules distilled from the **Instructor Revenue & Payout Ledger** API. Copy this file into a new Laravel JSON API and follow it for DTOs, services, repositories, HTTP, auth, and tests.

Domain rules that belong only to one product (Hamilton-Hare allocation, payout FSM, Filament finance screens) stay out of this document. The patterns below are the ones to reuse.

**Assumed stack (this project):** Laravel 13 API, PHP 8.3+, PHPUnit, Sanctum **personal access tokens** (`auth:sanctum` Bearer), Filament v4 admin, **Spatie Permission** (roles + `domain.action` permissions).

---

## Request flow

```
HTTP Request
    → routes/api.php (version prefix + {Feature}Routes::register())
    → Controller (thin)
    → FormRequest (validation + policy)
    → DTO (readonly, typed)
    → Service (business rules, transactions)
         ↳ other entity Service (prefer services over foreign repositories)
    → own Repository interface → Eloquent repository (data only)
    → Model
    ← API Resource wrapped in ApiResponse (never a raw model)
```

**Hard rules**

- Controllers stay thin: validated input → one service call → `ApiResponse` + Resource.
- Prefer services over injecting repositories into controllers. Existing list/show helpers may use a repository when the service would be a pure pass-through — do not spread that pattern to write paths.
- Services never return HTTP responses, never call `abort()`, and never touch `Request` or `FormRequest`.
- Repositories never contain business rules, validation, or service calls.
- Cross-entity orchestration goes through the other entity's **service** when rules are involved.
- JSON responses always go through an API Resource and `ApiResponse`.

---

## Directory layout

Flat `app/`. One folder per layer. Feature grouping lives inside DTOs, HTTP, tests, and exceptions — models stay shared.

```
app/
├── Console/Commands/                # payouts:run, etc.
├── DTOs/{Feature}/                  # CreateSubscriptionDTO, FilterPayoutDTO
├── Enums/                           # PlanTier, PayoutStatus, LedgerEntryType
├── Exceptions/                      # DomainException + {Feature}Exception
├── Filament/                                    # Admin: finance + dashboard charts + Users/Roles
│   ├── Pages/ + Widgets/
│   └── Resources/                               # Balances, payouts, batches, subscriptions, users, roles
├── Http/
│   ├── Controllers/Api/V1/{Feature}/
│   ├── Requests/Api/V1/{Feature}/
│   ├── Resources/Api/V1/{Feature}/
│   └── Routes/Api/V1/               # {Feature}Routes.php
├── Jobs/
├── Models/
├── Policies/
├── Providers/AppServiceProvider.php # repository bindings + Gate::policy overrides
├── Repositories/
│   ├── Interfaces/{Model}RepositoryInterface.php
│   └── Eloquent/Eloquent{Model}Repository.php
├── Services/                        # Auth, Ledger, Payout, UserAdmin, AdminDashboard, …
└── Support/
    ├── ApiResponse.php
    ├── Authorization/               # PermissionName, RolePermissionMap
    └── Pagination/PaginatedPayload.php
routes/api.php                       # v1 prefix + ::register() only
tests/Feature/
tests/Unit/
```

`routes/web.php` stays empty for an API-only app (`GET /` → 404). Health stays at `/up`. Filament lives under `/admin`.

---

## Naming

| Layer | Pattern | Example |
|-------|---------|---------|
| Enum | `{Name}` (no `Enum` suffix) | `PlanTier`, `PayoutStatus` |
| Repository interface | `{Model}RepositoryInterface` | `PayoutRepositoryInterface` |
| Repository | `Eloquent{Model}Repository` | `EloquentPayoutRepository` |
| Service | `{Feature}Service` | `PayoutService`, `AuthService` |
| DTO | `{Action}{Model}DTO` | `CreateSubscriptionDTO`, `FilterPayoutDTO` |
| Routes | `{Feature}Routes` | `SubscriptionRoutes` |
| Controller | `{Feature}Controller` | `Api\V1\Subscription\SubscriptionController` |
| Form request | `{Action}{Model}Request` | `StoreSubscriptionRequest` |
| API resource | `{Model}Resource` | `SubscriptionResource` |
| Exception | `{Feature}Exception` | `PayoutException` |
| Policy | `{Model}Policy` | `SubscriptionPolicy` |
| Feature test | `{Feature}ApiTest` / descriptive suite | `AuthApiTest`, `PayoutConcurrencyAndResilienceTest` |
| Service test | `{Feature}ServiceTest` | `RevenueAllocationServiceTest` |

| Layer | Methods |
|-------|---------|
| Repository | `create()`, `find()`, `findByX()`, `update()`, `delete()`, `getX()` |
| Service | Verb phrases: `createSubscription()`, `processInstructorPayout()`, `allocatePeriod()` |
| Routes | Static `register()` only |
| Controller | REST + named actions: `index`, `show`, `store`, `cancel`, `upgrade`, `run` |
| Route names | `api.v1.{resource}.{action}` |

Enum cases are TitleCase: `ThreeMonth`, `PayoutWithhold`. Backing values are snake_case strings.

---

## 1. DTOs

Readonly classes. They carry already-validated data from HTTP into a service. They do not validate, query the database, or apply business rules.

Location: `app/DTOs/{Feature}/`.

Use a DTO for every create, every update, and every list filter that has more than a primitive or two. A single `id` or `per_page` may stay a typed argument.

Snake_case stays at the HTTP boundary. DTO properties are camelCase. `toArray()` / `fromRequest()` convert at the boundary.

```php
readonly class CreateSubscriptionDTO
{
    public function __construct(
        public PlanTier $planTier,
        public int $priceCents,
        public int $platformFeeBps,
        public Carbon $startsAt,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromRequest(array $data): self { /* ... */ }
}
```

---

## 2. Services

One service per persisted entity (or cohesive workflow: `AuthService`, `RevenueAllocationService`). All business rules, orchestration, transactions, events, and jobs live here.

### Do

- Type-hint the entity's repository **interface** when the service owns that aggregate.
- Call another entity through **its service** when business rules apply.
- Wrap multi-table writes in `DB::transaction()`.
- Throw a feature exception from a named factory (`PayoutException::cycleAlreadyRunning()`).
- PHPDoc every public method: one-line purpose, `@param`, `@return`.
- Keep gateway / network I/O **outside** DB transactions (see payout two-phase commit).

### Do not

- Accept a `FormRequest` or return a `JsonResponse`.
- Call `abort()` or throw `HttpException`.
- Put unrelated features in one class.

Collaborator services must **not** be `final`, so unit tests can mock them. The service under test may be `final`.

---

## 3. Repositories

Data access only. One interface and one Eloquent implementation per model the service persists.

Bind only in `AppServiceProvider::register()`.

Every interface and implementation uses these five sections, in this order, even when a section is empty:

```php
// ==============================
// Create Operations
// ==============================
// Read Operations / Update Operations / Delete Operations / Get Operations
```

- **Read** — `find`, `findByX` (single model or null).
- **Get** — lists and paginators (`getPaginated`).
- Return models, collections, paginators, or booleans. Never arrays shaped for JSON.
- Eager-load every relation the API resource will touch.
- Append-only tables (ledger): no delete; updates only when a documented conversion exists (e.g. withhold → settled).

Lists paginate. Default `per_page` is 15, capped at 100 in the FormRequest.

---

## 4. Models

`declare(strict_types=1);` on every model. Attributes, casts, and traits sit above the sections. All five section blocks are present, in this order, even when empty:

```php
// Relationships / Accessors & Mutators / Scopes / Events & Observers / Custom Methods
```

- Fixed value sets are cast to backed enums in `casts()`.
- Every new model ships a factory with the migration.
- Models are persistence objects. Policies, HTTP, and business rules stay out of them.
- Currency is always integer cents (`BIGINT`). Never floats.

---

## 5. Enums

PHP backed enums for every fixed set (status, type, plan). File: `app/Enums/{Name}.php`.

- Cast on the model.
- Validate with `Rule::enum(PlanTier::class)`.
- Branch on cases in services. No magic strings.
- Resources expose `$enum?->value`.

---

## 6. Routes

`routes/api.php` only sets the version prefix and calls register methods.

```php
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    AuthRoutes::register();
    SubscriptionRoutes::register();
    // …
});
```

- One route class per feature.
- Group public routes separately from `auth:sanctum`.
- Constrain numeric ids with `->whereNumber()`.
- Stay on `/api/v1` until a breaking change forces `v2`.

---

## 7. Form requests

One request class per action. `authorize()` calls a **policy**. Controllers pass `$request->validated()` into `DTO::fromRequest()`.

```php
public function authorize(): bool
{
    return $this->user()?->can('view', $this->route('subscription')) ?? false;
}
```

---

## 8. Controllers

Namespace: `App\Http\Controllers\Api\V1\{Feature}`.

One primary service. One service method per action. No `try/catch` for formatting errors.

| HTTP | Method | Response |
|------|--------|----------|
| GET list | `index()` | `ApiResponse::paginated(...)` |
| GET one | `show()` | `ApiResponse::success(...)` |
| POST | `store()` | `ApiResponse::success(..., 201)` |
| Domain action | `cancel()` / `run()` | `ApiResponse::success(...)` (202 when async) |

---

## 9. API resources

`app/Http/Resources/Api/V1/{Feature}/{Model}Resource.php`.

- Relationships use `$this->whenLoaded('relation')`.
- Dates are ISO-8601. Date-only columns use `Y-m-d`.
- Enums expose `->value`.
- Never `return response()->json($model)`.

---

## 10. Response envelope

`App\Support\ApiResponse` is the only JSON exit.

Success: `{ success, message, data }`  
Paginated: adds `meta`, `links`  
Error: `{ success: false, message, data: null, error_code }` (+ `errors` on validation)

Force JSON for `api/*` in `bootstrap/app.php`. Map exceptions once via `ApiExceptionRenderer`:

| Exception | Status | `error_code` |
|-----------|--------|----------------|
| `DomainException` | exception status | exception code |
| `ValidationException` | 422 | `validation_failed` |
| `AuthenticationException` | 401 | `unauthenticated` |
| `AuthorizationException` / `AccessDeniedHttpException` | 403 | `forbidden` |
| Missing model | 404 | `{model}.not_found` |
| Unknown route | 404 | `not_found` |
| Method not allowed | 405 | `method_not_allowed` |
| Throttle | 429 | `too_many_requests` |
| Anything else | 500 | `server_error` |

`error_code` shape: `{entity}.{reason}` (`payout.cycle_already_running`, `user.duplicate_email`).

---

## 11. Authorization (Spatie Permission)

Two layers, both required:

1. **Capability** — Spatie permission named `domain.action` (`payouts.run`, `subscriptions.view`). Catalog: `App\Support\Authorization\PermissionName`. Roles: `App\Enums\RoleName` + `RolePermissionMap`. Seed with `RolesAndPermissionsSeeder`.
2. **Record** — Laravel policies for “this user, this row” (owner checks, instructor target). Policies call `$user->can(PermissionName::…)` then ownership when needed.

- FormRequest `authorize()` calls `$user->can('view', $model)` (policy), not raw permission strings for record actions.
- `Gate::before` grants every ability to `super_admin`.
- `User` → `UserPolicy` (admin CRUD + `viewAnyInstructors` / `viewInstructor`). Spatie `Role` → `RolePolicy` via explicit `Gate::policy` (Spatie model is not under `App\Models`).
- Do **not** bind a separate `InstructorPolicy` to `User` — that overrides `UserPolicy` and breaks Filament user management.
- Middleware aliases: `role`, `permission`, `role_or_permission` (bootstrap/app.php).
- Re-seed permissions after adding a catalog entry so roles pick them up.
- Nested instructor ledger/balance require `ledger.view` **and** `viewInstructor`. Nested instructor payouts require payout view permissions **and** `viewInstructor`.
- Never trust client-submitted owner ids for authorization.

### Filament admin

- Panel access: `User::canAccessPanel()` requires `admin.access`.
- Resource visibility: `GatesWithSpatiePermissions` + permission constants (not Filament-only gates).
- Write actions call **application services** (`PayoutService`, `SubscriptionService`, `RevenueAllocationService`, `UserAdminService`) after validating with **shared rule sets** (`FormRequest::ruleSet()` or `UserAdminService::*RuleSet()`) via `Validator::make` — do not put business validation only on Filament field rules.
- Dashboard: `AdminDashboardService` feeds stats/chart widgets on the home page.
- User management nav group: **Users** + **Roles & permissions** (role create/delete disabled; permission sync only).

### Authentication (Sanctum tokens)

- Register / login return `{ ...user fields, roles, permissions, token }` inside `data`.
- Clients send `Authorization: Bearer {token}`.
- Logout deletes the current access token.
- `GET /api/v1/auth/me` returns the authenticated user with roles/permissions.
- Prefer `AuthService` for login / logout / `issueToken`; `UserService` for registration (assigns `student` / `instructor` role) and instructor lookups.
- Cookie SPA sessions are **not** used for the JSON API.

---

## 12. Jobs, schedule, concurrency

- Slow or multi-instructor work is a queued job (`ProcessInstructorPayoutJob`).
- Schedule in `routes/console.php` (`payouts:run` daily, `ReconcileUncertainPayoutsJob` every 10 minutes).
- Distributed locks (`Cache::lock`) at command entrypoints; row locks inside transactions; unique `idempotency_key` at the DB layer.
- Gateway calls happen **after** the DB transaction that booked a withhold.

---

## 13. Performance, security, data

- Paginate every list. Eager-load relations the resource reads.
- Index filters, uniqueness, and joins (see `SYSTEM_ARCHITECTURE_AND_ERD.md`).
- Queue slow work. Hash passwords with the model cast.
- Secrets live in `.env` only. Do not log tokens or full sensitive payloads.

---

## 14. PHP style

- `declare(strict_types=1);` on every new PHP file and every model.
- Constructor property promotion. Explicit parameter and return types.
- Curly braces on every control structure.
- PHPDoc array shapes on public methods.
- Format with `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run Artisan generators with `--no-interaction`.

---

## 15. Tests (PHPUnit)

| Suite | Use for |
|-------|---------|
| `tests/Feature/...` | Auth, validation, envelope, persistence, authorization, payout resilience |
| `tests/Unit/...` | Hamilton-Hare, upgrade proration, pure service rules |
| Plain `PHPUnit\Framework\TestCase` | Enums / DTO mapping with no Laravel |

- `RefreshDatabase` on DB tests. In-memory SQLite via `phpunit.xml`.
- Factories, not hand-built dumps. Method names: `test_` + snake_case behavior.
- HTTP: `getJson` / `postJson`. Assert `success`, `message`, `data` (and `meta` / `links`).
- Auth: `Sanctum::actingAs($user)` or Bearer token from login. Do not assume cookie sessions.
- Fake notifications, storage, queues when needed. Mock `MockPaymentProvider` for deterministic gateway outcomes.
- Checklist resilience cases live in `PayoutConcurrencyAndResilienceTest`.

```bash
php artisan test --compact
php artisan test --compact --filter=test_remainder_allocation_zero_penny_leak
```

---

## 16. Build order for a new feature

1. Migration, model (five sections, enum casts), factory.
2. Enum, if needed.
3. Repository interface + Eloquent implementation + binding.
4. `{Feature}Exception` with named factories.
5. DTOs.
6. Service.
7. Policy, then FormRequests.
8. API Resource + Controller + `{Feature}Routes::register()`.
9. Feature test and service unit test.
10. `vendor/bin/pint --dirty --format agent`.

---

## 17. Merge checklist

- [ ] Controller action is thin: validated input → one service call → resource
- [ ] Service owns rules, transactions, and jobs
- [ ] Repository is data-only, five sections, interface bound
- [ ] DTOs for writes and non-trivial filters
- [ ] Enums for fixed values, cast on the model
- [ ] FormRequest validates and authorizes via policy
- [ ] `ApiResponse` envelope; lists use `paginated()`
- [ ] Domain failures throw `DomainException` subclasses
- [ ] `AccessDeniedHttpException` mapped to 403 in `ApiExceptionRenderer`
- [ ] New endpoint has authn / authz / validation / happy-path coverage
- [ ] Currency stays integer cents; ledger writes use idempotency keys
- [ ] Pint is clean

---

## Support classes to copy first

| Class | Role |
|-------|------|
| `App\Support\ApiResponse` | `success()`, `error()`, `paginated()` |
| `App\Support\Pagination\PaginatedPayload` | `{ data, meta, links }` |
| `App\Exceptions\DomainException` | Message + HTTP status + `error_code` |
| `App\Exceptions\ApiExceptionRenderer` | One JSON mapping for `api/*` |
