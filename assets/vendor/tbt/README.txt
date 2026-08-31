TBT canonical design tokens — vendored fallback copy
====================================================

WHAT THIS IS
------------
tbt-tokens.css in this directory is a byte-identical copy of TBT-Hub's
/assets/css/tbt-tokens.css (currently v2.1). TBT-Hub owns the file; this copy
exists only so the plugin still renders when Hub is deactivated.

Both tbtdd-game and tbtdd-tools declare 'tbt-tokens' as a hard stylesheet
dependency. Assets::ensure_shared_styles() registers this copy under that same
handle, and only when Hub has not already registered it. Matching the handle is
the whole point: a later Hub activation replaces this copy wholesale, and no
page can ever load two copies of the vocabulary under two different handles.

DRIFT CHECK
-----------
Because this copy is meant to be byte-identical, diff is the entire test:

    diff assets/vendor/tbt/tbt-tokens.css \
         ../TBT-Hub/assets/css/tbt-tokens.css

Any output means the copy has drifted and must be resynced from Hub, not
edited here. Never change a token value in this file — values are owned by the
Style Spec and changing one here would desynchronise this plugin silently,
which is exactly the failure the vendoring is meant to prevent.

Resyncing does not require a TBTDD_VERSION bump: the cache-buster for this file
is its own modification time, via Assets::asset_version().

WHY A :ROOT DEFAULT INSIDE A CASCADE LAYER
------------------------------------------
The tokens are declared as :root custom properties inside
@layer tbt-defaults, rather than each consumer writing a self-referencing
fallback such as var(--tbt-blue, #0856C9).

Fallbacks were rejected because they defeat the purpose. A fallback makes a
missing token sheet invisible — the page renders correctly-ish and the broken
dependency is never noticed, while every consuming file quietly grows a second,
unmanaged copy of the palette. That is the near-duplicate-colour problem the
Style Spec forbids, reintroduced one var() at a time.

The cascade layer solves the other half. Layered declarations lose to unlayered
ones regardless of specificity, so a page that genuinely needs to retheme (for
example by setting --tbt-domain) can override a token with a plain :root block
and win, without resorting to !important. Components are deliberately NOT
layered — see Hub's tbt-components.css.

DO NOT
------
- Do not edit any value in tbt-tokens.css. Resync it from Hub instead.
- Do not rename the 'tbt-tokens' handle or point it at a differently-named file.
- Do not add this directory to .gitignore. An unanchored `vendor/` pattern once
  matched assets/vendor/ and silently dropped this file from the repository,
  leaving ensure_shared_styles() registering a URL that 404s whenever Hub was
  inactive. The ignore rule is anchored to /vendor/ to keep that from recurring.
