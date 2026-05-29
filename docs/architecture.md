# Architecture — Quote Plus

Ce projet applique une architecture **Laravel layered** idiomatique, articulée autour
d'**Actions** (cas d'usage) et de l'**Active Record** Eloquent.

## Vue d'ensemble

```
HTTP (Controller)  →  Action (orchestration + métier)  →  Model (Active Record)
        │                      │
        │                      ├─ Data (DTO d'entrée, validation de forme)
        │                      └─ Rules (règles métier réutilisables)
```

**Règle d'or** : le HTTP s'arrête au Controller. L'Action porte le métier (validation comprise) et
ne connaît jamais la couche HTTP. Eloquent est le moteur de persistance, utilisé directement
**dans l'Action**. Corollaire : **tout accès aux données — lecture comme écriture — passe par une
Action**. Un Controller ne référence jamais `App\Models` (garde-fou ArchTest), ce qui exclut aussi
le route-model binding implicite (`__invoke(ArtisanProfile $p)`) : ce binding ferait requêter la base
pour le compte du Controller, c'est la même entorse. Charger une entité depuis une route = une Action
de lecture qui prend l'identifiant en `Data`.

### Écriture vs lecture

Une Action n'est pas qu'une écriture. Deux formes coexistent, l'invariant dur étant uniquement
« la donnée passe par une Action » :

| | Écriture (`CreateUser`, `SaveArtisanProfile`) | Lecture (`ShowArtisanProfile`) |
|---|---|---|
| Entrée | un `Data` (validé) **obligatoire** | un `Data` **optionnel** — présent dès qu'on a besoin de plusieurs entrées (uuid, user courant, filtres…) |
| Sortie | le `Model` persisté | `?Model` / `Collection` |
| Event | **optionnel** — dispatche un événement natif quand le besoin l'exige (le plus souvent en écriture) | **optionnel** — généralement aucun, mais possible si un besoin l'exige |
| Nommage | verbe d'action (`Create`, `Save`, `Submit`) | `Show` / `Get` / `List` |

On accepte la cérémonie d'une Action de lecture parfois triviale (un simple passe-plat Eloquent) :
c'est le foyer naturel du scoping / de l'autorisation à venir, et ça évite une règle « lecture simple
tolérée dans le Controller » que l'ArchTest ne saurait pas trancher (archi à deux vitesses refusée).

#### Le Controller ne nomme jamais un modèle — même au niveau type

La règle « un Controller ne référence jamais `App\Models` » couvre **aussi les références purement
type-level** : pas de `use App\Models\X` pour un `@var`, pas de `Collection<int, X>` en docblock dans
un Controller. Quand le Controller a besoin du type d'un modèle, **ce type est porté par la signature
de retour de l'Action et inféré** :

```php
// L'Action précise le type générique — l'import du modèle est ici, à sa place.
/** @return \Illuminate\Database\Eloquent\Collection<int, ArtisanProfile> */
public function handle(): Collection { return ArtisanProfile::query()->get(); }

// Controller : $profiles est déjà typé Collection<int, ArtisanProfile> par inférence,
// sans aucune annotation ni mention du modèle.
$profiles = $action->handle();
```

Pour resserrer un `?Model` en `Model`, on passe par le **flux de contrôle** (`if ($x === null) abort(404);`
resserre le type en dessous pour PHPStan), jamais par un `@var` qui forcerait un import.

> **Pourquoi pas de FQN en docblock ?** Le fixer Pint `fully_qualified_strict_types` réécrirait
> `\App\Models\X` en nom court importé → réintroduisant le `use` que l'ArchTest interdit (deadlock
> lint ↔ arch). Le deadlock n'est pas un bug du garde-fou : c'est le signal qu'un type modèle remonte
> sur la signature de l'Action, pas dans le Controller. On a écarté l'option inverse (désactiver le
> fixer Pint pour tolérer les références type-only) : elle affaiblit Pint pour tout le repo et crée une
> distinction arbitraire (`use` interdit mais FQN docblock toléré) au profit d'une commodité marginale.

## Arborescence (`app/`)

| Dossier | Rôle |
|---|---|
| `Models/` | Entités Active Record (Eloquent). Identité UUIDv7 via le trait natif `HasUuids` sur la colonne `uuid`. |
| `Actions/` | Cas d'usage — **écriture comme lecture** (`final readonly`). Orchestration + déclenchement des règles métier. **Jamais de `Illuminate\Http`.** |
| `Data/` | DTO d'entrée (`spatie/laravel-data`). Portent la **validation de forme**. Construits uniquement via des constructeurs nommés. |
| `Rules/` | Règles métier réutilisables (`implements ValidationRule`). |
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
- `App\Http\Controllers` : ne touche pas la base directement (`DB` facade) **ni `App\Models`** —
  tout accès aux données passe par une Action (lecture comprise).
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
