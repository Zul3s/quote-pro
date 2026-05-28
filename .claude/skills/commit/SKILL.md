---
name: commit
description: Create a well-formed git commit following Conventional Commits, English, imperative mood, with pre-flight checks and the project's co-author footer. Use when asked to commit, create a commit, make a commit, stage and commit changes, or "commit ça pour moi".
---

Produce **one** Conventional Commit in English, with the right scope and the co-author footer. Never `--no-verify`. Never amend a pushed commit. Never `git add -A`.

## 1. Pre-flight — gather state in parallel

Run these three together (don't read files, don't go exploring):

```bash
git status            # NEVER -uall (huge repos hang)
git diff              # unstaged + staged
git log --oneline -5  # mirror the existing style
```

If nothing to commit → say so and stop. Don't fabricate an empty commit.

## 2. Choose the scope of this commit

**One commit = one logical change.** If the diff spans unrelated concerns (e.g. a backend fix + a frontend refactor + a deps bump), tell the user and ask which to commit first — don't bundle them.

Stage **deliberately by path**, not `git add -A` / `git add .`:

```bash
git add app/Actions/CreateUser.php \
        tests/Feature/Action/CreateUserTest.php
```

**Never stage**:
- `.env`, `.env.local`, anything `.env.*` except `.env.example`
- credentials, tokens, private keys
- `vendor/`, `node_modules/`, `public/build/`, `storage/` (already gitignored — but double-check the `git status` output)
- screenshots, logs, scratch files

If the user named specific files, stage only those. If unclear, ask.

## 3. Write the message

### Format

```
<type>(<scope>): <subject>

<optional body>

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```

### Rules

- **English.** Project convention (chat is French, artefacts are English).
- **Imperative mood**: "add user creation flow", not "added" / "adds" / "adding".
- **Subject ≤ 72 chars**, no trailing period, lowercase after the colon.
- **Subject explains the *why* in compressed form**, not the *what*. The diff shows the what.
- **Body only if the subject can't carry it.** Wrap at 72. Single blank line between subject and body. Use the body to explain *why* a non-obvious change, link to an issue, or warn about a follow-up.
- **Co-author footer always**, exact line above, separated by a blank line. Trailing blank line at end of file.

### Types (Conventional Commits)

| Type | When |
|---|---|
| `feat` | New user-facing feature (new Use Case, new page, new endpoint). |
| `fix` | Bug fix. |
| `refactor` | Behaviour unchanged, structure changed. |
| `perf` | Behaviour unchanged, perf improved. |
| `test` | Adding or fixing tests only. |
| `docs` | Docs only (`docs/`, `README.md`, `CLAUDE.md`, inline docblocks). |
| `chore` | Maintenance: bumping deps, tweaking `.gitignore`, removing dead code. |
| `build` | Build system, asset bundling (`vite.config.ts`, `composer.json` scripts). |
| `ci` | CI config (`.github/workflows/`). |
| `style` | Pure formatting (Pint, Prettier). Rare — usually rolled into the change. |
| `revert` | Reverts a prior commit; reference its hash. |

### Scopes used in this repo

Look at `git log --oneline -20` for prior scopes before inventing one. Reasonable scopes for Quote Plus, in order of preference:

- **Layer-based**: `models`, `actions`, `data`, `http`, `front`
- **Feature-based**: `user`, `quote`, `auth` (when the change cuts across layers for one aggregate)
- **Cross-cutting**: `archi` (architecture rules / ArchTest), `ci`, `deps`, `docs`

Scope is **optional** — omit if the change is genuinely repo-wide (e.g. `chore: bump node to 22`).

### Examples that match this codebase

```
feat(user): add CreateUser action with email-uniqueness rule
fix(http): build CreateUserData from the request in the controller
refactor(actions): move form validation into the input Data DTO
test(actions): cover the EmailIsUnique business rule
docs(archi): document the Action validation model
chore(deps): bump laravel/framework to 13.7.2
```

## 4. Run the verification (if requested or if pre-commit hook isn't enough)

Pre-commit hooks may not exist in this repo. If the changes touch code that CI checks, run the same checks locally first — caller's call:

```bash
composer lint:check    # Pint
composer test          # Pest + ArchTest
npm run lint:check
npm run types:check
```

If any fails, **fix the underlying issue** — don't bypass. Never `--no-verify`.

## 5. Commit using HEREDOC (preserves formatting)

```bash
git commit -m "$(cat <<'EOF'
feat(user): add CreateUser use case with email validation

Domain: UserInterface + UserCreated event.
Application: CreateUser/{UseCase,Request} with Email validation.
Infrastructure: EloquentUserRepository + UserFactory + SendWelcomeEmail job.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Then verify:

```bash
git status                # confirm clean (or expected remaining work)
git log -1 --stat         # confirm message + files
```

## 6. After committing

- **Do not push** unless the user explicitly says so. Even a "good commit" doesn't authorise a push.
- If the user asks to push: confirm the remote/branch first, never force-push to `main` / `master`, never push tags they didn't ask for.

## Absolute prohibitions

These are non-negotiable. The user has not authorised them, even when the action seems convenient.

- `git commit --no-verify` — fix the hook failure, don't bypass.
- `git commit --amend` on a pushed commit — create a new commit instead.
- `git reset --hard`, `git checkout --`, `git clean -f` to "make the diff smaller" — investigate first.
- `git push --force` on `main` / `master` — refuse and warn.
- `git config user.*` changes — never.
- Committing files containing secrets even if the user asked — warn loudly and require explicit re-confirmation.
- Including `Generated with Claude Code` marketing line **in addition to** the co-author footer — the co-author footer is the only attribution; no extra line.

## Anti-patterns to refuse

- **"chore: update files"** — vague subject, useless `git log`. Re-write.
- **"WIP" commits on `main`** — only acceptable on a branch the user explicitly labelled as scratch.
- **Mixing `feat` + `refactor` in one commit** — split.
- **French commit messages** — convert to English (see [[feedback-commit-language]] memory). The chat stays French; the message doesn't.
- **A subject that just restates the file name** (`feat: update UserController.php`). Restate the *change* instead.
- **A body that lists changed files** — `git log --stat` already shows that. Use the body for *why*, not *what*.

## Sources of truth

- `git log --oneline -20` — the existing scope/type vocabulary in this repo.
- Conventional Commits spec — https://www.conventionalcommits.org (one-pager).
- This file — when in doubt, prefer the explicit rules here over external conventions.
