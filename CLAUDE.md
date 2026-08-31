# TBT Drag & Drop — Claude Code guidance

Keep this file concise. It is loaded at the start of every Claude Code session.

## Start here

- Work from the task, not from a full-repository scan.
- Check `git status`, then use targeted search and read only the files/sections relevant to the task.
- Do not change unrelated formatting, copy, CSS, or behavior.
- Inspect the final diff before finishing.

## Project basics

- WordPress plugin for The Blue Tree, version 2.0.0.
- Main plugin file: `drag-drop-exercises.php` — bootstrap only: header, constants, includes, hub item, activation hooks, `Plugin::instance()->boot()`.
- Classes live in `includes/`, markup in `templates/`, assets in `assets/`, all under the `TBT\DragDrop` namespace.
- The plugin creates `dd_exercise` items, publishes them at `/drag-and-drop/<slug>/`, and embeds them with `[dd_exercise]`.
- Front-end authoring is `[tbt_drag_generator]` and `[tbt_drag_exercises]`; the wp-admin meta box is the second authoring path and both write through one repository class.
- When TBT Hub is active, the post type nests under `TBT_HUB_SLUG`; otherwise it keeps its own WordPress menu.
- Plain PHP/JavaScript/CSS. No framework, no build step, no bundler — do not add one.

## Architecture rules

- **`Exercise_Repository` owns the four exercise meta keys.** No other class may call `get_post_meta()` or `update_post_meta()` for `_dd_gap_text`, `_dd_gap_items`, `_dd_gap_offsets` or `_dd_gap_instructions`. Two authoring paths, one definition of what is stored.
- **`Exercise_Validator` owns sanitisation and validation.** `sanitise()` cleans without enforcing completeness (draft saves); `validate()` adds the completeness rules (publishing).
- Keep authoring/admin behavior separate from learner-facing rendering.
- Preserve existing shortcode compatibility when changing rendering or assets.
- Do not introduce dependencies on other TBT plugins unless the task explicitly requires integration. Hub menu integration and the `[tbt_tree]` mark must continue to degrade safely when Hub is absent.

## Behavior to preserve

- An exercise consists of source text plus 1–7 gap items, with no duplicates (case-insensitive).
- **The plugin is desktop-only by decision.** HTML5 drag plus click-to-place. Do not add touch event handling.
- Saved exercise content is WordPress-managed data; do not invent migrations or alter stored formats unless the task requires it. `dd_exercise`, `[dd_exercise]`, `_dd_gap_text` and `_dd_gap_items` are stored data and keep their names.
- Offsets are an optimisation of fidelity, never a requirement: a missing or stale offset must fall back to first-occurrence matching, never fail the exercise.

## Design tokens

- `assets/vendor/tbt/tbt-tokens.css` is a byte-identical vendored copy of TBT-Hub's file. **Never edit a value in it, and never register it under any handle but `tbt-tokens`.** Resync it from Hub instead; see the README.txt beside it.
- No stylesheet in this plugin may define a colour of its own. Local `--dd-*`-style colour variables are forbidden — use the tokens.
- `.gitignore` anchors its vendor rule as `/vendor/` so `assets/vendor/` is never matched.

## Security

- Preserve nonce and capability checks on writes. Every REST route needs a real `permission_callback`, and every write re-checks ownership inside the callback too.
- Sanitize values before storage and escape output for the context in which it is rendered.
- Never commit credentials, FTP details, API keys, or local configuration.
- Do not weaken WordPress permissions or validation to make a UI change work.

## Coding style

- Follow the surrounding style rather than reformatting the entire file.
- Prefer small local changes over new abstractions for one-off behavior.
- Reuse existing helpers, hooks, selectors, and asset handles before adding parallel mechanisms.
- Keep comments for non-obvious constraints, not narration of obvious code.

## Validation

For PHP changes, syntax-check every file you touched:

```bash
php -l drag-drop-exercises.php
for f in includes/*.php templates/*.php; do php -l "$f"; done
```

For JavaScript changes, use `node --check <changed-file>` when Node is available.

For interaction/layout changes, state what still requires a live WordPress/Divi browser check; do not pretend static syntax checks verify drag behavior.

## Git and deployment

- `main` is the integration branch. Use a focused feature branch for changes.
- Keep commits task-focused and descriptive.
- A push to `main` triggers the FTPS deployment workflow.
- Markdown files and `.github/` are excluded from the FTP upload, and a documentation-only push skips the workflow.
- **Never rename `drag-drop-exercises.php` or the `/drag-drop-exercises/` server path.** The FTP action uploads but never deletes, so a renamed main file would sit beside the old one and WordPress would show two plugins. Do not alter deployment credentials or the server path unless the task is specifically about deployment.

## Context discipline

- Prefer targeted search + narrow reads over broad exploration.
- Do not load large logs or whole files into the conversation when a short excerpt answers the question.
- Use git history only when the current code does not explain an important design decision.
- Summarize completion as: what changed, what was checked, and any remaining live-site verification.
- For a new unrelated task, prefer a fresh Claude Code session rather than carrying a long old conversation forward.
