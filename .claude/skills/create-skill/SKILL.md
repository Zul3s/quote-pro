---
name: create-skill
description: Author a new Claude Code skill — pick scope, write a discoverable description, scaffold the directory, optionally bundle a driver/template, and verify it loads. Use when asked to create a skill, add a skill, write a slash command, scaffold a SKILL.md, or set up `.claude/skills/...`.
---

A skill is a `SKILL.md` (markdown with frontmatter) in a `.claude/skills/<name>/` directory. Claude Code **auto-discovers** skills by walking `.claude/skills/` upward from the cwd, and **auto-loads** them when the user's request matches the `description:` field. There is no registry, no build step, no install.

This skill is the checklist for getting that contract right.

## 1. Pick the scope

| Scope | Path | When |
|---|---|---|
| **Project** | `<repo>/.claude/skills/<name>/` | Skill is specific to this codebase, should be versioned, and shared with the team. |
| **Sub-project** | `apps/<app>/.claude/skills/<name>/` | Mono-repo with multiple deployables — skill applies to one of them. |
| **User** | `~/.claude/skills/<name>/` | Personal utility, useful across all your projects. Not shared, not versioned. |

If unsure, default to **project scope**: a skill in the repo travels with the code and survives a fresh clone. Move to user scope only if it is genuinely personal (formatting habits, generic helpers).

## 2. Write the `description:` — this is the make-or-break field

Claude scans every loaded skill's `description:` against the user's request and decides whether to inject the skill's body into context. **A vague description means the skill never fires.**

Rules:

- **Front-load the verbs an agent would type.** "create", "add", "review", "deploy", "run", "test", "scaffold", "refactor", "format"... If a verb that triggers the skill isn't in the description, matching is luck.
- **Name the concrete triggers.** Not "helpful utilities for X" — instead "Use when asked to *X*, *Y*, or *Z*."
- **Distinguish from neighbours.** If two skills overlap, say *when* this one applies and the other doesn't.
- **Single sentence, ≤ ~200 chars** is the sweet spot. Long descriptions get noisy and reduce match quality.

Bad: `description: Helps with skills.`
Good: `description: Author a new Claude Code skill — pick scope, write a description, scaffold the directory. Use when asked to create a skill, add a skill, scaffold a SKILL.md.`

## 3. Name the skill

- Lowercase, kebab-case, no slashes: `review-pr`, not `Review PR` or `review/pr`.
- Use a **verb-led** name when the skill *does* something (`create-skill`, `deploy-staging`). Use a noun when it *is* a body of knowledge (`postgres-conventions`).
- **`name:` in the frontmatter must match the directory name.** Both become the slash command `/<name>`.

## 4. Scaffold

```bash
NAME=my-skill
SCOPE_DIR=.claude/skills   # or: ~/.claude/skills for user scope
mkdir -p "$SCOPE_DIR/$NAME"
cp ~/.claude/skills/create-skill/template.md "$SCOPE_DIR/$NAME/SKILL.md"
$EDITOR "$SCOPE_DIR/$NAME/SKILL.md"
```

Then edit the frontmatter (`name:` matches `$NAME`, write the `description:`) and replace the body.

## 5. Body — what to write, what to cut

Skills are **operational instructions for an agent**, not documentation for humans. Keep them short, prescriptive, and verified.

Include:

- **One-line intro** stating what this skill does and the primary tool/file it points at.
- **Steps** in execution order. Numbered when sequence matters.
- **The exact commands you ran** and that worked. Not commands inferred from a README.
- **Anti-patterns / gotchas** — only those you actually hit.

Cut:

- Background, history, rationale prose. If "why" matters, one line is enough.
- Multiple alternative paths. Pick one and prescribe it.
- Generic advice ("write good code", "test your changes").
- Anything the agent can discover by reading the repo (file paths that change, current architecture surveys).

A skill that reads like a tutorial is too long. A skill that reads like a checklist is right.

## 6. Bundle resources alongside `SKILL.md`

The directory can hold anything `SKILL.md` references with relative paths:

```
.claude/skills/my-skill/
├── SKILL.md
├── template.md         # boilerplate to copy
├── driver.mjs          # script Claude can run
├── examples/
│   ├── case-a.md
│   └── case-b.md
└── prompts/snippet.txt
```

Reference them from `SKILL.md` like `[template](template.md)` or `bash .claude/skills/my-skill/driver.mjs`. **Paths in `SKILL.md` are interpreted relative to the skill directory** when the file is read, but inside command examples you usually want the path relative to the user's `cwd` (typically the unit/repo root) — be explicit when there's any chance of confusion.

If a driver script grows enough that the project's own test suite wants to reuse it, **graduate it** to `scripts/` or `e2e/` and update `SKILL.md` to point at the new location.

## 7. Verify it loads

1. Open a fresh Claude Code session in a directory inside the skill's scope (the repo for project scope, anywhere for user scope).
2. Type `/<name>` — the skill should appear in the slash-command list. If it doesn't, the `name:` is wrong or the file isn't where Claude expects.
3. Ask the agent something that should trigger auto-loading (a phrase that matches the `description:`). Confirm the skill content is being applied. If not, the `description:` is too vague — rewrite with concrete verbs.
4. Read your own `SKILL.md` out loud. Every command block — did *you* run it, in this session, and it worked? If no, delete it or run it now.

## Anti-patterns

- **Skill that paraphrases the README.** If a human can read `README.md` and get the same thing, the skill is noise. Skills earn their place by encoding *non-obvious operational knowledge* — the workaround, the env var, the order that matters.
- **`description:` without verbs.** "Documentation about deployment." → won't match "deploy the app." Use "Deploy the app — build, push, restart. Use when asked to deploy, ship, or release."
- **Catch-all skill.** A `my-helpers` skill covering five unrelated topics matches on none of them. Split into separate skills with focused descriptions.
- **Skill that contradicts CLAUDE.md.** Skills augment, they don't override. If a skill says "use yarn" and `CLAUDE.md` says "use npm", the agent gets conflicting signals. Resolve in CLAUDE.md.
- **Stale skill.** Skills rot like docs. If the commands no longer work, fix or delete — a wrong skill is worse than no skill.

## Quick reference — minimum viable skill

```markdown
---
name: my-skill
description: <verb-led, lists concrete triggers, single sentence>
---

<One-line intro: what this does, what tool/file is central.>

## Steps

1. <command>
2. <command>

## Gotchas

- <specific trap you actually hit>
```

That's the whole contract. Everything else is optional craft on top.
