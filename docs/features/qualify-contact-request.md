# Qualification automatique des demandes & boucle « infos manquantes »

> Status: in progress  <!-- draft | accepted | in progress | done -->
> Created: 2026-05-29
> Author: Jul3s

## Context

Le dashboard (`contact-request-dashboard.md`) liste les `ContactRequest` **brutes**, triées
par date, et avait explicitement renvoyé la qualification à « une feature séparée à venir ».
C'est cette feature.

Objectif : **poser automatiquement des étiquettes** sur chaque demande entrante, sans jamais
en supprimer une (l'artisan reste seul juge — « zéro lead supprimé »). Les étiquettes ordonnent
la **file de relance** et donnent à l'artisan une lecture immédiate de chaque demande.

Deux mécaniques imbriquées :
1. **Étiquetage** : pertinence, urgence, type de projet + résumé, estimation € (interne),
   qualité du lead, priorité (synthèse).
2. **Boucle « infos manquantes »** : si la description est trop maigre pour chiffrer, le système
   renvoie au prospect, dans le formulaire, un message précis sur ce qu'il manque. La garde est
   **souple, jamais bloquante** : le prospect peut soit **compléter et resoumettre**, soit
   **déclarer qu'il ne peut pas fournir ces infos** (case à cocher / second bouton) et soumettre
   quand même. La demande passe alors, **marquée « infos incomplètes assumées »**. Le trou d'info
   devient une action commerciale, **résolue à la source** — pas d'email, pas de canal entrant.

Le moteur est **hybride** : un LLM local (**Ollama**, derrière un contrat) produit les étiquettes
sémantiques ; les étiquettes mécaniques sont calculées de façon **déterministe** en PHP.

## Challenges raised & decisions

- **Un doc ou deux (étiquettes #2 vs boucle #3) ?** — **Un seul doc**. *(La boucle #3 se réduit à la garde synchrone de soumission ; elle n'est plus une feature distincte mais une facette du même flux d'entrée.)*
- **Moteur LLM ou déterministe ?** — **Hybride**. LLM : pertinence, type+résumé, estimation €. Déterministe : qualité du lead, priorité. *(Les étiquettes sémantiques exigent de comprendre du texte libre ; les mécaniques se dérivent de champs déjà structurés.)*
- **L'urgence est-elle une étiquette à part ?** — **Non.** On **réutilise directement `deadline`** (déjà déclaré par le prospect, déjà affiché au dashboard) ; pas d'enum `Urgency` ni de colonne dérivée. *(Une colonne déterministe 1:1 ne ferait que dupliquer `deadline` avec une granularité appauvrie et un risque de divergence.)*
- **Quel LLM ?** — **Ollama** (local) derrière un contrat `Qualifier`, avec une impl `OllamaQualifier` et un `FakeQualifier` pour les tests, **bindés explicitement** dans un provider. *(Cohérent avec la préférence bindings explicites + tests sans réseau ; pas de dépendance à une API payante pour une démo.)*
- **Où ranger le client LLM ?** — Pas de module feature `App\Qualification` (ce serait une archi « à deux vitesses » vs le layout par rôle). On l'éclate **par rôle** : le contrat **et ses value objects de sortie** (`SufficiencyResult`, `QualificationResult` — ils font partie de la signature du contrat) en **`App\Contracts`**, l'adaptateur en **`App\Services`** (`final`). Ces deux nouvelles couches sont **documentées dans `architecture.md` et gardées par ArchTest**, comme les couches existantes. *(Reste uniforme et vérifiable, sans réintroduire le port/adaptateur hexagonal abandonné.)*
- **Quand se déclenche la qualification ?** — **Deux temps**. (a) Garde **synchrone** à la soumission : un appel LLM juge si la description suffit ; sinon rejet + message au prospect. (b) Si OK : la demande est créée, `ContactRequestSubmitted` (déjà dispatché) déclenche un Listener **asynchrone** (`ShouldQueue`) qui calcule et persiste les étiquettes.
- **Où vit la garde synchrone ?** — Dans l'Action **existante** `SubmitContactRequest`, via une **Rule métier** `DescriptionIsSufficient` adossée au `Qualifier` et lancée par le `Validator`. Le **message d'échec de la Rule = le texte « ce qu'il manque »** affiché au formulaire. *(Conforme au modèle de validation du repo : validation métier dans l'Action via des Rules.)*
- **La garde est-elle bloquante ?** — **Non, souple.** Le prospect peut **passer outre** en cochant « je ne peux pas fournir ces infos » (champ `acknowledgeMissingInfo`). Quand ce drapeau est posé, l'Action **n'exécute pas** la Rule de suffisance ; la demande est créée et **marquée `missing_info_acknowledged = true`**. *(Zéro lead perdu : un prospect qui ne sait pas chiffrer ne doit jamais être bloqué à la porte.)*
- **Et si l'appel LLM de la garde échoue (Ollama down, timeout, JSON invalide) ?** — La Rule **échoue en mode ouvert (« fail-open »)** : elle **laisse passer** la soumission au lieu de la rejeter. La demande est créée (gate non appliquée) ; la qualification async (avec ses retries) reprend le relais. L'échec est **loggé** pour observabilité. *(Même principe « zéro lead perdu » : une panne d'infra ne doit jamais bloquer un prospect.)*
- **Où stocke-t-on les étiquettes ?** — **Colonnes nullables sur `contact_requests`** (relation 1:1). `qualified_at IS NULL` = « pas encore qualifiée ». *(Pas d'historique de re-qualification nécessaire pour la démo.)*
- **Si le LLM échoue dans le job async ?** — La demande **reste non qualifiée** (colonnes null), le job est **rejoué par la queue** (tries/backoff). Échec définitif = reste null, jamais perdue.
- **L'estimation est-elle montrée au prospect ?** — **Jamais**. Elle n'apparaît que sur le dashboard artisan. *(Usage interne explicite.)*
- **L'artisan peut-il corriger une étiquette ?** — Hors scope. *(« L'artisan tranche » = il décide d'agir ou non, pas d'éditer les étiquettes ; l'override manuel est une feature ultérieure.)*
- **Que vérifie exactement la « suffisance », et avec quelle exhaustivité ?** — Le LLM **déduit du profil artisan (`professions` + `services`) l'ensemble des infos nécessaires pour qualifier et chiffrer cette demande**, donc **adaptées au métier** (ex. peinture → surface m², état des supports, nombre de couches ; autre prestation → d'autres champs). Il compare la description à ce besoin et la juge insuffisante s'il manque de quoi identifier **le « quoi »** (nature des travaux) ou **les quantités chiffrables** (surface, nombre d'éléments…). Le retour est un **message en prose** listant précisément ce qui manque **pour ce métier**. *(Pas de liste de requirements figée ni de mapping par type : l'exhaustivité vient du raisonnement du LLM sur le profil ; le prompt `assess` reçoit le profil et est instruit d'être exhaustif vis-à-vis de ce dont l'artisan a besoin pour un devis.)*
- **Quel modèle Ollama, combien d'appels ?** — Modèle de base **`llama3.2:3b`** (configurable dans `config/services.php`), et **deux appels distincts** : `assess` (synchrone, léger, à la soumission) puis `qualify` (asynchrone, complet, dans le job). *(Garde d'entrée rapide séparée de la qualification complète ; la fusion en un appel reste une optimisation ultérieure.)*
- **Formule de la priorité ?** — Déterministe, **estimation = facteur dominant**. (1) `relevance ∈ {spam, out_of_area, out_of_trade}` → `low` (plancher dur). (2) Sinon score = `poids(estimation)` **dominant** + `poids(deadline)` + bonus `lead_quality`, avec : estimation `high → 4 / medium → 2 / low|null → 0` ; deadline `immediate → 2 / within_one_month → 1 / sinon 0` ; `lead_quality=complete → +1`. Seuils : `≥ 5 → high`, `2–4 → medium`, `< 2 → low`. Les **tranches d'estimation** (€ → high/medium/low) sont **paramétrables dans `config`** (défaut : `high ≥ 3000 €`, `medium ≥ 800 €`, sinon `low`). *(L'artisan priorise d'abord les gros chantiers chiffrables ; l'urgence et la complétude affinent.)*

