# Custom Dropdown Component — Design

## Problem

Two places in the plugin render a native `<select>` for a filter control:

- `FF_Widget_Filter::render_dropdown()` — the Filter widget's "Dropdown"
  display style.
- `FF_Widget_Price::render_buckets_dropdown()` — the Price widget's "Dropdown"
  bucket display style.

Native `<select>` elements can't be meaningfully restyled with CSS — the
closed control (padding, border, arrow) can be nudged a little, but the open
popup is rendered entirely by the browser/OS and ignores CSS almost
completely. This is the second of two related efforts (the first — Elementor
Style tab controls replacing hand-written `.filter-forge` CSS — is a separate,
already-completed design/plan at
`docs/superpowers/specs/2026-07-22-elementor-style-controls-design.md`). This
document covers only replacing these two `<select>` elements with a custom,
fully stylable, keyboard-accessible component.

Other native inputs in the plugin (plain checkbox/radio lists, the price
min/max number inputs, the price range slider) are explicitly **out of
scope** for this effort — see Non-goals.

## Goals

- Replace both `<select>` dropdowns' *visual* presentation with a custom
  trigger-button + popup-listbox component, fully stylable via new Elementor
  Style tab controls (colors, borders, radius, padding, typography, box
  shadow, hover/selected states).
- Full keyboard operability equivalent to native `<select>`: open/close,
  arrow-key navigation, Home/End, Enter/Space to commit, Escape to cancel,
  type-ahead-to-jump.
- No regression if JavaScript fails to load: the real `<select>` must remain
  present, unhidden, and fully functional until the enhancement script
  successfully runs.
- One shared, reusable component for both dropdown instances — not two
  separate implementations.
- No new build step. Plain vanilla JS, consistent with the existing
  `assets/js/ff-filters.js` / `assets/js/ff-url.js`.

## Non-goals

- **No changes to any other input type.** Checkbox/radio lists (Filter
  widget's "Checkbox list"/"Radio" display styles), the swatch/toggle/icon
  display styles (already custom-skinned via existing CSS), the price
  min/max number inputs, and the price range slider are unchanged. The user
  explicitly scoped this effort to the `<select>` dropdowns only.
- **No searchable/filterable combobox.** A plain listbox with type-ahead
  (jump-to-match, not filter-the-list) was chosen over a visible search input
  — lighter to build, and matches native `<select>` keyboard behavior exactly.
- **No mobile/touch fallback to the native picker.** The custom dropdown
  applies uniformly at every screen size; there is no touch/small-screen
  branch that leaves the native `<select>` visible instead.
- **No PHP/render changes.** `render_dropdown()` and
  `render_buckets_dropdown()` are untouched — this is a pure client-side
  enhancement layer over markup that already exists. Neither method's output
  changes in any way.
- **No changes to `ff-filters.js`'s existing filtering/URL-navigation logic.**
  The new component integrates by mutating the existing (now hidden) native
  `<select>`'s value and dispatching a real `change` event on it — the
  existing `change` listener in `ff-filters.js` (which already special-cases
  `.ff-price--buckets-dropdown` for its `min|max` value format) requires zero
  modification.
- **No automated tests.** Same reasoning as the Style-tab-controls effort:
  Elementor widgets aren't unit-tested (`Widget_Base` needs editor context),
  and this plugin has no JS test runner. Verification is manual.

## Design

### Component: `assets/js/ff-dropdown.js`

A new vanilla JS file, enqueued in `class-plugin.php` alongside the existing
scripts:

```php
wp_enqueue_script( 'ff-dropdown', FF_PLUGIN_URL . 'assets/js/ff-dropdown.js', array(), FF_VERSION, true );
```

No dependency on `ff-url`/`ff-filters` — it only ever mutates a `<select>`'s
`.value` and dispatches `change`; it never touches `window.location` or
`URLSearchParams` itself.

On `DOMContentLoaded`, it queries
`select.ff-filter--dropdown, select.ff-price--buckets-dropdown` and enhances
each match found. "Enhance" means, for a given `<select>`:

1. Wrap it in a new `<div class="ff-dropdown">` container (inserted via
   `insertAdjacentElement`, moving the select inside).
2. Add class `ff-dropdown__native` to the select (this is what
   `ff-filters.css` uses to visually hide it — see CSS section below) and
   `aria-hidden="true"` (the custom trigger/panel become the accessible
   interface instead).
3. Build a `<button type="button" class="ff-dropdown__trigger">` whose text
   content mirrors the select's currently-selected `<option>`'s
   `textContent` (so it shows e.g. "Blue (5)" exactly as the native select
   would have).
4. Build a `<ul class="ff-dropdown__panel" role="listbox" tabindex="-1"
   hidden>` with one `<li role="option" class="ff-dropdown__option"
   data-value="...">` per `<option>` in the select, in the same order,
   copying each option's `textContent` and `value`. The `<li>` matching the
   select's current value gets `aria-selected="true"`.
5. Assign a unique id prefix per enhanced instance (e.g.
   `ff-dropdown-{n}-option-{i}`) so multiple dropdown widgets on one page
   don't collide on ARIA ids.

If the enhancement script never runs (JS disabled, script blocked, error
before this point), step 2 never happens, so the native `<select>` is never
hidden and remains fully usable exactly as it renders today.

### Interaction / keyboard behavior

Follows the ARIA "collapsible listbox" pattern — focus stays on the trigger
button or on the listbox (`<ul>`) element itself; individual `<li>` options
are never focused directly. The active option is tracked via
`aria-activedescendant` on the listbox pointing at the active `<li>`'s id.

**Trigger button, closed:**
- Click, `Enter`, `Space`, `ArrowDown`, or `ArrowUp` opens the panel. The
  active option starts at whichever option is currently selected (or the
  first option, if none is meaningfully selected — the "All" placeholder is
  always option 0). Sets `aria-expanded="true"` on the trigger and removes
  `hidden` from the panel; focus moves to the panel.

**Panel, open (listbox has focus):**
- `ArrowDown` / `ArrowUp` — move the active option by one, clamped at the
  last/first option (no wraparound, matching native `<select>`).
- `Home` / `End` — jump active to the first/last option.
- `Enter` / `Space` — commit: set `select.value` to the active option's
  value, dispatch a `new Event('change', { bubbles: true })` on the select,
  update `aria-selected` on the options, update the trigger's text to the
  committed option's label, close the panel (`hidden` back on the `<ul>`,
  `aria-expanded="false"`), return focus to the trigger.
