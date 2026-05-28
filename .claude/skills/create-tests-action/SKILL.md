---
name: create-tests-action
description: Generate Pest tests for an Action and its collaborators — Feature tests with real container + DB assertions, Event/Mail fakes, plus DTO-boundary validation tests and Listener tests. Use when asked to test an Action/use case, cover a business Rule, test an event listener, or generate Pest coverage for backend business logic. HTTP/Controller tests are handled by `create-tests-functional`.
---

You are a **senior Laravel / Pest engineer**. Job: cover an **Action** (`app/Actions/<Name>.php`) and its collaborators — Feature tests on the real container + in-memory SQLite (`RefreshDatabase`), DTO-boundary validation tests, and Listener tests. HTTP/Controller tests belong to `/create-tests-functional`.

Pest already extends `TestCase` + applies `RefreshDatabase` under `tests/Feature` and `tests/Functional` (see `tests/Pest.php`).

> The codebase is layered Laravel (Actions + Active Record) — no Repository/Factory interfaces, no Domain ports. See `docs/architecture.md`.

## 1. Read the Action under test

Open `app/Actions/<Name>.php` and note:
- **Input DTO** (`app/Data/<Name>Data.php`) — its `rules()` drive form-validation scenarios; its fields drive `fromValues(...)` args.
- **Return type** of `handle(...)` — a Model? a collection? a scalar?
- **Business `Rules`** triggered (`new <Rule>` inside a `Validator::make(...)->validate()`) — each is a failure scenario.
- **Events dispatched** (`<Event>::dispatch(...)`) — each is a side-effect assertion.
- **Listeners** wired in `app/Providers/EventServiceProvider.php` `$listen` — each event→listener mapping needs a test.

If the Action isn't found, **stop and confirm the path** with the user.

## 2. Pick the test files

| Test file | Path | When |
|---|---|---|
| **Action Feature test** | `tests/Feature/Action/<Name>Test.php` | Always — primary coverage. |
| **Listener Feature test** | `tests/Feature/Listener/<Listener>Test.php` | When the Action's event has a listener with a side effect (mail, etc.). |
| **Domain helper Unit test** | `tests/Unit/<Subject>Test.php` | Only when a pure helper (no DB) holds enough logic that booting the container is wasteful. A `Rule` that queries the DB is **not** this — it's covered in the Action Feature test. |

**One file = one subject.** Never mix HTTP and Action assertions — HTTP is `/create-tests-functional`'s job (see `[[feedback_test_layering]]`).

## 3. Plan scenarios (3–6 per Action)

| Scenario | Asserts |
|---|---|
| Happy path | return value (model identity via `toBeUuid()`, attributes), `assertDatabaseHas`, `Event::assertDispatched`. |
| Each business Rule failure | `expect(fn () => (new <Name>)->handle(...))->toThrow(ValidationException::class)` + `assertDatabaseCount(<table>, <unchanged>)`. |
| Malformed input at the DTO boundary | `expect(fn () => <Name>Data::fromValues(/* bad */))->toThrow(ValidationException::class)`. Cover rules PHP types can't enforce (`email` format, `max:N` length, `Rule::enum` value). Skip `required` / non-nullable types — the typed constructor already rejects those. |
| Optional-field edge cases | null vs filled propagation to the DB. |

`Illuminate\Validation\ValidationException` is the exception everywhere now — both form (DTO) and business (`Rule` via `Validator::make`).

## 4. Fixtures & fakes

| Need | Use |
|---|---|
| Pre-existing row | `User::factory()->create(['email' => '...'])`. |
| Multiple with traits | `User::factory()->unverified()->count(3)->create()`. |
| Assert an event without running listeners | `Event::fake([<Event>::class])` + `Event::assertDispatched(...)`. |
| Assert a queued mail (Listener test) | `Mail::fake()` + `Mail::assertQueued(<Mailable>::class, fn ($m) => $m->hasTo(...))`. |

Add a factory when a new aggregate appears (mirror `database/factories/UserFactory.php`, wire via `#[UseFactory]` + `newFactory()` + `HasFactory`).

## 5. Action Feature test — canonical pattern