## Compromises accepted

- **Appel LLM synchrone sur un endpoint public** — *coût économisé : pas de complexité d'enrichissement asynchrone côté prospect / coût accepté : latence ajoutée à la soumission + surface d'abus (mitigée par le rate-limit IP déjà en place sur le formulaire) + dépendance à la dispo d'Ollama pour soumettre.*
- **Deux appels LLM par demande (garde sync + qualification async)** — *coût économisé : séparation nette « porte d'entrée légère » / « qualification complète » / coût accepté : le LLM est sollicité deux fois ; la fusion en un seul appel est une optimisation ultérieure.*
- **Estimation à partir d'un barème en texte libre** (`ArtisanProfile.services`) — *coût économisé : table de tarifs structurée {prestation, prix, unité} / coût accepté : l'estimation est best-effort, sa fiabilité dépend de la qualité du texte tarifaire saisi.*
- **Étiquettes déterministes simplistes** (urgence = mapping direct, priorité = formule fixe) — *coût économisé : moteur de scoring configurable / coût accepté : formules figées dans le code, non paramétrables par l'artisan.*
- **Colonnes sur `contact_requests` plutôt que table dédiée** — *coût économisé : relation + jointures + plomberie / coût accepté : pas d'historique des qualifications successives, une seule passe stockée.*
- **Garde souple (override possible) plutôt que bloquante** — *coût économisé : aucun lead perdu, le prospect qui ne sait pas chiffrer passe quand même / coût accepté : des demandes « infos incomplètes assumées » entrent en base ; l'estimation € peut alors être null même après qualification, et la file contient des leads non chiffrables que l'artisan devra traiter manuellement.*
- **Garde « fail-open » sur panne LLM** — *coût économisé : aucun lead perdu quand Ollama est indisponible / coût accepté : pendant une panne, des descriptions maigres entrent sans avoir été filtrées par la garde (la qualification async les rattrapera quand le service revient).*

