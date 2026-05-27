---
name: create-controller
description: Expose an existing Action over HTTP — thin invokable controller that bridges the HTTP request to the Action's Data DTO, named routes in routes/web.php, rate-limiters/middleware, then regenerate Wayfinder so the front can consume it. The missing link between create-usecase and create-front. Use when asked to add a controller, wire a route, expose an Action over HTTP, create an endpoint, add a rate limiter, or "brancher un Use Case sur HTTP".
---

You are a **senior Laravel engineer**. Job: take an Action that already exists in `app/Actions/<Name>.php` and expose it over HTTP. The chaînon between `/create-usecase` (which built the Action + Data + Model slice) and `/create-front` (which consumes the generated Wayfinder helpers).

Canonical template: `app/Http/Controllers/User/CreateUserController.php` + `routes/web.php`. When in doubt, open it.

**Out of scope:** the Action / Data / Model / Rules (`/create-usecase`), React pages (`/create-front`), HTTP tests (`/create-tests-functional`).

## 1. Pin the contract (do not skip)

Read the slice before writing anything:
- `app/Data/<Name>Data.php` — the input DTO. The controller builds it with `<Name>Data::fromRequest($request)`, which **validates the form** (throws `Illuminate\Validation\ValidationException` → handled natively). The controller never validates by hand.
- `app/Actions/<Name>.php` — `handle()` return type (a Model? a collection?). Decides the success payload shape.

Then lock the **exposure type** — ask if unclear:

| Exposure | Pattern |
|---|---|
| Web form submit (redirect + flash) | `Route::post(...)` → controller returns `redirect()->route(...)->with('success', …)`. |
| JSON API | controller returns `new JsonResponse([...], 201)`. |
| Content-negotiated (both) | branch on `$request->expectsJson()` — see `CreateUserController`. |
| Static GET page (no Action) | **no controller** — `Route::inertia('/path', 'page/name')->name(...)`. |
| GET page needing data | controller returns `Inertia::render('page/name', [...])`. |

## 2. Write the controller

Skip for a static GET page (go to step 3).

Namespace `App\Http\Controllers\<Aggregate>\<Name>Controller`, `final readonly`, single `__invoke`. Inject `Illuminate\Http\Request` and the `Action`; bridge HTTP → Data → Action.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\<Aggregate>;

use App\Actions\<Name>;
use App\Data\<Name>Data;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class <Name>Controller
{
    public function __invoke(Request $request, <Name> $action): JsonResponse|RedirectResponse
    {
        $model = $action->handle(<Name>Data::fromRequest($request));

        if ($request->expectsJson()) {
            return new JsonResponse([/* serialise model attributes */], 201);
        }

        return redirect()->route('<route-name>')->with('success', 'Message de confirmation.');
    }
}
```

Rules:
- **Thin.** Bridge `Data::fromRequest($request)` → `$action->handle(...)` → response. Zero business logic, no DB access (ArchTest forbids the `DB` facade here).
- **Serialise from the model directly** (`$model->uuid`, `$model->request_type->value`) — no Response DTO layer.
- **Do not** `try/catch` validation — `ValidationException` is rendered natively (422 JSON / redirect-back-with-errors).
- Flash key is `success` (shared to every Inertia page by `HandleInertiaRequests`).

## 3. Register routes in `routes/web.php`

```php
use App\Http\Controllers\<Aggregate>\<Name>Controller;

Route::post('/<path>', <Name>Controller::class)
    ->middleware('throttle:<name>')        // step 4, only if needed
    ->name('<aggregate>.<verb>');
```

Route **names** are the front's contract — Wayfinder derives the TS helpers from them, and `create-front` imports those. Don't rename an existing route without flagging the front impact.

## 4. Rate-limiter / middleware (only if the contract asks)

Define named limiters in `app/Providers/AppServiceProvider` (`configureRateLimiters()`), apply with `->middleware('throttle:<name>')`.

```php
RateLimiter::for('<name>', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
```

## 5. Validation & errors — nothing to wire

Form validation lives in the `Data` (fired by `fromRequest`); business rules live in the Action's `Rules`. Both throw `Illuminate\Validation\ValidationException`, which Laravel renders as **422 JSON** or **redirect-back-with-errors** automatically. `bootstrap/app.php` has **no** custom exception mapping to touch. Only add a `withExceptions(...)` clause if the Action throws a genuinely new exception type needing a specific status.

## 6. Regenerate Wayfinder — unblocks `/create-front`

```bash
php artisan wayfinder:generate
```

(Re)writes `resources/js/actions/App/Http/Controllers/<Aggregate>/<Name>Controller.ts` and `resources/js/routes/...`. Confirm the file appeared — it's what the front's `useForm().post(controller.url())` imports. **Never hand-edit generated files.** Note: moving/renaming a controller changes this generated path, so any existing front import must follow.

## 7. Verify

```bash
php artisan route:list --path=<path>
./vendor/bin/pest tests/Unit/ArchTest.php
composer lint
```

## Anti-patterns

- **Validating in the controller.** `Data::fromRequest` already validates the form. A `$request->validate([...])` here is a smell.
- **Business logic in `__invoke`.** Rules, persistence, events belong to the Action.
- **Querying the DB / using the `DB` facade in the controller** — ArchTest forbids it; the Action owns persistence.
- **`try/catch` to set a status code.** `ValidationException` is native; let Laravel render it.
- **Hand-editing `resources/js/actions/` or `routes/`.** Regenerate with `php artisan wayfinder:generate`.
- **A controller for a page that needs no data.** Use `Route::inertia('/path', 'page')`.
- **Hardcoded flash key.** Use `success`.

## Sources of truth

- `app/Http/Controllers/User/CreateUserController.php` — canonical content-negotiated controller.
- `app/Http/Controllers/ContactRequest/SubmitContactRequestController.php` — web + JSON, enum serialisation.
- `routes/web.php` — route registration.
- `app/Providers/AppServiceProvider.php` — named rate-limiters.
- `app/Http/Middleware/HandleInertiaRequests.php` — shared Inertia props (`flash.success`, `auth`).
