# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Business domain — Quote Plus

Quote Plus is a conversion-assistance tool for craftsmen and service providers.
It automatically qualifies incoming quote requests and generates contextualised
follow-ups for prospects who haven't replied.

## Stack

- **Backend**: Laravel 13, PHP ≥ 8.3, Pest 4, `spatie/laravel-data`, Inertia.js, Wayfinder.
- **Frontend**: React 19 + TypeScript, Inertia React adapter, Tailwind v4, Vite 8 (with `babel-plugin-react-compiler`).
- **DB**: SQLite by default (`database/database.sqlite`) — created automatically by `composer setup`.

## Commands

### Dev server
```bash
composer dev          # runs in parallel: php artisan serve, queue:listen, pail (logs), vite
```

### Tests (Pest)
```bash
./vendor/bin/pest                                       # full suite
./vendor/bin/pest tests/Unit/ArchTest.php               # single file
./vendor/bin/pest --filter="creates a user"             # targeted test
composer test                                           # config:clear + pint --test + pest
```
- Three testsuites in `phpunit.xml`:
  - `Unit` (`tests/Unit/`) — pure isolated tests, no DB.
  - `Feature` (`tests/Feature/`) — Action (`Action/`) + Listener (`Listener/`) coverage, with `RefreshDatabase`.
  - `Functional` (`tests/Functional/`) — HTTP/Controller coverage (Inertia, JSON API, web forms), with `RefreshDatabase`.
- `RefreshDatabase` wiring lives in `tests/Pest.php` (applied to both `Feature` and `Functional`).
- `tests/Unit/ArchTest.php` and `tests/Unit/ArchDataConstructionTest.php` are the **CI architectural guardrails**.
- Run a single testsuite with `./vendor/bin/pest --testsuite=Functional` (or `Feature`, `Unit`).

### Lint / format / types
```bash
composer lint          # Pint (PHP) — formats in place
composer lint:check    # Pint --test (CI mode)
npm run lint           # ESLint --fix
npm run lint:check     # ESLint without fix
npm run format         # Prettier --write resources/
npm run format:check   # Prettier --check (CI)
npm run types:check    # tsc --noEmit
composer ci:check      # full bundle: npm lint:check + format:check + types:check + composer test
```

### Build
```bash
npm run build          # frontend production build
npm run build:ssr      # build + SSR
```

## Architecture (Layered Laravel — Actions + Active Record)

`docs/architecture.md` (in French) is the **source of truth** — read before any structural change.

> The project moved away from a hexagonal/DDD layout (Domain/Application/Infrastructure, Repository,
> Factory, ports) on 2026-05-27. Standard Laravel `app/` layout is used.

```
HTTP (Controller)  →  Action (orchestration + business rules)  →  Model (Active Record)
                            ├─ Data  (input DTO, form validation)
                            └─ Rules (reusable business rules)
```

**Golden rule**: HTTP stops at the Controller. The Action carries the business (validation included)
and never touches the HTTP layer. Eloquent is used directly.

### Layers (`app/`)
- `Models/` — Eloquent entities. UUIDv7 identity via the native `HasUuids` trait on the `uuid` column (`uniqueIds()` returns `['uuid']`; `id` stays the auto-increment PK).
- `Actions/` — use cases, **writes and reads alike** (`final readonly`). Writes: `handle(XxxData): Model`, run business `Rules` via the `Validator` facade, persist. Reads (`Show`/`Get`/`List`): return `?Model`/`Collection`, and take a `Data` only when the query needs several inputs. A native event is **optional on either side** — dispatched when a need calls for it (most often on writes), not a defining trait. **Never use `Illuminate\Http`.** Corollary: all data access — read or write — goes through an Action; controllers never touch `App\Models`.
- `Data/` — input DTOs (`extends Spatie\LaravelData\Data`, `readonly` props) carrying **form validation** (`rules()`). Built **only** via named constructors `fromRequest(Request)` / `fromValues(...)`, each calling `validateAndCreate`. Direct `new XxxData(...)` is forbidden (AST guardrail). The constructor stays **public** (Spatie hydrates through it; `private` breaks hydration).
- `Rules/` — reusable business rules (`implements ValidationRule`), e.g. `EmailIsUnique`.
- `Enums/` — `RequestType`, `Deadline`.
- `Events/` — native events (`use Dispatchable`), carry the model.
- `Listeners/` — wired explicitly in `EventServiceProvider` (`shouldDiscoverEvents(): false`).
- `Mail/` — Mailables (`ShouldQueue`).
- `Http/Controllers/` — thin invokable controllers: `$action->handle(XxxData::fromRequest($request))`.
- `Providers/` — `AppServiceProvider` (rate limiters, defaults), `EventServiceProvider` (event→listener map).

### Validation model (the core decision)
- **Form** (required/email/max/enum) → in the **Data**, fired at construction (`fromRequest`/`fromValues`). Throws `Illuminate\Validation\ValidationException` → 422 / redirect-back, handled natively.
- **Business** (uniqueness, invariants) → in the **Action**, via `Rules` objects. The Action is the single authority, so validity holds for HTTP, queue, CLI and tests alike.

### Naming
| Type | Convention | Example |
|---|---|---|
| Model | business noun | `User`, `ContactRequest` |
| Action | verb (use case) | `CreateUser` |
| Input DTO | `Data` suffix | `CreateUserData` |
| Business rule | affirmative noun | `EmailIsUnique` |
| Event | past tense | `UserCreated` |
| Listener | imperative | `SendWelcomeEmail` |
| Controller | `Controller` suffix | `CreateUserController` |

## Frontend (Inertia + Wayfinder)

- Entry: `resources/js/app.tsx`, pages under `resources/js/pages/`.
- **Wayfinder** (`@laravel/vite-plugin-wayfinder`) generates TS helpers from Laravel routes into `resources/js/routes/`, `resources/js/actions/`, `resources/js/wayfinder/`. Regenerated by Vite — **do not edit by hand**.
- Fonts via `bunny('Instrument Sans')` (laravel-vite-plugin fonts plugin).

## ArchTest rules to respect (failing CI otherwise)

`tests/Unit/ArchTest.php` (Pest arch):
- `App\Actions` are `final` **and** never use `Illuminate\Http` / `Inertia` (the seam).
- `App\Http\Controllers` never use the `DB` facade nor `App\Models` (data access — read or write — goes through an Action; this also rules out implicit route-model binding). This covers **type-level** references too: no `use App\Models\X` for a `@var`/generic docblock. A model's type rides on the Action's return signature and is inferred in the controller; narrow `?Model` via control flow (`if ($x === null) abort(404)`), never a `@var` (which would force an import → lint↔arch deadlock).
- `App\Data` extend `Spatie\LaravelData\Data`; `App\Rules` implement `ValidationRule`.
- `App\Enums` are enums; `App\Events` are `final`.

`tests/Unit/ArchDataConstructionTest.php` (AST, php-parser):
- forbids `new XxxData(...)` outside `app/Data/` — input DTOs must be built via `fromRequest`/`fromValues` so validation always runs.

Changing the architecture means updating these tests **and** `docs/architecture.md`.

## Conventions for this repo

- Communicate with the user in French; write code identifiers, commits, PR titles/descriptions, and tooling files (this one) in English.
- `docs/architecture.md` is intentionally written in French — keep it that way unless the user says otherwise.
