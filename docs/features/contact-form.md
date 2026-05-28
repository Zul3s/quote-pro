# Contact form — première brique de Quote Plus

> Status: in progress  <!-- draft | accepted | in progress | done -->
> Created: 2026-05-26
> Author: Jul3s

## Context

Quote Plus est un outil **mono-artisan** : il sert *un* artisan, pas une marketplace. Cette feature est la **première brique fonctionnelle** du projet : la page d'accueil publique `/` expose un formulaire de contact que tout prospect peut remplir pour exprimer un besoin (devis, info, urgence…).

L'objectif est double :
1. **Réceptionner et persister** les demandes des prospects.
2. **Catégoriser à la source** la demande (type, délai, zone) pour permettre, dans les features suivantes, des actions ciblées : notification artisan, dashboard, scoring IA.

Aucun envoi d'email, aucun dashboard artisan, aucune logique IA dans ce scope — seulement la collecte structurée.

## Challenges raised & decisions

- **Multi-tenant ou mono-artisan ?** — Mono-artisan, pas d'`artisan_id` sur les demandes. *(Quote Plus est l'outil personnel d'un artisan.)*
- **Catégorisation métier (plomberie/électricité) ou transverse (type/délai) ?** — Transverse. *(L'artisan connaît son métier ; ce qu'on doit qualifier c'est le besoin du prospect.)*
- **Auth requise ?** — Non, formulaire et soumission 100% publics. *(Pas de notion d'utilisateur dans cette feature.)*
- **Email de confirmation prospect ?** — Non, simple page de remerciement. *(Suffisant pour la démo, évite la config mail.)*
- **Anti-spam ?** — Rate-limit IP via `RateLimiter` Laravel natif. Pas de captcha. *(Projet démo, garde-fou trivial inclus.)*
- **Page d'accueil ou route dédiée `/contact` ?** — Le formulaire est sur `/` directement. *(Single-page artisan, pas de landing séparée.)*
- **IA de scoring sur la description ?** — Reportée à une feature séparée. *(Critères de jugement non encore définis ; sortir du scope ici permet de cadrer la collecte sans bloquer sur les décisions IA.)*
- **Domain Event dès maintenant ?** — Oui, `ContactRequestSubmitted` émis sans listener. *(Hook prêt pour brancher mail / IA / dashboard sans toucher au Use Case.)*

## Compromises accepted

- **Pas de dashboard artisan dans ce scope** — *coût économisé : auth + page listing + page détail / coût accepté : démo se lit via `php artisan tinker` ou un SQLite browser.*
- **Pas d'email artisan ni de confirmation prospect par mail** — *coût économisé : config mail + templates + tests d'envoi / coût accepté : la démo reste muette tant qu'on ne branche pas le listener du Domain Event.*
- **Catégorisation 100% déclarative (radio buttons), pas de classifieur sur le texte libre** — *coût économisé : LLM / prompts / coûts API / tests non-déterministes / coût accepté : si le prospect coche "information" alors qu'il décrit une urgence, on rate le signal — assumé.*
- **IA de scoring sortie du scope** — *coût économisé : décisions prématurées sur critères / modèle / async / coût accepté : pas de tri intelligent visible en démo — les demandes sont stockées brutes, triables ensuite par date.*

## Out of scope

- Dashboard / liste / détail des demandes côté artisan.
- Authentification artisan, gestion de compte.
- Envoi d'email (vers prospect ou vers artisan).
- Scoring / classification IA de la description libre.
- Multi-tenant, notion d'`artisan_id`.
- Captcha, détection de spam avancée.
- Upload de pièces jointes (photos du chantier, plans, devis existants).
- Édition / suppression d'une demande après envoi.
- Internationalisation : FR uniquement pour la démo.

## Definition of Done

- [x] La route `GET /` rend une page Inertia avec un formulaire affichant les champs : `name` (text), `email` (email), `phone` (text, optionnel), `requestType` (radio cards : `quote`, `information`, `urgent`, `other`), `deadline` (radio : `immediate`, `within_one_month`, `over_one_month`, `not_urgent`), `postalCode` (text, optionnel), `description` (textarea).
- [ ] La route `POST /contact-requests` accepte ces champs et, en cas de succès, redirige vers `GET /thank-you` avec un flash `success`.
- [x] La route `GET /thank-you` rend une page Inertia de confirmation avec un message du type *« Merci, votre demande a bien été reçue »* et un lien retour vers `/`.
- [ ] Après un POST valide, une ligne existe en base `contact_requests` avec : `uuid`, `name`, `email`, `phone` (nullable), `request_type` (enum), `deadline` (enum), `postal_code` (nullable), `description`, `created_at`.
- [ ] Validation : un POST avec un champ requis manquant ou un email invalide renvoie 422 (API) ou redirige avec les erreurs Inertia (web), et **aucune ligne** n'est créée en base.
- [x] Le Domain Event `ContactRequestSubmitted` est dispatché **une fois** par soumission valide (vérifiable via `Event::fake()` dans un test).
- [x] La route `POST /contact-requests` est protégée par un rate-limiter nommé `contact` (5 requêtes / minute / IP par défaut) ; au 6e essai dans la fenêtre, la réponse est 429.
- [x] La couche Domain ne contient **que** des interfaces pour `ContactRequest` (entity + repository + factory) et l'event `ContactRequestSubmitted` (`final readonly`).
- [x] `./vendor/bin/pest tests/Unit/ArchTest.php` passe (aucune violation de layering).
- [x] Le formulaire est utilisable au clavier seul (tab order cohérent, labels associés aux inputs, `aria-invalid` sur les champs en erreur).

## Implementation TODO

### Backend
- [x] Use Case `app/Application/UseCase/SubmitContactRequest/` (UseCase + Request, pas de Response — retourne l'entité Domain) — via `/create-action`
- [x] Domain interfaces : `ContactRequestInterface` (entity), `ContactRequestRepositoryInterface`, `ContactRequestFactoryInterface`, event `ContactRequestSubmitted`, value objects / enums `RequestType` et `Deadline` — via `/create-action`
- [x] Eloquent model `app/Infrastructure/Entity/ContactRequest.php` implémentant `ContactRequestInterface`
- [x] Repository `app/Infrastructure/Repository/EloquentContactRequestRepository.php`
- [x] Factory `app/Infrastructure/Factory/ContactRequestFactory.php`
- [x] Migration `database/migrations/2026_05_26_120142_create_contact_requests_table.php` (colonnes : `id` PK + `uuid` unique, `name`, `email`, `phone` nullable, `request_type`, `deadline`, `postal_code` nullable, `description` text, `timestamps`)
- [x] Controller `app/Infrastructure/Http/Controller/ContactRequest/SubmitContactRequestController.php` (POST, content-negotiated). `GET /` et `GET /thank-you` n'ayant besoin d'aucune donnée, ils sont câblés via `Route::inertia(...)` (pas de `HomeController`/`ThankYouController`)
- [x] Routes dans `routes/web.php` : `GET /` → `contact/index`, `POST /contact-requests` (middleware `throttle:contact`) → submit, `GET /thank-you` → `contact/thank-you`
- [x] Rate limiter `contact` défini dans `app/Infrastructure/Providers/AppServiceProvider.php` (ou dédié) : `RateLimiter::for('contact', fn (Request $r) => Limit::perMinute(5)->by($r->ip()))`
- [x] Bindings ajoutés dans `app/Infrastructure/Providers/DomainServiceProvider.php` : `ContactRequestRepositoryInterface` → `EloquentContactRequestRepository`, `ContactRequestFactoryInterface` → `ContactRequestFactory` (event `ContactRequestSubmitted` **sans listener** dans cette feature)
- [x] Use Case tests `tests/Feature/UseCase/SubmitContactRequestTest.php` (happy path + persistance + dispatch event + validation Request) — via `/create-tests-action`
- [ ] Controller tests `tests/Functional/Controller/ContactRequest/SubmitContactRequestControllerTest.php` (POST 302 + flash, GET `/` rend la bonne page Inertia, GET `/thank-you` rend la page, 422 sur payload invalide, 429 après rate-limit) — via `/create-tests-functional`

### Frontend
- [x] shadcn primitives à installer : `Button`, `Input`, `Label`, `Textarea`, `RadioGroup`, `Card` — via `/create-front`
- [x] Page Inertia `resources/js/pages/contact/index.tsx` (formulaire principal sur `/`) — via `/create-front`
- [x] Page Inertia `resources/js/pages/contact/thank-you.tsx` (page de remerciement) — via `/create-front`
- [x] Type partagé `resources/js/types/contact-request.ts` pour le payload `SubmitContactRequestPayload` (mêmes champs que la Request backend)
- [x] Vérifier que Wayfinder régénère bien les helpers `resources/js/routes/contact-requests/` et `resources/js/actions/`

### Final validation
- [ ] `./vendor/bin/pest` — full suite verte
- [ ] `./vendor/bin/pest tests/Unit/ArchTest.php` — layering OK
- [ ] `npm run lint:check && npm run types:check && npm run format:check`
- [ ] Smoke manuel : soumission valide → arrive sur `/thank-you` + ligne en DB + event dispatché (visible dans `pail`) ; soumission email invalide → erreurs affichées, pas de ligne ; 6 soumissions rapides → 429 sur la dernière.

## Open questions

-
