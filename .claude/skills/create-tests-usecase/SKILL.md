---
name: create-tests-usecase
description: Generate Pest tests for a Use Case (Application + Domain) — Feature tests with real container + DB assertions, Event/Bus fakes, Domain Service fakes via anonymous classes, plus Unit tests for pure Domain helpers when warranted. Use when asked to test a Use Case, add Use Case tests, cover a Specification, write Domain unit tests, or generate Pest coverage for backend business logic. HTTP/Controller tests are handled by `create-tests-functional`.
---

You are a **senior PHP / Pest engineer**. Job: produce the test coverage for a given Use Case — Feature tests using the real Laravel container + an in-memory SQLite (per `RefreshDatabase`), plus Unit tests for **pure Domain** helpers when the logic warrants it. Lean on Eloquent factories as fixtures; fake Domain Services with anonymous classes.

Tests run with `./vendor/bin/pest`. Pest already extends `TestCase` and applies `RefreshDatabase` under `tests/Feature` (see `tests/Pest.php`).

## 1. Identify the Use Case under test

Open and read:

```bash
ls app/Application/UseCase/<Name>/
```

Take note of:
- **Request fields** (`Request.php`) — these drive your scenario inputs.
- **Return type** (`UseCase::execute(...)` signature) — Domain entity? `Response`? Void?
- **Dependencies** in the constructor — repositories, factories, specs, dispatchers, Domain services.
- **Specifications called** — each `isSatisfiedBy()` branch is a candidate failure scenario.
- **Events dispatched** — each `$events->dispatch(new X(...))` is a "side effect" assertion.
- **Jobs wired** — open `app/Infrastructure/Providers/DomainServiceProvider::boot()` and find any `$events->listen(<Event>::class, fn (...) => <Job>::dispatch(...))`. That mapping needs a test.

If the Use Case isn't found, **stop and confirm the path** with the user before fabricating tests.

## 2. Pick which test files to create