## Out of scope

- **Email de relance sortant** vers le prospect et **traitement d'une réponse entrante** (parsing inbound, re-estimation) — la boucle infos manquantes est résolue **dans le formulaire**, pas par mail.
- **Édition / override manuel** des étiquettes par l'artisan depuis le dashboard.
- **Re-qualification** d'une demande existante (re-déclenchement manuel, historique des passes).
- **Suppression** d'une demande (« zéro lead supprimé » est un principe, pas une action à implémenter).
- **Affichage de l'estimation au prospect** (page de remerciement, email).
- **Nouveaux filtres dashboard** par pertinence / priorité / qualité (les filtres type + délai existants restent ; seul le **tri par priorité** est ajouté).
- **Score de confiance / explicabilité** des sorties LLM.
- **Authentification** des routes (`/`, `/dashboard`) — cohérent avec les lots précédents, à brancher avec l'auth.
- **Multi-artisan / multi-profil** : le barème vient du singleton `ArtisanProfile`.

## Definition of Done

### Garde synchrone souple & boucle infos manquantes
- [x] Un `POST /` **sans** `acknowledge_missing_info` dont la description est jugée **insuffisante** par le `Qualifier` renvoie une **422 (API) / redirect-back avec erreurs (web)**, le message d'erreur portant le **détail de ce qu'il manque** (texte renvoyé par le LLM), et **aucune** ligne n'est créée dans `contact_requests`.
- [x] Un `POST /` **avec** `acknowledge_missing_info = true` **ne lance pas** la garde de suffisance : la ligne est créée même si la description est maigre, avec `missing_info_acknowledged = true`, et `ContactRequestSubmitted` est dispatché.
- [x] Un `POST /` dont la description est jugée **suffisante** crée la ligne `contact_requests` (avec `qualified_at` **null**, `missing_info_acknowledged = false`) et dispatche `ContactRequestSubmitted`.
- [x] Un `POST /` pendant lequel `Qualifier::assess` **lève une exception** (LLM indisponible) **ne renvoie pas d'erreur au prospect** : la demande est **créée quand même** (fail-open), l'échec est loggé, et `ContactRequestSubmitted` est dispatché.
- [x] Le formulaire public affiche le message « infos manquantes » renvoyé par le serveur (sur le champ `description` ou en bannière) **et révèle l'option « je ne suis pas en mesure de fournir ces informations »** (case à cocher / second bouton) ; cocher puis resoumettre fait passer la demande.

