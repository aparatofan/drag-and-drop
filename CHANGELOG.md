# Changelog

All notable changes to TBT Drag & Drop.

## 2.2.1

The gap and word labels turn blue, and the gap number moves onto the corner of
its slot. CSS only — no markup, no behaviour, no stored data changes.

### Changed

- **The labels are blue, not maroon.** `.tbtdd-tag` now paints
  `--tbt-selected-bg` behind `--tbt-blue` instead of white on `--tbt-le`. The
  numbers and letters are a quiet reference marker for naming a gap out loud,
  not a domain flag, so they no longer borrow Learn English's `#660000`.
  Nothing in the plugin points at `--tbt-le` any more, and the hard-coded
  `#FFFFFF` on the badge goes with it. The 2.1.0 note asking for a Style Book
  §2 amendment has been removed from this changelog: the extension it recorded
  no longer happens.
- **The gap number sits in the slot's top-left corner.** `.tbtdd-gap` becomes
  the positioning context and drops its `8px` gap, so the badge overlaps the
  slot instead of taking room on the line — a sentence with gaps in it now
  reads at closer to its natural width. A 3px `--tbt-surface` ring separates
  the badge from the slot's border, and from the green or red border a checked
  slot takes on. The letter on a word token is deliberately left inline: a
  floating badge on a draggable token clips while dragging.

## 2.2.0

Optional extra words in the bank, and the "Create another exercise" button
moved out of stage 3's working row.

### Added

- **Extra words.** A teacher can type words that are *not* in the exercise text;
  they join the word bank, fill no gap, and are wrong wherever they are dropped.
  The student therefore sees more words than there are gaps and has to choose
  rather than place what is left over. Entirely optional: an exercise with none
  behaves exactly as before.
  - Stored in a fifth meta key, `_dd_gap_distractors`, owned by
    `Exercise_Repository` like the other four and deleted rather than stored
    empty.
  - `Exercise_Validator::clean_distractors()` accepts a list or one
    comma-separated string, strips markup, drops blanks, drops anything that
    repeats a gap item (two identical tokens where one is the answer is not a
    harder exercise, it is an unfair one), and caps the list at
    `MAX_DISTRACTORS` (7). Deliberately *not* checked against the text: being
    absent from it is the point.
  - Both authoring paths have the field — "Extra words (optional)" at the end of
    the generator's stage 2, and the same field in the wp-admin meta box — and
    both write it through the repository, so neither path can silently drop what
    the other stored. The REST payload keeps the stored list when the key is
    absent from the body, for the same reason.
  - The generator writes the field back from the save response, so words the
    server dropped or truncated do not linger on screen as if they were saved.

### Changed

- **"Create another exercise" sits below stage 3, at the size of a main action**
  (60px tall, 18px label, full width on narrow screens), in its own centred
  block rather than in the stage's button row. It follows TBT Swipe, where
  "Create another deck" is a block of its own under the saved result instead of
  a fourth button beside the working ones: finishing this exercise and starting
  the next are two different moves. It still appears only once the exercise is
  saved and hides again on the first edit.

## 2.1.1

Fixes the library's Edit links and Create new button resolving to the library's
own page, and the draft row that read "Edited" with no date after it.

### Fixed

- **`Tools_Shortcode::generator_url()` no longer returns the current page just
  because it is the page being rendered.** It now resolves in order and stops
  at the first hit: the `generator=""` attribute, the generator page
  `remember_tool_page()` recorded the first time `[tbt_drag_generator]`
  rendered, and only then the current page — and that last step applies solely
  when `has_shortcode()` finds the generator shortcode in the post content. The
  `tbt_drag_drop_generator_url` filter still runs last over whatever resolved,
  and an empty result stays a legitimate answer meaning "no generator page is
  known". With the two shortcodes on one page nothing changes; with them on two
  pages the library stops linking to itself.
- **A library row with nowhere to edit shows no Edit action.** `tools.js` fell
  back to `window.location.href`, so the Edit link reloaded the library. It now
  renders the action only when a generator URL resolved — the rule
  `library.php` already applied to Create new. The row keeps Open, Share,
  Duplicate and Delete.
- **No row prints a bare "Edited".** The label is dropped whole when there is
  no date to show, instead of printing the word with an empty value.

### Changed

- **The list response sends the local modified time, not GMT.** `draft` is a
  `date_floating` status, so a post inserted as a draft carries
  `post_modified_gmt` as `0000-00-00 00:00:00` until something calls
  `wp_update_post()` on it. `get_post_modified_time( 'c', true, $post )` reads
  that as no date and returns `false`, which is what emptied the label on a
  freshly created draft. `post_modified` holds a real timestamp from the
  insert, and `'c'` carries the site's offset, so the client reads it exactly.

## 2.1.0

Every gap carries a number and every word a letter, so a gap or a word can be
named out loud during a lesson. Always on: nothing is stored, nothing is
configurable, and the generator is untouched. The version bump is also the
asset cache bust.

### Added

- **A number on every gap.** `Renderer::reading_html()` prints the reading-order
  number in a `.tbtdd-tag--number` badge and wraps the badge and its slot in a
  `.tbtdd-gap`, so the pair never breaks apart across a line. The badge is
  `aria-hidden`: the slot's own `aria-label` already announces "Gap 3, empty" or
  what the gap now contains. The wrapper takes over the `0 4px` margin the slot
  used to carry.
- **A letter on every word.** `Renderer::render()` reads the letters off the
  shuffled bank — A, B, C … — and the token prints its own in a
  `.tbtdd-tag--letter` badge beside the word, as a flex child rather than an
  overlay so a short badge and a short word cannot collide. The letter comes
  from the shuffle and nothing else, so it can never hint at the gap its word
  belongs to. It belongs to the word, not the position: it travels with the
  token into a slot and back, and only a redo reshuffle reassigns it.
- Both labels render in compact (in-lesson) mode too — a gap is named the same
  way on its own page and inside a lesson.

### Changed

- **`returnToBank()` inserts, it no longer appends.** A returned word goes back
  at its letter's position, so the bank always reads A, B, C … with holes where
  words are in use, instead of scattering as soon as one word came back.
- **Redo relabels.** `redo()` already reshuffled the bank in the DOM; it now
  rewrites each token's `data-tbtdd-letter` and badge text from its new
  position, so a fresh attempt starts from A again. That is the only place a
  letter changes, and every slot is empty when it runs.

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
