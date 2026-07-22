# Elementor Style Controls — Design

## Problem

The site currently relies on hand-written custom CSS to get the look the
plugin's widgets need:

```css
.filter-forge{ color: white; }
.filter-forge button { border-radius: 8px; padding: 4px 16px; border-color: #B4453F; color: #B4453F; }
.filter-forge button:hover{ background-color: #B4453F; }
.ff-reset{ color: #B4453F; }
.ff-price__clear{ margin-top: 12px; }
.ff-filter__header{ padding-bottom: 4px; border-bottom: 2px solid #B4453F; }
```

`.filter-forge` is not a class the plugin emits anywhere — it's a class the
user is manually adding to a wrapping Elementor element to scope these rules.
The goal is for every one of these rules to instead be configurable from each
widget's own Elementor **Style** tab, so the site no longer needs any custom
CSS to achieve this look (or variations on it).

This is one of two related efforts (the other — replacing native `<select>`
dropdowns with a custom, stylable, keyboard-accessible component — is a
separate design/plan). This document covers styling controls only; no query,
filtering, or markup *structure* behavior changes.

## Goals

- Every property set by the custom CSS above becomes a native Elementor Style
  tab control on the relevant widget(s), with no functional change to
  filtering behavior.
- Since a full Style tab is being introduced, go a reasonable amount broader
  than a strict 1:1 mapping: add Typography controls, a Hover state (not just
  hover background), and Box Shadow for buttons — the kind of controls users
  expect from any polished Elementor widget's Style tab.
- Existing style-ish controls that currently live in the Content tab (per
  Elementor convention, colors/typography belong in Style) get relocated into
  the new Style tab for consistency. Because Elementor persists control values
  by control ID (not by which tab/section registers them), moving a control's
  section is purely a UI reorg — saved values on existing pages are
  unaffected.
- No new inline-style PHP for anything that is *not* per-item/data-dependent.
  Static, page-wide style choices (text color, button appearance, header
  divider, reset link color) should be expressed with Elementor's native
  `selectors` mechanism (plus `Group_Control_Typography` /
  `Group_Control_Border` / `Group_Control_Box_Shadow`) so Elementor's own CSS
  pipeline (responsive breakpoints, hover states, caching) handles it — not
  hand-rolled `style="..."` strings.

## Non-goals

- No changes to `FF_Filter_State`, query classes, providers, or count logic.
- No changes to the *rendering logic* of controls that are relocated
  (per-option icon colors, per-bucket colors, slider CSS custom properties)
  — only which Style/Content section registers them moves. Their inline
  per-item styling stays exactly as implemented today, since that logic is
  inherently data-dependent (which option/bucket is currently active) and
  isn't expressible as a single static CSS rule.
- No automated tests. Per `CLAUDE.md`, Elementor widget classes
  (`includes/widgets/`) are not unit-tested — `Widget_Base` needs the
  editor's document/controls-stack context, which `WP_UnitTestCase` doesn't
  provide. Verification is manual: build the plugin zip, reload the dev site,
  add each widget in the Elementor editor, and confirm the Style tab renders
  and the frontend CSS matches. The user will do this manually and report
  back if anything needs a follow-up fix.
- No new PHP classes or files. All changes are confined to
  `includes/widgets/class-widget-base.php`, `class-widget-filter.php`,
  `class-widget-price.php`, and `class-widget-reset.php`.

## Design

### Shared controls in `FF_Widget_Base`

Three new protected methods, following the existing pattern of
`register_header_controls()` / `register_relationship_controls()`:

**`register_text_style_controls()`** — new Style-tab section "Text":
- Text Color (`selectors: '{{WRAPPER}}' => 'color: {{VALUE}};'`)
- Typography (`Group_Control_Typography`, selector `{{WRAPPER}}`)

