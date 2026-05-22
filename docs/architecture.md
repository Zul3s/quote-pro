# Architecture — Quote Plus

Ce projet applique une architecture **DDD / Clean / Hexagonale** inspirée du projet Symfony de référence `team_joy`, transposée dans le monde Laravel.

## Vue d'ensemble

```
┌─────────────────────────────────────┐
│  Infrastructure (Laravel-aware)     │
│  Controllers · Eloquent · Jobs      │
└──────────────┬──────────────────────┘
               │ dépend de ↓
┌──────────────┴──────────────────────┐
│  Application (orchestration)        │
│  Use Cases · Request/Response DTOs  │
└──────────────┬──────────────────────┘
               │ dépend de ↓
┌──────────────┴──────────────────────┐
│  Domain (cœur métier — PHP pur)     │
│  Interfaces · Specs · Events · VO   │
└─────────────────────────────────────┘
       ↑ implémente / fournit
       │
  (Infrastructure)
```

**Règle d'or** : les dépendances vont toujours vers le centre (Domain). Domain ne sait rien de l'extérieur ; Application ne connaît que Domain ; Infrastructure peut tout connaître.

## Conventions par couche

### `app/Domain/` — Cœur métier

Contient les interfaces, value objects (enums, classes immuables), événements de domaine, specifications (règles métier) et exceptions métier.

**Autorisé** : PHP pur, `Ramsey\Uuid`, `DateTimeImmutable`, `Carbon` (à la rigueur).

**Interdit** (vérifié par `tests/Arch/ArchTest.php`) : `Illuminate\*`, `Spatie\LaravelData\*`, `extends Model`, `app()`, `request()`, `FormRequest`, query builders Eloquent.

Sous-dossiers :
- `Entity/` — interfaces d'entité (`UserInterface extends EntityInterface`) et de Read Models partiels
- `Repository/` — interfaces de repository (`UserRepositoryInterface`)
- `Factory/` — interfaces de factory (`UserFactoryInterface`)
- `Specification/` — règles métier (`CanCreateUser`)
- `Event/` — événements de domaine (`UserCreated`, past tense, `final readonly`)
- `Model/` — value objects et enums (`ValidationReason`)
- `Exception/` — exceptions métier (`ValidationsException`)

### `app/Application/` — Use Cases

