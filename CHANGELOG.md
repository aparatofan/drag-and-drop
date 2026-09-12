# Changelog

All notable changes to TBT Drag & Drop.

## 2.7.0

A completion is now reported for a **finished** board, not a perfect one, and
the real score is sent with it. Reporting a completion also stops the presence
heartbeat for good, so a student who presses **Redo exercise** and keeps
playing no longer reads as working for the rest of the page's life.

### Changed

- **The reporting rule: Check pressed with every gap filled.** 2.6.x reported
  only a board that was entirely correct, which is why the activity table never
  held a single `dragdrop` row — a ten-gap exercise with distractors almost
  never comes out perfect first time, and a signal that rare is one a teacher
  stops reading. Green now means "handed it in"; the score says how it went.
  Nine gaps of ten filled still reports nothing.
- **The row carries the attempt's real score.** `score` is the number correct
  rather than the slot count, against the same `score_max`. A board filled and
  entirely wrong is reported as 0 — a zero is a result, not a reason to
  suppress the row. Reading it as a score rather than as a bare "done" needs
  TBT Notes 1.11.1.

### Fixed

- **The heartbeat stays stopped after a completion.** Placing a token used to
  re-arm the shared presence timer even once the exercise had reported, and
  nothing stopped it again, because the once-per-page-load guard blocks a
  second completion. The student stayed pinned as working in the panel until
  the tab closed. **Redo exercise** sits right beside **Check**, so this was
  easy to trip. A genuinely fresh attempt is a fresh page load, as it already
  was for the assisted flag.

### Notes

- **Show correct is unchanged and matters more than before.** Revealing the
  answers still forfeits the completion for the rest of the page load, and a
  revealed slot holds a token like any other — so that flag, not the fill test,
  is what keeps Check → Show correct → Redo → Check from reporting a board the
  student never solved.
- The **Check** button is not gated or relabelled: it stays pressable at any
  time with any number of gaps filled, and simply reports nothing until they
  are all filled. Pressing it on an empty board does nothing, as before.
- No PHP behaviour changed — 2.6.0's server side, the `activity` config block
  and the `dragdrop` tool slug are all as shipped. Version bump only.

## 2.6.0

Finishing an exercise now reports the completion to the teacher's Class
Progress panel, and the page beats a presence signal while a student is
working. Reporting is an optional integration owned by TBT Notes: with Notes
inactive nothing is sent and the exercise behaves exactly as it did in 2.5.1.
No schema change, no new meta, no REST route of its own.

### Added

- **A completion is reported when Check is pressed and every gap is correct.**
  The student turns green in the panel with the exercise's title beside them.
  The row carries the score — always n of n today — the exercise's post ID, the
  lesson the exercise was embedded in, and how long the attempt took, timed
  from the first word placed rather than from page load.
- **Using Show correct forfeits the completion for the rest of the page load.**
  Check → Show correct → Redo → Check reaches a perfect board in three clicks,
  so without this the panel would report a student for pressing a button three
  times. The flag is never cleared by **Redo exercise**; a page reload gives a
  clean slate. A student who peeks once and then redoes the exercise honestly
  is not reported until they reload — deliberate, so that "assisted once,
  assisted for the sitting" stays cheap to reason about.
- **A presence heartbeat every twenty seconds while an exercise is being
  done.** It starts on the first word placed, not on page load, so a lesson
  page that merely contains an exercise does not mark every student in the room
  as working the moment it paints; it stops the moment a completion is
  reported. The timer is per page, not per exercise, so a lesson carrying three
  exercises beats once rather than three times.

### Changed

- **`TBTDDGame` carries the activity endpoint alongside `strings`.** The keys
  are added only when a user is logged in and TBT Notes is active — checked by
  class, not by plugin file — inside the localise guard that already existed,
  so a multi-exercise page still prints one declaration. `strings` is
  unchanged.
- **The exercise config carries an `activity` block.** The exercise's own post
  ID, its title, the post being viewed when that is not the exercise itself,
  and the server's gap count. The player refuses to report a board whose slot
  count disagrees with that number.

### Notes

- Reporting never gates the learner: requests are fire-and-forget with
  `keepalive`, failures are swallowed, and nothing is shown. Nothing sent
  identifies anybody — the server takes the user from the session and resolves
  their class and teacher itself.
- A student who scores 9/10 and moves on is not reported; they read as working
  until the heartbeat lapses. Partial scores are not recorded.
- Everything else is untouched: the score box, **Show correct**, **Redo
  exercise**, the bank reshuffle and the letter relabelling all behave exactly
  as in 2.5.1.

## 2.5.1

