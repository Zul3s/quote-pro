# Architecture — Quote Plus

Ce projet applique une architecture **Laravel layered** idiomatique, articulée autour
d'**Actions** (cas d'usage) et de l'**Active Record** Eloquent.

> **Historique** : le projet a d'abord suivi une architecture hexagonale/DDD (Domain/Application/
> Infrastructure, Repository, Factory, interfaces d'entité, ports). Elle a été abandonnée le
> 2026-05-27 : mettre de l'Active Record *derrière* un Repository cumulait le couplage Eloquent
> **et** la cérémonie Data Mapper sans bénéfice. On a « descendu la couture ».

## Vue d'ensemble

```
HTTP (Controller)  →  Action (orchestration + métier)  →  Model (Active Record)
        │                      │
        │                      ├─ Data (DTO d'entrée, validation de forme)
        │                      └─ Rules (règles métier réutilisables)
```

**Règle d'or** : le HTTP s'arrête au Controller. L'Action porte le métier (validation comprise) et
ne connaît jamais la couche HTTP. Eloquent est le moteur de persistance, utilisé directement.

## Arborescence (`app/`)

| Dossier | Rôle |
|---|---|
| `Models/` | Entités Active Record (Eloquent). Identité UUIDv7 via le trait natif `HasUuids` sur la colonne `uuid`. |
| `Actions/` | Cas d'usage (`final readonly`, `handle(Data): Model`). Orchestration + déclenchement des règles métier. **Jamais de `Illuminate\Http`.** |
| `Data/` | DTO d'entrée (`spatie/laravel-data`). Portent la **validation de forme**. Construits uniquement via des constructeurs nommés. |
| `Rules/` | Règles métier réutilisables (`implements ValidationRule`). Successeurs des Specifications. |
| `Enums/` | Énumérations (`RequestType`, `Deadline`). |
| `Events/` | Événements natifs (`use Dispatchable`), portent le modèle concerné. |
| `Listeners/` | Réactions aux événements (ex. envoi d'email). Câblés explicitement dans `EventServiceProvider`. |
| `Mail/` | Mailables. |
| `Http/Controllers/` | Controllers invokables fins : font le pont HTTP → Data → Action. |
| `Http/Middleware/` | Middlewares HTTP. |
| `Providers/` | `AppServiceProvider` (rate limiters, defaults) et `EventServiceProvider` (mapping event→listener explicite). |

## Modèle de validation

C'est le point structurant du projet. **L'Action est l'autorité de validation** — pas le bord HTTP —
pour qu'une donnée invalide ne puisse jamais l'atteindre, quel que soit l'appelant (HTTP, job, CLI, test).

On distingue deux validations :

1. **Forme** (`required`, `email`, `max`, enum) → portée par le **Data**, déclenchée à sa construction.
2. **Métier** (unicité, invariants) → portée par l'**Action**, via des objets `Rules`.

### Le DTO d'entrée (`app/Data/`)

- Étend `Spatie\LaravelData\Data`, propriétés `readonly`.
- Construit **exclusivement** via des **constructeurs nommés par source**, chacun validant
  (`validateAndCreate`) :
  - `fromRequest(Illuminate\Http\Request $r): self` — couplé HTTP (assumé ; c'est pour ça que les
    DTO vivent dans `app/Data` et non dans `app/Actions`),
  - `fromValues(...): self` — valeurs typées, hors HTTP.
- Le `new XxxData(...)` direct contournerait la validation. Il est **interdit**, garanti par un test
  AST (voir garde-fous). Le constructeur ne peut pas être `private` : Spatie hydrate par réflexion
  via le constructeur et lèverait « Call to private __construct ».

### L'Action (`app/Actions/`)

```php
final readonly class CreateUser
{
    public function handle(CreateUserData $data): User
    {
        // 1. Règle métier — l'Action est l'autorité
        Validator::make(['email' => $data->email], ['email' => [new EmailIsUnique]])->validate();

        // 2. Active Record
        $user = User::create([
            'email' => $data->email,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
        ]);

        // 3. Événement natif
        UserCreated::dispatch($user);

        return $user;
    }
}
```

### Le Controller

```php
final readonly class CreateUserController
{
    public function __invoke(Request $request, CreateUser $action): JsonResponse|RedirectResponse
    {
        $user = $action->handle(CreateUserData::fromRequest($request)); // pont HTTP → Data
        // ... sérialisation JSON ou redirect
    }
}
```

La forme est validée dans `fromRequest` (lève `Illuminate\Validation\ValidationException` →
422 JSON ou redirect-back-with-errors, géré nativement par Laravel). Le métier est validé dans l'Action.

## Garde-fous CI (`tests/Unit/`)

La discipline ne repose pas sur la convention mais sur des règles **mécaniques** vérifiées en CI.

### `ArchTest.php` (Pest arch)
- `App\Actions` : `final` **et** ne dépend jamais de `Illuminate\Http` / `Inertia` (la couture).
- `App\Http\Controllers` : ne touche pas la base directement (`DB` facade).
- `App\Data` : étend `Spatie\LaravelData\Data`.
- `App\Rules` : implémente `Illuminate\Contracts\Validation\ValidationRule`.
- `App\Enums` : sont des enums. `App\Events` : `final`.

### `ArchDataConstructionTest.php` (niveau AST, php-parser)
- Interdit tout `new XxxData(...)` hors `app/Data/` : le DSL Pest arch ne sait pas distinguer une
  instanciation d'une simple référence de type, donc on parcourt l'AST. Force le passage par
  `fromRequest`/`fromValues` (donc par la validation).

## Naming

| Type | Convention | Exemple |
|---|---|---|
| Modèle Eloquent | nom métier | `User`, `ContactRequest` |
| Action | verbe (cas d'usage) | `CreateUser`, `SubmitContactRequest` |
| DTO d'entrée | suffixe `Data` | `CreateUserData` |
| Règle métier | nom affirmatif | `EmailIsUnique` |
| Événement | passé | `UserCreated` |
| Listener | impératif | `SendWelcomeEmail` |
| Controller | suffixe `Controller` | `CreateUserController` |

## Tests

- `tests/Feature/Action/` — Actions (orchestration + règles métier), avec DB et `Event::fake`.
- `tests/Feature/Listener/` — listeners (ex. `Mail::fake`).
- `tests/Functional/Controller/` — couche HTTP (contrat JSON, Inertia/redirect, 422).
- `tests/Unit/` — arch tests + helpers purs.

## Async & événements

- Mapping event → listener **explicite** dans `EventServiceProvider` (`shouldDiscoverEvents(): false`),
  pas d'auto-discovery.
- Les emails sont des Mailables `ShouldQueue`, mis en file par le listener.