Un Use Case par dossier, contenant :
- `UseCase.php` — orchestration (jamais d'accès direct DB ou HTTP)
- `Request.php` — DTO d'entrée, `extends AbstractRequest`
- `Response.php` — DTO de sortie, **uniquement si nécessaire** (combinaison d'entités ou besoin de sérialisation Inertia/TS), `extends AbstractResponse`. Sinon, le Use Case retourne directement une entité Domain (ou `array<XxxInterface>`).

**Autorisé** :
- Domain (interfaces)
- `spatie/laravel-data` — voir décision plus bas
- `Illuminate\Contracts\Events\Dispatcher`, `Illuminate\Contracts\Bus\Dispatcher` (jobs)

**Interdit** : Eloquent direct (`extends Model`, `User::query()`), `FormRequest`, `Illuminate\Http\Request`, accès direct DB.

### `app/Infrastructure/` — Implémentations techniques

Toutes les implémentations concrètes. Laravel s'utilise librement ici.

Sous-dossiers :
- `Entity/` — modèles Eloquent qui **implémentent** les interfaces de `Domain/Entity/` (`User extends Authenticatable implements UserInterface`)
- `Repository/` — implémentations Eloquent des repositories (`EloquentUserRepository implements UserRepositoryInterface`)
- `Factory/` — implémentations des factories Domain
- `Event/` — adaptateurs d'événements (`LaravelEventDispatcher`)
- `Http/Controller/` — controllers fins qui délèguent à un Use Case
- `Http/Middleware/` — middlewares HTTP (ex : `HandleInertiaRequests`)
- `Job/` — jobs asynchrones (équivalent des `MessageHandler` Symfony)
- `Providers/` — Service Providers Laravel = wiring du container (équivalent `config/services.yaml` Symfony). Référencés dans `bootstrap/providers.php`.
- `Service/` — adaptateurs externes (mailer, API, LLM, etc.)

**Note** : le dossier `app/` ne contient **que** `Domain/`, `Application/`, `Infrastructure/`. Les conventions Laravel par défaut (`app/Http/`, `app/Providers/`, `app/Models/`) ont été supprimées : leurs contenus ont été redistribués dans `Infrastructure/`.

## Où ranger un Service ? La règle des trois familles

Le mot "service" est ambigu. Selon ce qu'il fait, un service vit dans une couche différente.

| Couche | Quoi | Rôle | Couplage | Exemple |
|---|---|---|---|---|
| `Domain/Service/` | **Contrats** (interfaces) | Définir ce dont le métier a besoin, sans dire comment | PHP pur, zéro framework | `MailerInterface` |
| `Application/Service/` | **Orchestrateurs applicatifs** | Logique transversale partagée par plusieurs Use Cases — pas un Use Case lui-même, mais un outil de Use Case | PHP pur, peut dépendre de Domain | _(vide à ce stade, ex futur : un `DateRangeCalculator`)_ |
| `Infrastructure/Service/` | **Implémentations techniques** | Adapters concrets vers le monde extérieur (Laravel, libs, APIs HTTP, etc.) | Couplé framework ou lib | `Mailer/LaravelMailer` |

**Pour choisir la bonne couche** :

- Une interface qui décrit un besoin métier (sans implémentation) va dans `Domain/Service/`.
- Une classe qui dépend de Laravel ou d'une lib externe va dans `Infrastructure/Service/`.
- Une classe en PHP pur (sans framework) réutilisée par plusieurs Use Cases, qui n'est ni un Use Case ni un contrat, va dans `Application/Service/`.

Si une classe n'est utilisée que par un seul Use Case, ce n'est pas un service applicatif : c'est du code privé du Use Case (méthode privée, ou collaborator dédié dans son dossier de Use Case).

Le dossier `Application/Service/` n'existe pas tant qu'on n'a pas de contenu à y mettre — un dossier vide est du bruit.

## Décision : `spatie/laravel-data` autorisé dans `Application/`

`AbstractRequest extends Data` et `AbstractResponse extends Data` introduisent une dépendance à Spatie dans la couche Application. **C'est un choix assumé.**

**Justification** :
- validation par attributs identique à ce qu'on faisait avec `#[Assert\…]` en Symfony,
- hydratation auto depuis la `Request` HTTP, JSON, array, model,
- génération automatique des types TypeScript côté front (Inertia + React) si l'on installe `spatie/laravel-typescript-transformer`.

**Garde-fous** :
- Domain reste 100 % framework-agnostic (aucun `extends Data` autorisé en Domain).
- Tout `Request` de Use Case doit `extends AbstractRequest` (règle ArchTest).
- Une éventuelle migration future vers un autre DTO se ferait en modifiant une seule classe (`AbstractRequest`).

## Naming standard

| Type | Convention | Exemple |
|---|---|---|
| Interface Domain | suffix `Interface` | `UserInterface` |
| Repository interface | suffix `RepositoryInterface` | `UserRepositoryInterface` |
| Repository impl. | préfix `Eloquent` | `EloquentUserRepository` |
| Factory interface | suffix `FactoryInterface` | `UserFactoryInterface` |
| Specification | préfix `Can…` ou nom métier | `CanCreateUser` |
| Use Case classe | toujours `UseCase` | `CreateUser\UseCase` |
| Request DTO | toujours `Request` | `CreateUser\Request` |
| Response DTO | toujours `Response` | `CreateUser\Response` |
| Domain Event | past tense, `final readonly` | `UserCreated` |
| Job | impératif | `SendWelcomeEmail` |
| Controller | suffix `Controller` | `CreateUserController` |

## Exemple de Use Case (fil rouge)

`app/Application/UseCase/CreateUser/` regroupe :

```php
// Request.php
final class Request extends AbstractRequest {
    public function __construct(
        #[Required, Email, Max(180)] public string $email,
        #[Nullable, Max(100)] public ?string $firstName = null,
        #[Nullable, Max(100)] public ?string $lastName = null,
    ) {}
}

// UseCase.php
final readonly class UseCase {
    public function __construct(
        private UserFactoryInterface $userFactory,
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $events,
        private CanCreateUser $canCreateUser,
    ) {}

    public function execute(Request $request): UserInterface {
        $this->canCreateUser->isSatisfiedBy($request->email); // throw ValidationsException si KO
        $user = $this->userFactory->create($request->email, $request->firstName, $request->lastName);
        $this->userRepository->save($user);
        $this->events->dispatch(new UserCreated($user->getUuid(), $user->getEmail()));
        return $user;
    }
}
```

Le Controller est minimal :

```php
final readonly class CreateUserController {
    public function __invoke(Request $request, UseCase $useCase): JsonResponse {
        $user = $useCase->execute($request);
        return new JsonResponse([...], 201);
    }
}
```

`spatie/laravel-data` résout le `Request` directement depuis la requête HTTP et applique la validation avant l'entrée dans le Use Case.

## Read Models partiels

Pour exposer une vue partielle d'une entité (ex : liste de devis sans charger toutes les relations), créer :
1. Une interface dans `app/Domain/Entity/` (ex : `QuoteSummaryInterface`)
2. Un DTO `final readonly` dans `app/Infrastructure/Entity/` qui implémente l'interface
3. Une méthode dans le Repository qui hydrate ce DTO via un `select` ciblé

Le Domain ne change pas de structure (mélangé avec les entités riches dans `Domain/Entity/`).

## Garde-fous CI

`tests/Unit/ArchTest.php` applique une **approche allow-list** : par défaut **rien n'est autorisé** ; on liste explicitement les dépendances acceptées. C'est plus sûr qu'une deny-list (impossible d'oublier d'interdire quelque chose puisque tout import non listé fait échouer la CI).

**Domain** ne peut utiliser **que** : `App\Domain`, `Ramsey\Uuid`, `DateTimeImmutable`, `RuntimeException`. Ajouter une dépendance suppose de l'ajouter explicitement à la règle (et donc à la doc).

**Application** ne peut utiliser **que** : `App\Application`, `App\Domain`, `Ramsey\Uuid`, `Spatie\LaravelData`. Si un Use Case a besoin d'un autre service Laravel (ex : `Illuminate\Contracts\Events\Dispatcher` pour dispatcher un event Laravel natif), il faut soit l'ajouter explicitement à la règle, soit passer par une interface Domain et son implémentation Infrastructure (préféré).

Autres règles structurelles :
- tout `*\Request` dans `App\Application\UseCase` doit `extends AbstractRequest`
- `App\Domain\Entity/`, `Repository/`, `Factory/` ne contiennent que des interfaces
- Domain Events (`App\Domain\Event\User`) doivent être `final readonly`
- les Specifications étendent `AbstractSpecification`
- les Use Case classes sont `final`
- `Illuminate\Database\Eloquent\Model` ne peut être utilisé dans `App\Domain` ou `App\Application`

## Bindings

Les bindings interface → implémentation sont centralisés dans `app/Providers/DomainServiceProvider.php` (enregistré dans `bootstrap/providers.php`). C'est aussi là que les Domain Events sont mappés vers leurs Jobs async.