### Étiquetage asynchrone
- [x] Après traitement du job de qualification, la ligne `contact_requests` a ses colonnes peuplées : `relevance`, `project_type`, `summary`, `estimated_amount_min`, `estimated_amount_max`, `lead_quality`, `priority`, et `qualified_at` **non-null** (l'urgence n'est pas une colonne : elle reste portée par `deadline`).
- [ ] `relevance` ∈ { `relevant`, `out_of_area`, `out_of_trade`, `spam` } (LLM, croisant la description avec les `professions` + zone de l'`ArtisanProfile`).
- [ ] `project_type` (string libre) et `summary` (1 phrase) sont issus du LLM ; `estimated_amount_min`/`max` (entiers €, nullables) résultent du croisement description × `ArtisanProfile.services`. Pour une demande `missing_info_acknowledged = true`, l'estimation **peut légitimement rester null** (pas de quantités à chiffrer) sans que la qualification soit considérée en échec.
- [x] `lead_quality` est `complete` si `phone` **et** `postal_code` sont renseignés, sinon `incomplete` (déterministe).
- [x] `priority` est calculée **déterministe** par la formule documentée (cf. Challenges) à partir de `relevance`, `estimation` (dominant), `deadline` et `lead_quality` : `relevance=spam`/`out_of_*` → `low` ; score `≥ 5 → high`, `2–4 → medium`, `< 2 → low` ; ex. `relevant` + estimation `high` + `deadline=immediate` + `lead_quality=complete` (4+2+1=7) → `high`.
- [x] Si le `Qualifier` lève une exception dans le job, la ligne **reste non qualifiée** (colonnes null, `qualified_at` null) et le job est **rejoué** par la queue ; la demande n'est jamais perdue ni supprimée.

### Lecture / dashboard
- [x] `GET /dashboard` ordonne la liste par **priorité décroissante** puis `created_at` décroissant (file de relance), et chaque ligne expose les étiquettes : badges `relevance` / `deadline` (urgence, déjà affiché) / `lead_quality` / `priority`, le `project_type` + `summary`, et l'estimation € (`min–max`) **côté artisan uniquement**.
- [x] Une demande **non encore qualifiée** (`qualified_at` null) est affichée avec un état explicite (ex. badge « en attente de qualification ») au lieu d'étiquettes vides.
- [x] Une demande `missing_info_acknowledged = true` est signalée par un badge dédié (ex. « infos incomplètes assumées »), et son estimation absente s'affiche comme « non chiffrable » plutôt qu'en erreur.

### Architecture & garde-fous
- [x] `App\Contracts\Qualifier` est un **contrat** ; `App\Services\OllamaQualifier` est bindé en prod, `FakeQualifier` en test — **aucun test ne fait d'appel réseau** (suite verte sans Ollama).
- [x] `docs/architecture.md` documente les **deux nouvelles couches par rôle** : `App\Contracts` (le contrat **et ses value objects de sortie**, distincts des `App\Data` d'entrée validés) et `App\Services` (adaptateurs d'intégrations externes, `final`). Tableau des couches + section ArchTest mis à jour.
- [x] `tests/Unit/ArchTest.php` étendu d'**une** règle au niveau namespace, uniforme avec `Actions` : `App\Services` sont `final` et n'utilisent ni `Illuminate\Http` ni `Inertia`. *(Pas de règle hardcodant des noms de classes pour `App\Contracts` — interface + VOs readonly triviaux à garder.)*
- [x] `./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php` passe (10 tests verts, règle `Services` comprise).

## Implementation TODO

### Backend — fondations qualification
- [x] Enums `app/Enums/Relevance.php` (`relevant`/`out_of_area`/`out_of_trade`/`spam`), `app/Enums/LeadQuality.php` (`complete`/`incomplete`), `app/Enums/Priority.php` (`high`/`medium`/`low`) — via `/create-action` *(pas d'enum `Urgency` : on réutilise `Deadline`)*
- [x] Migration `database/migrations/2026_05_29_161524_add_qualification_to_contact_requests.php` : colonnes nullables `relevance`, `project_type`, `summary`, `estimated_amount_min` (int), `estimated_amount_max` (int), `lead_quality`, `priority`, `qualified_at` (timestamp), + `missing_info_acknowledged` (boolean, default false) + casts enum/datetime/bool sur le modèle `ContactRequest` *(pas de colonne `urgency` : `deadline` existe déjà)* — via `/create-action`
- [x] Contrat LLM `app/Contracts/Qualifier.php` (interface : `assess(string $description, ArtisanProfile $profile): SufficiencyResult` — **déduit du profil (`professions` + `services`) les infos nécessaires au devis pour ce métier** et juge la description suffisante ou non ; `SufficiencyResult` portant `sufficient: bool` + `message` (prose) listant ce qui manque pour ce métier ; et `qualify(ContactRequest $request, ArtisanProfile $profile): QualificationResult`) — *(nouvelle couche par rôle ; scaffold manuel)*
- [x] Value objects de sortie `app/Contracts/SufficiencyResult.php` + `app/Contracts/QualificationResult.php` (`final readonly`, rangés **avec** le contrat dont ils typent les retours ; **pas** des `App\Data` — ce ne sont pas des DTO d'entrée validés) — *(scaffold manuel)*
- [x] Impl `app/Services/OllamaQualifier.php` (`implements Qualifier`, `final`, HTTP Ollama via le client `Http`, parsing JSON) + double `tests/Fakes/FakeQualifier.php` (`implements Qualifier`, modes configurables : sufficient/insufficient, résultat de qualif fixé, **et mode « throwing »** pour simuler une panne sur `assess`/`qualify`) — *(scaffold manuel)*
- [x] Binding explicite `Qualifier::class => OllamaQualifier::class` dans `AppServiceProvider` + section `ollama` dans `config/services.php` (`base_url`, `model` défaut `llama3.2:3b`) + tranches d'estimation pour la priorité (`estimation_high` défaut `3000`, `estimation_medium` défaut `800`)
- [x] Fixtures : états `ContactRequestFactory` (`qualified`, `irrelevant`, `missingInfoAcknowledged`, `unqualified` — priorité dérivée comme l'Action via `config`) + `DatabaseSeeder` (dev/staging only) répartissant 40 demandes sur ces états pour un dashboard réaliste

### Backend — garde synchrone (boucle infos manquantes)
- [x] Champ `acknowledgeMissingInfo` (bool, default false) ajouté au DTO `app/Data/SubmitContactRequestData.php` (`nullable|boolean`) + propagation de `missing_info_acknowledged` à la création — via `/create-action`
- [x] Business rule `app/Rules/DescriptionIsSufficient.php` (`implements ValidationRule`, dépend du `Qualifier` + charge l'`ArtisanProfile`, `$fail($result->message)` quand insuffisant ; **try/catch fail-open** : toute exception du `Qualifier` est loggée et la Rule **passe** — ne bloque jamais) — via `/create-action`
- [x] Brancher la Rule dans l'Action **existante** `app/Actions/SubmitContactRequest.php` : valider via `Validator` **avant** `ContactRequest::create`, **uniquement si** `! $data->acknowledgeMissingInfo` ; sinon persister directement avec `missing_info_acknowledged = true` — via `/create-action`
- [x] Action tests `tests/Feature/Action/SubmitContactRequestTest.php` étendus : (a) description insuffisante sans override (FakeQualifier insufficient) → exception + rien en base ; (b) description insuffisante **avec** `acknowledgeMissingInfo` → création + `missing_info_acknowledged = true`, **sans** appel à la garde ; (c) description suffisante → création normale + `ContactRequestSubmitted` dispatché ; (d) **`Qualifier::assess` qui jette (FakeQualifier en mode throwing) → demande créée quand même** (fail-open), pas d'exception propagée — via `/create-tests-action`

### Backend — étiquetage asynchrone
- [x] Action `app/Actions/QualifyContactRequest.php` (`handle(ContactRequest): ContactRequest` : charge le profil, appelle `Qualifier::qualify`, calcule les étiquettes déterministes qualité/priorité — la priorité lisant `deadline` directement —, persiste les colonnes + `qualified_at`) — via `/create-action`
- [x] Listener `app/Listeners/QualifySubmittedContactRequest.php` (`implements ShouldQueue`, délègue à l'Action `QualifyContactRequest`) + wiring `ContactRequestSubmitted => [QualifySubmittedContactRequest::class]` dans `EventServiceProvider` — via `/create-action`
- [x] Action tests `tests/Feature/Action/QualifyContactRequestTest.php` : qualité complete/incomplete selon phone+postal, formule priorité (spam/out → low ; estimation `high` dominante → high ; estimation `low` + `deadline=not_urgent` → low ; cas medium aux seuils 2–4), persistance des colonnes LLM via FakeQualifier, `qualified_at` posé — via `/create-tests-action`
- [x] Listener tests : le Listener est `ShouldQueue` et appelle l'Action (qualifie via l'événement, queue sync) ; le cas « Qualifier qui jette → ligne non qualifiée » est couvert dans `QualifyContactRequestTest` — via `/create-tests-action`

### Backend — lecture
- [x] Adapter `app/Actions/ListContactRequests.php` : tri `priority` desc (via `CASE`) puis `created_at` desc ; le payload inclut les nouvelles colonnes — via `/create-action`
- [x] Controller tests `tests/Functional/Controller/ContactRequest/SubmitContactRequestControllerTest.php` étendus (description insuffisante sans override → redirect-back + `assertSessionHasErrors` avec le message « infos manquantes », rien en base ; même POST **avec** `acknowledge_missing_info=true` → 302 succès + ligne créée `missing_info_acknowledged=true`) + `ListContactRequestsControllerTest.php` (ordre par priorité, étiquettes dans la prop, état non-qualifiée, badge infos incomplètes) — via `/create-tests-functional`

### Frontend
- [x] shadcn : réutiliser `Badge` ; installer `Checkbox` si absent ; vérifier l'affichage d'erreur serveur sur le formulaire de contact — via `/create-front`
- [x] Page de contact (`/`) : afficher le message « infos manquantes » renvoyé par le serveur (erreur sur `description` ou bannière) **et révéler, au refus, la case `acknowledgeMissingInfo` « je ne suis pas en mesure de fournir ces informations » (ou un second bouton submit)** qui, cochée + resoumise, fait passer la demande — via `/create-front`
- [x] Page `resources/js/pages/dashboard/index.tsx` : badges pertinence/urgence/qualité/priorité, `project_type` + `summary`, estimation `min–max €` (artisan), état « en attente de qualification », tri par priorité reflété — via `/create-front`
- [x] Types partagés `resources/js/types/contact-request.ts` : étendre `ContactRequestRow` (champs de qualification nullables + `missing_info_acknowledged: boolean`) + maps de labels `RELEVANCE_LABELS` / `LEAD_QUALITY_LABELS` / `PRIORITY_LABELS` *(les labels d'urgence = `DEADLINE_LABELS` existants)* — via `/create-front`
- [x] Vérifier que Wayfinder régénère `resources/js/routes/` et `resources/js/actions/` (aucune route nouvelle attendue, mais payloads modifiés)

### Final validation
- [x] `./vendor/bin/pest` — suite complète au vert (79 tests)
- [x] `./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php` — guardrails passent
- [x] `npm run lint:check && npm run types:check && npm run format:check` + `composer lint:check`
- [ ] Smoke manuel : happy path (description riche → demande créée → job qualifie → étiquettes au dashboard, file triée par priorité) ; soft-gate (description maigre → message « précisez la surface… » au formulaire + case « je ne peux pas fournir » → resoumission cochée → demande créée, badge « infos incomplètes assumées ») ; résilience (Ollama coupé → la soumission **passe quand même** (fail-open), la demande est créée et reste « en attente de qualification » jusqu'au retour du service)

## Open questions

- _Aucune — toutes résolues lors du cadrage (cf. Challenges)._
