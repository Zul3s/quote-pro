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
  - `Feature` (`tests/Feature/`) — Use Case + Domain coverage, with `RefreshDatabase`.
  - `Functional` (`tests/Functional/`) — HTTP/Controller coverage (Inertia, JSON API, web forms), with `RefreshDatabase`. Named `Functional` and **not** `Application` to avoid confusion with the DDD `app/Application/` layer.
- `RefreshDatabase` wiring lives in `tests/Pest.php` (applied to both `Feature` and `Functional`).
- `tests/Unit/ArchTest.php` is the **CI architectural guardrail** — any DDD layering violation is caught there.
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

## Architecture (DDD / Clean / Hexagonal)

`docs/architecture.md` (in French) is the **source of truth** — read before any structural change. Operational summary:

```
Infrastructure (Laravel)  →  Application (Use Cases)  →  Domain (pure PHP)
```

The `app/` folder contains **only** `Domain/`, `Application/`, `Infrastructure/`. Default Laravel layout (`app/Http/`, `app/Providers/`, `app/Models/`) has been removed — its content is redistributed inside `Infrastructure/`.

### `app/Domain/` — pure PHP, zero framework
- `Entity/`, `Repository/`, `Factory/`: **interfaces only** (enforced by ArchTest).
- `Specification/`: business rules (`CanXxx`), extending `AbstractSpecification`.
- `Event/<Aggregate>/`: `final readonly` events, past tense (`UserCreated`).
- `Model/`, `Exception/`: value objects, enums, business exceptions.
- `Service/`: interfaces only (e.g. `MailerInterface`) — no implementation.
- ArchTest allow-list: `App\Domain`, `Ramsey\Uuid`, `DateTimeImmutable`, `RuntimeException`. Adding any other import requires updating the rule explicitly.

### `app/Application/UseCase/<UseCaseName>/`
- `UseCase.php` (`final`, orchestration only),
- `Request.php` (`extends AbstractRequest`, attribute-based validation via `spatie/laravel-data`),
- `Response.php` (`extends AbstractResponse`) — **only when needed**; otherwise the Use Case returns a Domain entity directly.
- ArchTest allow-list: `App\Application`, `App\Domain`, `Ramsey\Uuid`, `Spatie\LaravelData`.
- **Forbidden**: Eloquent, `FormRequest`, `Illuminate\Http\Request`, direct DB access.

### `app/Infrastructure/`
- `Entity/`: Eloquent models that **implement** Domain interfaces (`User implements UserInterface`).
- `Repository/`: Eloquent implementations, prefixed `Eloquent...`.
- `Http/Controller/`: thin controllers that delegate to a Use Case.
- `Job/`: async handlers (equivalent of Symfony `MessageHandler`).
- `Providers/DomainServiceProvider.php`: **all** interface → implementation bindings, plus Domain Event → Job wiring (`$events->listen(UserCreated::class, ...)`). Registered from `bootstrap/providers.php`.

### "Service" — three families
- Interface (business need) → `Domain/Service/`
- Framework/lib implementation → `Infrastructure/Service/`
- Pure-PHP orchestrator shared across Use Cases → `Application/Service/` (create the folder only when there is content).

### Naming
| Type | Convention |
|---|---|
| Domain interface | `Interface` suffix |
| Repository impl. | `Eloquent` prefix |
| Specification | `Can…` prefix |
| Use Case class | `UseCase` |
| Request / Response DTO | `Request` / `Response` |
| Domain Event | past tense, `final readonly` |
| Job | imperative |
| Controller | `Controller` suffix |

## Frontend (Inertia + Wayfinder)

- Entry: `resources/js/app.tsx`, pages under `resources/js/pages/`.
- **Wayfinder** (`@laravel/vite-plugin-wayfinder`) generates TS helpers from Laravel routes into `resources/js/routes/`, `resources/js/actions/`, `resources/js/wayfinder/`. Regenerated by Vite — **do not edit by hand**.
- Fonts via `bunny('Instrument Sans')` (laravel-vite-plugin fonts plugin).

## ArchTest rules to respect (failing CI otherwise)

**Allow-list approach**: any import not listed makes CI fail.
- `App\Domain` can only use its own allow-list (see above).
- `App\Application` likewise.
- Every `*\Request` under `App\Application\UseCase` must `extends AbstractRequest`.
- Use Case classes: `final`. Domain Events: `final readonly`.
- `Illuminate\Database\Eloquent\Model` is forbidden inside `Domain` and `Application`.

To add a new dependency in Domain or Application, **explicitly update** `tests/Unit/ArchTest.php` (and reflect the change in `docs/architecture.md`).

## Conventions for this repo

- Communicate with the user in French; write code identifiers, commits, PR titles/descriptions, and tooling files (this one) in English.
- `docs/architecture.md` is intentionally written in French — keep it that way unless the user says otherwise.