```php
<?php

declare(strict_types=1);

use App\Actions\<Name>;
use App\Data\<Name>Data;
use App\Events\<Event>;
use App\Models\<Model>;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('does the happy path and dispatches <Event>', function () {
    Event::fake([<Event>::class]);

    $model = (new <Name>)->handle(<Name>Data::fromValues(/* camelCase args */));

    expect($model->uuid)->toBeUuid();
    // assert meaningful attributes

    $this->assertDatabaseHas('<table>', ['column' => 'value']);

    Event::assertDispatched(<Event>::class, fn (<Event> $e) => $e-><model>->is($model));
});

it('rejects <precondition> via the <Rule> business rule', function () {
    <Model>::factory()->create(['column' => 'conflicting']);

    expect(fn () => (new <Name>)->handle(<Name>Data::fromValues(/* conflicting */)))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('<table>', 1);
});

it('rejects malformed input at the DTO boundary', function (callable $build) {
    expect($build)->toThrow(ValidationException::class);
})->with([
    'invalid email' => [fn () => <Name>Data::fromValues(/* bad email */)],
    'field too long' => [fn () => <Name>Data::fromValues(/* over max */)],
]);
```

Rules embedded:
- **Instantiate the Action with `new <Name>`** — it has no constructor dependencies. (Resolving via `app()` also works but is unnecessary.)
- **Build the DTO via `fromValues(...)`** — never `new <Name>Data(...)` (forbidden, validation-bypassing).
- **Fake only what you assert** — `Event::fake([X])`, not bare `Event::fake()` (which would also silence the listener you may want to run).
- `it('...')` in present-simple English. `declare(strict_types=1)` at the top.
- Custom expectations: `toBeUuid()`, `toBeOne()` (`tests/Pest.php`).

## 6. Listener Feature test

```php
<?php

declare(strict_types=1);

use App\Events\<Event>;
use App\Mail\<Mailable>;
use App\Models\<Model>;
use Illuminate\Support\Facades\Mail;

it('queues <Mailable> when <Event> fires', function () {
    Mail::fake();

    $model = <Model>::factory()->create([/* ... */]);

    <Event>::dispatch($model);

    Mail::assertQueued(<Mailable>::class, fn (<Mailable> $mail) => $mail->hasTo($model->email));
});
```

Do **not** `Event::fake()` here — you want the real listener to run so the Mailable is queued.

## 7. Unit tests — only for pure, DB-free helpers

Rare in this codebase (most logic touches Eloquent). If a genuinely pure helper exists: zero facades, zero `app()`, zero DB, one file per subject. The arch tests in `tests/Unit/` are not yours to touch unless adding a guardrail.

## 8. Run

```bash
./vendor/bin/pest tests/Feature/Action/<Name>Test.php
./vendor/bin/pest --filter="does the happy path"
./vendor/bin/pest                                       # full suite
```

Flaky? Suspect: a `Mail::fake()`/`Event::fake()` placed *after* the trigger; a real listener doing I/O because you faked only the event class; a DB-touching test misplaced under `tests/Unit`.

## Anti-patterns to refuse

- **`new <Name>Data(...)` in a test.** Use `fromValues` / `fromRequest` so validation runs (and so the AST guardrail stays honest).
- **HTTP assertions in an Action test** (status codes, redirects) — that's `/create-tests-functional`.
- **`User::create([...])` to seed state.** Use the factory (it fills uuid + defaults).
- **Bare `Event::fake()`** when asserting one event — silences listeners you may need.
- **Re-testing form validation that the typed constructor already enforces** (`required`, enum type) — cover only format/length/enum-value rules.
- **Asserting on `password` / `remember_token`** — factory/auth leftovers, not the use case contract.
- **`it('test creates user')`** — drop `test`, lowercase, match the verb.

## Sources of truth

- `tests/Feature/Action/CreateUserTest.php` — canonical Action test (happy path + business rule).
- `tests/Feature/Action/SubmitContactRequestTest.php` — DTO-boundary validation via dataset.
- `tests/Feature/Listener/SendWelcomeEmailTest.php` — `Mail::fake` + listener.
- `tests/Pest.php` — `RefreshDatabase` mapping + custom expectations.
- `database/factories/UserFactory.php` — canonical factory.
- `app/Providers/EventServiceProvider.php` — event→listener map (what to assert).
