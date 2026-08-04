# Filter Commit Mode (Instant vs. Submit) — Design Spec

**Date:** 2026-08-04
**Status:** Approved for planning

## 1. Purpose

Today every filter-committing widget navigates the page on every change: a checkbox
click, a radio selection, a dropdown change, an Orderby change, or (via the existing
Price widget Apply button / Enter key) a price range edit. This adds an opt-in,
per-widget-instance **Commit Mode** control — **Instant** (today's behavior, default)
or **Submit** — to the Filter, Price, and Orderby widgets, plus a new global
**Apply Filters** widget (`FF_Widget_Apply`) that commits whatever is currently staged,
from anywhere on the page.

## 2. Architecture — stage into the URL, commit is just a real navigation

No new client-side state store. The plugin already treats the URL query string as the
single source of truth (`FF_Filter_State` reads only `$_GET`); Submit mode extends that
by staging in-progress edits into the *browser's* URL via `history.replaceState`
before the server ever sees them, and "commit" is simply a real navigation to
whatever's currently staged.

`assets/js/ff-url.js` gains two functions alongside the existing `get`/`set`/`remove`/
`navigate`:

```js
function stage( params ) {
    const query = params.toString();
    window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) );
}

function commit() {
    navigate( new URLSearchParams( window.location.search ) );
}
```

`commit()` deliberately delegates to the existing `navigate()` (a real
`window.location.href` assignment), **not** `window.location.reload()` — reload
re-fetches the current history entry in place, while `navigate()` pushes a new entry,
consistent with how every Instant-mode change already behaves (the back button steps
through prior filter states one at a time). Using `reload()` here would make Submit-mode
commits behave differently from Instant-mode commits for no reason.

Every existing `FFUrl.navigate( params )` call in `ff-filters.js` that's triggered by a
user editing a filter (not by clicking Clear/Reset/Apply) becomes conditional on the
nearest ancestor's `data-ff-commit-mode` attribute:

```js
const mode = target.closest( '[data-ff-commit-mode]' )?.getAttribute( 'data-ff-commit-mode' );
if ( 'submit' === mode ) {
    FFUrl.stage( params );
} else {
    FFUrl.navigate( params );
}
```

**Explicitly accepted trade-off (confirmed during design):** because staging lives in
the real address bar, (a) any commit — a local submit button, the global Apply widget,
or even an Instant-mode widget elsewhere on the page triggering its own navigation —
applies *everything* currently staged page-wide, not just the widget that was
interacted with; and (b) a manual browser refresh mid-edit (before clicking Apply)
silently applies whatever's staged, since by then it *is* the real URL. Both are
accepted as reasonable given the alternative (an in-memory JS store) would duplicate
the URL-as-truth model this plugin uses everywhere else.

## 3. Shared "Commit Mode" control (`FF_Widget_Base`)

New `register_commit_mode_controls()` method, same pattern as the existing
`register_relationship_controls()`, added to a `Behavior` content section:

```php
$this->add_control(
    'ff_commit_mode',
    array(
        'label'        => __( 'Commit Mode', 'filter-forge' ),
        'type'         => \Elementor\Controls_Manager::SWITCHER,
        'label_on'     => __( 'On Submit', 'filter-forge' ),
        'label_off'    => __( 'Instant', 'filter-forge' ),
        'return_value' => 'submit',
        'default'      => '',
    )
);

$this->add_control(
    'ff_submit_label',
    array(
        'label'     => __( 'Submit Button Label', 'filter-forge' ),
        'type'      => \Elementor\Controls_Manager::TEXT,
        'default'   => __( 'Apply', 'filter-forge' ),
        'condition' => array( 'ff_commit_mode' => 'submit' ),
    )
);
```

Default is Instant (empty string, not `'submit'`) so every existing page keeps its
current behavior unchanged after upgrade. A small `protected function commit_mode(): string`
helper reads `$settings['ff_commit_mode']` and returns `'instant'` or `'submit'`, used
by all three widgets when building their `data-ff-commit-mode` attribute.

Local submit buttons render as plain `<button type="button">` elements carrying a
widget-specific class (`ff-filter__submit`, `ff-orderby__submit`) that all click-bind to
`FFUrl.commit()` — no per-button logic needed, since commit is page-wide. They
automatically pick up the existing `register_button_style_controls()` styling every
widget already exposes; no new style controls are needed for them. The Price widget's
existing `.ff-price__apply` button is reused as-is (see §4.3) rather than adding a
second button class.

## 4. Per-widget changes

### 4.1 Filter widget (`FF_Widget_Filter`)

- `register_commit_mode_controls()` added to `register_controls()`.
- `$wrapper_attrs` (already built in `render()`) gains
  `data-ff-commit-mode="instant|submit"`.
- In Submit mode, a `<button type="button" class="ff-filter__submit">{label}</button>`
  renders after the list/dropdown markup, unconditionally (always visible in Submit
  mode — no JS-driven show/hide, no "only show if something changed" logic).
- Applies uniformly to every display style (checkbox, radio, dropdown, swatch, toggle)
  since they all funnel through the same generic `change` handler in `ff-filters.js`.

### 4.2 Orderby widget (`FF_Widget_Orderby`)

- `register_commit_mode_controls()` added to `register_controls()`.
- The bare `<select class="ff-orderby ff-orderby--dropdown" data-ff-param="orderby">`
  gains `data-ff-commit-mode` directly (it has no wrapper element today).
- In Submit mode, a sibling `<button type="button" class="ff-orderby__submit">`
  renders after the `<select>`.

### 4.3 Price widget (`FF_Widget_Price`)

`register_commit_mode_controls()` added once; all three modes read `commit_mode()`,
each with different mechanics since each mode already has different existing behavior:

- **Input / Slider mode:** the wrapper (`.ff-price--input` / `.ff-price--slider`) gains
  `data-ff-commit-mode`. The existing `.ff-price__apply` button and Enter-key handling
  in `ff-filters.js` (`ffApplyPriceRange`) are **unchanged** and stay visible/active in
  Submit mode — they already are this feature. In **Instant mode**, the Apply button is
  not rendered, and a native `change` listener is added on `.ff-price__range` /
  `.ff-price__input` (fires on slider release / number-input blur — not on every drag
  pixel or keystroke) that calls the same `ffApplyPriceRange` path, now routed through
  `FFUrl.navigate` directly since Instant mode never stages.
- **Buckets (list) mode:** currently plain `<a href="...">` links with zero JS — the
  only commit-instantly-by-default piece of the whole plugin that isn't already
  JS-driven. The `<ul>` wrapper gains `data-ff-commit-mode`. Each `<a>` gains
  `data-ff-price-min` / `data-ff-price-max` attributes (mirroring what's already
  encoded in `href`). Instant mode: unchanged, real link, zero JS involved. Submit
  mode: a new click handler in `ff-filters.js` intercepts clicks on
  `.ff-price__bucket` inside a `data-ff-commit-mode="submit"` wrapper, calls
  `preventDefault()`, stages `min_price`/`max_price` via `FFUrl.stage()`, and toggles
  `.ff-price__bucket--active` client-side (since there's no reload to reflect the
  server-rendered active state until commit).
- **Buckets (dropdown) mode:** the existing dedicated `change` handler for
  `.ff-price--buckets-dropdown` (today always calls `FFUrl.navigate`) branches on
  `data-ff-commit-mode` on the `<select>` the same way the generic handler does.

## 5. Global "Apply Filters" widget (`FF_Widget_Apply`)

New `includes/widgets/class-widget-apply.php`, registered in `class-plugin.php`
alongside the other three widgets. Modeled directly on `FF_Widget_Reset`'s simplicity:

- `get_name()`: `ff-apply`. `get_title()`: `"Apply Filters - Forge"`. `get_icon()`:
  `ff-icon-anvil` (shared icon convention, per the other three widgets).
- Content control: `ff_apply_label` (TEXT, default `"Apply Filters"`).
- Style controls: `register_button_style_controls()` (it renders a `<button>`, unlike
  Reset's `<a>`, so it reuses the shared button styling rather than Reset's
  link-specific color/typography/decoration controls).
- `render()`: unconditional — `<button type="button" class="ff-apply">{label}</button>`.
  No check for "is anything staged," no disabled state. Clicking it always calls
  `FFUrl.commit()`; if nothing was staged anywhere on the page, this is a harmless
  no-op reload of the current URL.
- Not gated by `is_supported_archive()` the way Filter/Price/Orderby are — like Reset,
  it has no query-dependent content to suppress.

## 6. CSS

`assets/css/ff-filters.css` additions:

```css
.ff-filter__submit,
.ff-orderby__submit,
.ff-apply {
    margin-top: 0.5em;
}
```

No other new rules — these buttons inherit whatever the theme/Elementor button style
controls apply, same as the existing `.ff-price__apply` button today.

## 7. Files touched

- **New:** `includes/widgets/class-widget-apply.php` (`FF_Widget_Apply`).
- `includes/class-plugin.php`: `require_once` the new widget file, register it in
  `register_widgets()`.
- `includes/widgets/class-widget-base.php`: add `register_commit_mode_controls()` and
  `commit_mode()`.
- `includes/widgets/class-widget-filter.php`, `class-widget-orderby.php`,
  `class-widget-price.php`: add the commit-mode control, `data-ff-commit-mode`
  attribute, and (Filter/Orderby) local submit button markup.
- `assets/js/ff-url.js`: add `stage()` and `commit()`.
- `assets/js/ff-filters.js`: branch existing `navigate()` calls on
  `data-ff-commit-mode`; add the Price-buckets-list click interceptor (§4.3); add
  Instant-mode `change` listeners for Price Input/Slider (§4.3); bind
  `.ff-filter__submit` / `.ff-orderby__submit` / `.ff-apply` clicks to `FFUrl.commit()`.
- `assets/css/ff-filters.css`: §6.

## 8. Testing

No PHPUnit test file for `FF_Widget_Apply` or the commit-mode additions to the other
three widgets — per `CLAUDE.md`, Elementor widget classes aren't unit tested (no
editor/document context in `WP_UnitTestCase`). Verified manually against the running
wp-env site:

