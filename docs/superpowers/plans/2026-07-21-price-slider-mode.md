# Price Widget Real-Slider Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `FF_Widget_Price`'s misleadingly-named `slider` mode (which actually
renders min/max number inputs) into two real modes: `input` (the existing min/max
inputs, renamed) and `slider` (a new, real dual-handle range slider with
customizable track/range/handle colors).

**Architecture:** Two overlapping native `<input type="range">` elements per the
existing `data-ff-price-role="min"/"max"` attribute convention, with a JS-positioned
colored overlay div for the active range, and three new Elementor `COLOR` controls
exposed as CSS custom properties. No AJAX; the existing Apply-button-driven
`FFUrl.navigate()` flow is reused unchanged.

**Tech Stack:** PHP (Elementor widget class), vanilla JS (no framework/build step),
plain CSS.

## Global Constraints

- No AJAX / no live-navigate-while-dragging — dragging only updates the visual
  state; clicking **Apply** is what calls `FFUrl.navigate()`.
- No build step for JS/CSS — write plain ES5-compatible JS and plain CSS directly
  into `assets/js/ff-filters.js` / `assets/css/ff-filters.css`.
- Per `CLAUDE.md`, Elementor widget classes (`includes/widgets/`) are **not**
  PHPUnit-tested — `\Elementor\Widget_Base` needs editor context PHPUnit can't
  provide. Each task below substitutes `php -l` syntax checks (fast, local, no
  Docker needed) for the "write failing test" cycle on PHP steps, and the final
  task is a full manual verification pass against the running wp-env site (the
  project's established pattern for widget verification — see the design plan's
  Task 17).
- New files added under `includes/` must get an explicit `require_once` in
  `class-plugin.php` — not applicable here, since no new files are created; only
  existing files are modified.
- The mode option value currently called `slider` (min/max inputs) becomes `input`;
  the new real slider takes over the `slider` key. This is a deliberate breaking
  change to any already-saved widget instance — accepted per the approved design
  spec (`docs/superpowers/specs/2026-07-21-price-slider-mode-design.md`), since the
  plugin is pre-release.

---

### Task 1: Rename existing min/max-input mode to `input`, extract shared bounds helper

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-price.php`
- Modify: `filter-forge/assets/css/ff-filters.css`
- Modify: `filter-forge/assets/js/ff-filters.js`

**Interfaces:**
- Produces: `FF_Widget_Price::get_price_bounds(): object` — returns an object with
  `->min_price` and `->max_price` string properties (the site's cheapest/most
  expensive published product price). Task 2 calls this too.
- Produces: `FF_Widget_Price::render_min_max_inputs( ?string $current_min, ?string $current_max ): void` —
  renamed from the existing `render_slider()`, same behavior, now wrapped in
  `.ff-price--input` instead of `.ff-price--slider`.
- Consumes: nothing new (this task only renames/extracts existing code).

- [ ] **Step 1: Extract `get_price_bounds()` and rename `render_slider()` to `render_min_max_inputs()`**

In `filter-forge/includes/widgets/class-widget-price.php`, replace the existing
`render_slider()` method (the one with the doc-comment starting "No JS slider
library is used...") with:

```php
    private function get_price_bounds(): object {
        global $wpdb;

        $bounds = $wpdb->get_row(
            "SELECT MIN(meta_value + 0) AS min_price, MAX(meta_value + 0) AS max_price
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_price'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'"
        );

        return (object) array(
            'min_price' => $bounds->min_price ?? '0',
            'max_price' => $bounds->max_price ?? '0',
        );
    }

    /**
     * Literal min/max number input pair -- matches the design's "render
     * links/inputs with literal min_price/max_price values" approach used by
     * every other filter in the plugin.
     */
    private function render_min_max_inputs( ?string $current_min, ?string $current_max ): void {
        $bounds = $this->get_price_bounds();

        printf(
            '<div class="ff-price ff-price--input">
                <label>%1$s <input type="number" class="ff-price__input" data-ff-price-role="min" min="%3$s" max="%4$s" step="any" value="%5$s" /></label>
                <label>%2$s <input type="number" class="ff-price__input" data-ff-price-role="max" min="%3$s" max="%4$s" step="any" value="%6$s" /></label>
                <button type="button" class="ff-price__apply">%7$s</button>
            </div>',
            esc_html__( 'Min', 'filter-forge' ),
            esc_html__( 'Max', 'filter-forge' ),
            esc_attr( $bounds->min_price ),
            esc_attr( $bounds->max_price ),
            esc_attr( $current_min ?? $bounds->min_price ),
            esc_attr( $current_max ?? $bounds->max_price ),
            esc_html__( 'Apply', 'filter-forge' )
        );
    }
