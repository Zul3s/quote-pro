---
name: create-tests-functional
description: Generate Pest HTTP/Controller functional tests for a feature — JSON API contract, web form redirects + session flash, Inertia render assertions, Domain exception → HTTP translation. Lives under `tests/Functional/` (Functional testsuite). Use when asked to test a Controller, test an HTTP endpoint, write a route test, cover the API/web layer, test Inertia responses, or write functional tests. Business logic is covered by `create-tests-usecase`.
---

You are a **senior PHP / Pest engineer**. Job: cover the **transport layer only** of a feature — the contract between an HTTP request and the response shape (JSON, redirect, Inertia render, session, status code). Business behaviour (DB writes, events, jobs, business rules) is **already validated by `create-tests-usecase`** — re-asserting it here is duplication that breaks twice.

Test file lives at `tests/Functional/Controller/<Subject>/<Name>ControllerTest.php`. The `Functional` testsuite is its own block in `phpunit.xml` and uses `RefreshDatabase` (wired in `tests/Pest.php`). Run it in isolation with `./vendor/bin/pest --testsuite=Functional`.

**Why `Functional` and not `Application`?** The DDD `app/Application/` layer (Use Cases) is something entirely different — naming the testsuite the same way breeds confusion. "Functional" here means *whole HTTP request → response* coverage, as opposed to `Feature/UseCase/` (one Use Case in isolation) or `Unit/` (pure Domain).

## 1. Identify the Controller and its route(s)

```bash
# Controller class + the route that hits it
ls app/Infrastructure/Http/Controller/<Subject>/
grep -n "<Name>Controller" routes/web.php routes/api.php 2>/dev/null
```

For each route, note:
- **HTTP method** (`GET`, `POST`, `PUT`, `DELETE`).
- **URL** (literal — tests use the literal path, not Wayfinder helpers).
- **Inertia render** (`Route::inertia('/path', 'page')`) vs **invokable controller** (`Route::post('/path', X::class)`).
- **Middleware stack** (auth, throttle, CSRF — CSRF is automatic on `post()`, exempt on `postJson()`).

If the route doesn't exist yet, **stop and confirm** with the user — there's nothing to test against.

## 2. Pick scenarios — by transport, not by business

Two transport modes, four scenarios each at most. Use what applies:

| Transport | Scenario | Status |
|---|---|---|
| **JSON API** (`postJson`, `getJson`, `putJson`, `deleteJson`) | Happy path | 200 / 201 / 204 + body shape |
|  | Domain validation failure | 422 + `assertJsonValidationErrors` |
|  | Unauthorised | 401 / 403 |
|  | Not found | 404 |
| **Web form / Inertia** (`get`, `post`, `from(...)->post`) | Happy path | 302 redirect + flash success |
|  | Domain validation failure | 302 redirect back + `assertSessionHasErrors` |
|  | Inertia render of a GET page | 200 + `assertInertia(fn ($page) => $page->component(...))` |
|  | Auth redirect | 302 to login |

Aim for **2–5 scenarios** per controller. If you find yourself testing the same business rule from two transports, you're testing twice — pick one (usually the JSON API path, more compact).

## 3. Silence downstream consequences

Faking is for **isolation from what `create-tests-usecase` already covers**. Do *not* assert on the consequence — only silence it so the controller test doesn't depend on real Job/Mail/I/O execution.

```php
Event::fake([UserCreated::class]);   // silence the listener chain
Bus::fake([SendWelcomeEmail::class]); // silence async jobs
Mail::fake();                         // silence real mail sending if any
```

**Do not** put `Event::assertDispatched(...)` / `Bus::assertDispatched(...)` in a controller test. That assertion belongs to the Use Case test (`create-tests-usecase`).

## 4. JSON API tests — the canonical pattern

```php
<?php

declare(strict_types=1);

use App\Domain\Event\<Aggregate>\<EventName>;
use App\Infrastructure\Entity\<Aggregate>;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| <Name>Controller — HTTP / Inertia layer
|--------------------------------------------------------------------------
| Transport concerns only: response shape, status code, session flash,
| Domain exception → HTTP translation (see bootstrap/app.php).
| Business behaviour is covered in tests/Feature/UseCase/<Name>Test.php.
*/

it('returns a 201 JSON payload on API success', function () {
    Event::fake([<EventName>::class]);

    $response = $this->postJson('/<route>', [
        'email' => 'http.user@example.com',
        'firstName' => 'Http',
        // camelCase — matches the Request DTO
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['uuid', 'email', 'first_name', 'last_name'])
        ->assertJsonFragment(['email' => 'http.user@example.com']);
});

it('returns 422 with field errors when a Domain rule fails', function () {
    <Aggregate>::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/<route>', [
        'email' => 'taken@example.com',
        'firstName' => 'Dup',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
```

