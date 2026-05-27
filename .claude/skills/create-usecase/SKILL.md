---
name: create-usecase
description: Turn a business need into a DDD Use Case (Application → Domain → Eloquent Infra) — scaffold Request/UseCase/Response, derive Domain interfaces/specs/events, wire bindings, then optionally generate the Eloquent model + repository + factory + migration so the slice is immediately consumable. Use when asked to add a Use Case, create a Use Case, implement a feature, scaffold a command/query, or "transformer un besoin métier en code".
---

You are a **senior PHP / DDD engineer**. Job: take a business need, produce the Application + Domain artefacts, then — on confirmation — generate the matching Eloquent Infrastructure so the Use Case resolves at runtime without manual follow-up. Tests are out of scope (handled by `/create-tests-usecase` and `/create-tests-functional`). Controllers, routes, rate-limiters and front are out of scope too — they come after this skill.

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

**If the user passed a `docs/features/<slug>.md` path as argument**, that doc is the source of truth — read it first and skip the questions whose answers are already pinned there. Keep the path in mind: you'll have to update it in step 10.

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
use App\Domain\Service\RequestValidatorInterface;
use App\Domain\Specification\Can<Verb><Aggregate>;

final readonly class UseCase
{
    public function __construct(
        private RequestValidatorInterface $validator,
        private <Aggregate>FactoryInterface $factory,
        private <Aggregate>RepositoryInterface $repository,
        private EventDispatcherInterface $events,
        private Can<Verb><Aggregate> $spec,
    ) {}

    public function execute(Request $request): <Aggregate>Interface
    {
        $this->validator->validate($request);

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
- **First line is always `$this->validator->validate($request);`** — the Use Case is self-defending: the Domain-level `RequestValidatorInterface` translates Spatie's `Illuminate\Validation\ValidationException` into the Domain's `ValidationsException` (mapped to HTTP 422 / Inertia errors by `bootstrap/app.php`). Application stays framework-free; the metier holds regardless of caller (HTTP Controller, async Job, CLI, scheduled task). PHP types alone don't catch `#[Email]` / `#[Max(N)]` violations once someone calls `new Request(...)` directly, so a re-validation is the only honest contract.
- Order inside `execute`: **validate → spec → factory → repository → events → return**.
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
- The Eloquent classes you reference here are scaffolded in step 8 — bindings stay green at runtime.

## 8. Generate the Eloquent Infrastructure (ask the user first)

The Use Case is unusable until the Domain interfaces have concrete implementations. Default behaviour: generate them now so the slice resolves at runtime. **Always ask** before scaffolding — there are cases where you should skip:

- The aggregate maps to an **existing Eloquent model / table** (don't overwrite, the user wires it manually).
- Storage is **not relational** (Redis, external API, ElasticSearch — repository goes through a different adapter).
- The Use Case is a **pure Query** that reuses existing repository methods — nothing new on the Infra side.

Otherwise, generate the four pieces below. **Field names** are derived from the Domain interface (`getX()`/`setX()` → column `snake_case(X)`). **Column types** are inferred from PHP types — when an inference is ambiguous (text vs varchar, decimal precision, enum casting), state your choice in one line to the user before writing.

### 8.1 Eloquent Entity — `app/Infrastructure/Entity/<Name>.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Entity;

use App\Domain\Entity\<Name>Interface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class <Name> extends Model implements <Name>Interface
{
    protected $table = '<table_snake_plural>';

    protected $fillable = [/* every column except id/uuid/timestamps */];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            // enum columns: '<col>' => <Enum>::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entity): void {
            if (empty($entity->getAttribute('uuid'))) {
                $entity->setAttribute('uuid', Uuid::uuid7()->toString());
            }
        });
    }

    public function getUuid(): UuidInterface
    {
        return Uuid::fromString($this->getAttribute('uuid'));
    }

    // Implement every getter/setter from <Name>Interface against $this->getAttribute()/setAttribute().
}
```

Notes:
- Extend `Model` directly — not `Authenticatable` (that's User's auth concern, not the default).
- `final` unless ArchTest or another concrete need says otherwise.
- UUID v7 auto-generated in `booted()` — never expose the autoincrement `id` outside Infra.
- Soft deletes (`SoftDeletes` trait + `softDeletes()->index()` in the migration) **only if** the Domain interface exposes `getDeletedAt()` / `setDeletedAt()`.
- Cast enum columns through Laravel's native enum cast (`'request_type' => RequestType::class`) — no manual conversion in getters.

### 8.2 Repository — `app/Infrastructure/Repository/Eloquent<Name>Repository.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\<Name>Interface;
use App\Domain\Repository\<Name>RepositoryInterface;
use App\Infrastructure\Entity\<Name>;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

final class Eloquent<Name>Repository implements <Name>RepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?<Name>Interface
    {
        return <Name>::query()->where('uuid', $uuid->toString())->first();
    }

    public function save(<Name>Interface $entity): void
    {
        if (! $entity instanceof <Name>) {
            throw new InvalidArgumentException(sprintf(
                'Eloquent<Name>Repository expects %s, %s given.',
                <Name>::class,
                $entity::class,
            ));
        }

        $entity->save();
    }
}
```

- One method per interface method — no extras.
- `instanceof` guard before `->save()` — prevents callers from sneaking in a fake/mock that wouldn't persist.

### 8.3 Concrete Factory — `app/Infrastructure/Factory/<Name>Factory.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\<Name>Interface;
use App\Domain\Factory\<Name>FactoryInterface;
use App\Infrastructure\Entity\<Name>;

