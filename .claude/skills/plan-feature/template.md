# <Feature title>

> Status: draft  <!-- draft | accepted | in progress | done -->
> Created: YYYY-MM-DD
> Author: <user>

## Context

<Pourquoi cette feature existe. Le besoin formulé par le métier, restitué après challenge. 2–6 lignes.>

## Challenges raised & decisions

<Questions soulevées pendant le cadrage et leur résolution. Une ligne par décision.>

- **<question>** — <decision retenue> *(raison courte)*

## Compromises accepted

<Simplifications retenues vs scope idéal. Une ligne par compromis, avec ce qu'on économise et ce qu'on accepte de perdre.>

- <simplification> — *coût économisé : … / coût accepté : …*

## Out of scope

<Explicitement hors scope. Une ligne par item, pour empêcher le scope creep ultérieur.>

- <item>

## Definition of Done

<Critères observables, vérifiables par un humain (UI / DB / réseau / side channel). Pas de "propre", "robuste".>

- [ ] <criterion 1>
- [ ] <criterion 2>
- [ ] <criterion 3>

## Implementation TODO

### Backend
- [ ] Action `app/Actions/<Name>.php` + input DTO `app/Data/<Name>Data.php` — via `/create-action`
- [ ] Business rules `app/Rules/<Rule>.php` (if any) — via `/create-action`
- [ ] Native Event + Listener (+ Mailable) for side effects — via `/create-action`
- [ ] Eloquent model `app/Models/<Name>.php` + migration `database/migrations/...` — via `/create-action`
- [ ] Controller + named route in `routes/web.php` — via `/create-controller`
- [ ] Action tests `tests/Feature/Action/<Name>Test.php` — via `/create-tests-action`
- [ ] Controller tests `tests/Functional/Controller/<Subject>/<Name>ControllerTest.php` — via `/create-tests-functional`

### Frontend
<!-- Drop this whole section if the feature has no UI surface. -->
- [ ] shadcn primitives to install (`Button`, `Input`, …) — via `/create-front`
- [ ] Inertia page `resources/js/pages/<area>/<page>.tsx` — via `/create-front`
- [ ] Shared type in `resources/js/types/` if a new payload shape
- [ ] Verify Wayfinder regenerates `resources/js/routes/` and `resources/js/actions/`

### Final validation
- [ ] `./vendor/bin/pest` — full suite green
- [ ] `./vendor/bin/pest tests/Unit/ArchTest.php tests/Unit/ArchDataConstructionTest.php` — guardrails pass
- [ ] `npm run lint:check && npm run types:check && npm run format:check`
- [ ] Manual smoke: 1 happy path + 1 error path

## Open questions

<Tout ce qui reste non résolu au moment d'écrire. Doit être vide pour passer à `Status: accepted`.>

-