- `Escape` — close the panel without changing anything, return focus to the
  trigger.
- `Tab` — close the panel without changing anything, let the browser's
  default Tab focus movement proceed.
- Typing a printable character — type-ahead: characters typed within ~600ms
  of each other accumulate into a search string; the active option jumps to
  the next option (searching forward from just after the current active
  option, wrapping around to the start if nothing matches after it) whose
  label starts with that string, case-insensitive. This only moves the
  highlight — `Enter`/click still commits it, exactly like native `<select>`
  behavior when its own popup is open.
- Clicking an `<li>` option directly commits it immediately (same as
  `Enter`) and closes the panel.
- Clicking anywhere outside the open panel (a document-level `click`
  listener checking `event.target.closest('.ff-dropdown')`) closes it without
  changing the selection.

### Styling controls

New shared method on `FF_Widget_Base`:

```php
protected function register_dropdown_style_controls( array $condition ): void
```

Registers a "Dropdown" Style-tab section, scoped by the `$condition` the
caller passes in (so the section only shows when a dropdown display style is
actually selected):

- **Trigger**: Text Color, Background Color, `Group_Control_Border`, Border
  Radius (dimensions), Padding (dimensions), `Group_Control_Typography`,
  Focus Outline Color (selector `{{WRAPPER}} .ff-dropdown__trigger:focus`).
  Selector base: `{{WRAPPER}} .ff-dropdown__trigger`.
- **Panel**: Background Color, `Group_Control_Border`, Border Radius,
  `Group_Control_Box_Shadow`, Max Height (slider, px, with the panel's
  `overflow-y: auto` already handling scroll — this control just caps it).
  Selector base: `{{WRAPPER}} .ff-dropdown__panel`.
- **Option**: Padding, `Group_Control_Typography`, Hover Background Color
  (selector `{{WRAPPER}} .ff-dropdown__option:hover`), Hover Text Color,
  Selected Background Color (selector
  `{{WRAPPER}} .ff-dropdown__option[aria-selected="true"]`), Selected Text
  Color.

Every control follows the same "blank/unset by default" rule established in
the Style-tab-controls effort — nothing changes for pages that don't touch
these controls until the structural CSS (below) is in place, at which point
the *unstyled* look becomes whatever base appearance
`assets/css/ff-filters.css` gives the new classes (a plain, minimal look, not
zero styling — a completely unstyled trigger/panel would look broken, unlike
the Style-tab-controls effort where "unset" meant "identical to the current
production CSS").

Callers:
- `FF_Widget_Filter::register_controls()` calls
  `register_dropdown_style_controls( array( 'ff_display_style' => 'dropdown' ) )`.
- `FF_Widget_Price::register_controls()` calls
  `register_dropdown_style_controls( array( 'ff_price_bucket_style' => 'dropdown' ) )`.

### Structural CSS (`assets/css/ff-filters.css`)

Additions (non-configurable, baseline structural rules — analogous to the
existing `.ff-filter--swatch`/`.ff-filter--toggle` rules already in this
file):

- `.ff-dropdown` — `position: relative`, so the panel can be positioned
  against it.
- `.ff-dropdown__native` — visually hidden the same way
  `.ff-filter__input--icon-mode` already is in this file (`position:
  absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden`) — not
  `display: none`, so it stays a valid, focusable-if-needed form control in
  the DOM.
- `.ff-dropdown__trigger` — base button-like layout (full width, flex for
  label + caret, cursor pointer), plus a CSS-drawn caret/arrow indicator.
- `.ff-dropdown__panel` — `position: absolute`, `z-index`, `overflow-y:
  auto`, base list reset (no default `<ul>` bullets/margin).
- `.ff-dropdown__option` — base padding/cursor so it's usable before any
  Style tab control is set.

## Testing / Verification

No automated tests (see Non-goals). Manual verification checklist, run by
the user after the zip is rebuilt and the dev site reloaded:

1. Load a page with the Filter widget in Dropdown mode and the Price widget
   in Buckets/Dropdown mode; confirm both render the custom trigger + panel
   instead of a native `<select>` popup.
2. Mouse: click to open, click an option to select and close, click outside
   to close without changing.
3. Keyboard only: Tab to the trigger, open with Enter/Space/Arrow, navigate
   with Arrow/Home/End, type a letter to jump, Enter to commit, Escape to
   cancel, Tab away to close.
4. Confirm selecting an option actually navigates (URL gets the right
   `ff_tax_*`/`category`/`min_price`/`max_price` param) — i.e. the existing
   `ff-filters.js` change-event pipeline still fires correctly.
5. Set the new Dropdown Style tab controls (trigger, panel, option
   normal/hover/selected) on both widgets and confirm the frontend reflects
   them.
6. Disable JavaScript (or block the script) and confirm the native
   `<select>` is still visible and fully functional as a fallback.