final class <Name>Factory implements <Name>FactoryInterface
{
    public function create(/* mirror Domain factory signature exactly */): <Name>Interface
    {
        $entity = new <Name>;
        // Use setAttribute() for fields without a Domain setter (e.g. read-only on the interface).
        // Use $entity->setX() for fields with a Domain setter.

        return $entity;
    }
}
```

- The factory **does not call `save()`** — that's the repository's job. Keep the two responsibilities separate.
- When the Domain interface has only getters (immutable aggregate), use `setAttribute()` directly here.

### 8.4 Migration — `database/migrations/<timestamp>_create_<table>_table.php`

Use `php artisan make:migration create_<table>_table --create=<table>` to get a fresh timestamp, then fill the `Schema::create` block. Column rules:

| Domain type | Migration column |
|---|---|
| `string` required | `$table->string('col', N)` (pick N from `Max(N)` in the Request, else 255) |
| `?string` | `->nullable()` |
| `enum` | `$table->string('col')` — Eloquent enum cast handles the conversion |
| `int` | `$table->integer('col')` or `unsignedInteger` if positive-only |
| `bool` | `$table->boolean('col')` |
| `DateTimeImmutable` (business date) | `$table->timestamp('col')` |
| `text` (long free-form) | `$table->text('col')` (e.g. `description`) |

Mandatory scaffolding for every aggregate table:
- `$table->id();` (autoincrement PK, kept internal)
- `$table->uuid('uuid')->unique();` (external identity)
- `$table->timestamps();` (created_at / updated_at)
- `$table->softDeletes()->index();` **only** if the entity has soft-delete fields.
- `down()` does `Schema::dropIfExists('<table>')`.

### 8.5 Run migrations

```bash
php artisan migrate
```

If the migration fails, fix the column definition — don't roll back the Domain or the Use Case to avoid the schema issue.

## 9. Verify the layering before handing off

Run the architectural guardrails — no need to invoke the test skill yet:

```bash
./vendor/bin/pest tests/Unit/ArchTest.php
composer lint:check
```

If ArchTest fails, fix the violation **in the code you just wrote**, not by editing the allow-list — unless you genuinely need a new dependency, in which case update `tests/Unit/ArchTest.php` *and* `docs/architecture.md` in the same change.

## 10. Sync the feature doc (if any)

If the skill was invoked with a `docs/features/<slug>.md` argument (or you found one matching this Use Case), update it before handing off:

- **Tick only what's actually shipped this run.** A partially-done item stays `- [ ]`. An item that points to another skill (e.g. *"via /create-front"* or *"via /create-tests-usecase"*) stays `- [ ]` until that skill runs.
- **Update `Status:`** — `accepted` → `in progress` on first `- [x]`; bump to `done` only when **every** DoD item is `- [x]`.
- **Fix concrete details** the spec couldn't know yet — e.g. the migration timestamp in the filename, the actual chosen column type (`string` + cast vs SQL enum), the rate-limiter name if you altered it.
- Skip this step only if no such doc exists or the skill wasn't invoked from one.

## 11. Hand off — what's left, who owns it

State clearly to the user:

- **Done**: Application Use Case + Domain interfaces + bindings + (if opted in at step 8) Eloquent Entity + Repository + Factory + migration applied.
- **If step 8 was skipped**: the binding will throw `BindingResolutionException` at runtime until the user writes the Infra by hand — say so explicitly.
- **Not done by this skill, regardless of step 8**:
  - Controller + route entry point + any rate-limiter / middleware (`routes/web.php`, `routes/api.php`, `app/Infrastructure/Http/Controller/...`).
  - Frontend (Inertia pages, forms) — see `/create-front`.
  - Tests (Pest) — see `/create-tests-usecase` for the Use Case layer and `/create-tests-functional` for the HTTP layer.

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

- `app/Application/UseCase/CreateUser/` — canonical Application structure.
- `app/Application/UseCase/AbstractRequest.php`, `AbstractResponse.php` — base classes.
- `app/Domain/Specification/AbstractSpecification.php` + `CanCreateUser.php` — specification pattern.
- `app/Domain/Event/User/UserCreated.php` — event pattern.
- `app/Infrastructure/Entity/User.php` — Eloquent model pattern (note: User extends `Authenticatable` for auth — generic aggregates extend `Model`).
- `app/Infrastructure/Repository/EloquentUserRepository.php` — repository pattern with `instanceof` guard.
- `app/Infrastructure/Factory/UserFactory.php` — concrete factory pattern.
- `app/Infrastructure/Providers/DomainServiceProvider.php` — bindings + event-to-job map.
- `docs/architecture.md` — full ruleset (French).
- `tests/Unit/ArchTest.php` — the mechanical enforcement.