The library header takes the Admin Bar layout: a thin line joins the title, the
search and Create into one row, the search and the button hold fixed widths so
the bar is identical on every TBT tool, and the button carries no shadow. The
title reads in sentence case. Front-end layout only — no schema change, no new
meta, no REST route change, no change to how the tool behaves.

### Changed

- **A joining line runs title ─ search ─ button.** Two decorative
  `.tbtdd-libbar__line` spans replace the single `.tbtdd-section-rule` span, one
  after the title and one after the search, each taking the width the fixed
  items leave and drawn 1px in `--tbt-border` with a 10px gap either side of
  every item. Drag & Drop has no dropdown, so the second line runs the whole way
  from the search to the button. On an empty library the title's line runs to
  the button on its own, which is now CSS rather than a JavaScript `hidden`
  toggle.
- **Fixed widths, so the tools line up.** The search is 300px and the title zone
  is at least 234px, so the search always starts 244px in, level with Students
  and Matching Game. The Create button is a fixed 250 × 53px and loses its
  shadow at rest and on hover; the keyboard focus ring stays. The label is
  unchanged.
- **The title reads in sentence case.** Divi uppercases headings site-wide and
  the library title is an `h2`, so `.tbtdd-section-title` now sets
  `text-transform: none` — the same fix Matching Game 0.8.2 made.
- **The row folds at 1100px rather than 900px.** Below it the lines step aside,
  the title and Create share the first line and the search takes a full second
  line, as before.

## 2.5.0

The library header is one row instead of three stacked blocks: the title, the
search and Create sit on a single line, the same shape the other TBT tools
use. Front-end layout and behaviour only — no schema change, no new meta, no
REST route change.

### Changed

- **Title, search and Create share one line.** `.tbtdd-section-head` and
  `.tbtdd-library__head` are gone, replaced by `.tbtdd-libbar`: the heading on
  the left, the search taking the middle, the Create button closing the row.
  Drag & Drop has nothing to filter by besides the title, so where the other
  tools put a dropdown this one gives the space to the search field. The
  geometry is Matching Game 0.8.0's, to the pixel, so two tool pages set side
  by side line up.
- **The search label is visually hidden and the placeholder does the work.**
  `Search by exercise title` replaces the old `Exercise title` placeholder and
  the visible `Search your exercises` label above it; the label itself stays in
  the markup for screen readers. The search is still server-side through the
  REST `search` param on a 300 ms debounce, and pagination is untouched.
- **The field carries its own controls.** A magnifier sits inside the field, a
  × button appears as soon as there is text to clear, `Escape` inside the
  field clears it, and `/` anywhere outside a text field focuses the first
  visible library search on the page. `/` is ignored while the create dialog is
  open, so typing a slash into a title still types a slash.
- **A summary line appears only while searching.** `3 of 14 exercises` followed
  by a `Clear filters` button, which also appears inside the
  "No exercises match that search" hint. Deleting or duplicating an exercise
  while a search is running adjusts the library total, so the `of Y` half stays
  honest without a second request.
- **An empty library hides the search rather than offering it.** The shortcode
  counts the teacher's own exercises before rendering and prints the number on
  the markup, so a teacher with nothing saved gets the title, the rule line and
  Create — and never a search bar that flashes in and disappears once the
  first request lands. The count uses the same owner-and-status scope as
  `Exercises_Controller::list_items()`.
- **Create new exercise is the one uppercase pill in the plugin.** The button
  is the shared library CTA, so it takes the pill radius, the wider padding and
  the uppercase label that every TBT tool's library CTA has. Every other button
  here — row actions, generator, pagination — keeps its sentence case and
  12px corners deliberately: the CTA is meant to be the one thing on the row
  that does not look like the rest.
- **Colours still come from the shared tokens.** The toolbar reads
  `--tbt-blue`, `--tbt-border`, `--tbt-input`, `--tbt-surface`,
  `--tbt-selected-bg`, `--tbt-muted` and `--tbt-focus-ring`; it defines no
  colour of its own, and `assets/vendor/tbt/tbt-tokens.css` is untouched. The
  `.tbtdd-sr-only` helper is copied into `tools.css` because the tools bundle
  never loads `game.css`, where it was defined.

## 2.4.0

An exercise can hold fifteen gaps instead of seven. One constant, plus the
strings and comments that had the old number written into them by hand.

### Changed

- **`Exercise_Validator::MAX_ITEMS` is 15.** Seven was a reading limit, not a
  storage one, and it held while an exercise was a short paragraph. A longer
  text gapped throughout rather than only sampled needs more than seven, and
  that is the case this raises the cap for. The bank stays scannable because
  it wraps: `.tbtdd-bank` and `.tbtdd-chips` are both `flex-wrap: wrap` with
  no cap, so more gaps make the rows deeper, never wider, and `.tbtdd-tag`
  sets `min-width` with horizontal padding, so a two-digit gap number widens
  its badge instead of clipping inside it.
