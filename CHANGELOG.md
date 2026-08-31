# Changelog

All notable changes to TBT Drag & Drop.

## 2.0.0

The admin-only gap-fill plugin becomes a full TBT Teacher Tool: exercises have
their own public page, teachers build them on the front end, and the whole
surface follows TBT Style Book v1.0.

Desktop only. No touch support, no AI generation in this release.

### Added

- **Public exercise pages.** `dd_exercise` is now a public post type with the
  rewrite slug `drag-and-drop`, so every published exercise resolves at
  `/drag-and-drop/<slug>/` and renders through `templates/single-exercise.php`.
  Themes can override that at `tbt-drag-drop/single-exercise.php`.
- **Front-end generator**, `[tbt_drag_generator]`: three stage cards — write the
  exercise, choose the gaps by clicking or dragging across words, publish and
  share. Gap selection is click-to-gap, not typed.
- **Front-end library**, `[tbt_drag_exercises]`: the teacher's own exercises with
  search, pagination and per-row Open / Edit / Share / Duplicate / Delete.
- **Access gate.** Both tools require the shared `tbt_use_teaching_tools`
  capability, and honour TBT Swipe's `tbts_manage` and `manage_options` the same
  way TBT Matching Games does, so a teacher who reaches one tool reaches this one
  with nothing to configure. Filters: `tbt_drag_drop_can_use_tools`,
  `tbt_drag_drop_tool_roles`, `tbt_drag_drop_upsell_html`.
- **REST API** under `tbt-drag-drop/v1`, owner-scoped: list, create, read,
  update, trash and duplicate. Every route has a real permission callback and
  every write re-checks ownership inside the callback.
- **`_dd_gap_offsets`** (new meta, `int[]`): the byte offset in `_dd_gap_text`
  where each gap begins, index-aligned with `_dd_gap_items`. This is what lets a
  gap made from the *second* occurrence of a repeated word gap that occurrence
  instead of the first. It is an optimisation of fidelity, never a requirement:
  a missing or stale offset falls back to first-occurrence matching and no
  exercise ever fails because of one.
- **`_dd_gap_instructions`** (new meta, `string`, optional): the player's support
  line, falling back to a default when empty.
- **Click-to-place** in the player, alongside HTML5 drag: click a token to pick
  it up, click a gap to place it. Tokens and gaps are focusable and respond to
  Enter and Space, and an `aria-live` region reports what moved.
- Vendored `assets/vendor/tbt/tbt-tokens.css`, a byte-identical copy of TBT-Hub's
  canonical token file, registered under the shared `tbt-tokens` handle only when
  Hub has not already registered it.
- wp-admin list table: a **Gaps** column and an **Edit on the front end** row
  link; the meta box now shows the exercise's permalink beside its shortcode and
  has a field for the student instructions.

### Changed

- Restructured into `includes/` + `templates/` under the `TBT\DragDrop`
  namespace, mirroring TBT Matching Games. `Exercise_Repository` is the single
  owner of the four exercise meta keys — no other class reads or writes them.
- Display name and text domain are now **TBT Drag & Drop** / `tbt-drag-drop`.
  The main file (`drag-drop-exercises.php`), the server folder
  (`/drag-drop-exercises/`), the post type (`dd_exercise`), the shortcode
  (`[dd_exercise]`) and the two original meta keys are all unchanged.
- The player is restyled against the shared tokens: Tool Hero, white reading
  panel, object-card token bank, one uppercase call to action and sentence-case
  secondary buttons. Tokens are no longer pills.
- `[dd_exercise]` renders in compact mode by default — no hero, no tree mark —
  because an embedded exercise sits inside a lesson that already has its own
  heading. `compact="no"` brings the hero back.
- The renderer hands `game.js` a JSON config block instead of putting the
  answers in a `data-` attribute on the container.
- Gap de-duplication is now case-insensitive on both authoring paths. Two gaps
  differing only in case produce two bank tokens that either gap accepts, which
  is the ambiguity the rule exists to prevent.
- Duplicate deploy safeguards from Matching Games: a preflight that refuses to
  upload unless `drag-drop-exercises.php` is already in the target folder, a
  concurrency group, and a retry for the host's intermittent connection resets.

### Removed

- Every local `--dd-*` colour variable. Colour now comes from the shared token
  file and nowhere else.
- `assets/css/frontend.css` and `assets/js/frontend.js`, replaced by
  `assets/css/game.css` and `assets/js/game.js`. The FTP deploy never deletes,
  so the old files stay on the server; nothing enqueues them.
- The legacy 4px button radius, uppercase secondary buttons, `#660000` title and
  dashed accent border.

## 1.0.0

- Initial release: `dd_exercise` post type, a wp-admin meta box with a typed list
  of up to seven gap items, and the `[dd_exercise]` shortcode.