- Every Filter widget display style (checkbox, radio, dropdown, swatch, toggle), left
  in Instant mode, behaves exactly as before (regression check).
- Same widgets switched to Submit mode: clicking/checking an option updates the address
  bar (visible via dev tools or the URL bar) but does **not** reload the page or
  refresh option counts; clicking the local submit button reloads with the staged
  param(s) applied.
- Orderby in Submit mode: selecting a sort option stages, doesn't re-sort until the
  local submit button is clicked.
- Price Input/Slider mode in Instant mode: dragging the slider handle doesn't fire
  repeatedly mid-drag, only commits on release; typing in the number input doesn't
  commit on every keystroke, only on blur/Enter.
- Price Buckets (list) mode in Submit mode: clicking a bucket link does not navigate,
  toggles the active-bucket styling locally, and stages `min_price`/`max_price`.
- Mixed-mode page (some widgets Instant, some Submit): confirms that triggering an
  Instant-mode widget's navigation also applies any changes staged by Submit-mode
  widgets elsewhere on the page (accepted page-wide-commit behavior, §2).
- The global Apply Filters widget, placed anywhere on the page, commits all currently
  staged changes regardless of which widget(s) staged them; clicking it with nothing
  staged is a harmless reload.
- Back-button behavior after a Submit-mode commit matches Instant-mode's existing
  behavior (steps back to the prior filter state, not past this page entirely) —
  confirms the `commit() → navigate()` (not `reload()`) choice in §2.
- Existing parent/child relationship behavior (`should_render()`,
  reset-on-parent-change) is unaffected in Instant mode; in Submit mode, a child
  widget's visibility change (revealed/hidden based on a parent's selection) is
  correctly deferred until commit, since visibility is server-rendered.

## 9. Out of scope (deferred, not this pass)

- No "unsaved/pending changes" visual indicator on local submit buttons or the global
  Apply widget.
- No `beforeunload` warning for staged-but-uncommitted changes.
- No change to the accepted trade-off that a manual page refresh mid-edit silently
  applies staged changes (§2) — an in-memory JS store was considered and explicitly
  rejected in favor of keeping the URL as the only state, per this plugin's existing
  architecture.
- No live-updating faceted option counts for staged-but-uncommitted Submit-mode
  selections — counts only reflect the last real commit, same limitation the existing
  Price Apply-button modes already have today.
