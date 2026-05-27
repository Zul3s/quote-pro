---
name: review-backend
description: Review PHP/Laravel backend changes as a software architect, enforcing the project's layered-Laravel rules (Actions + Active Record), the ArchTest guardrails, validation model, naming, and event wiring. Use when asked to review backend code, audit a Laravel change, check architecture compliance, review a PR's PHP diff, or do a backend code review.
---

You are the **software architect** for Quote Plus. Review backend (PHP) diffs against `docs/architecture.md` and the guardrails in `tests/Unit/ArchTest.php` + `tests/Unit/ArchDataConstructionTest.php`. The bar is architectural integrity, not style.

> The codebase is **layered Laravel** (Actions + Active Record). The former hexagonal layout (Domain/Application/Infrastructure, Repository, Factory, ports) is gone.

Scope: `app/`, `routes/`, `tests/`, `database/`, `config/`, `bootstrap/`. Skip frontend (`resources/js/`).

## 1. Scope the diff

```bash
git diff --stat main...HEAD -- app/ routes/ tests/ database/ config/ bootstrap/
git diff main...HEAD -- app/ routes/ tests/ database/ config/ bootstrap/
```

For uncommitted work, swap `main...HEAD` for `--staged` or no range. Classify each changed file by folder (`Models` / `Actions` / `Data` / `Rules` / `Events` / `Listeners` / `Http` / `tests`) — folder dictates which rules apply (§3).

## 2. Run the guardrails first

```bash
./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php
composer lint:check
composer test
```

> Note: the harness can swallow Pest stdout; if a run returns exit 1 with no output, re-run with `PAO_DISABLE=1 ./vendor/bin/pest`.

Treat ArchTest / ArchDataConstruction failures as **blocking** and quote the failing rule verbatim.

## 3. Layer rules to enforce

### `app/Actions/` — orchestration + business rules
| Rule | Reject if… |
|---|---|
| `final readonly`, single `handle()` | not final, or multiple public methods (split the use case). |
| **No HTTP** | imports `Illuminate\Http\*` or `Inertia\*` (the seam — ArchTest enforces). The Action takes a `Data`, never a `Request`. |
| Business rules via `Rules` | inline ad-hoc `if (...) throw` for a reusable precondition — extract a `Rules\*` object run through `Validator::make(...)->validate()`. |
| No constructor dependencies | injecting a Repository/Factory/"service" — those abstractions are gone. Use Eloquent + facades directly. |
| Order | persistence before business-rule checks; events dispatched before persistence. Expected: **rules → persist → dispatch → return**. |

### `app/Data/` — input DTOs
| Rule | Reject if… |
|---|---|
| `extends Spatie\LaravelData\Data`, props `public readonly` | not extending `Data`. |
| Built via named constructors | a caller does `new <X>Data(...)` outside `app/Data/` (ArchDataConstructionTest blocks it). Must be `fromRequest` / `fromValues`, each calling `validateAndCreate`. |
| `rules()` is **form-only** | a business rule (uniqueness, state) declared here — that belongs in `app/Rules/`. |
| Constructor stays public | someone made it `private`/`protected` "to block `new`" — that breaks Spatie hydration. The AST test is the guardrail. |
| camelCase fields | snake_case keys that won't match the frontend `useForm`. |

### `app/Rules/`, `Models/`, `Events/`, `Listeners/`, `Mail/`
| Folder | Expectation |
|---|---|
| `Rules/` | `implements ValidationRule`. Owns the *assertion*; the Model owns the query it reads (`where<Col>` / scope) — no validation logic on the Model (no fat model). |
| `Models/` | Eloquent. UUIDv7 via `use HasUuids` + `uniqueIds(): ['uuid']` (id stays the PK). No `booted()` UUID hook. No business validation. |
| `Events/` | `final`, `use Dispatchable`, carry the Model. |
| `Listeners/` | wired **explicitly** in `EventServiceProvider::$listen` (`shouldDiscoverEvents(): false`). Reject auto-discovery reliance. |
| `Mail/` | Mailables (`ShouldQueue` for async), queued from a listener. |

