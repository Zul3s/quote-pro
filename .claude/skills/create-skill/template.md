---
name: <skill-name>
description: <One sentence, verb-led. List the concrete triggers — "Use when asked to X, Y, or Z." Verbs an agent would actually type: create, run, review, deploy, scaffold, refactor, format, test, debug.>
---

<One-line intro: what this skill does and the primary tool/file/command it points at.>

## Steps

1. <First step — exact command if applicable>
2. <Second step>
3. <Third step>

## Conventions

- <Project-specific rule the agent should follow>
- <Another rule>

## Gotchas

- **<specific symptom>** — <cause> → <workaround>

<!--
Authoring notes (DELETE this comment before committing):

- `name:` must match the directory name (`.claude/skills/<skill-name>/`).
- `description:` is what Claude matches against to auto-load this skill.
  Front-load verbs. Be specific. Avoid "helpful utilities for...".
- Bundled files (driver scripts, examples, more templates) live next to
  this file. Reference them with relative paths.
- Keep it short and prescriptive. One path, not options.
- Every command block in this file should be one you ran and verified.
- See ~/.claude/skills/create-skill/SKILL.md for the full checklist.
-->
