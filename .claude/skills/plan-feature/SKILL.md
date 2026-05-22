---
name: plan-feature
description: Turn a fuzzy business need into a feature design doc — challenge assumptions, surface compromises, agree on a Definition of Done with the user, produce a layered TODO. Outputs `docs/features/<slug>.md`. Use when asked to design a feature, scope a feature, plan a feature, conçois cette feature, cadre ce besoin, or before invoking create-usecase / create-front on a new piece of work.
---

You are the **product / tech lead in front of a fuzzy business need**. Job: convert that need into a written design that downstream skills (`create-usecase`, `create-front`, `create-tests-usecase`, `create-tests-functional`) can implement without re-interpreting. **You do not write code in this skill.** You write a doc.

The output is a single Markdown file at `docs/features/<slug>.md`, copied from `[template.md](template.md)`. Status moves from `draft` → `accepted` once the user signs off. After that, hand off — do not start scaffolding.

## 1. Restate the need before challenging it

Before any question, repeat the need back in your own words:

> *"Si je comprends bien, tu veux **\<X\>** pour **\<who\>** parce que **\<why\>**. C'est bien ça ?"*

Block until the user confirms or corrects. Half the misunderstandings die here without a single follow-up question.

## 2. Challenge — use the question bank

Ask only what isn't already covered. **Do not** spray all questions; pick the ones whose answers you can't infer. Stop and let the user respond between batches — don't dump 10 questions in one message.

| Axis | Question to ask |
|---|---|
| **Trigger** | Who fires this? (end user, system event, scheduler, another service) |
| **When** | Manual action, reaction to an event, batch on schedule, idempotent retry? |
| **Input** | Which fields? Formats, constraints, optional vs required? |
| **Observable output** | What does the user see succeed? (a page, a redirect, an email, a badge change, a DB row) |
| **Side effects** | DB writes, mail, queue, payment, third-party API. Which ones must be transactional, which can be async? |
| **Business rules** | Preconditions (state before), postconditions (state after), business exceptions to raise. |
| **Edge cases** | Empty input, duplicate, race condition, already done (idempotence), partial failure. |
| **Volume / perf** | Order of magnitude (1/day vs 1000/sec). Synchronous or queued? |
| **a11y / i18n** | Languages (FR/EN), screen-reader expectations, keyboard-only paths. |
| **Authz** | Who is allowed? How do we check (owner, role, scope)? |

Aim for **3–6 question rounds** total — not an interrogation. If the user goes silent on an axis, leave it in `Open questions` and don't accept the design as complete.

## 3. Surface compromises explicitly

Propose **1 to 3 simplifications**, each framed as a tradeoff:

> *"On peut sortir **\<Z\>** du scope — ça économise **\<coût évité\>**, ça coûte **\<coût accepté\>** (par exemple : l'utilisateur devra rafraîchir manuellement au lieu d'un push live). OK pour toi ?"*

Force an explicit choice per compromise. Never silently downsize the feature. Record the choice (and its reason) in the `Compromises accepted` section of the doc.

## 4. Co-write the Definition of Done — observable criteria only

A criterion is **observable** if a human can answer "yes/no" by looking at:
- the UI (screen state, flash message, redirect target),
- the network (status code, JSON shape, headers),
- the DB (a row exists / a column has a value),
- a side channel (queue contains a job, an email was sent).

**Refuse** "the code is clean", "well tested", "robust", "user-friendly". Those are not DoD criteria — they are values that apply to everything.

**Accept** examples:
- "A POST `/quotes/{uuid}/archive` from the quote's owner returns 302 to `/quotes/{uuid}` with a flash `success`, and the `quotes.archived_at` column is now non-null."
- "Visiting `/quotes` shows archived quotes greyed-out with a `Badge` 'archivée' from shadcn."
- "The `QuoteArchived` Domain Event is dispatched once per archive call."

Write each as a `- [ ]` checkbox in the `Definition of Done` section.

## 5. Mark out-of-scope explicitly

Anything raised during the discussion that was decided *not* to do, **document it** under `Out of scope`. This is the cheapest scope-creep defense available — when the question comes back in 3 weeks, you point at this section.

## 6. Produce the Implementation TODO — atomic, skill-targeted

Group by layer, mirror the project's skill chain. Each item is **atomic** (one deliverable, one PR-worthy unit) and **references the skill that should produce it**.

Item format: `- [ ] <concrete artefact> — via /<skill-name>`

Backend block — keep only the lines that apply:

