# Dashboard des demandes de contact

> Status: in progress  <!-- draft | accepted | in progress | done -->
> Created: 2026-05-29
> Author: Jul3s

## Context

La collecte des `ContactRequest` existe (feature `contact-form`), mais les demandes
ne sont consultables que via `tinker` ou un browser SQLite — le dashboard artisan
avait été explicitement sorti du scope de `contact-form.md`.

Cette feature livre la **première vue de lecture côté artisan** : une page qui liste
les demandes reçues, filtrables et paginées, avec le détail dépliable par ligne.
Pas d'étiquetage / qualification IA dans ce lot (feature séparée à venir) — on liste
les demandes **brutes**, triées par date.

## Challenges raised & decisions

- **Étiquetage / qualification dans ce lot ?** — Non, hors scope. *(Ce dashboard ne dépend ni de l'IA ni du mail ; il débloque la visibilité tout de suite. La qualification fera l'objet d'un doc dédié.)*
- **Accès aux données depuis le controller ?** — Via une **Action de lecture** `ListContactRequests` (sur le modèle de `ShowArtisanProfile`) ; le controller ne touche jamais `App\Models`. *(Règle d'archi `ArchTest`.)*
- **Liste seule, page détail, ou détail inline ?** — **Détail inline** : un clic déplie la ligne (drawer/accordion) côté front, une seule route, un seul payload. *(Pas de seconde route ni d'Action `Show`.)*
- **Tri / filtres ?** — **Filtres serveur** par `request_type` et `deadline` (query params), tri `created_at` décroissant. L'Action prend un `Data` de filtre validé. *(2 filtres optionnels justifient un DTO ; validation de forme = enum.)*
- **Pagination ?** — **Pagination serveur** `paginate(25)` + UI Inertia, avec `withQueryString()` pour conserver les filtres entre les pages.
- **Route & nom ?** — `GET /dashboard` nommée `dashboard`, page Inertia `dashboard/index`.
- **Contrôle d'accès ?** — Route **ouverte** (pas de middleware `auth`), cohérent avec `/profile` déjà non protégée tant que le flux de login n'existe pas.
- **Type de retour de l'Action de lecture ?** — Un `LengthAwarePaginator` (extension assumée du contrat « read → `?Model`/`Collection` » pour porter la pagination serveur). Le type reste inféré dans le controller, aucun import `App\Models`.

## Compromises accepted

- **Pas de qualification / étiquettes** — *coût économisé : moteur IA + mail + re-estimation / coût accepté : la file n'est triable que par date, pas par priorité métier.*
- **Détail inline plutôt que page dédiée** — *coût économisé : Action `Show` + route + page détail / coût accepté : tous les champs de toutes les lignes de la page courante transitent dans le payload, même non dépliés.*
- **Route non protégée** — *coût économisé : mise en place d'un flux de login / coût accepté : `/dashboard` expose les demandes (données prospects) tant que l'auth n'est pas branchée.*
- **Filtres limités à type + délai** — *coût économisé : recherche plein-texte, filtre par CP, par date / coût accepté : pas de recherche libre sur nom/description dans ce lot.*

## Out of scope

- Étiquetage / qualification automatique des demandes (pertinence, urgence, estimation €, résumé, priorité) — feature séparée.
- Page détail dédiée (`GET /dashboard/{uuid}`) et Action `ShowContactRequest`.
- Recherche plein-texte (nom, email, description), filtre par code postal ou plage de dates.
- Édition / suppression / archivage d'une demande depuis le dashboard.
- Authentification artisan / protection de la route.
- Export (CSV, PDF), actions de masse.
- Tri configurable par l'utilisateur (autre que `created_at` desc).

## Definition of Done

- [ ] `GET /dashboard` (route nommée `dashboard`) rend la page Inertia `dashboard/index` avec une prop paginée contenant les `contact_requests` triées par `created_at` décroissant.
- [ ] Chaque ligne affiche au moins : `name`, `request_type` (badge), `deadline` (badge), `postal_code`, `created_at` ; la `description` longue est tronquée dans la ligne et visible en entier au dépliage inline (drawer/accordion), sans navigation ni requête supplémentaire.
- [ ] `GET /dashboard?type=quote` ne renvoie que les demandes dont `request_type = quote` ; `GET /dashboard?deadline=immediate` ne renvoie que celles dont `deadline = immediate` ; les deux combinés appliquent les deux filtres (ET logique).
- [ ] Un `type` ou `deadline` hors enum renvoie une 422 (API) / redirect-back avec erreurs (web) ; **aucune** requête de liste n'est exécutée avec une valeur invalide.
- [ ] La liste est paginée à 25 par page ; les liens de pagination conservent les filtres actifs (`?type=...&deadline=...&page=2` fonctionne).
- [ ] Quand aucune demande ne correspond, la page affiche un état vide explicite (ex. « Aucune demande pour ces critères ») au lieu d'un tableau vide.
- [ ] Les contrôles de filtre (selects type / délai) reflètent l'état courant de la requête (valeur sélectionnée = filtre appliqué) et déclenchent une navigation Inertia conservant l'état.
- [ ] `./vendor/bin/pest tests/Unit/ArchTest.php` passe : le controller ne référence ni `DB` ni `App\Models`, l'Action n'utilise pas `Illuminate\Http`.

## Implementation TODO

### Backend
- [x] Action de lecture `app/Actions/ListContactRequests.php` (`handle(ListContactRequestsData): LengthAwarePaginator`, `where` conditionnels sur `request_type`/`deadline`, `orderByDesc('created_at')`, `paginate(25)->withQueryString()`) — via `/create-action`
- [x] Input DTO `app/Data/ListContactRequestsData.php` (`type` nullable, `deadline` nullable) avec validation de forme : `type` `nullable` + `Rule::enum(RequestType::class)`, `deadline` `nullable` + `Rule::enum(Deadline::class)` ; construit via `fromRequest` — via `/create-action`
- [x] Controller invokable `app/Http/Controllers/ContactRequest/ListContactRequestsController.php` rendant `dashboard/index` avec la prop paginée + les filtres courants ; route `GET /dashboard` nommée `dashboard` dans `routes/web.php` — via `/create-controller`
- [x] Action tests `tests/Feature/Action/ListContactRequestsTest.php` (tri date desc, filtre type, filtre deadline, filtres combinés, pagination à 25, validation DTO rejette enum invalide) — via `/create-tests-action`
- [x] Controller tests `tests/Functional/Controller/ContactRequest/ListContactRequestsControllerTest.php` (GET rend `dashboard/index` avec la prop paginée + filtres, écho des filtres validés via query params, pagination 25/page conservant les filtres en page 2, état vide, filtre hors enum → redirect-back + `assertSessionHasErrors`) — via `/create-tests-functional`

### Frontend
- [x] shadcn primitives installées : `Table`, `Badge`, `Select` (détail inline rendu via une ligne dépliable maison + `Fragment`, sans `Collapsible`/`Sheet` ; pagination rendue depuis les `links` du paginator avec `<Link>` Inertia, sans le primitive `Pagination`) — via `/create-front`
- [x] Page Inertia `resources/js/pages/dashboard/index.tsx` : tableau des demandes, badges type/délai, ligne dépliable (détail inline : email, téléphone, description complète), selects de filtre câblés sur `router.get` (`preserveState`/`preserveScroll`/`replace`, query params nettoyés), liens de pagination depuis le paginator, état vide — via `/create-front`
- [x] Type partagé `resources/js/types/contact-request.ts` : `ContactRequestRow` (forme lue, snake_case), `ContactRequestFilters`, `Paginated<T>` (`data`, `links`, meta) + maps de labels `REQUEST_TYPE_LABELS`/`DEADLINE_LABELS` — via `/create-front`
- [x] Vérifier que Wayfinder régénère `resources/js/routes/` et `resources/js/actions/` pour la route `dashboard`

### Final validation
- [x] `./vendor/bin/pest` — suite complète au vert (51 tests)
- [x] `./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php` — guardrails passent (9 tests)
- [x] `npm run lint:check && npm run types:check && npm run format:check` + `composer lint:check`
- [ ] Smoke manuel : 1 happy path (liste affichée, filtre appliqué, page 2 conserve le filtre, détail déplié) + 1 error path (filtre type invalide → erreur, pas de plantage)

## Open questions

-
