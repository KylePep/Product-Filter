# Price Widget — Real Slider Mode — Design Spec

**Date:** 2026-07-21
**Status:** Approved for planning

## 1. Purpose

`FF_Widget_Price`'s `ff_price_mode` control currently offers two values: `buckets`
(predefined ranges) and `slider`. Despite its label ("Slider (dynamic min/max)"), the
`slider` mode has never rendered an actual slider — it renders two `<input type="number">`
fields (min/max) with an Apply button. This spec splits that single mode into two:

- `input` — the existing min/max number-input behavior, unchanged except for its
  option key/label.
- `slider` — a new, real dual-handle range slider, with a customizable color for the
  active range between the two handles (plus track and handle colors).

`buckets` mode is untouched by this change.

## 2. Mode key change (breaking, deliberately)

The option value currently stored as `slider` (min/max inputs) is renamed to `input`.
The value `slider` is reassigned to the new real-slider UI. Any existing saved widget
instance with `ff_price_mode = slider` will therefore render the new slider control,
not the old input pair, the next time the page loads. This is accepted as fine: the
plugin is pre-release/dev-only, there are no production installs to preserve
compatibility for.

Updated control:
```php
'ff_price_mode' => array(
    'options' => array(
        'input'   => __( 'Min/Max Inputs', 'filter-forge' ),
        'slider'  => __( 'Slider', 'filter-forge' ),
        'buckets' => __( 'Predefined buckets', 'filter-forge' ),
    ),
),
```

## 3. Slider markup & interaction

Two overlapping native `<input type="range">` elements, sharing the same
`data-ff-price-role="min"` / `="max"` attributes the input-mode fields already use (so
existing Apply/URL-building JS logic can be reused rather than duplicated):

```html
<div class="ff-price ff-price--slider" style="--ff-slider-track:...; --ff-slider-range:...; --ff-slider-handle:...;">
  <div class="ff-price__slider-values">
    <span data-ff-slider-display="min">$25</span> – <span data-ff-slider-display="max">$180</span>
  </div>
  <div class="ff-price__slider-track">
    <div class="ff-price__slider-range"></div>
    <input type="range" class="ff-price__range" data-ff-price-role="min" min="0" max="500" step="1" value="25">
    <input type="range" class="ff-price__range" data-ff-price-role="max" min="0" max="500" step="1" value="180">
  </div>
  <button type="button" class="ff-price__apply">Apply</button>
</div>
```

- Bounds (`min`/`max` on both range inputs) come from the same `_price` postmeta
  MIN/MAX query the input-mode already runs. This query is extracted into a shared
  private helper, `get_price_bounds(): object`, called by both render paths so the SQL
  isn't duplicated.
- `step="1"` (whole currency unit) on both inputs — a deliberate simplification;
  sub-unit precision isn't needed for a drag slider.
- Initial values default to the current filter state (`min_price`/`max_price` from
  `FF_Filter_State`) same as input-mode today, falling back to the bounds when unset.

### Overlapping-input CSS trick

Both range inputs are stacked via `position: absolute` inside `.ff-price__slider-track`.
Each input's own native track is made invisible and `pointer-events: none`; only its
thumb (`::-webkit-slider-thumb`, `::-moz-range-thumb`) keeps `pointer-events: auto`.
This is what lets the user grab either handle without the invisible full-width track of
the *other* input intercepting the click.

### Live values + clamp (JS, `assets/js/ff-filters.js`)

An `input` event listener (added for `.ff-price__range` elements) does three things,
purely client-side — no navigation, no `FFUrl` calls:
1. If the min handle's value would exceed the max handle's (or vice versa), clamp it
   to the other handle's current value so the handles can never cross.
2. Update the live text readout (`[data-ff-slider-display="min"/"max"]`).
3. Reposition/resize `.ff-price__slider-range` (left % / width %) to visually span
   between the two handles' current values, relative to the track's min/max bounds.

Clicking **Apply** is the only action that calls `FFUrl.navigate()` — reusing the
existing `ffApplyPriceRange()` function, which already reads whichever elements match
`[data-ff-price-role="min"/"max"]` inside the closest wrapper, regardless of `<input>`
type. The wrapper-lookup selector (`applyBtn.closest('.ff-price--slider')` today)
becomes `applyBtn.closest('.ff-price--input, .ff-price--slider')` so Apply works from
either mode. Same broadening applies to the existing Enter-key handler (harmless no-op
on a range input, but kept consistent).

## 4. Color controls

Three new Elementor `COLOR` controls on `FF_Widget_Price`, shown only when
`ff_price_mode = slider`:

| Control | Default | CSS custom property |
|---|---|---|
| Track Color | `#dcdcde` | `--ff-slider-track` |
| Active Range Color | `#2271b1` | `--ff-slider-range` |
| Handle Color | `#ffffff` (with a border) | `--ff-slider-handle` |

These are set as inline custom properties on the `.ff-price--slider` wrapper div at
render time (same pattern as the existing bucket active/inactive colors, just as CSS
variables instead of inline `style="color:"` since three separate elements consume
them). `ff-filters.css` adds slider track/thumb/range rules that read these variables,
falling back to the defaults above if unset.

## 5. Files touched

- `includes/widgets/class-widget-price.php`:
  - `ff_price_mode` option keys/labels updated (§2).
  - New color controls (§4), conditioned on `ff_price_mode = slider`.
  - `render_slider()` renamed `render_min_max_inputs()` (no behavior change).
  - New `render_slider_range()` implementing §3.
  - Shared `get_price_bounds()` extracted from the existing inline bounds query,
    called by both render methods.
- `assets/js/ff-filters.js`:
  - New `input`-event listener for `.ff-price__range` handling clamp + live
    display + range-overlay positioning (§3).
  - Broaden the Apply-button and Enter-key wrapper selectors to match both
    `.ff-price--input` and `.ff-price--slider`.
- `assets/css/ff-filters.css`:
  - Rename existing `.ff-price--slider` rules (the min/max input layout) to
    `.ff-price--input`.
  - New rules for `.ff-price__slider-track`, `.ff-price__slider-range`,
    `.ff-price__range` (incl. the overlapping-input pointer-events trick and
    webkit/moz thumb styling), consuming the custom properties from §4.

## 6. Testing

No PHPUnit changes. Per `CLAUDE.md`, Elementor widget classes are not unit tested —
`\Elementor\Widget_Base` needs editor context that `WP_UnitTestCase` doesn't provide.
This is verified manually against the running wp-env site:

- Slider renders with correct bounds on a WooCommerce archive; dragging either handle
  updates the live text and colored range visually without navigating.
- Handles cannot be dragged past each other.
- Clicking Apply navigates with the correct `min_price`/`max_price` query args.
- The three color controls visibly change track/range/handle color.
- Switching mode back to `input` in the editor still renders the original min/max
  inputs unchanged.
- `buckets` mode is unaffected.

## 7. Out of scope

- Sub-unit (cents) precision on the slider — whole-currency-unit steps only (§3).
- Preserving old `slider`-mode-as-min/max-inputs behavior for any existing saved
  widget instance (§2 — accepted breaking change, pre-release).
- Live navigation while dragging (Apply-button click remains required, per the
  approved design discussion).
