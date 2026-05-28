---
name: create-action
description: Turn a business need into an Action (layered Laravel + Active Record) — scaffold the input Data DTO, business Rules, native Event + Listener, the Action itself, then optionally the Eloquent model + migration so the slice is immediately usable. Use when asked to add a use case, create an Action, implement a feature, scaffold a command/query, or "transformer un besoin métier en code".
---

You are a **senior Laravel engineer**. Job: take a business need and produce a **use case as an Action** plus the pieces it needs (input `Data`, business `Rules`, native `Event`/`Listener`, Eloquent `Model`). Tests are out of scope (`/create-tests-action`, `/create-tests-functional`). Controllers, routes, rate-limiters and front come after (`/create-controller`, `/create-front`).

> "Use case" here = an **Action** (`app/Actions/`). The hexagonal layout (Repository, Factory, Domain interfaces, ports) is gone — see `docs/architecture.md`.

Canonical slice to mirror: `app/Actions/CreateUser.php` + `app/Data/CreateUserData.php` + `app/Rules/EmailIsUnique.php`. When in doubt, open them.

## 1. Pin the business need (do not skip)

Lock these before writing a file — ask the user if unclear:

| Question | Why it matters |
|---|---|
| **What triggers it?** (HTTP route, queue job, scheduler, internal call) | Decides whether a Controller follows (out of scope). |
| **Input fields + constraints** | Become `Data` properties + `rules()` (form validation). |
| **Command or Query?** | Command mutates + may dispatch an event. Query is read-only — no Event, often no Data. |
| **Output shape** | A Model? A collection? A scalar? The Action returns it directly. |
| **Business rules** | Each precondition (uniqueness, state invariant) becomes a `Rules\*` `ValidationRule`. |
| **Side effects** (mail, async) | Each becomes a native Event + a Listener (Mailable for email). |

Echo the answers back to the user before scaffolding.

**If invoked with a `docs/features/<slug>.md` path**, that doc is the source of truth — read it first, skip pinned questions, and update it in step 8.

## 2. Name the Action

Verb-led, PascalCase, single concept: `CreateUser`, `ArchiveQuote`, `SendQuoteReminder`. One Action = one file `app/Actions/<Name>.php`, `final readonly`.

## 3. Write the input Data DTO — `app/Data/<Name>Data.php`

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

final class <Name>Data extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:180'],
            'firstName' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate($request->all());
    }

    public static function fromValues(string $email, ?string $firstName = null): self
    {
        return self::validateAndCreate(compact('email', 'firstName'));
    }
}
```

Rules:
- `extends Spatie\LaravelData\Data`, properties **public `readonly`**, promoted.
- **Constructor stays public** — a `private`/`protected` ctor breaks Spatie hydration (`DataFromArrayResolver` instantiates through it). The "no direct `new`" guarantee is enforced by `tests/Unit/ArchDataConstructionTest.php`, not by visibility.
- Built **only** via named constructors (`fromRequest` / `fromValues`), each calling `validateAndCreate` → form is validated exactly once, for every caller. Add a `fromX` per source you actually need.
- `rules()` carries **form validation only** (`required`, `email`, `max`, `Rule::enum(...)`, …). Field names in **camelCase**, matching the frontend `useForm({ ... })` keys.
- **Never** put business rules here ("email must be unique" is a `Rule`, step 4).
- For enum properties, validate with `Rule::enum(<Enum>::class)`; in `fromValues` pass the enum's `->value` so the array stays scalar before casting.

## 4. Business rules — `app/Rules/<Rule>.php`

One reusable object per business precondition. **Reuse before adding** — check `app/Rules/` first.

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\<Model>;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class <Rule> implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (/* precondition fails */) {
            $fail('validation.<key>')->translate();
        }
    }
}
```

- The Model exposes the *query* the rule needs (Eloquent dynamic `where<Col>()`, or a scope) — the rule owns the *assertion*. Never put the assertion on the Model (no fat model).

## 5. Side effects — Event + Listener (+ Mailable)

- Native event `app/Events/<Verb>ed.php`: `final`, `use Dispatchable`, carries the Model.
- Listener `app/Listeners/<Imperative>.php`: `handle(<Event> $event)`.
- Register the mapping **explicitly** in `app/Providers/EventServiceProvider.php` `$listen` (no auto-discovery).
- Email → a `Mailable` (`ShouldQueue`) in `app/Mail/`, queued from the listener via `Mail::to(...)->send(...)`.

```php
// app/Events/<Verb>ed.php
final class <Verb>ed
{
    use Dispatchable;

    public function __construct(public readonly <Model> $<model>) {}
}
```

