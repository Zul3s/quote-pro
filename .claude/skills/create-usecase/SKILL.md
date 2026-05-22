---
name: create-usecase
description: Turn a business need into a DDD Use Case (Application → Domain) — scaffold Request/UseCase/Response, derive Domain interfaces, specifications and events, wire bindings. Use when asked to add a Use Case, create a Use Case, implement a feature, scaffold a command/query, or "transformer un besoin métier en code".
---

You are a **senior PHP / DDD engineer**. Job: take a business need and produce the Application + Domain artefacts so the orchestration runs. Tests are out of scope — another skill owns them. Infrastructure implementations (Eloquent models, Eloquent repositories, Laravel adapters) are out of scope too — you stop at writing the Domain interfaces and updating the bindings list.

Canonical template to mirror: `app/Application/UseCase/CreateUser/`. When in doubt, open it.

## 1. Pin the business need (do not skip)

Before writing a single file, lock these answers — ask the user if any are unclear:

| Question | Why it matters |
|---|---|
| **What triggers it?** (HTTP route, queue job, scheduler, internal call) | Determines whether you need a Controller later (out of scope). |
| **Input fields and their constraints** | Becomes `Request` properties + `spatie/laravel-data` validation attributes. |
| **Command or Query?** | Command mutates state and may dispatch events. Query is read-only — skip Factory + Event. |
| **Output shape** | Domain entity? Array of entities? Combined payload? Decides whether to write a `Response` DTO. |
| **Business rules** | Each precondition becomes a `Can<X>` specification. |
| **Side effects** (mail, async work) | Each becomes a Domain Event + a listener-to-Job mapping. |

Write the answers in a short list back to the user before scaffolding — surfaces misunderstandings cheaply.

## 2. Decide the Use Case name and directory

- Verb-led, PascalCase, single concept: `CreateUser`, `ArchiveQuote`, `SendQuoteReminder`. Not `UserService`, not `Quotes`.
- Directory: `app/Application/UseCase/<Name>/`.
- One Use Case = one directory.

```bash
mkdir -p app/Application/UseCase/<Name>
```

## 3. Write the Request

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\<Name>;

use App\Application\UseCase\AbstractRequest;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;

final class Request extends AbstractRequest
{
    public function __construct(
        #[Required, Email, Max(180)]
        public string $email,

        #[Nullable, Max(100)]
        public ?string $firstName = null,
    ) {}
}
```

Rules:
- `final class Request extends AbstractRequest` — **must** extend `AbstractRequest` (ArchTest enforces).
- Promoted constructor properties only. Public, typed.
- Field names in **camelCase** — they must match the frontend `useForm({ ... })` keys exactly.
- Validation attributes from `Spatie\LaravelData\Attributes\Validation\*`. Common ones: `Required`, `Nullable`, `Email`, `Max(N)`, `Min(N)`, `Uuid`, `In([...])`, `Regex('/.../')`, `Boolean`, `IntegerType`, `StringType`.
- **Never** put business logic here — validation only. "Email must be unique" is a Specification, not a Request rule.

## 4. Discover Domain dependencies (audit before creating)

For each piece the orchestration needs, **check what already exists** before adding anything:

```bash
ls app/Domain/Entity/        # *Interface.php files
ls app/Domain/Repository/    # *RepositoryInterface.php
ls app/Domain/Factory/       # *FactoryInterface.php
ls app/Domain/Specification/ # Can*.php
ls app/Domain/Service/       # *Interface.php (mailer, etc.)
ls app/Domain/Event/         # <Aggregate>/<Event>.php
```

Map the business need to Domain pieces:

| Need | Domain piece (file) | Add if missing |
|---|---|---|
| Reference an aggregate (User, Quote, …) | `App\Domain\Entity\<Name>Interface` extending `EntityInterface` | New aggregate → write the interface (getters/setters typed in Domain language). |
| Persist or fetch it | `App\Domain\Repository\<Name>RepositoryInterface` | Add methods you need (`save`, `findByUuid`, `findActiveByEmail`, …). Method names are business-led, not SQL-led. |
| Construct a fresh aggregate | `App\Domain\Factory\<Name>FactoryInterface::create(...)` | Mirror Request fields, return the Entity interface. |
| A precondition that depends on state | `App\Domain\Specification\Can<Verb><Name>` extending `AbstractSpecification` | One spec per rule; throws `ValidationsException` via `toReturn($reasons, $exceptionMode)`. |
| Notify others something happened | `App\Domain\Event\<Aggregate>\<EventName>` — `final readonly`, past tense | New aggregate folder if needed. |
| Reach external systems (mail, payment, …) | `App\Domain\Service\<Name>Interface` | Add the interface only — implementation lives in `Infrastructure/Service/`. |

**Reuse before extending.** If `UserRepositoryInterface` already has the lookup you need, do not add a near-duplicate. If you must extend, add a method to the existing interface — don't create a parallel one.

## 5. Write missing Domain pieces

All Domain code is **pure PHP**. Allowed imports: `App\Domain`, `Ramsey\Uuid`, `DateTimeImmutable`, `RuntimeException`. Anything else triggers an ArchTest failure.

### Entity interface (only for a new aggregate)

```php
<?php
declare(strict_types=1);