## 5. Web form / Inertia tests

```php
it('redirects back with session errors on web form duplicate', function () {
    <Aggregate>::factory()->create(['email' => 'taken-web@example.com']);

    $response = $this->from('/<get-route>')->post('/<post-route>', [
        'email' => 'taken-web@example.com',
        'firstName' => 'Dup',
    ]);

    $response->assertRedirect('/<get-route>')
        ->assertSessionHasErrors(['email']);
});

it('redirects with a flash success on web form submission', function () {
    Event::fake([<EventName>::class]);

    $response = $this->from('/<get-route>')->post('/<post-route>', [
        'email' => 'web.user@example.com',
        'firstName' => 'Web',
    ]);

    $response->assertRedirect('/<get-route>')
        ->assertSessionHas('success');
});
```

### Inertia render assertions

For a GET route that renders an Inertia page, use `Inertia\Testing\AssertableInertia`:

```php
use Inertia\Testing\AssertableInertia as Assert;

it('renders the create page with required props', function () {
    $response = $this->get('/users/create');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/create')
            ->has('flash')
        );
});
```

Common `AssertableInertia` chain operations:
- `->component('users/create')` — page name matches the second arg of `Inertia::render('...')` or `Route::inertia('...', 'users/create')`.
- `->has('items')`, `->has('items', 3)`, `->has('items.0.uuid')` — assert prop existence + counts.
- `->where('user.email', 'expected@example.com')` — exact value.
- `->whereType('items.0.uuid', 'string')` — type-only assertion.
- `->missing('debug')` — prop absent.

## 6. Domain exception → HTTP translation

`bootstrap/app.php` maps Domain exceptions to HTTP status codes (e.g. `ValidationsException` → 422 with field errors; `EntityNotFoundException` → 404). A controller test is the **right place** to verify that translation works end-to-end. A Use Case test isn't — it asserts that the exception is *thrown*; the controller test asserts that the translation maps it to the correct status + body shape.

Open `bootstrap/app.php` to see what translations are wired, and add one scenario per translation that affects this route.

## 7. Auth / middleware scenarios

If the route sits behind `auth` middleware:

```php
it('redirects unauthenticated users to login', function () {
    $response = $this->get('/<protected-route>');

    $response->assertRedirect('/login');
});

it('allows an authenticated user through', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/<protected-route>');

    $response->assertStatus(200);
});
```

For JSON API routes, expect 401 instead of redirect:

```php
$this->getJson('/<api-route>')->assertStatus(401);
```

## 8. Run

```bash
./vendor/bin/pest tests/Functional/Controller/<Subject>/<Name>ControllerTest.php
./vendor/bin/pest --testsuite=Functional                   # whole HTTP layer
./vendor/bin/pest --filter="returns a 201"
./vendor/bin/pest                                          # full suite
```

If the test fails with a 500 + a Domain exception trace, the translation in `bootstrap/app.php` is missing or wrong — fix it there, not by catching exceptions in the controller.

## Anti-patterns to refuse

- **Asserting on DB state** (`assertDatabaseHas`) inside a controller test. That's `create-tests-usecase`'s job. Here you verify the HTTP response only.
- **Asserting `Event::assertDispatched` / `Bus::assertDispatched`** inside a controller test. Same — Use Case test owns the dispatch assertion. Here you only `fake()` to isolate.
- **Re-testing every business rule for both JSON and web transports.** Pick one transport per rule. Most projects converge on testing one rule once via the JSON API path because it's terser.
- **Calling the Use Case directly from a controller test.** If you want that, you wanted a Use Case test — write one with `create-tests-usecase`.
- **`postJson` with snake_case keys when the `Request` DTO is camelCase** (or vice versa). The request body must mirror the DTO. Mismatch makes the test fail for the wrong reason — looks like a validation bug, actually a transport mismatch.
- **`Event::fake()` bare** (no argument). Silences all listeners — including the ones whose absence would surface a real wiring bug elsewhere. Always pass a class array.
- **Asserting the entire JSON response body verbatim** with `assertExactJson`. Brittle. Prefer `assertJsonStructure` for shape, `assertJsonFragment` for the meaningful fields.
- **Tests for routes that don't exist yet.** Add the route + controller first (out of scope for this skill — see `create-usecase` for the backend wiring and `create-front` for the page).

## Sources of truth

- `tests/Functional/Controller/User/CreateUserControllerTest.php` — canonical pattern (JSON 201, JSON 422, web form 302 + errors, web form success + flash).
- `routes/web.php`, `routes/api.php` — the route table.
- `bootstrap/app.php` — Domain exception → HTTP status translation (the layer this skill verifies works end-to-end).
- `app/Infrastructure/Http/Controller/<Subject>/<Name>Controller.php` — the controller under test.
- Inertia testing reference — `Inertia\Testing\AssertableInertia`.
