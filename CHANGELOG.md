# Changelog

All notable changes to TBT Drag & Drop.

## 2.0.1

Corrections after the first live review. No structural change and no new
dependencies; the version bump is also the asset cache bust.

### Style Book divergence

Style Book §6B still describes the old `135deg` blue-to-navy Tool Hero. Both
shipped tools — TBT Matching Games and TBT Swipe — moved to a `92deg`
blue-to-white fade with the tree mark sitting in the pale end, and the shipped
version is what this release matches. The Style Book is deliberately **not**
edited here; this note records the divergence so the next Style Book revision
can settle it.

The canonical sources this pass took its values from are
`tbt_matching_game`'s `.tbtmg-hero` and `tbt-swipe`'s `.tbt-stage-card`,
`.tbt-deck` and buttons.

### Changed

- **Both heroes** — the player's `.tbtdd-hero` and the tools' `.tbt-tool-hero` —
  take the shipped gradient and geometry: `92deg` blue to 65%, white by 95%,
  `clamp(20px, 4vw, 52px)` gap, `26px 28px` padding, `overflow: hidden`. Hero
  copy is capped at `max-width: 62%` so no white text can reach the pale end.
- **The tool heroes show the colour tree.** `templates/tool-hero.php` printed
  the flat white PNG unconditionally, so the generator and library heroes wore a
  ghost tree while the player had Hub's animated mark. They now use the same
  `[tbt_tree]` fallback the player does, with `animate="no"`: a tool page is a
  workspace, not an arrival.
- **The generator renders no hero by default.** `[tbt_drag_generator]` now
  defaults to `hero="no"`, since that page supplies its own header from a Divi
  library block. `hero="yes"` still renders the canonical Tool Hero, and the
  library default (`no`) is unchanged. The "Back to my exercises" link stays —
  it is chrome, not hero.
- **Stage cards match Swipe.** A 5px coloured top rim, and the stage number and
  name as one 28px content-face line instead of a 14px label above a 22px
  heading. `tools.js` keeps a `data-state` on each stage, so the rims run grey →
  blue → green as the exercise is built: stage 1 done once there is text, stage
  2 waiting until there is, done once there is a gap, stage 3 waiting until
  there are gaps and done once published.
- **Library rows take Swipe's left rim** — 6px, blue for published and
  `--tbt-muted` for a draft, lifting 2px on hover and holding the grey on a
  draft. The rim carries the same information as the badge, which is what makes
  a scanned list readable.
- **A filled token shows one border.** A token inside a slot now gives up its
  own border and background; the slot keeps the frame and, after checking,
  carries the verdict. A token in the bank is unchanged.
- **Redo exercise is a filled button.** A new sentence-case
  `.tbtdd-button--primary` sits beside the uppercase Check CTA, so the row after
  checking reads white "Show correct" next to blue "Redo exercise" — and once
  Show correct hides itself, no lone white button is left. Uppercase stays
  reserved for Check.
- **The instructions field opens with the default sentence in it**, not just as
  a placeholder, so a teacher edits real text instead of retyping the default to
  change three words. Emptying the field still deletes the meta and the renderer
  still falls back to the default.

### Added

- **"Create another exercise"** in Stage 3, mirroring Swipe's `Create another
  deck`. Revealed after any successful publish or draft save, hidden again as
  soon as the teacher edits anything, and left hidden when no generator URL
  resolves rather than pointing at a page that cannot be reached.

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