namespace App\Domain\Entity;

interface <Name>Interface extends EntityInterface
{
    public function get<Field>(): <Type>;
    public function set<Field>(<Type> $value): static;
}
```

### Repository interface (methods are business-led)

```php
<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\<Name>Interface;
use Ramsey\Uuid\UuidInterface;

interface <Name>RepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?<Name>Interface;
    public function save(<Name>Interface $entity): void;
}
```

### Factory interface

```php
<?php
declare(strict_types=1);

namespace App\Domain\Factory;

use App\Domain\Entity\<Name>Interface;

interface <Name>FactoryInterface
{
    public function create(/* mirror Request fields */): <Name>Interface;
}
```

### Specification (one per rule)

```php
<?php
declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Model\ValidationReason;
use App\Domain\Repository\<Name>RepositoryInterface;

final class Can<Verb><Name> extends AbstractSpecification
{
    public function __construct(
        private readonly <Name>RepositoryInterface $repository,
    ) {}

    public function isSatisfiedBy(/* args */, bool $exceptionMode = true): bool
    {
        $reasons = [];

        if (/* failed precondition */) {
            $reasons[] = new ValidationReason('validation.<key>', '<propertyPath>');
        }

        return $this->toReturn($reasons, $exceptionMode);
    }
}
```

### Domain Event — `final readonly`, past-tense name, under `Event/<Aggregate>/`

```php
<?php
declare(strict_types=1);

namespace App\Domain\Event\<Aggregate>;

use App\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

final readonly class <Verb>ed implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public UuidInterface $<aggregate>Uuid,
        /* business-relevant payload only */
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function aggregateId(): UuidInterface     { return $this-><aggregate>Uuid; }
}
```

## 6. Write the UseCase

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\<Name>;

use App\Domain\Entity\<Aggregate>Interface;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Event\<Aggregate>\<EventName>;
use App\Domain\Factory\<Aggregate>FactoryInterface;
use App\Domain\Repository\<Aggregate>RepositoryInterface;
use App\Domain\Specification\Can<Verb><Aggregate>;

final readonly class UseCase
{
    public function __construct(
        private <Aggregate>FactoryInterface $factory,
        private <Aggregate>RepositoryInterface $repository,
        private EventDispatcherInterface $events,
        private Can<Verb><Aggregate> $spec,
    ) {}

    public function execute(Request $request): <Aggregate>Interface
    {
        $this->spec->isSatisfiedBy($request->/* relevant field */);

        $entity = $this->factory->create(/* map Request fields */);

        $this->repository->save($entity);

        $this->events->dispatch(new <EventName>(
            <aggregate>Uuid: $entity->getUuid(),
            /* event payload */
        ));

        return $entity;
    }
}
```

Rules:

- `final readonly class UseCase` — `final` is enforced by ArchTest.
- Constructor takes **only** Domain interfaces (`...Interface`) and Specifications (concrete classes are fine because they're in Domain). Never an Eloquent model, never `Illuminate\Http\Request`, never `app()` / `request()`.
- `execute(Request $request)` is the single entry point. No other public methods.
- Order inside `execute`: **spec → factory → repository → events → return**.
- Return type: prefer the Domain entity (`UserInterface`) or `iterable<XxxInterface>`. Add a `Response` DTO only when the payload is a combination of entities or must be serialised to Inertia/TypeScript.

### When (and only when) you write a Response

```php
<?php
declare(strict_types=1);

namespace App\Application\UseCase\<Name>;

use App\Application\UseCase\AbstractResponse;

final class Response extends AbstractResponse
{
    public function __construct(
        public string $userUuid,
        public string $confirmationToken,
        /* combined / serialisable fields */
    ) {}
}
```

The Use Case then returns `new Response(...)`. Don't create one "just in case."

## 7. Wire bindings in `DomainServiceProvider`

Edit `app/Infrastructure/Providers/DomainServiceProvider.php`:

```php
public array $bindings = [
    // existing...
    <Aggregate>RepositoryInterface::class => Eloquent<Aggregate>Repository::class,
    <Aggregate>FactoryInterface::class    => <Aggregate>Factory::class,
];

public function boot(Dispatcher $events): void
{
    // existing...
    $events->listen(<EventName>::class, function (<EventName> $event): void {
        <JobName>::dispatch($event-><aggregate>Uuid->toString());
    });
}
```

- Every new `…Interface` you wrote gets a line in `$bindings`. **Explicit, no auto-discovery** — project convention.
- Every new Domain Event with an async consequence gets a `$events->listen(...)` line in `boot()`.
- If the Eloquent implementation doesn't exist yet, **stop and tell the user** — the binding will resolve at runtime with a missing class. Out of this skill's scope, but flag it.

## 8. Verify the layering before handing off

Run the architectural guardrails — no need to invoke the test skill yet:

```bash
./vendor/bin/pest tests/Unit/ArchTest.php
composer lint:check
```

If ArchTest fails, fix the violation **in the code you just wrote**, not by editing the allow-list — unless you genuinely need a new dependency, in which case update `tests/Unit/ArchTest.php` *and* `docs/architecture.md` in the same change.

## 9. Hand off — what's left, who owns it

State clearly to the user:

- **Done**: Application Use Case + Domain interfaces + bindings.
- **Not done by this skill**:
  - Eloquent model + Eloquent repository in `app/Infrastructure/` (will throw `BindingResolutionException` until written).
  - Migration in `database/migrations/`.
  - Controller + route entry point.
  - Tests (Pest) — invoke the test-creation skill for that.

## Anti-patterns to refuse

- **Putting validation logic in the Request.** Attribute-level constraints only (type, length, format). Business rules live in `Can<X>` specifications.
- **Calling Eloquent from the UseCase.** `User::create(...)`, `User::query()`, `DB::transaction(...)` — all forbidden. Go through `RepositoryInterface`.
- **Importing `Illuminate\*` in Application** — not in the ArchTest allow-list. If you genuinely need a Laravel contract, prefer wrapping it behind a `Domain/Service/<X>Interface`.
- **A Response that wraps a single Domain entity** — return the entity directly.
- **Use Case with multiple public methods** — split into multiple Use Cases.
- **Spec that doesn't throw** — `isSatisfiedBy($x)` with default `$exceptionMode = true` should throw `ValidationsException`. Only pass `false` when the caller needs the bool and handles it.
- **Event with no `aggregateId`** — every event references its aggregate's UUID.
- **Adding a Repository method that returns "the user where X = Y" purely for one Use Case** — fine if it'll be reused; if not, consider whether the Use Case has the wrong shape.
- **Coupling two Use Cases by calling one from another.** Use Cases call Domain, not other Use Cases. Shared logic goes into a Domain Service or a Specification.

## Sources of truth

- `app/Application/UseCase/CreateUser/` — canonical structure.
- `app/Application/UseCase/AbstractRequest.php`, `AbstractResponse.php` — base classes.
- `app/Domain/Specification/AbstractSpecification.php` + `CanCreateUser.php` — specification pattern.
- `app/Domain/Event/User/UserCreated.php` — event pattern.
- `app/Infrastructure/Providers/DomainServiceProvider.php` — bindings + event-to-job map.
- `docs/architecture.md` — full ruleset (French).
- `tests/Unit/ArchTest.php` — the mechanical enforcement.