| Test file | Path | When |
|---|---|---|
| **Use Case Feature test** | `tests/Feature/UseCase/<Name>Test.php` | Always. Primary coverage. |
| **Domain Unit test** | `tests/Unit/Domain/<Subject>/<Class>Test.php` | When a Domain helper (Specification with multiple branches, Value Object, Factory in `Domain/Factory/` that isn't trivial) holds enough logic that container-based tests would be wasteful. |

**HTTP / Controller tests are out of scope here** — they belong to `create-tests-functional` (testsuite `Functional`, path `tests/Functional/Controller/...`). If the Use Case has an HTTP entry, hand off to that skill once this one is done.

**One file = one test subject.** Never assert on HTTP behaviour and Use Case logic in the same file — split.

## 3. Plan scenarios before writing

For the Use Case Feature test, write a quick list before any code:

| Scenario | Asserts |
|---|---|
| Happy path | return value (entity shape + identity), DB row(s) via `assertDatabaseHas`, event dispatched via `Event::assertDispatched`. |
| Each Specification failure | `expect(fn () => …)->toThrow(ValidationsException::class)` + `assertDatabaseCount(<table>, <expected unchanged>)`. |
| Each side effect | Event/Job/Service dispatched with the right payload. One assertion per outgoing message. |
| Edge cases driven by Request optional fields | Null vs filled propagation through the chain. |

Aim for **3–6 scenarios per Use Case**. A flat list of 15 micro-tests is noise.

## 4. Fixtures — pick the right tool

| Need | Use |
|---|---|
| A pre-existing aggregate in the DB | `User::factory()->create(['email' => '...'])`. Factory lives at `database/factories/<Name>Factory.php`. |
| Multiple aggregates with shared traits | Factory states: `User::factory()->unverified()->count(3)->create()`. |
| A pre-existing aggregate **without** persistence (rare in Feature tests) | `User::factory()->make([...])` — returns the model unsaved. |
| Bypass a Domain Service call | Anonymous class implementing the `Domain/Service/<X>Interface`, bound via `$this->app->instance(<X>Interface>::class, $fake)`. |
| Bypass async work | `Bus::fake([<Job>::class])` + `Bus::assertDispatched(...)`. |
| Bypass Domain Event consumers | `Event::fake([<Event>::class])` + `Event::assertDispatched(...)`. |

**Add a new factory** when the Use Case introduces a new aggregate:

```bash
# Mirror database/factories/UserFactory.php and wire it on the Eloquent model
# via #[UseFactory(...)] + newFactory() + HasFactory trait.
```

A factory **only** exists for an `Infrastructure/Entity/<Name>` Eloquent model — there is no concept of "Domain fixture" in this codebase.

## 5. Use Case Feature test — the canonical pattern

```php
<?php

declare(strict_types=1);

use App\Application\UseCase\<Name>\Request;
use App\Application\UseCase\<Name>\UseCase;
use App\Domain\Entity\<Aggregate>Interface;
use App\Domain\Event\<Aggregate>\<EventName>;
use App\Domain\Exception\ValidationsException;
use App\Infrastructure\Entity\<Aggregate>;
use App\Infrastructure\Job\<JobName>;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| <Name> Use Case
|--------------------------------------------------------------------------
| Application Use Case only. HTTP/transport in tests/Functional/Controller/.
*/

it('does the happy path', function () {
    Event::fake([<EventName>::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    $entity = $useCase->execute(new Request(
        // exact field names from Request.php (camelCase)
    ));

    expect($entity)->toBeInstanceOf(<Aggregate>Interface::class);
    expect($entity->getUuid()->toString())->toBeUuid();
    // assert on each meaningful getter

    $this->assertDatabaseHas('<table>', [
        'column' => 'value',
    ]);

    Event::assertDispatched(<EventName>::class, function (<EventName> $event) use ($entity) {
        return $event->aggregateId()->equals($entity->getUuid());
    });
});

it('rejects <precondition> via specification', function () {
    <Aggregate>::factory()->create(['column' => 'conflicting-value']);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    expect(fn () => $useCase->execute(new Request(/* conflicting input */)))
        ->toThrow(ValidationsException::class);

    $this->assertDatabaseCount('<table>', 1); // nothing new created
});

it('dispatches the <Job> when the Domain Event fires', function () {
    Bus::fake([<JobName>::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);
    $entity = $useCase->execute(new Request(/* valid input */));

    Bus::assertDispatched(
        <JobName>::class,
        fn (<JobName> $job) => $job-><aggregate>Uuid === $entity->getUuid()->toString(),
    );
});
```

Rules embedded above:
- **Resolve via `app(UseCase::class)`** — use real bindings, real Eloquent, real SQLite. The architecture is set up so this is fast.
- **Never call `User::create([...])`** to build a precondition — use the factory, or the Use Case under test itself.
- **Fake only what you assert on** — `Event::fake([X])` not `Event::fake()` (the latter silences everything, including unrelated listeners).
- **Custom Pest expectations available**: `toBeUuid()`, `toBeOne()` (see `tests/Pest.php`). Use them where they read better.
- **Strict types declaration at the top of every test file.**
- **`it('...')` description in present-simple, English** ("creates", "rejects", "dispatches"). Match the existing style.

## 6. Domain Service fakes — anonymous classes

When the Use Case calls a `Domain/Service/<X>Interface`, fake it inline:

```php
$fakeMailer = new class implements MailerInterface {
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function send(string $toEmail, ?string $toName, string $subject, string $view, array $context = []): void
    {
        $this->calls[] = compact('toEmail', 'toName', 'subject', 'view', 'context');
    }
};

$this->app->instance(MailerInterface::class, $fakeMailer);

app(UseCase::class)->execute(new Request(/* ... */));

expect($fakeMailer->calls)->toHaveCount(1);
expect($fakeMailer->calls[0]['toEmail'])->toBe('expected@example.com');
```

Why anonymous classes:
- Zero ceremony, lives next to the assertion.
- No file to maintain.
- Captures only what *that* test asserts on.

Reuse the fake across `it()` blocks only if 3+ tests share the exact same shape — otherwise duplicate.

## 7. Unit tests — only when Domain logic justifies it

Add a Unit test under `tests/Unit/Domain/<Subject>/` when:

- A Specification has **3+ branches** worth covering in isolation (faster than booting the container).
- A Value Object has **non-trivial construction or comparison** (`equals`, `withX(...)`).
- A Domain Service interface has a **pure helper implementation in Domain** (rare — most live in Infrastructure).

Pattern:

```php
<?php

declare(strict_types=1);

use App\Domain\Specification\Can<Verb><Aggregate>;
use App\Domain\Repository\<Aggregate>RepositoryInterface;

it('passes when no conflict is found', function () {
    $repo = new class implements <Aggregate>RepositoryInterface {
        public function findActiveByEmail(string $email): ?<Aggregate>Interface { return null; }
        // implement the other methods to satisfy the interface
    };

    $spec = new Can<Verb><Aggregate>($repo);

    expect($spec->isSatisfiedBy('any@example.com', exceptionMode: false))->toBeTrue();
});
```

Rules for Unit tests:
- **Zero Laravel facades, zero `app()`**, zero DB. The point is speed and isolation.
- Use anonymous classes (or simple stubs) for Domain repository interfaces. **No mocking library** — the project hasn't reached for one and doesn't need it.
- One file per Domain class under test.

## 8. Run and iterate

```bash
./vendor/bin/pest tests/Feature/UseCase/<Name>Test.php   # the file you just wrote
./vendor/bin/pest --filter="creates a user"               # one scenario
./vendor/bin/pest                                          # full suite
composer test                                              # config:clear + pint + pest
```

If a test is **flaky**, suspect:
- DB state leaking between tests (forgot `RefreshDatabase` — but `tests/Pest.php` applies it across `Feature`, so this means a test in `Unit/` hit the DB; move it).
- A `Bus::fake()` / `Event::fake()` placed *after* the dispatch.
- A real listener firing because you only faked the event class but the listener does I/O — fake the consequence (`Bus::fake`) too.

## Anti-patterns to refuse

- **Mocking repositories in a Use Case test.** The real `EloquentUserRepository` + SQLite is fine and exposes the binding wiring at the same time. Mocks here only catch interface contract drift, not Use Case behaviour.
- **HTTP assertions in a Use Case test, or Use Case assertions in a Controller test.** Each file owns one layer — see `[[feedback-test-layering]]` memory.
- **`User::create([...])` to seed state.** Use the factory. The factory enforces UUID + defaults; manual creation skips them.
- **`Event::fake()` (bare) when you only assert on one event.** Use `Event::fake([X::class])` so unrelated listeners still run — otherwise you silence the very Job→Event mapping you should be verifying elsewhere.
- **Asserting `Queue::assertPushed` on the Use Case test when the Job is dispatched by a Domain Event listener.** Use `Bus::fake([<Job>::class])`, and assert dispatch happened. The listener wiring lives in `DomainServiceProvider`; the test confirms the wiring still works.
- **Tests against private methods.** If you need to reach in, refactor toward a Domain helper and test that.
- **Magical shared `beforeEach`** that prepares fixtures for unrelated tests. Inline the setup so each `it()` reads top-to-bottom.
- **Naming a test `it('test creates user')`.** Drop `test`. Lowercase. Match the verb the Use Case performs.
- **Asserting on the `password` / `remember_token` columns.** They're factory leftovers (auth scaffolding), not part of the Use Case contract.

## Sources of truth

- `tests/Feature/UseCase/CreateUserTest.php` — canonical Use Case test.
- `tests/Feature/UseCase/SendWelcomeEmailTest.php` — canonical pattern for **faking a Domain Service** via anonymous class.
- `tests/Unit/ArchTest.php` — Unit test about the layering itself — leave it alone unless adding allow-list entries.
- `tests/Pest.php` — `RefreshDatabase` mapping, custom expectations (`toBeUuid`, `toBeOne`).
- `database/factories/UserFactory.php` — canonical fixture/factory.
- `app/Infrastructure/Entity/User.php` — how an Eloquent model is wired to its factory via `#[UseFactory(...)]` + `newFactory()`.
- `app/Infrastructure/Providers/DomainServiceProvider.php` — bindings and event-to-job listeners; consult to know which Jobs to assert against.