```

Then update the dispatch in `render()` — find:

```php
        } else {
            $this->render_slider( $current_min, $current_max );
        }
```

and replace with:

```php
        } else {
            $this->render_min_max_inputs( $current_min, $current_max );
        }
```

- [ ] **Step 2: Rename the mode option key from `slider` to `input`**

In the same file, find the `ff_price_mode` control:

```php
        $this->add_control(
            'ff_price_mode',
            array(
                'label'   => __( 'Mode', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'slider',
                'options' => array(
                    'slider'  => __( 'Slider (dynamic min/max)', 'filter-forge' ),
                    'buckets' => __( 'Predefined buckets', 'filter-forge' ),
                ),
            )
        );
```

Replace with:

```php
        $this->add_control(
            'ff_price_mode',
            array(
                'label'   => __( 'Mode', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'input',
                'options' => array(
                    'input'   => __( 'Min/Max Inputs', 'filter-forge' ),
                    'slider'  => __( 'Slider', 'filter-forge' ),
                    'buckets' => __( 'Predefined buckets', 'filter-forge' ),
                ),
            )
        );
```

(Default stays on the existing, fully-working `input` behavior for this task; Task
4 will not change this default, keeping `input` as the widget's out-of-the-box
mode — same rendered behavior a fresh widget had before this plan.)

Also find the mode dispatch condition just above the `else` branch:

```php
        if ( 'buckets' === ( $settings['ff_price_mode'] ?? 'slider' ) ) {
```

Replace with:

```php
        if ( 'buckets' === ( $settings['ff_price_mode'] ?? 'input' ) ) {
```

- [ ] **Step 3: Rename the CSS class for the min/max-input layout**

In `filter-forge/assets/css/ff-filters.css`, find:

```css
.ff-price--slider {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75em;
}

.ff-price--slider label {
    display: flex;
    flex-direction: column;
    gap: 0.25em;
    font-size: 0.85em;
}
```

Replace with:

```css
.ff-price--input {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75em;
}

.ff-price--input label {
    display: flex;
    flex-direction: column;
    gap: 0.25em;
    font-size: 0.85em;
}
```

- [ ] **Step 4: Update JS selectors that referenced the old class name**

In `filter-forge/assets/js/ff-filters.js`, find (in the click handler):

```javascript
    const applyBtn = event.target.closest( '.ff-price__apply' );
    if ( applyBtn ) {
        const wrapper = applyBtn.closest( '.ff-price--slider' );
        if ( wrapper ) {
            ffApplyPriceRange( wrapper );
        }
        return;
    }
```

Replace with:

```javascript
    const applyBtn = event.target.closest( '.ff-price__apply' );
    if ( applyBtn ) {
        const wrapper = applyBtn.closest( '.ff-price--input' );
        if ( wrapper ) {
            ffApplyPriceRange( wrapper );
        }
        return;
    }
```

And find (in the keydown handler):

```javascript
    const wrapper = event.target.closest( '.ff-price--slider' );
    if ( ! wrapper ) {
        return;
    }
```

Replace with:

```javascript
    const wrapper = event.target.closest( '.ff-price--input' );
    if ( ! wrapper ) {
        return;
    }
```

- [ ] **Step 5: Syntax-check the PHP file**

Run: `php -l "filter-forge/includes/widgets/class-widget-price.php"`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-price.php`

- [ ] **Step 6: Verify no other references to the old class/method names remain**

Run: `grep -rn "ff-price--slider\|render_slider\b" filter-forge/ --include=*.php --include=*.js --include=*.css`
Expected: no output (empty) — confirms every reference was renamed.

- [ ] **Step 7: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php filter-forge/assets/css/ff-filters.css filter-forge/assets/js/ff-filters.js
git commit -m "Rename price widget's min/max-input mode from 'slider' to 'input'"
```

---

### Task 2: Add slider color controls and render the new slider's markup

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-price.php`

**Interfaces:**
- Consumes: `FF_Widget_Price::get_price_bounds()` from Task 1.
- Produces: `FF_Widget_Price::render_slider_range( ?string $current_min, ?string $current_max ): void`,
  called by `render()` when `ff_price_mode === 'slider'`. Renders a `.ff-price--slider`
  wrapper containing `.ff-price__slider-values` (live text spans), a
  `.ff-price__slider-track` div containing `.ff-price__slider-range` (the colored
  overlay, server-positioned) and two `input.ff-price__range[data-ff-price-role]`
  range inputs, and the existing `.ff-price__apply` button. Task 3 styles these
  classes; Task 4 wires up their drag interactivity.

- [ ] **Step 1: Add the three color controls**

In `filter-forge/includes/widgets/class-widget-price.php`, find the `ff_show_clear`
control:

```php
        $this->add_control(
            'ff_show_clear',
            array(
                'label'   => __( 'Show Clear button', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
            )
        );
```

Insert the following immediately **before** it:

```php
        $this->add_control(
            'ff_slider_track_color',
            array(
                'label'     => __( 'Slider Track Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#dcdcde',
                'condition' => array( 'ff_price_mode' => 'slider' ),
            )
        );

        $this->add_control(
            'ff_slider_range_color',
            array(
                'label'     => __( 'Slider Active Range Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#2271b1',
                'condition' => array( 'ff_price_mode' => 'slider' ),
            )
        );

        $this->add_control(
            'ff_slider_handle_color',
            array(
                'label'     => __( 'Slider Handle Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'condition' => array( 'ff_price_mode' => 'slider' ),
            )
        );

```

- [ ] **Step 2: Update the `render()` dispatch to call the new slider renderer**

Find the block from Task 1:

```php
        if ( 'buckets' === ( $settings['ff_price_mode'] ?? 'input' ) ) {
            $style = $settings['ff_price_bucket_style'] ?? 'list';
            if ( 'dropdown' === $style ) {
                $this->render_buckets_dropdown( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            } else {
                $this->render_buckets_list( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            }
        } else {
            $this->render_min_max_inputs( $current_min, $current_max );
        }
```

Replace with:

```php
        $mode = $settings['ff_price_mode'] ?? 'input';

        if ( 'buckets' === $mode ) {
            $style = $settings['ff_price_bucket_style'] ?? 'list';
            if ( 'dropdown' === $style ) {
                $this->render_buckets_dropdown( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            } else {
                $this->render_buckets_list( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            }
        } elseif ( 'slider' === $mode ) {
            $this->render_slider_range( $current_min, $current_max );
        } else {
            $this->render_min_max_inputs( $current_min, $current_max );
        }
```

- [ ] **Step 3: Add `render_slider_range()`**

Add this new method right after `render_min_max_inputs()`:

```php
    /**
     * Real dual-handle range slider: two overlapping native range inputs plus a
     * server-positioned colored overlay div (so the range looks correct even
     * before JS runs); Task 4's JS keeps the overlay/live text in sync while
     * dragging.
     */
    private function render_slider_range( ?string $current_min, ?string $current_max ): void {
        $settings = $this->get_settings_for_display();
        $bounds   = $this->get_price_bounds();

        $bound_min = (float) $bounds->min_price;
        $bound_max = (float) $bounds->max_price;
        $span      = max( $bound_max - $bound_min, 1 );

        $value_min = null !== $current_min ? (float) $current_min : $bound_min;
        $value_max = null !== $current_max ? (float) $current_max : $bound_max;

        $left  = ( $value_min - $bound_min ) / $span * 100;
        $width = ( $value_max - $value_min ) / $span * 100;

        $track_color  = $settings['ff_slider_track_color'] ?? '#dcdcde';
        $range_color  = $settings['ff_slider_range_color'] ?? '#2271b1';
        $handle_color = $settings['ff_slider_handle_color'] ?? '#ffffff';

        printf(
            '<div class="ff-price ff-price--slider" style="--ff-slider-track:%1$s;--ff-slider-range:%2$s;--ff-slider-handle:%3$s;">
                <div class="ff-price__slider-values">
                    <span data-ff-slider-display="min">%4$s</span> &ndash; <span data-ff-slider-display="max">%5$s</span>
                </div>
                <div class="ff-price__slider-track">
                    <div class="ff-price__slider-range" style="left:%6$s%%;width:%7$s%%;"></div>
                    <input type="range" class="ff-price__range" data-ff-price-role="min" min="%8$s" max="%9$s" step="1" value="%10$s" />
                    <input type="range" class="ff-price__range" data-ff-price-role="max" min="%8$s" max="%9$s" step="1" value="%11$s" />
                </div>
                <button type="button" class="ff-price__apply">%12$s</button>
            </div>',
            esc_attr( $track_color ),
            esc_attr( $range_color ),
            esc_attr( $handle_color ),
            esc_html( (string) round( $value_min ) ),
            esc_html( (string) round( $value_max ) ),
            esc_attr( (string) round( $left, 4 ) ),
            esc_attr( (string) round( $width, 4 ) ),
            esc_attr( (string) round( $bound_min ) ),
            esc_attr( (string) round( $bound_max ) ),
            esc_attr( (string) round( $value_min ) ),
            esc_attr( (string) round( $value_max ) ),
            esc_html__( 'Apply', 'filter-forge' )
        );
    }
```

- [ ] **Step 4: Syntax-check the PHP file**

Run: `php -l "filter-forge/includes/widgets/class-widget-price.php"`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-price.php`

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php
git commit -m "Add real-slider markup and color controls to price widget"
```

---

### Task 3: Style the slider (track, overlay, overlapping-input pointer-events trick)

**Files:**
- Modify: `filter-forge/assets/css/ff-filters.css`

**Interfaces:**
- Consumes: the class names and CSS custom properties (`--ff-slider-track`,
  `--ff-slider-range`, `--ff-slider-handle`) produced by
  `render_slider_range()` in Task 2.
- Produces: nothing new for later tasks (Task 4's JS only manipulates inline
  `style.left`/`style.right`/`textContent` — it doesn't depend on any class this
  task adds beyond what Task 2 already emits).

- [ ] **Step 1: Add the slider CSS rules**

Append to the end of `filter-forge/assets/css/ff-filters.css`:

```css
.ff-price--slider {
    display: flex;
    flex-direction: column;
    gap: 0.6em;
}

.ff-price__slider-values {
    font-size: 0.9em;
    font-weight: 600;
}

.ff-price__slider-track {
    position: relative;
    height: 1.25em;
}

.ff-price__slider-track::before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 0.25em;
    transform: translateY(-50%);
    border-radius: 999px;
    background: var(--ff-slider-track, #dcdcde);
}

.ff-price__slider-range {
    position: absolute;
    top: 50%;
    height: 0.25em;
    transform: translateY(-50%);
    border-radius: 999px;
    background: var(--ff-slider-range, #2271b1);
}

.ff-price__range {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 1.25em;
    margin: 0;
    background: transparent;
    pointer-events: none;
    -webkit-appearance: none;
    appearance: none;
}

.ff-price__range::-webkit-slider-runnable-track {
    -webkit-appearance: none;
    background: transparent;
}

.ff-price__range::-moz-range-track {
    background: transparent;
    border: none;
}

.ff-price__range::-webkit-slider-thumb {
    -webkit-appearance: none;
    pointer-events: auto;
    width: 1.1em;
    height: 1.1em;
    border-radius: 50%;
    border: 1px solid #8c8f94;
    background: var(--ff-slider-handle, #ffffff);
    cursor: pointer;
}

.ff-price__range::-moz-range-thumb {
    pointer-events: auto;
    width: 1.1em;
    height: 1.1em;
    border-radius: 50%;
    border: 1px solid #8c8f94;
    background: var(--ff-slider-handle, #ffffff);
    cursor: pointer;
}
```

- [ ] **Step 2: Sanity-check the CSS parses**

Run: `node -e "require('fs').readFileSync('filter-forge/assets/css/ff-filters.css','utf8')" && echo OK`
Expected: `OK` (this just confirms the file is readable/well-formed UTF-8 after the
edit; there's no CSS linter configured in this project to run more deeply).

- [ ] **Step 3: Commit**

```bash
git add filter-forge/assets/css/ff-filters.css
git commit -m "Style the price widget's real slider (track, overlay, handles)"
```

---

### Task 4: Wire up slider drag interactivity in JS

**Files:**
- Modify: `filter-forge/assets/js/ff-filters.js`

**Interfaces:**
- Consumes: `.ff-price--slider`, `.ff-price__slider-track`, `.ff-price__slider-range`,
  `.ff-price__range[data-ff-price-role]`, `[data-ff-slider-display="min"/"max"]`
  from Task 2's markup. Reuses the existing `ffApplyPriceRange( wrapper )` function
  (defined at the top of this file) unchanged.
- Produces: nothing consumed by later tasks (Task 5 is manual verification only).

- [ ] **Step 1: Add the clamp + live-visual-update functions**

In `filter-forge/assets/js/ff-filters.js`, insert the following two functions right
after the existing `ffApplyPriceRange` function (i.e. after its closing `}` on line
9, before the `document.addEventListener( 'change', ...)` block):

```javascript
function ffClampSliderHandles( wrapper, changedRole ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );

    if ( ! minInput || ! maxInput ) {
        return;
    }

    if ( parseFloat( minInput.value ) > parseFloat( maxInput.value ) ) {
        if ( 'min' === changedRole ) {
            minInput.value = maxInput.value;
        } else {
            maxInput.value = minInput.value;
        }
    }
}

function ffUpdateSliderVisual( wrapper ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );
    const range    = wrapper.querySelector( '.ff-price__slider-range' );
    const minLabel = wrapper.querySelector( '[data-ff-slider-display="min"]' );
    const maxLabel = wrapper.querySelector( '[data-ff-slider-display="max"]' );

    if ( ! minInput || ! maxInput || ! range ) {
        return;
    }

    const boundMin = parseFloat( minInput.min );
    const boundMax = parseFloat( minInput.max );
    const span     = Math.max( boundMax - boundMin, 1 );

    const minVal = parseFloat( minInput.value );
    const maxVal = parseFloat( maxInput.value );

    range.style.left  = ( ( minVal - boundMin ) / span * 100 ) + '%';
    range.style.width = ( ( maxVal - minVal ) / span * 100 ) + '%';

    if ( minLabel ) {
        minLabel.textContent = minInput.value;
    }
    if ( maxLabel ) {
        maxLabel.textContent = maxInput.value;
    }
}

document.addEventListener( 'input', function ( event ) {
    const rangeInput = event.target.closest( '.ff-price__range' );
    if ( ! rangeInput ) {
        return;
    }

    const wrapper = rangeInput.closest( '.ff-price--slider' );
    if ( ! wrapper ) {
        return;
    }

    ffClampSliderHandles( wrapper, rangeInput.getAttribute( 'data-ff-price-role' ) );
    ffUpdateSliderVisual( wrapper );
} );
```

- [ ] **Step 2: Broaden the Apply-button and Enter-key wrapper selectors to include the new slider class**

Find (added in Task 1, Step 4):

```javascript
    const applyBtn = event.target.closest( '.ff-price__apply' );
    if ( applyBtn ) {
        const wrapper = applyBtn.closest( '.ff-price--input' );
        if ( wrapper ) {
            ffApplyPriceRange( wrapper );
        }
        return;
    }
```

Replace with:

```javascript
    const applyBtn = event.target.closest( '.ff-price__apply' );
    if ( applyBtn ) {
        const wrapper = applyBtn.closest( '.ff-price--input, .ff-price--slider' );
        if ( wrapper ) {
            ffApplyPriceRange( wrapper );
        }
        return;
    }
```

Find (added in Task 1, Step 4):

```javascript
    const wrapper = event.target.closest( '.ff-price--input' );
    if ( ! wrapper ) {
        return;
    }
```

Replace with:

```javascript
    const wrapper = event.target.closest( '.ff-price--input, .ff-price--slider' );
    if ( ! wrapper ) {
        return;
    }
```

- [ ] **Step 3: Sanity-check the JS parses**

Run: `node --check filter-forge/assets/js/ff-filters.js`
Expected: no output (a syntax error would print a `SyntaxError` and non-zero exit;
silence means the file parsed cleanly).

- [ ] **Step 4: Commit**

```bash
git add filter-forge/assets/js/ff-filters.js
git commit -m "Wire up price slider drag interactivity (clamp, live values, overlay)"
```

---

### Task 5: Manual end-to-end verification against the running site

**Files:** none (verification only).

**Interfaces:** none — this task consumes the finished feature from Tasks 1–4 and
produces nothing further.

- [ ] **Step 1: Start the dev environment (skip if already running)**

Run: `npm run env:start`
Expected: containers start; site reachable at `http://localhost:8888`.

- [ ] **Step 2: Add a Price Filter widget in slider mode**

In the Elementor editor, edit a WooCommerce Shop or category archive page, add the
"Price Filter - Forge" widget, and set **Mode** to **Slider**. Set custom values for
Track Color, Active Range Color, and Handle Color (pick three visually distinct
colors, e.g. black track, red active range, blue handles) to make each easy to spot.
Publish/update the page.

- [ ] **Step 3: Verify default render and bounds**

Load the published archive page on the front end. Confirm:
- The slider renders with two visible handles at the full range (no `min_price`/
  `max_price` in the URL yet).
- The live text above the track shows the site's actual min and max product prices.
- The colored overlay spans the full track (since no filter is active yet), and the
  track/range/handle colors match what was configured in Step 2.

- [ ] **Step 4: Verify dragging is visual-only until Apply**

Drag the left handle to the right and the right handle to the left. Confirm:
- The live text values update continuously while dragging.
- The colored overlay's position/width updates continuously while dragging.
- The page does **not** navigate/reload during dragging.
- The two handles cannot be dragged past each other (dragging the left handle past
  the right one stops it at the right handle's value, and vice versa).

- [ ] **Step 5: Verify Apply navigates correctly**

With the handles set to some narrowed range, click **Apply**. Confirm:
- The page reloads with `min_price` and `max_price` query args matching the values
  shown in the live text just before clicking.
- The product grid (Elementor Loop Grid in Main Query mode) reflects the narrowed
  price range.
- Reloading the slider widget on this filtered page shows the handles/live text
  reflecting the current `min_price`/`max_price`, not the full bounds.

- [ ] **Step 6: Verify `input` mode and `buckets` mode still work unchanged**

Change the widget's **Mode** to **Min/Max Inputs**. Confirm it renders and behaves
exactly as the pre-existing min/max-input-and-Apply-button behavior did (number
inputs, Apply button, same URL params). Then set **Mode** to **Predefined buckets**
and confirm bucket rendering/links are unaffected by this plan's changes.

- [ ] **Step 7: Note completion**

No commit for this task (verification only). If any check in Steps 3–6 fails, file
it as a bug against the specific task whose code is responsible and fix there before
considering the plan complete.
