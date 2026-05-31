# Résumé + estimation directement sur la ligne du dashboard

> Status: accepted  <!-- draft | accepted | in progress | done -->
> Created: 2026-05-31
> Author: Jul3s

## Context

Aujourd'hui, sur le dashboard (`/dashboard`), chaque demande est une ligne de tableau
(Nom, Priorité, Type, Délai, Reçue le). Le **résumé** généré par la qualification et
l'**estimation interne** (`estimated_amount_min`/`max`) ne sont visibles qu'après avoir
déplié le panneau de détail (clic sur le chevron). On veut pouvoir **scanner ces deux
infos directement sur la ligne**, sans déplier, pour prioriser plus vite.

La donnée existe déjà côté back (colonnes `summary`, `estimated_amount_min`,
`estimated_amount_max` sur `ContactRequest`) et est **déjà envoyée au front** dans le type
`ContactRequestRow`. **C'est donc un changement purement frontend** : aucune migration,
aucune Action, aucun changement de payload. Tout se passe dans
`resources/js/pages/dashboard/index.tsx`.

## Challenges raised & decisions

- **Où placer résumé + estimation sur la ligne ?** — Une **colonne dédiée « Résumé »**
  insérée entre « Nom » et « Priorité », avec l'**estimation affichée en petit dessous** du
  résumé *(regroupe les deux infos « contenu » au même endroit sans ajouter 2 colonnes à un
  tableau déjà à 6 colonnes)*.
- **Comment tronquer un résumé en texte libre potentiellement long ?** — **`line-clamp-2`**
  (2 lignes max puis ellipsis) *(compromis lisibilité / hauteur de ligne)*.
- **Que devient le panneau déplié ?** — On le **garde** (email, téléphone, code postal, type
  de projet, besoin complet) mais on **retire le résumé et l'estimation** du détail puisqu'ils
  passent sur la ligne *(évite la duplication)*.
- **Lignes non encore qualifiées (`qualified_at === null`, `summary === null`) ?** — La cellule
  « Résumé » affiche un placeholder discret (« En attente » / « — ») cohérent avec la colonne
  Priorité qui affiche déjà « En attente » *(pas de cellule vide ambiguë)*.

## Compromises accepted

- **Résumé tronqué à 2 lignes et retiré du détail déplié** — *coût économisé : pas de colonne
  large pour du texte libre, pas de duplication résumé/détail. / coût accepté : le résumé
  complet n'est plus consultable nulle part dans l'UI s'il dépasse 2 lignes.*
  → **Mitigation à coût nul** : poser un attribut HTML `title={row.summary}` sur la cellule, ce
  qui restitue le texte intégral en tooltip natif au survol (pas de primitive shadcn à installer).

## Out of scope

- Tri ou filtrage par montant d'estimation (la colonne reste lecture seule, pas de header triable).
- Édition du résumé ou de l'estimation depuis le dashboard.
- Tooltip riche shadcn (`Tooltip`) — on s'appuie sur l'attribut `title` natif.
- Modification du back, du payload Inertia, ou des types TypeScript (déjà en place).
- Affichage de l'estimation côté formulaire public / côté prospect (elle reste « interne »).

## Definition of Done

- [ ] Le tableau du dashboard comporte une colonne « Résumé » entre « Nom » et « Priorité »
      (`<TableHead>Résumé</TableHead>` présent dans le `TableHeader`).
- [ ] Pour une demande **qualifiée**, la cellule affiche le `summary` tronqué à 2 lignes
      (`line-clamp-2`) **sans avoir à déplier la ligne**, et l'estimation formatée
      (`formatEstimate(min, max)`) en petit dessous.
- [ ] Pour une demande **non qualifiée** (`qualified_at === null`), la cellule affiche un
      placeholder (« En attente » / « — ») au lieu d'une cellule vide.
- [ ] Le résumé complet est restitué au survol de la cellule via l'attribut `title`.
- [ ] Le panneau déplié **n'affiche plus** les blocs « Estimation (interne) » ni « Résumé »
      (ils ont migré sur la ligne) mais affiche toujours email, téléphone, code postal,
      type de projet et besoin complet.
- [ ] Le `colSpan` de la ligne de détail dépliée est ajusté pour rester aligné avec le nouveau
      nombre de colonnes (passe de 5 à 6).
- [ ] `npm run types:check` passe (le type `ContactRequestRow` est déjà complet, aucun champ
      nouveau requis).

## Implementation TODO

### Frontend
- [ ] Modifier la page Inertia `resources/js/pages/dashboard/index.tsx` — via `/create-front` :
  - ajouter `<TableHead>Résumé</TableHead>` entre « Nom » et « Priorité » dans le `TableHeader` ;
  - ajouter la `<TableCell>` correspondante dans chaque ligne : `summary` en `line-clamp-2` +
    `title={row.summary ?? ''}`, estimation via `formatEstimate(...)` en `text-xs text-muted-foreground`
    dessous, placeholder « En attente » quand `qualified_at === null` ;
  - retirer du panneau déplié les blocs « Estimation (interne) » et « Résumé » (≈ lignes 404-427) ;
  - ajuster le `colSpan` de la ligne de détail dépliée (5 → 6).
- [ ] Aucun nouveau type partagé (`ContactRequestRow` contient déjà `summary`,
      `estimated_amount_min`, `estimated_amount_max`).
- [ ] Pas de régénération Wayfinder nécessaire (aucune route ni Action modifiée).

### Final validation
- [ ] `./vendor/bin/pest --testsuite=Functional` — vert (les tests Inertia assertent les props
      passées, inchangées ; vérifier qu'aucune assertion ne dépendait du rendu DOM du détail).
- [ ] `npm run lint:check && npm run types:check && npm run format:check`
- [ ] Smoke manuel : 1) une demande qualifiée montre résumé + estimation sur la ligne sans
      déplier ; 2) une demande « En attente » montre le placeholder et pas de cellule vide.

## Open questions

-