```
### Backend
- [ ] Use Case `app/Application/UseCase/<Name>/` — via `/create-usecase`
- [ ] Domain interfaces (Entity / Repository / Factory / Specification / Event) — via `/create-usecase`
- [ ] Eloquent model + repository in `app/Infrastructure/Entity/` and `app/Infrastructure/Repository/`
- [ ] Migration `database/migrations/...`
- [ ] Controller + route in `routes/web.php`
- [ ] Bindings updated in `DomainServiceProvider`
- [ ] Use Case tests `tests/Feature/UseCase/<Name>Test.php` — via `/create-tests-usecase`
- [ ] Controller tests `tests/Functional/Controller/<Subject>/<Name>ControllerTest.php` — via `/create-tests-functional`
```

Frontend block — only if the feature touches UI:

```
### Frontend
- [ ] shadcn primitives to install (`Button`, `Input`, …) — via `/create-front`
- [ ] Inertia page `resources/js/pages/<area>/<page>.tsx` — via `/create-front`
- [ ] Shared type in `resources/js/types/` if a new payload shape
- [ ] Verify Wayfinder regenerates `resources/js/routes/` and `resources/js/actions/`
```

Validation block — always:

```
### Final validation
- [ ] `./vendor/bin/pest` — full suite green
- [ ] `./vendor/bin/pest tests/Unit/ArchTest.php` — layering still passes
- [ ] `npm run lint:check && npm run types:check && npm run format:check`
- [ ] Manual smoke: 1 happy path + 1 error path
```

**Reject vague items** like `- [ ] improve UX`, `- [ ] refactor`, `- [ ] make robust`. Rewrite them into concrete artefacts or drop them.

## 7. Write the doc and hand off

1. Slugify the feature title (`Archiver un devis` → `archive-quote`). Kebab-case, English preferred for path stability.
2. Copy `[template.md](template.md)` to `docs/features/<slug>.md`.
3. Fill in every section. `Open questions` may stay non-empty for `draft` status, must be empty for `accepted`.
4. Show the result to the user. On approval, switch the front-matter line from `Status: draft` to `Status: accepted`.
5. End with one sentence pointing at the next skill: "Implementation can now start with `/create-usecase` (backend) and/or `/create-front` (frontend) — both should read this doc first."

**Do not** then proceed to implement. The next skill takes over.

## Language convention

- **This SKILL.md and `template.md`** → English (tooling, like every other skill in this repo).
- **Section headers in the output doc** → English (they come from the template).
- **Content the user fills in** (Context, Challenges, DoD criteria, TODO descriptions) → **French is fine**, mirror the language of the conversation. `docs/architecture.md` is in French; feature docs are in the same family.

## Anti-patterns to refuse

- **Jumping to the TODO before locking the DoD.** Without DoD, items have no acceptance bar. Insist on the DoD round.
- **Vague TODO items** ("améliorer l'UX", "refactor", "rendre robuste"). One concrete artefact per item, with a target skill.
- **Silently accepting a compromise.** Each compromise needs an explicit "OK" from the user, in writing in the doc.
- **Starting implementation in this skill.** No `mkdir app/...`, no `php artisan make:*`, no code edits. Output: one Markdown file.
- **Skipping `Out of scope`.** It's not optional — empty section is fine, missing section is not.
- **Promoting to `Status: accepted` while `Open questions` is non-empty.** Either resolve them or leave the status at `draft`.
- **Editing a previous feature doc with new requirements** instead of creating a new doc. Each feature gets its own file; supersession is a paragraph at the top of the new doc.
- **Acting as a single source of truth on edge cases** when the user said "à voir". List it in `Open questions` and stop. Don't infer.

## Sources of truth to consult during design

- `docs/architecture.md` — DDD/Clean rules a TODO must respect (allow-lists, naming, layering). Cite layer constraints if relevant.
- `CLAUDE.md` — commands, conventions, testsuite layout (`Feature` / `Functional` / `Unit`).
- `app/Application/UseCase/CreateUser/` — canonical Use Case shape; useful as analogy during the challenge ("la feature ressemble à `CreateUser` mais avec…").
- `app/Infrastructure/Providers/DomainServiceProvider.php` — current bindings; tells you which interfaces already exist and which are net-new.
- Sibling skills (`/create-usecase`, `/create-front`, `/create-tests-usecase`, `/create-tests-functional`, `/review-backend`, `/review-frontend`, `/commit`) — the actual implementers; the TODO references them by name.
