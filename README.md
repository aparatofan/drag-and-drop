# TBT Drag & Drop

A WordPress plugin for [The Blue Tree](https://thebluetree.pl). Teachers build
gap-fill drag-and-drop exercises, publish them at their own URL, and embed them
in lesson pages with a shortcode.

Desktop only, by decision: HTML5 drag plus click-to-place, no touch fallback.
No AI generation.

- **Main file:** `drag-drop-exercises.php` (never rename it — see below)
- **Server folder:** `/drag-drop-exercises/`
- **Version:** 2.0.0 · **Requires:** WordPress 6.4, PHP 8.0

## Shortcodes

| Shortcode | What it does |
|---|---|
| `[dd_exercise id="123"]` | Embeds an exercise in a lesson page. Attributes: `show_title`, `show_instructions`, `compact` (all default to yes/compact). `compact="no"` renders the full hero. |
| `[tbt_drag_generator]` | The front-end builder. Attributes: `hero="yes"`, `library="/my-exercises/"`. |
| `[tbt_drag_exercises]` | The teacher's own exercise list. Attributes: `hero="no"`, `generator="/build-an-exercise/"`. |

`[dd_exercise]` is unchanged from 1.0.0, so every lesson page that already
embeds an exercise keeps working.

URL attributes accept an absolute URL or a site-root-relative path. A bare word
is rejected rather than promoted to `http://word`.

## The two Divi pages to create

The tools are shortcodes, so they need pages to live on. Two pages, both behind
whatever membership gate the site uses:

1. **Build an exercise** — put `[tbt_drag_generator library="/my-exercises/"]`
   on it.
2. **My exercises** — put `[tbt_drag_exercises generator="/build-an-exercise/"]`
   on it.

Both can share one page instead; give it just `[tbt_drag_generator]` and
`[tbt_drag_exercises]` with no attributes and each will default to the current
page.

The first time the generator renders, its page URL is recorded in the
`tbtdd_tool_page_url` option. That is what the TBT Hub card and the wp-admin
"Edit on the front end" row link point at. To move the tool to a different page,
delete that option and load the new page once.

## Access

One capability, shared across the TBT Teaching Tools:

- `tbt_use_teaching_tools` — granted on activation to `administrator` and to
  every role listed in TBT Swipe's `tbt_swipe_manager_roles` option.
- `tbts_manage` (Swipe's own) and `manage_options` are honoured too, so a
  teacher who can reach Swipe reaches this tool with nothing to configure.

Ownership: a teacher may edit and delete their own exercises. An administrator
may edit any. The library only ever lists the current teacher's own work.

Filters: `tbt_drag_drop_can_use_tools`, `tbt_drag_drop_tool_roles`,
`tbt_drag_drop_upsell_html`, `tbt_drag_drop_hero`,
`tbt_drag_drop_generator_url`.

## Stored data

Four post meta keys on the `dd_exercise` post type. All writes go through
`Exercise_Repository`; nothing else touches them.

| Key | Type | Notes |
|---|---|---|
| `_dd_gap_text` | `string` | The exercise text. Unchanged since 1.0.0. |
| `_dd_gap_items` | `string[]` | Gap texts in reading order, max 7, no duplicates (case-insensitive). Unchanged since 1.0.0. |
| `_dd_gap_offsets` | `int[]` | Byte offset in `_dd_gap_text` where each gap begins, index-aligned with `_dd_gap_items`. Written by the front end only. |
| `_dd_gap_instructions` | `string` | Optional player support line; falls back to a default when empty. |

`_dd_gap_offsets` is what lets a gap made from the *second* occurrence of a
repeated word gap that occurrence. The renderer uses an offset only when
`substr($text, $offset, strlen($item))` still equals the item, and otherwise
falls back to first-occurrence matching, which is what the wp-admin meta box has
always produced. An exercise never fails because its offsets are missing or
stale.

The meta box cannot express which occurrence a gap means, so it never writes
offsets, and it clears them when a save changes the text or the item list rather
than leaving positions that describe a document that no longer exists.

## REST API

Namespace `tbt-drag-drop/v1`. Every route requires the teaching-tools
capability; the single-exercise routes also require ownership, re-checked inside
each write callback.

| Route | Methods |
|---|---|
| `/exercises` | `GET` (own exercises, paginated), `POST` (create a draft) |
| `/exercises/<id>` | `GET`, `PUT`, `DELETE` (trash, never permanent) |
| `/exercises/<id>/duplicate` | `POST` |

`PUT` with `status: publish` runs full validation; anything else sanitises
without enforcing completeness, so saving early never loses a teacher's work.

## Design tokens

`assets/vendor/tbt/tbt-tokens.css` is a byte-identical copy of TBT-Hub's
canonical token file. It is registered under the shared `tbt-tokens` handle, and
only when Hub has not already registered it, so a Hub activation replaces this
copy wholesale. Never edit a value in it and never rename the handle — see
`assets/vendor/tbt/README.txt`.

No stylesheet in this plugin defines a colour of its own.

## Deployment

A push to `main` runs `.github/workflows/deploy.yml`, which uploads over FTPS to
`/drag-drop-exercises/`. Markdown files and `.github/` are excluded from the
upload, and a documentation-only push skips the workflow entirely.

The action uploads but never deletes, which is why two names are fixed:

- **`drag-drop-exercises.php`** must keep its name. A renamed main file would sit
  on the server next to the old one and WordPress would show two plugins.
- **`/drag-drop-exercises/`** must stay the server folder, for the same reason.

A preflight step refuses to deploy unless `drag-drop-exercises.php` is already in
the target folder, because the FTP client silently creates any directory it
cannot find and would otherwise report success while the live site never changed.

## After deploying 2.0.0

Rewrite rules are flushed once when the stored `tbtdd_rewrite_version` option
does not match the plugin version, so `/drag-and-drop/<slug>/` should work
immediately. If it 404s, re-save Settings → Permalinks once.
