# TBT Drag Exercises — Claude Code guidance

Keep this file concise. It is loaded at the start of every Claude Code session.

## Start here

- Work from the task, not from a full-repository scan.
- Check `git status`, then use targeted search and read only the files/sections relevant to the task.
- Do not reread the whole main PHP file when a symbol or nearby section is enough.
- Do not change unrelated formatting, copy, CSS, or behavior.
- Inspect the final diff before finishing.

## Project basics

- WordPress plugin for The Blue Tree.
- Main plugin file: `drag-drop-exercises.php`.
- Front-end/admin assets live in `assets/`.
- The plugin creates `dd_exercise` items and embeds them with `[dd_exercise]`.
- When TBT Hub is active, the post type nests under `TBT_HUB_SLUG`; otherwise it keeps its own WordPress menu.
- Keep the current simple PHP/JavaScript/CSS architecture. Do not add a framework or build system for a local change.

## Behavior to preserve

- An exercise consists of source text plus 1–7 gap items.
- Saved exercise content is WordPress-managed data; do not invent migrations or alter stored formats unless the task requires it.
- Keep authoring/admin behavior separate from learner-facing rendering.
- Preserve existing shortcode compatibility when changing rendering or assets.
- Do not introduce dependencies on other TBT plugins unless the task explicitly requires integration. Hub menu integration must continue to degrade safely when Hub is absent.

## Security

- Preserve nonce and capability checks on writes.
- Sanitize values before storage and escape output for the context in which it is rendered.
- Never commit credentials, FTP details, API keys, or local configuration.
- Do not weaken WordPress permissions or validation to make a UI change work.

## Coding style

- Follow the surrounding style rather than reformatting the entire file.
- Prefer small local changes over new abstractions for one-off behavior.
- Reuse existing helpers, hooks, selectors, and asset handles before adding parallel mechanisms.
- Keep comments for non-obvious constraints, not narration of obvious code.

## Validation

For PHP changes, syntax-check the files touched:

```bash
php -l drag-drop-exercises.php
```

Also syntax-check any changed PHP file if the project later gains more PHP files.

For JavaScript changes, use `node --check <changed-file>` when Node is available.

For interaction/layout changes, state what still requires a live WordPress/Divi browser check; do not pretend static syntax checks verify drag behavior.

## Git and deployment

- `main` is the integration branch. Use a focused feature branch for changes.
- Keep commits task-focused and descriptive.
- A push to `main` triggers the FTPS deployment workflow.
- Markdown files are excluded from the FTP upload, although a push to `main` still starts the workflow.
- Never alter deployment credentials or the `/drag-drop-exercises/` server path unless the task is specifically about deployment.

## Context discipline

- Prefer targeted search + narrow reads over broad exploration.
- Do not load large logs or whole files into the conversation when a short excerpt answers the question.
- Use git history only when the current code does not explain an important design decision.
- Summarize completion as: what changed, what was checked, and any remaining live-site verification.
- For a new unrelated task, prefer a fresh Claude Code session rather than carrying a long old conversation forward.