Applying to `{{WRAPPER}}` (the widget's own root element) rather than to
specific inner selectors reproduces the cascading behavior of
`.filter-forge { color: white }` — all descendant text (labels, option text,
counts) inherits unless something more specific overrides it.

**`register_button_style_controls()`** — new Style-tab section "Buttons",
using `start_controls_tabs()` for Normal/Hover states (Elementor's standard
pattern):
- Normal: Text Color, Background Color, Border (`Group_Control_Border`),
  Border Radius (dimensions control, linked corners by default), Padding
  (dimensions control), Box Shadow (`Group_Control_Box_Shadow`)
- Hover: Text Color, Background Color, Border Color, Box Shadow

Selector `{{WRAPPER}} button` — matches every real `<button>` element a
widget renders (Filter's Clear button; Price's Apply and Clear buttons) with
one control group, same as `.filter-forge button` does today.

**`register_header_style_controls()`** — new Style-tab section "Header":
- Typography (selector `{{WRAPPER}} .ff-filter__header`)
- Padding (dimensions control, same selector)
- Border Bottom Width (slider, px), Border Bottom Style (select:
  solid/dashed/dotted/none), Border Bottom Color — three explicit controls
  rather than the generic 4-side `Group_Control_Border`, since the source CSS
  is specifically a bottom divider and forcing users to zero out three sides
  of a generic border group would be needlessly indirect.

All three are called from both `FF_Widget_Filter` and `FF_Widget_Price`
(both already call the Content-tab equivalents). `FF_Widget_Reset` calls
none of them — see below.

### `FF_Widget_Filter`

New Style tab, in order:
1. Text (from base)
2. Buttons (from base) — styles the Clear button
3. Header (from base)
4. Option Icons — existing `ff_option_icon_active_color` /
   `ff_option_icon_inactive_color` controls, moved here unchanged (same
   control IDs, same conditions on `ff_display_style`). Rendering logic in
   `render_list()` is untouched.

### `FF_Widget_Price`

New Style tab, in order:
1. Text (from base) — covers bucket labels, min/max input labels, slider
   value display text
2. Buttons (from base) — styles both the Apply button and the Clear button
3. Clear Button Spacing — one new control, margin-top (slider, px),
   `selectors: '{{WRAPPER}} .ff-price__clear' => 'margin-top: {{SIZE}}{{UNIT}};'`.
   Kept separate from the shared Buttons section since it applies only to
   the Clear button, not Apply.
4. Header (from base)
5. Bucket Colors — existing `ff_bucket_active_color` /
   `ff_bucket_inactive_color`, moved here unchanged.
6. Slider Colors — existing `ff_slider_track_color` / `ff_slider_range_color`
   / `ff_slider_handle_color`, moved here unchanged (still written as inline
   CSS custom properties on the slider wrapper, per current
   `render_slider_range()` logic).

### `FF_Widget_Reset`

`FF_Widget_Reset` extends `\Elementor\Widget_Base` directly today (not
`FF_Widget_Base`) and has no header/relationship controls, so it gets one
small, self-contained new section rather than reusing the base-class
methods:

New Style tab, section "Style":
- Text Color (selector `{{WRAPPER}} .ff-reset`)
- Hover Color (selector `{{WRAPPER}} .ff-reset:hover`)
- Typography (`Group_Control_Typography`, selector `{{WRAPPER}} .ff-reset`)
- Text Decoration (select: none/underline/overline/line-through, selector
  `{{WRAPPER}} .ff-reset`)

### Defaults

Every *new* control (Text, Buttons, Header, Reset's Style section, Clear
Button Spacing) defaults to blank/unset. Elementor only prints a selector's
CSS when the control has a value, so until a user picks a value the existing
hardcoded look in `assets/css/ff-filters.css` remains exactly as it is today
— nothing regresses for existing pages that don't touch the new controls.

Controls being *relocated* (icon colors, bucket colors, slider colors) keep
their current defaults exactly as-is (e.g. `ff_bucket_active_color` stays
defaulted to `#2271b1`) — only their section/tab placement changes.

## Testing / Verification

No automated tests (see Non-goals). Manual verification checklist, to be run
by the user after the zip is rebuilt and the dev site reloaded:

1. Open each of the three widgets (Filter, Price, Reset) in the Elementor
   editor; confirm a Style tab now exists with the sections described above.
2. Confirm previously-set values for relocated controls (icon colors, bucket
   colors, slider colors, if any test page has them set) still show correctly
   in their new location.
3. Set Text Color, Button styles (normal + hover), Header divider, and Reset
   link color/hover on a live archive page; confirm the frontend matches,
   without the `.filter-forge` custom CSS enabled.
4. Confirm pages that have **not** touched the new controls render
   identically to before (no regression from leaving controls unset).
