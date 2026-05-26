---
name: create-controller
description: Expose an existing Use Case over HTTP — thin invokable controller, named routes in routes/web.php, rate-limiters/middleware, Domain-exception mapping, then regenerate Wayfinder so the front can consume it. The missing link between create-usecase and create-front. Use when asked to add a controller, wire a route, expose a Use Case over HTTP, create an endpoint, add a rate limiter, or "brancher un Use Case sur HTTP".
---

You are a **senior Laravel engineer**. Job: take a Use Case that already exists in `app/Application/UseCase/<Name>/` and expose it over HTTP. This is the chaînon between `/create-usecase` (which built the Application + Domain + Eloquent slice) and `/create-front` (which consumes the generated Wayfinder helpers).

Canonical template to mirror: `app/Infrastructure/Http/Controller/User/CreateUserController.php` + the matching lines in `routes/web.php`. When in doubt, open it.

**Out of scope** (do not do here):
- Use Case / Domain / Eloquent model / interface→impl bindings → `/create-usecase` (bindings already live in `DomainServiceProvider::$bindings`).
- React pages → `/create-front`.
- HTTP/Controller tests → `/create-tests-functional`.

## 1. Pin the contract (do not skip)

Read the Use Case before writing anything:
- `app/Application/UseCase/<Name>/Request.php` — the input DTO. It `extends AbstractRequest` (= `Spatie\LaravelData\Data`), so **Laravel auto-hydrates + auto-validates it from the HTTP request**. The controller never validates by hand.
- `app/Application/UseCase/<Name>/UseCase.php` — its `execute()` return type. Domain entity? `Response` DTO? Array? Decides the success payload shape.

Then lock the **exposure type** — ask the user if unclear:

| Exposure | Pattern |
|---|---|
| Web form submit (redirect + flash) | `Route::post(...)` → controller returns `redirect()->route(...)->with('success', …)`. |
| JSON API | controller returns `new JsonResponse([...], 201)`. |
| Content-negotiated (both) | branch on `$http->expectsJson()` — see `CreateUserController`. |
| Static GET page (no Use Case) | **no controller** — `Route::inertia('/path', 'page/name')->name(...)` straight in `routes/web.php`. |
| GET page needing data | controller returns `Inertia::render('page/name', [...])`. |

## 2. Write the controller

Skip this step entirely for a static GET page (step 1 row 4) — go to step 3.

Namespace `App\Infrastructure\Http\Controller\<Aggregate>\<Name>Controller`, `final readonly`, single `__invoke`. Inject the laravel-data `Request`, the `UseCase`, and — only for content negotiation — `Illuminate\Http\Request as HttpRequest`.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\<Aggregate>;

use App\Application\UseCase\<Name>\Request;
use App\Application\UseCase\<Name>\UseCase;
use Illuminate\Http\RedirectResponse;

final readonly class <Name>Controller
{
    public function __invoke(Request $request, UseCase $useCase): RedirectResponse
    {
        $useCase->execute($request);

        return redirect()
            ->route('<route-name>')
            ->with('success', 'Message de confirmation.');
    }
}
```

Rules:
- **Thin.** Resolve → delegate to `$useCase->execute($request)` → translate the result to an HTTP response. Zero business logic.
- **Do not try/catch Domain exceptions** — see step 5.
- Flash key is `success` (the `HandleInertiaRequests` middleware already shares `flash.success` to every Inertia page).

## 3. Register routes in `routes/web.php`

Named routes, controller imported at the top, content controllers referenced by `::class` (they're invokable).

```php
use App\Infrastructure\Http\Controller\<Aggregate>\<Name>Controller;

Route::inertia('/', 'contact/index')->name('home');                    // static GET page
Route::post('/contact-requests', <Name>Controller::class)
    ->middleware('throttle:contact')                                   // step 4
    ->name('contact-requests.store');
Route::inertia('/thank-you', 'contact/thank-you')->name('thank-you');
```

Route names matter — Wayfinder derives the TS helpers from them (step 6) and `create-front` imports those.

## 4. Rate-limiter / middleware (only if the contract asks for it)

Define named limiters in `AppServiceProvider::boot()` (via `configureDefaults()` or a dedicated method), then apply with `->middleware('throttle:<name>')` on the route.

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
```

The 6th request in the window then returns `429` automatically.

## 5. Domain exception → HTTP mapping

Mapping is **centralised in `bootstrap/app.php`** `withExceptions(...)`, not in controllers. `ValidationsException` and `EntityNotFoundException` are already wired there (→ 422 / 404).

Only touch this if the Use Case throws a **new** Domain exception type that needs a specific status. Add a `$exceptions->render(...)` clause following the existing pattern — never catch it in the controller.

## 6. Regenerate Wayfinder — this unblocks `/create-front`

The Wayfinder vite plugin regenerates on `npm run dev`, but to regenerate without booting the dev server:

```bash
php artisan wayfinder:generate
```

This (re)writes `resources/js/actions/App/Infrastructure/Http/Controller/<Aggregate>/<Name>Controller.ts` and `resources/js/routes/...`. Confirm the file appeared — that import is exactly what the front page's `useForm().post(store.url())` needs. **Never hand-edit generated files.**

## 7. Verify

```bash
php artisan route:list --path=<path>          # the route is registered with the right name + middleware
./vendor/bin/pest tests/Unit/ArchTest.php     # controller sits in Infrastructure, no layering break
composer lint                                 # Pint formats the new PHP
```

## Anti-patterns

- **Validating in the controller.** The laravel-data `Request` already validates on hydration. A `$request->validate([...])` call is a smell.
- **Business logic in `__invoke`.** Specifications, persistence, events all belong to the Use Case. The controller only adapts HTTP ↔ Use Case.
- **`try/catch` around `execute()` to set a status code.** Map the exception in `bootstrap/app.php` instead.
- **Re-binding interfaces here.** `DomainServiceProvider::$bindings` already wires repository/factory/event. This skill adds routes + limiters only.
- **Hand-editing `resources/js/actions/` or `resources/js/routes/`.** Regenerate with `php artisan wayfinder:generate`.
- **A controller for a page that needs no data.** Use `Route::inertia('/path', 'page')` directly.
- **Hardcoded flash key.** Use `success` — it's the one shared by `HandleInertiaRequests`.

## Sources of truth

- `app/Infrastructure/Http/Controller/User/CreateUserController.php` — canonical controller (content-negotiated).
- `routes/web.php` — route registration patterns.
- `app/Infrastructure/Providers/AppServiceProvider.php` — where named rate-limiters go.
- `bootstrap/app.php` — `withExceptions(...)` Domain→HTTP mapping + middleware stack.
- `app/Infrastructure/Http/Middleware/HandleInertiaRequests.php` — shared Inertia props (`flash.success`, `auth`).