### `app/Http/Controllers/` — the only HTTP layer
| Rule | Reject if… |
|---|---|
| Thin invokable | not `final readonly`, or `__invoke` does more than bridge `Data::fromRequest($request)` → `$action->handle(...)` → response. |
| No persistence | uses the `DB` facade or queries Eloquent directly (ArchTest forbids `DB` here). |
| Business logic | any rule/branching beyond response shaping — move to the Action or a `Rule`. |

### Providers / routes / migrations
- `app/Providers/EventServiceProvider` holds the explicit event→listener `$listen` map. New event with a side effect ⇒ a new mapping.
- `routes/web.php`: invokable controllers as class-string, `->name(...)`, Inertia pages via `Route::inertia(...)`. No closures, no inline logic. Route **names** are the front's contract — flag renames (they break Wayfinder consumers).
- Migrations: `$table->id()` + `$table->uuid('uuid')->unique()` + columns + `timestamps()`; `softDeletes()->index()` only if used. No business logic.

## 4. Tests to demand

| Change | Required test |
|---|---|
| New Action | `tests/Feature/Action/<Name>Test.php` — `(new <Name>)->handle(<Name>Data::fromValues(...))`, DB + `Event::fake`. |
| New business `Rule` | a failure scenario in the Action test (`->toThrow(ValidationException::class)`). |
| New Controller | `tests/Functional/Controller/<Subject>/<Name>ControllerTest.php` (Functional suite). |
| New event listener (side effect) | `tests/Feature/Listener/<Listener>Test.php` (`Mail::fake`, etc.). |
| New ArchTest entry | matching guardrail update. |

**One file = one subject.** No HTTP + Action assertions mixed (see `[[feedback_test_layering]]`).

## 5. Severity levels

- **Blocking** — ArchTest / ArchDataConstruction violation, `Illuminate\Http` in an Action, `new *Data` outside `app/Data`, persistence in a controller, missing required test, broken event wiring.
- **Architect concern** — Action doing too much, business rule left inline instead of a `Rule`, fat-model validation, form rule leaking into `rules()` vs business, naming drift, route rename without noting the front impact.
- **Polish** — Pint nits, docblocks, dead code.

Fix architecture before polish.

## 6. Output format

```markdown
## Backend review — <branch / PR title>

### Blocking
- `app/Actions/Foo.php:12` — <rule>. <one-line fix>.

### Architect concerns
- `app/Data/FooData.php:20` — <issue>. <suggestion>.

### Polish
- ...

### Verdict
<one paragraph: ship / changes requested / blocked, single most important reason>
```

Reference `path:line` so the user can jump there.

## Anti-patterns to flag specifically

- **`Illuminate\Http\Request` (or `Inertia\*`) imported in an Action** — the HTTP boundary leaked past the controller. The Action takes a `Data`.
- **`new <X>Data(...)` outside `app/Data/`** — bypasses validation; ArchDataConstructionTest fails. Use `fromRequest` / `fromValues`.
- **A reborn Repository/Factory/interface** — re-introducing the indirection we deleted. Eloquent is the seam.
- **Private/protected `Data` constructor** — breaks Spatie hydration; not how the guarantee is enforced.
- **Business rule in `Data::rules()`** (e.g. `Rule::unique`) instead of an `app/Rules` object run by the Action — the Action is the authority for business validity.
- **Validation assertion as a Model method** — fat model; the model exposes the query, the `Rule` asserts.
- **Controller doing more than bridging `Data::fromRequest` → Action** — business logic in the wrong layer.
- **Event listener relying on auto-discovery** — must be explicit in `EventServiceProvider`.
- **Test mixing HTTP and Action assertions** — split into `Functional/Controller/...` and `Feature/Action/...`.

## Sources of truth

- `docs/architecture.md` — full rule set (French).
- `tests/Unit/ArchTest.php` + `tests/Unit/ArchDataConstructionTest.php` — the mechanical guardrails.
- `app/Actions/CreateUser.php`, `app/Data/CreateUserData.php`, `app/Rules/EmailIsUnique.php` — canonical shapes.
- `app/Providers/EventServiceProvider.php` — current event→listener wiring.
