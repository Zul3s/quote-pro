# Profil artisan

> Status: in progress  <!-- draft | accepted | in progress | done -->
> Created: 2026-05-28
> Author: Jul3s

## Context

L'application est **mono-artisan** : un seul artisan l'utilise. Pour pouvoir, à terme,
qualifier automatiquement les demandes entrantes (`ContactRequest`), l'artisan doit pouvoir
renseigner et éditer un **profil unique** composé de :

1. son **code postal** (servira plus tard à évaluer si un chantier est loin ou non) ;
2. sa/ses **profession(s) principale(s)** (ex. plâtrier, peintre) ;
3. un **descriptif texte de ses prestations & tarifs** (ex. « pose de faux plafond ~ 35 €/m² »).

Ce lot ne couvre **que la saisie et le stockage** du profil. L'exploitation du profil
(distance, matching) viendra dans des features séparées.

## Challenges raised & decisions

- **Multi-artisan ?** — Non, l'app est mono-artisan : le profil est un **singleton** (une seule ligne), pas une donnée par utilisateur *(pas de relation `user_id`, pas de notion de propriétaire)*.
- **Où stocker le profil ?** — Modèle dédié **`ArtisanProfile`** (table `artisan_profiles`, une seule ligne) *(sépare le métier de l'auth `User`, plus clair et évolutif)*.
- **Modélisation des professions ?** — **Texte libre / tags** stockés en `json` (`array<string>`) *(souplesse de saisie ; le matching auto étant hors scope, la fiabilité du matching n'est pas un critère ici)*.
- **Descriptif prestations ?** — **Un seul champ texte libre** (`services`) *(conforme à la formulation « un contenu texte » ; pas de structure prix/unité)*.
- **Création vs édition ?** — **Une seule Action upsert** `SaveArtisanProfile` : crée la ligne si absente, sinon met à jour. Une page `/profile` avec formulaire pré-rempli *(cohérent avec un singleton)*.
- **Champs obligatoires ?** — `postal_code` requis (5 chiffres FR), **au moins une** profession requise, `services` optionnel.
- **Contrôle d'accès ?** — **Pas de middleware `auth` pour l'instant** (aucun flux de login en place) ; à protéger quand l'auth sera branchée.

## Compromises accepted

- **Pas de calcul de distance dans ce lot** — *coût économisé : géocodage des codes postaux + logique de distance / coût accepté : le code postal n'est encore qu'une donnée stockée, inexploitée tant que la feature distance n'existe pas.*
- **Professions en texte libre plutôt qu'enum** — *coût économisé : maintenance d'une liste fermée de métiers / coût accepté : pas de garantie de normalisation, le futur matching auto devra composer avec des libellés libres.*
- **Prestations en texte libre plutôt que lignes structurées** — *coût économisé : répéteur d'items {label, prix, unité} côté UI + table/colonnes dédiées / coût accepté : pas de tri ni de calcul possible sur les prestations.*
- **Route non protégée** — *coût économisé : mise en place d'un flux de login / coût accepté : la page `/profile` est ouverte tant que l'auth n'est pas branchée.*

## Out of scope

- Calcul de distance « chantier loin ou non » à partir du code postal.
- Catégorisation / matching automatique des `ContactRequest` à partir du profil.
- Protection de la route par authentification (`auth` middleware) — à ajouter avec le flux de login.
- Structuration des prestations (prix/unité par ligne), tri, calculs.
- Gestion multi-artisan / multi-profil.

## Definition of Done

- [x] Une migration crée la table `artisan_profiles` avec les colonnes `uuid`, `postal_code`, `professions` (json), `services` (nullable text), timestamps.
- [ ] Visiter `GET /profile` (route nommée `profile.edit`) rend la page Inertia `settings/profile` ; si aucune ligne n'existe le formulaire est vide, sinon il est **pré-rempli** avec les valeurs enregistrées.
- [ ] `POST /profile` (route nommée `profile.store`) avec un code postal valide (5 chiffres) et ≥ 1 profession crée la ligne `artisan_profiles` si elle n'existe pas, et renvoie une redirection 302 vers `/profile` avec un flash `success`.
- [ ] Un second `POST /profile` **met à jour la même ligne** (toujours une seule ligne dans `artisan_profiles`) au lieu d'en créer une nouvelle.
- [ ] Un `POST /profile` sans code postal, avec un code postal mal formé, ou sans aucune profession, renvoie une 422 (API) / redirect-back avec erreurs de validation (web) et n'écrit **rien** en base.
- [x] `professions` est stocké et relu comme un tableau de chaînes (cast `array`), `services` peut être `null`.

## Implementation TODO

### Backend
- [x] Action `app/Actions/SaveArtisanProfile.php` (upsert : `first()` puis update, sinon create) + input DTO `app/Data/SaveArtisanProfileData.php` (`postalCode`, `professions`, `services`) — via `/create-action`
- [x] Validation de forme dans le DTO : `postalCode` `required|string|regex:/^\d{5}$/`, `professions` `required|array|min:1` + `professions.*` `string|max:100`, `services` `nullable|string|max:5000` — via `/create-action`
- [x] Modèle Eloquent `app/Models/ArtisanProfile.php` (`HasUuids`, `uniqueIds() = ['uuid']`, cast `professions => 'array'`) + migration `database/migrations/2026_05_29_100000_create_artisan_profiles_table.php` — via `/create-action`
- [ ] Controller `app/Http/Controllers/ArtisanProfile/SaveArtisanProfileController.php` + routes nommées `profile.edit` (GET, rend Inertia + profil courant) et `profile.store` (POST) dans `routes/web.php` — via `/create-controller`
- [ ] Action tests `tests/Feature/Action/SaveArtisanProfileTest.php` (création, upsert idempotent sur une seule ligne, validation de forme) — via `/create-tests-action`
- [ ] Controller tests `tests/Functional/Controller/ArtisanProfile/SaveArtisanProfileControllerTest.php` (GET rend la page pré-remplie / vide, POST 302 + flash, validation → 422/redirect-back) — via `/create-tests-functional`

### Frontend
- [ ] shadcn primitives à installer si absents (`Input`, `Textarea`, `Button`, `Label`) + un composant de saisie de tags pour `professions` (peut être géré avec `Input` + liste, ou un combobox) — via `/create-front`
- [ ] Page Inertia `resources/js/pages/settings/profile.tsx` : formulaire `useForm` câblé sur le helper Wayfinder `profile.store`, pré-rempli depuis les props, gestion des erreurs de validation et du flash `success` — via `/create-front`
- [ ] Type partagé `resources/js/types/` pour la forme du profil (`{ postalCode: string; professions: string[]; services: string | null }`) si le payload est réutilisé
- [ ] Vérifier que Wayfinder régénère `resources/js/routes/` et `resources/js/actions/` (routes `profile.edit` / `profile.store`)

### Final validation
- [ ] `./vendor/bin/pest` — suite complète au vert
- [ ] `./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php` — guardrails passent
- [ ] `npm run lint:check && npm run types:check && npm run format:check`
- [ ] Smoke manuel : 1 happy path (saisie + sauvegarde + re-visite pré-remplie) + 1 error path (code postal invalide ou aucune profession)

## Open questions

-