- **`MAX_DISTRACTORS` stays at 7.** Nothing about the extra words got harder
  to scan, so the other half of the bank is unchanged. A full exercise is now
  fifteen gaps plus seven extras: **22 bank tokens at most, still inside the
  26-letter limit** 2.3.0's keyboard fill reasons from. Every badge letter
  still names exactly one word, and no AA/AB double-letter scheme is needed.
- **Stored exercises are untouched.** `_dd_gap_items` is the same meta key
  holding the same shape, with a higher permitted length. Nothing migrates,
  and an exercise written against the old cap reads, plays and re-saves
  exactly as it did.
- **The cap is no longer written out by hand.** The meta box heading, its
  "up to N items" alert and the generator's cap notice now take
  `Exercise_Validator::MAX_ITEMS` through `sprintf`, matching how the extra
  words field already read `MAX_DISTRACTORS`; the `gapLimit` string became
  `%d`, which the translators comment above it had promised all along. The
  JS fallbacks in `tools.js` and `admin.js` moved to 15, and the comments in
  `game.js` and `Renderer::bank_letter()` that reasoned from "fourteen" now
  say twenty-two. `Exercise_Validator` remains the only place the cap is
  enforced: both authoring paths and the REST route reach storage through it.

## 2.3.0

Keyboard gap fill: a student can complete an exercise without the pointer.
Player only — the generator and the library screens are untouched.

### Added

- **Type a letter into a gap.** Tab to a gap and press the letter printed on a
  word's badge, and that word drops into the gap. The lettered word badges and
  numbered gap badges from 2.1.0 already showed the mapping; this release makes
  the letters do something. It exists because a touchpad drag is slow and
  error-prone for some students.
  - **Desktop and touchpad only. This does nothing for mobile.** A focusable
    gap raises no on-screen keyboard, so a phone or tablet is no better off
    than before. Select-then-place for touch remains a separate, future job.
    Nothing here adds `contenteditable`, an `<input>`, or any other element
    that would summon a soft keyboard.
  - Typing over a filled gap sends the word that was there back to the bank.
    Typing a letter that is sitting in another gap moves it, and the gap it
    came from empties. Typing the letter already in the gap does nothing.
  - `Backspace` and `Delete` return a gap's word to the bank and keep the
    focus on the gap, so a mistype is fixed without reaching for the mouse.
    Both stop the browser using Backspace to navigate back.
  - After a fill the focus moves to the next empty gap and wraps round to the
    lowest-numbered one, so an exercise is completed with one Tab and then
    letters. Focus never lands on a gap that is already filled.
  - A letter naming no word nudges the gap for 150ms. Movement only, never
    colour: green and red are the verdict on an answer, and a key that names
    nothing is not an answer. The nudge is dropped under
    `prefers-reduced-motion`, where an unrecognised key simply does nothing.
  - `Ctrl`, `Cmd` and `Alt` combinations are left to the browser, so Ctrl+A
    and Cmd+R behave as they always did. `Shift` is not, since that is how a
    capital letter is typed. `Tab` and `Shift+Tab` are never intercepted.
  - Keyboard entry reaches a word only while its letter names exactly one.
    Badges are dealt `chr(65 + index % 26)`, so a bank past 26 words would
    repeat a letter; those words stay drag-only rather than risk placing the
    wrong one. No AA/AB double-letter badges. A bank holds at most seven gap
    items plus seven extra words, so no exercise reaches that limit.
  - Every path runs through the same `place()` and `returnToBank()` the
    pointer uses, so Check reads a typed word exactly as it reads a dragged
    one, and drag-and-drop is unchanged.
- **A line under the word bank** telling students the keyboard route exists.
  It is the plugin's own line, not part of the teacher's per-exercise
  instructions, so it appears on every exercise without touching stored data.

### Fixed

- **A word dragged straight from one gap to another left the first gap looking
  and reading as though it still held it** — the gap kept `is-filled`, so it
  stayed solid-bordered and white, and its `aria-label` went on naming a word
  that had moved. `place()` now clears the gap a word is taken from. Reachable
  by dragging since 2.0.0; found while building the keyboard move.
- **A focused gap lost its blue border once answers had been checked.** The
  slot's `:focus-visible` rule sat above `.is-correct` / `.is-wrong` at equal
  specificity, so the verdict colour won and a keyboard user was left with
  only the pale ring over a green or red fill. The focus rule now comes last
  among the slot rules.

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