## 6. Write the Action — `app/Actions/<Name>.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\<Name>Data;
use App\Events\<Verb>ed;
use App\Models\<Model>;
use App\Rules\<Rule>;
use Illuminate\Support\Facades\Validator;

final readonly class <Name>
{
    public function handle(<Name>Data $data): <Model>
    {
        // 1. Business rules — the Action is the authority, whatever the caller.
        Validator::make(['email' => $data->email], ['email' => [new <Rule>]])->validate();

        // 2. Active Record (map camelCase DTO → snake_case columns).
        $model = <Model>::create([
            'email' => $data->email,
            'first_name' => $data->firstName,
        ]);

        // 3. Native event.
        <Verb>ed::dispatch($model);

        return $model;
    }
}
```

Rules:
- `final readonly class`, single public method `handle(<Name>Data $data)`. No constructor dependencies — use Eloquent and facades directly.
- **Never** use `Illuminate\Http` (no `Request`, no `Response`) — that boundary is the Controller's. Enforced by ArchTest.
- Order: **business rules → persist → dispatch → return**.
- Form validation is NOT re-done here — it already happened in the `Data` constructor. The Action only owns *business* rules.
- A pure Query Action skips Data/Rules/Event noise: it just reads (`<Model>::where(...)->get()`) and returns.

## 7. Eloquent model + migration (ask the user first)

If the aggregate has no model yet, generate one. **Ask first** — skip when it maps to an existing model/table or storage is non-relational.

```php
// app/Models/<Model>.php
final class <Model> extends Model
{
    use HasUuids; // UUIDv7 here; identity assigned at creation

    protected $fillable = [/* columns except id/timestamps */];

    /** @return list<string> */
    public function uniqueIds(): array { return ['uuid']; } // uuid column, id stays PK

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            // '<col>' => <Enum>::class,
        ];
    }
}
```

- `use HasUuids` (native, UUIDv7 in this Laravel) + `uniqueIds(): ['uuid']` → `uuid` filled at creation, `id` stays the auto-increment PK. No `booted()` hook.
- `User` extends `Authenticatable`; generic aggregates extend `Model`.
- Migration via `php artisan make:migration create_<table>_table --create=<table>`: `$table->id()`, `$table->uuid('uuid')->unique()`, columns, `$table->timestamps()`, `softDeletes()->index()` only if needed. Then `php artisan migrate`.

## 8. Sync the feature doc (if any)

If invoked from `docs/features/<slug>.md`: tick only what shipped this run (items pointing to other skills stay `- [ ]`), bump `Status:` (`accepted` → `in progress` on first tick; `done` only when all DoD items are ticked), and fix concrete details (migration timestamp, chosen column types).

## 9. Verify + hand off

```bash
./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php
composer lint:check
```

Fix violations in the code you wrote. Then state to the user:
- **Done**: Action + Data + Rules + Event/Listener + (if opted in) Model + migration.
- **Not done here**: Controller + route + rate-limiter (`/create-controller`), front (`/create-front`), tests (`/create-tests-action`, `/create-tests-functional`).

## Anti-patterns to refuse

- **Business logic in the Data.** `rules()` is form only; preconditions are `Rules` objects.
- **`Illuminate\Http` inside an Action.** The Action takes a `Data`, never a `Request`.
- **`new <Name>Data(...)` anywhere outside `app/Data/`.** Always `fromRequest` / `fromValues` (ArchDataConstructionTest fails otherwise).
- **Re-introducing Repository/Factory/interface indirection.** Use Eloquent directly; the model is the seam.
- **Private/protected Data constructor.** It breaks Spatie hydration — the AST test is the guardrail instead.
- **Validation assertion on the Model (fat model).** The model exposes the query; the `Rule` asserts.
- **An Action calling another Action.** Share logic via a `Rule` or a small helper, not by chaining use cases.
- **Auto-discovered event listeners.** Wire them explicitly in `EventServiceProvider`.

## Sources of truth

- `app/Actions/CreateUser.php` — canonical Action.
- `app/Data/CreateUserData.php`, `app/Data/SubmitContactRequestData.php` — DTO + named constructors (enum case).
- `app/Rules/EmailIsUnique.php` — business rule pattern.
- `app/Events/UserCreated.php` + `app/Listeners/SendWelcomeEmail.php` + `app/Mail/WelcomeEmail.php` — side-effect chain.
- `app/Providers/EventServiceProvider.php` — explicit event→listener map.
- `app/Models/User.php`, `app/Models/ContactRequest.php` — Active Record + HasUuids.
- `docs/architecture.md` — full ruleset (French).
- `tests/Unit/ArchTest.php` + `tests/Unit/ArchDataConstructionTest.php` — the mechanical guardrails.
