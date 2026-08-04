# Filter Commit Mode (Instant vs. Submit) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-widget-instance "Commit Mode" toggle (Instant / On Submit) to the
Filter, Price, and Orderby widgets, plus a new global "Apply Filters" widget, so filter
changes can be staged and committed together instead of always reloading the page on
every change.

**Architecture:** No new storage. Staged (uncommitted) filter changes are written into
the browser's own address bar via `history.replaceState` (`FFUrl.stage()`); committing
is just a real navigation to whatever is currently in the address bar
(`FFUrl.commit()`, which delegates to the existing `FFUrl.navigate()`). Every widget
that can commit a filter renders a `data-ff-commit-mode="instant|submit"` attribute
that the shared `ff-filters.js` event handlers branch on.

**Tech Stack:** PHP 8+ (WordPress/Elementor/WooCommerce plugin), vanilla JS (no build
step, no framework), PHPUnit (`WP_UnitTestCase`) for pure/testable PHP logic only.

## Global Constraints

- No `vendor/autoload.php` at runtime — every new PHP file needs an explicit
  `require_once` in `includes/class-plugin.php` (widget files inside
  `register_widgets()`, in dependency order).
- Elementor widget classes (anything extending `\Elementor\Widget_Base` /
  `FF_Widget_Base`) are **not** unit tested — no editor/document context in
  `WP_UnitTestCase`. Only pure, static, non-Elementor-dependent logic gets a PHPUnit
  test. Everything else is verified manually against the running wp-env site (final
  task).
- No build step for JS/CSS — edit `assets/js/*.js` and `assets/css/*.css` directly;
  they're enqueued as-is.
- Default `ff_commit_mode` must be **Instant** (empty string, not `'submit'`) so
  existing pages keep behaving exactly as they do today after upgrade.
- `FFUrl.commit()` must delegate to the existing `FFUrl.navigate()`, not
  `window.location.reload()` — this keeps browser back-button history behavior
  identical between Instant-mode and Submit-mode commits.
- Run `npm run test:php` after any PHP change that touches tested code; Docker Desktop
  and `npm run env:start` must be running first.

---

### Task 1: `FF_Widget_Base` — commit-mode helper and shared controls

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-base.php`
- Test: `filter-forge/tests/test-class-widget-base.php` (new)

**Interfaces:**
- Consumes: nothing new.
- Produces: `FF_Widget_Base::resolve_commit_mode( array $settings ): string` (static,
  pure — returns `'submit'` or `'instant'`), `FF_Widget_Base::commit_mode(): string`
  (protected instance method, reads `$this->get_settings_for_display()`),
  `FF_Widget_Base::register_commit_mode_controls(): void` (protected, adds the
  `ff_commit_mode` SWITCHER and `ff_submit_label` TEXT Elementor controls in a new
  "Behavior" content section). Tasks 3–5 call `register_commit_mode_controls()` from
  `register_controls()` and `commit_mode()` from `render()`.

- [ ] **Step 1: Write the failing test for `resolve_commit_mode`**

Create `filter-forge/tests/test-class-widget-base.php`:

```php
<?php

class Test_FF_Widget_Base extends WP_UnitTestCase {

    public function test_resolve_commit_mode_defaults_to_instant_when_unset() {
        $this->assertSame( 'instant', FF_Widget_Base::resolve_commit_mode( array() ) );
    }

    public function test_resolve_commit_mode_returns_instant_for_any_non_submit_value() {
        $this->assertSame( 'instant', FF_Widget_Base::resolve_commit_mode( array( 'ff_commit_mode' => '' ) ) );
        $this->assertSame( 'instant', FF_Widget_Base::resolve_commit_mode( array( 'ff_commit_mode' => 'bogus' ) ) );
    }

    public function test_resolve_commit_mode_returns_submit_when_set() {
        $this->assertSame( 'submit', FF_Widget_Base::resolve_commit_mode( array( 'ff_commit_mode' => 'submit' ) ) );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
npm run test:php -- --filter Test_FF_Widget_Base
```
Expected: FAIL — `Call to undefined method FF_Widget_Base::resolve_commit_mode()`.

- [ ] **Step 3: Implement `resolve_commit_mode`, `commit_mode`, and `register_commit_mode_controls`**

Open `filter-forge/includes/widgets/class-widget-base.php`. Immediately after the
closing brace of `get_relationship_config()` (the method that ends around line 162,
right before `protected function register_text_style_controls(): void {`), insert:

```php
    protected function register_commit_mode_controls(): void {
        $this->start_controls_section(
            'ff_behavior',
            array(
                'label' => __( 'Behavior', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

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

        $this->end_controls_section();
    }

    protected function commit_mode(): string {
        return self::resolve_commit_mode( $this->get_settings_for_display() );
    }

    public static function resolve_commit_mode( array $settings ): string {
        return 'submit' === ( $settings['ff_commit_mode'] ?? '' ) ? 'submit' : 'instant';
    }

```

- [ ] **Step 4: Run the test to verify it passes**

```bash
npm run test:php -- --filter Test_FF_Widget_Base
```
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-base.php filter-forge/tests/test-class-widget-base.php
git commit -m "Add shared commit-mode helper and Elementor controls to FF_Widget_Base"
```

---

### Task 2: `ff-url.js` — `stage()` and `commit()`

**Files:**
- Modify: `filter-forge/assets/js/ff-url.js`

**Interfaces:**
- Consumes: nothing new (uses the existing `navigate` closure variable already defined
  in the same IIFE).
- Produces: `window.FFUrl.stage( params )` (writes `params` into the address bar via
  `history.replaceState`, no reload), `window.FFUrl.commit()` (re-navigates to the
  current, possibly-staged, address bar URL via the existing `navigate()`). Task 6's
  `ff-filters.js` edits call both.

No automated test exists for this file (the project has no JS test runner — vanilla
JS, no build step, per `CLAUDE.md`). Verified manually in this task via the browser
console, and again end-to-end in Task 9.

- [ ] **Step 1: Add `stage` and `commit`**

Open `filter-forge/assets/js/ff-url.js`. It currently reads:

```js
window.FFUrl = ( function () {
    function get( key ) {
        return new URLSearchParams( window.location.search ).get( key );
    }

    function set( key, value, params ) {
        const target = params || new URLSearchParams( window.location.search );
        if ( value === '' || value === null ) {
            target.delete( key );
        } else {
            target.set( key, value );
        }
        return target;
    }

    function remove( key, params ) {
        const target = params || new URLSearchParams( window.location.search );
        target.delete( key );
        return target;
    }

    function navigate( params ) {
        const query = params.toString();
        window.location.href = window.location.pathname + ( query ? '?' + query : '' );
    }

    return { get: get, set: set, remove: remove, navigate: navigate };
} )();
```

Replace it with:

```js
window.FFUrl = ( function () {
    function get( key ) {
        return new URLSearchParams( window.location.search ).get( key );
    }

    function set( key, value, params ) {
        const target = params || new URLSearchParams( window.location.search );
        if ( value === '' || value === null ) {
            target.delete( key );
        } else {
            target.set( key, value );
        }
        return target;
    }

    function remove( key, params ) {
        const target = params || new URLSearchParams( window.location.search );
        target.delete( key );
        return target;
    }

    function navigate( params ) {
        const query = params.toString();
        window.location.href = window.location.pathname + ( query ? '?' + query : '' );
    }

    /**
     * Writes params into the address bar without reloading -- this is how
     * Submit-mode widgets stage an in-progress change. No new history entry
     * (replaceState, not pushState), so it doesn't spam the back button.
     */
    function stage( params ) {
        const query = params.toString();
        window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) );
    }

    /**
     * Commits whatever is currently staged in the address bar. Delegates to
     * navigate() (not location.reload()) so this pushes a real history entry,
     * same as an Instant-mode change -- reload() would re-fetch the current
     * entry in place instead, making back-button behavior inconsistent
     * between the two commit modes.
     */
    function commit() {
        navigate( new URLSearchParams( window.location.search ) );
    }

    return { get: get, set: set, remove: remove, navigate: navigate, stage: stage, commit: commit };
} )();
```

- [ ] **Step 2: Verify manually in the browser console**

```bash
npm run env:start
```
Visit any WooCommerce archive page on `http://localhost:8888`, open the browser dev
console, and run:

```js
FFUrl.stage( new URLSearchParams( 'foo=bar' ) );
```
Expected: the address bar now shows `?foo=bar`, the Network tab shows no new request,
and the page content is unchanged.

```js
FFUrl.commit();
```
Expected: the page reloads (a real request fires), and the reloaded page's URL still
contains `?foo=bar`.

- [ ] **Step 3: Commit**

```bash
git add filter-forge/assets/js/ff-url.js
git commit -m "Add FFUrl.stage() and FFUrl.commit() for deferred filter commits"
```

---

### Task 3: Filter widget — commit mode, wrapper attribute, submit button

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-filter.php`

**Interfaces:**
- Consumes: `$this->register_commit_mode_controls()` and `$this->commit_mode()` from
  Task 1.
- Produces: every `.ff-filter` list/dropdown wrapper now carries
  `data-ff-commit-mode="instant|submit"`; in Submit mode, a
  `<button type="button" class="ff-filter__submit">` renders after the options. Task 6
  reads both.

- [ ] **Step 1: Register the commit-mode controls**

In `register_controls()`, find:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();

        $this->register_text_style_controls();
```

Replace with:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();
        $this->register_commit_mode_controls();

        $this->register_text_style_controls();
```

- [ ] **Step 2: Add the `data-ff-commit-mode` attribute and the submit button in `render()`**

Find:

```php
        $wrapper_attrs = ' data-ff-filter-key="' . esc_attr( $filter_key ) . '"'
            . ' data-ff-param="' . esc_attr( $param ) . '"'
            . ' data-ff-parent-key="' . esc_attr( $relationship['parent_key'] ) . '"'
            . ' data-ff-reset-on-change="' . ( $relationship['reset_on_change'] ? 'yes' : 'no' ) . '"';
```

Replace with:

```php
        $commit_mode = $this->commit_mode();

        $wrapper_attrs = ' data-ff-filter-key="' . esc_attr( $filter_key ) . '"'
            . ' data-ff-param="' . esc_attr( $param ) . '"'
            . ' data-ff-parent-key="' . esc_attr( $relationship['parent_key'] ) . '"'
            . ' data-ff-reset-on-change="' . ( $relationship['reset_on_change'] ? 'yes' : 'no' ) . '"'
            . ' data-ff-commit-mode="' . esc_attr( $commit_mode ) . '"';
```

Then find the end of `render()`:

```php
        $show_clear = 'yes' === ( $settings['ff_show_clear'] ?? '' );

        if ( $show_clear && ! empty( $selected ) ) {
            printf(
                '<button type="button" class="ff-filter__clear" data-ff-param="%1$s">%2$s</button>',
                esc_attr( $param ),
                esc_html__( 'Clear', 'filter-forge' )
            );
        }
    }
```

Replace with:

```php
        $show_clear = 'yes' === ( $settings['ff_show_clear'] ?? '' );

        if ( $show_clear && ! empty( $selected ) ) {
            printf(
                '<button type="button" class="ff-filter__clear" data-ff-param="%1$s">%2$s</button>',
                esc_attr( $param ),
                esc_html__( 'Clear', 'filter-forge' )
            );
        }

        if ( 'submit' === $commit_mode ) {
            printf(
                '<button type="button" class="ff-filter__submit">%s</button>',
                esc_html( $settings['ff_submit_label'] ?? __( 'Apply', 'filter-forge' ) )
            );
        }
    }
```

- [ ] **Step 3: Confirm nothing broke**

```bash
npm run test:php -- --filter Test_FF_Category_Filter
npm run test:php -- --filter Test_FF_Meta_Filter
```
Expected: PASS — these exercise `FF_Category_Filter`/`FF_Meta_Filter` directly and
don't touch widget rendering, but confirm the wider test suite still boots cleanly
after this file's edit (a PHP parse error here would break autoloading for the whole
suite via `register_widgets()`).

- [ ] **Step 4: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-filter.php
git commit -m "Add commit-mode toggle and submit button to Filter widget"
```

---

### Task 4: Orderby widget — commit mode, wrapper attribute, submit button

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-orderby.php`

**Interfaces:**
- Consumes: `$this->register_commit_mode_controls()` and `$this->commit_mode()` from
  Task 1.
- Produces: the `<select class="ff-orderby ff-orderby--dropdown">` now carries
  `data-ff-commit-mode="instant|submit"`; in Submit mode, a
  `<button type="button" class="ff-orderby__submit">` renders after it. Task 6 reads
  both.

- [ ] **Step 1: Register the commit-mode controls**

In `register_controls()`, find:

```php
    protected function register_controls(): void {
        $this->register_header_controls();

        $this->register_text_style_controls();
        $this->register_header_style_controls();
        $this->register_dropdown_style_controls( array() );
    }
```

Replace with:

```php
    protected function register_controls(): void {
        $this->register_header_controls();
        $this->register_commit_mode_controls();

        $this->register_text_style_controls();
        $this->register_header_style_controls();
        $this->register_dropdown_style_controls( array() );
    }
```

- [ ] **Step 2: Add the attribute and submit button in `render()`**

Find:

```php
        echo '<select class="ff-orderby ff-orderby--dropdown" data-ff-param="orderby">';

        foreach ( $options as $value => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $value ),
                selected( $value, $current, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
    }
```

Replace with:

```php
        $settings    = $this->get_settings_for_display();
        $commit_mode = $this->commit_mode();

        printf(
            '<select class="ff-orderby ff-orderby--dropdown" data-ff-param="orderby" data-ff-commit-mode="%s">',
            esc_attr( $commit_mode )
        );

        foreach ( $options as $value => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $value ),
                selected( $value, $current, false ),
                esc_html( $label )
            );
        }

        echo '</select>';

        if ( 'submit' === $commit_mode ) {
            printf(
                '<button type="button" class="ff-orderby__submit">%s</button>',
                esc_html( $settings['ff_submit_label'] ?? __( 'Apply', 'filter-forge' ) )
            );
        }
    }
```

Note: `render()` doesn't already call `$this->get_settings_for_display()` before this
point (it only calls `FF_Plugin::instance()->filter_state->get( 'orderby' )`), so this
adds the first `$settings` local in the method — check the full method body after
editing to confirm there isn't a second, now-duplicate `$settings =` assignment
elsewhere in the same method.

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-orderby.php
git commit -m "Add commit-mode toggle and submit button to Orderby widget"
```

---

### Task 5: Price widget — commit mode across Input, Slider, and Buckets modes

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-price.php`

**Interfaces:**
- Consumes: `$this->register_commit_mode_controls()` and `$this->commit_mode()` from
  Task 1.
- Produces: `render_min_max_inputs()`, `render_slider_range()`,
  `render_buckets_list()`, `render_buckets_dropdown()` all gain a `string $commit_mode`
  parameter and emit `data-ff-commit-mode` on their wrapper element. The Input/Slider
  Apply button (`.ff-price__apply`) only renders when `$commit_mode === 'submit'`.
  Each bucket-list `<a>` gains `data-ff-price-min`/`data-ff-price-max` attributes. Task
  6 reads all of this.

- [ ] **Step 1: Register the commit-mode controls**

In `register_controls()`, find:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();

        $this->register_text_style_controls();
        $this->register_button_style_controls();
        $this->register_dropdown_style_controls( array( 'ff_price_bucket_style' => 'dropdown' ) );
```

Replace with:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();
        $this->register_commit_mode_controls();

        $this->register_text_style_controls();
        $this->register_button_style_controls();
        $this->register_dropdown_style_controls( array( 'ff_price_bucket_style' => 'dropdown' ) );
```

- [ ] **Step 2: Thread `$commit_mode` through `render()`**

Find:

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

Replace with:

```php
        $mode        = $settings['ff_price_mode'] ?? 'input';
        $commit_mode = $this->commit_mode();

        if ( 'buckets' === $mode ) {
            $style = $settings['ff_price_bucket_style'] ?? 'list';
            if ( 'dropdown' === $style ) {
                $this->render_buckets_dropdown( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max, $commit_mode );
            } else {
                $this->render_buckets_list( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max, $commit_mode );
            }
        } elseif ( 'slider' === $mode ) {
            $this->render_slider_range( $current_min, $current_max, $commit_mode );
        } else {
            $this->render_min_max_inputs( $current_min, $current_max, $commit_mode );
        }
```

- [ ] **Step 3: Update `render_buckets_list()`**

Find the whole method:

```php
    private function render_buckets_list( array $buckets, ?string $current_min, ?string $current_max ): void {
        $settings       = $this->get_settings_for_display();
        $active_icon    = $settings['ff_bucket_icon_active'] ?? array();
        $inactive_icon  = $settings['ff_bucket_icon_inactive'] ?? array();
        $active_color   = $settings['ff_bucket_active_color'] ?? '#2271b1';
        $inactive_color = $settings['ff_bucket_inactive_color'] ?? '#3c3c3c';

        // Both icons are required -- a half-configured pair would leave one state with no visual at all.
        $has_icon = ! empty( $active_icon['value'] ) && ! empty( $inactive_icon['value'] );

        echo '<ul class="ff-price ff-price--buckets">';

        foreach ( $buckets as $bucket ) {
            [ $min, $max ] = $this->bucket_min_max( $bucket );

            $url = add_query_arg(
                array_filter(
                    array(
                        'min_price' => $min,
                        'max_price' => $max,
                    ),
                    static function ( $value ) {
                        return '' !== $value;
                    }
                )
            );

            $is_active = $current_min === $min && $current_max === $max;
            $color     = $is_active ? $active_color : $inactive_color;

            printf(
                '<li><a href="%1$s" class="ff-price__bucket%2$s" style="color:%3$s !important;">%4$s',
                esc_url( $url ),
                $is_active ? ' ff-price__bucket--active' : '',
                esc_attr( $color ),
                esc_html( $bucket['label'] ?? '' )
            );

            if ( $has_icon ) {
                \Elementor\Icons_Manager::render_icon(
                    $is_active ? $active_icon : $inactive_icon,
                    array(
                        'class'       => 'ff-price__bucket-icon',
                        'style'       => 'color:' . esc_attr( $color ) . ' !important;',
                        'aria-hidden' => 'true',
                    )
                );
            }

            echo '</a></li>';
        }

        echo '</ul>';
    }
```

Replace with:

```php
    private function render_buckets_list( array $buckets, ?string $current_min, ?string $current_max, string $commit_mode ): void {
        $settings       = $this->get_settings_for_display();
        $active_icon    = $settings['ff_bucket_icon_active'] ?? array();
        $inactive_icon  = $settings['ff_bucket_icon_inactive'] ?? array();
        $active_color   = $settings['ff_bucket_active_color'] ?? '#2271b1';
        $inactive_color = $settings['ff_bucket_inactive_color'] ?? '#3c3c3c';

        // Both icons are required -- a half-configured pair would leave one state with no visual at all.
        $has_icon = ! empty( $active_icon['value'] ) && ! empty( $inactive_icon['value'] );

        printf( '<ul class="ff-price ff-price--buckets" data-ff-commit-mode="%s">', esc_attr( $commit_mode ) );

        foreach ( $buckets as $bucket ) {
            [ $min, $max ] = $this->bucket_min_max( $bucket );

            $url = add_query_arg(
                array_filter(
                    array(
                        'min_price' => $min,
                        'max_price' => $max,
                    ),
                    static function ( $value ) {
                        return '' !== $value;
                    }
                )
            );

            $is_active = $current_min === $min && $current_max === $max;
            $color     = $is_active ? $active_color : $inactive_color;

            printf(
                '<li><a href="%1$s" class="ff-price__bucket%2$s" style="color:%3$s !important;" data-ff-price-min="%4$s" data-ff-price-max="%5$s">%6$s',
                esc_url( $url ),
                $is_active ? ' ff-price__bucket--active' : '',
                esc_attr( $color ),
                esc_attr( $min ),
                esc_attr( $max ),
                esc_html( $bucket['label'] ?? '' )
            );

            if ( $has_icon ) {
                \Elementor\Icons_Manager::render_icon(
                    $is_active ? $active_icon : $inactive_icon,
                    array(
                        'class'       => 'ff-price__bucket-icon',
                        'style'       => 'color:' . esc_attr( $color ) . ' !important;',
                        'aria-hidden' => 'true',
                    )
                );
            }

            echo '</a></li>';
        }

        echo '</ul>';
    }
```

- [ ] **Step 4: Update `render_buckets_dropdown()`**

Find:

```php
    private function render_buckets_dropdown( array $buckets, ?string $current_min, ?string $current_max ): void {
        echo '<select class="ff-price ff-price--buckets-dropdown">';
```

Replace with:

```php
    private function render_buckets_dropdown( array $buckets, ?string $current_min, ?string $current_max, string $commit_mode ): void {
        printf( '<select class="ff-price ff-price--buckets-dropdown" data-ff-commit-mode="%s">', esc_attr( $commit_mode ) );
```

(The rest of the method body is unchanged.)

- [ ] **Step 5: Update `render_min_max_inputs()`**

Find the whole method:

```php
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

Replace with:

```php
    private function render_min_max_inputs( ?string $current_min, ?string $current_max, string $commit_mode ): void {
        $bounds = $this->get_price_bounds();

        printf(
            '<div class="ff-price ff-price--input" data-ff-commit-mode="%7$s">
                <label>%1$s <input type="number" class="ff-price__input" data-ff-price-role="min" min="%3$s" max="%4$s" step="any" value="%5$s" /></label>
                <label>%2$s <input type="number" class="ff-price__input" data-ff-price-role="max" min="%3$s" max="%4$s" step="any" value="%6$s" /></label>
                %8$s
            </div>',
            esc_html__( 'Min', 'filter-forge' ),
            esc_html__( 'Max', 'filter-forge' ),
            esc_attr( $bounds->min_price ),
            esc_attr( $bounds->max_price ),
            esc_attr( $current_min ?? $bounds->min_price ),
            esc_attr( $current_max ?? $bounds->max_price ),
            esc_attr( $commit_mode ),
            'submit' === $commit_mode
                ? '<button type="button" class="ff-price__apply">' . esc_html__( 'Apply', 'filter-forge' ) . '</button>'
                : ''
        );
    }
```

- [ ] **Step 6: Update `render_slider_range()`**

Find the whole method:

```php
    private function render_slider_range( ?string $current_min, ?string $current_max ): void {
        $bounds = $this->get_price_bounds();

        $bound_min = (float) $bounds->min_price;
        $bound_max = (float) $bounds->max_price;
        $span      = max( $bound_max - $bound_min, 1 );

        $value_min = null !== $current_min ? (float) $current_min : $bound_min;
        $value_max = null !== $current_max ? (float) $current_max : $bound_max;

        $left  = ( $value_min - $bound_min ) / $span * 100;
        $width = ( $value_max - $value_min ) / $span * 100;

        printf(
            '<div class="ff-price ff-price--slider">
                <div class="ff-price__slider-values">
                    <span data-ff-slider-display="min">$%1$s</span> &ndash; <span data-ff-slider-display="max">$%2$s</span>
                </div>
                <div class="ff-price__slider-track">
                    <div class="ff-price__slider-range" style="left:%3$s%%;width:%4$s%%;"></div>
                    <input type="range" class="ff-price__range" data-ff-price-role="min" min="%5$s" max="%6$s" step="1" value="%7$s" />
                    <input type="range" class="ff-price__range" data-ff-price-role="max" min="%5$s" max="%6$s" step="1" value="%8$s" />
                </div>
                <button type="button" class="ff-price__apply">%9$s</button>
            </div>',
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

Replace with:

```php
    private function render_slider_range( ?string $current_min, ?string $current_max, string $commit_mode ): void {
        $bounds = $this->get_price_bounds();

        $bound_min = (float) $bounds->min_price;
        $bound_max = (float) $bounds->max_price;
        $span      = max( $bound_max - $bound_min, 1 );

        $value_min = null !== $current_min ? (float) $current_min : $bound_min;
        $value_max = null !== $current_max ? (float) $current_max : $bound_max;

        $left  = ( $value_min - $bound_min ) / $span * 100;
        $width = ( $value_max - $value_min ) / $span * 100;

        printf(
            '<div class="ff-price ff-price--slider" data-ff-commit-mode="%10$s">
                <div class="ff-price__slider-values">
                    <span data-ff-slider-display="min">$%1$s</span> &ndash; <span data-ff-slider-display="max">$%2$s</span>
                </div>
                <div class="ff-price__slider-track">
                    <div class="ff-price__slider-range" style="left:%3$s%%;width:%4$s%%;"></div>
                    <input type="range" class="ff-price__range" data-ff-price-role="min" min="%5$s" max="%6$s" step="1" value="%7$s" />
                    <input type="range" class="ff-price__range" data-ff-price-role="max" min="%5$s" max="%6$s" step="1" value="%8$s" />
                </div>
                %9$s
            </div>',
            esc_html( (string) round( $value_min ) ),
            esc_html( (string) round( $value_max ) ),
            esc_attr( (string) round( $left, 4 ) ),
            esc_attr( (string) round( $width, 4 ) ),
            esc_attr( (string) round( $bound_min ) ),
            esc_attr( (string) round( $bound_max ) ),
            esc_attr( (string) round( $value_min ) ),
            esc_attr( (string) round( $value_max ) ),
            'submit' === $commit_mode
                ? '<button type="button" class="ff-price__apply">' . esc_html__( 'Apply', 'filter-forge' ) . '</button>'
                : '',
            esc_attr( $commit_mode )
        );
    }
```

- [ ] **Step 7: Confirm nothing broke**

```bash
npm run test:php -- --filter Test_FF_Filter_State
```
Expected: PASS — unrelated to this file, but confirms the suite still boots (no PHP
parse error introduced).

- [ ] **Step 8: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php
git commit -m "Add commit-mode toggle to Price widget across all three modes"
```

---

### Task 6: `ff-filters.js` — route commits through stage/navigate based on mode

**Files:**
- Modify: `filter-forge/assets/js/ff-filters.js`

**Interfaces:**
- Consumes: `FFUrl.stage()`, `FFUrl.commit()` (Task 2); `data-ff-commit-mode`
  attributes rendered by Tasks 3–5; `data-ff-price-min`/`data-ff-price-max` attributes
  on bucket-list links (Task 5).
- Produces: no new exports — this file only wires DOM event listeners.

- [ ] **Step 1: Branch the generic checkbox/radio/dropdown handler on commit mode**

Find the end of the main `change` listener (the one handling `[data-ff-param]`
checkboxes/radios/selects) in `filter-forge/assets/js/ff-filters.js`:

```js
    if ( list ) {
        const filterKey = list.getAttribute( 'data-ff-filter-key' );
        document.querySelectorAll( '[data-ff-parent-key="' + filterKey + '"][data-ff-reset-on-change="yes"]' ).forEach(
            function ( child ) {
                params = FFUrl.remove( child.getAttribute( 'data-ff-param' ), params );
            }
        );
    }

    FFUrl.navigate( params );
} );
```

Replace the last two lines (`FFUrl.navigate( params );` and the closing `} );`) with:

```js
    const commitScope = input.closest( '[data-ff-commit-mode]' );
    if ( commitScope && 'submit' === commitScope.getAttribute( 'data-ff-commit-mode' ) ) {
        FFUrl.stage( params );
    } else {
        FFUrl.navigate( params );
    }
} );
```

This covers both list-based filters (the `data-ff-commit-mode` attribute lives on the
`.ff-filter` wrapper, an ancestor of `input`) and dropdown-style filters/Orderby (the
attribute lives directly on the `<select>`, which `input` *is* in that branch —
`Element.closest()` matches the element itself first).

- [ ] **Step 2: Branch the Price buckets-dropdown handler on commit mode**

Find:

```js
document.addEventListener( 'change', function ( event ) {
    const bucketSelect = event.target.closest( '.ff-price--buckets-dropdown' );
    if ( bucketSelect ) {
        const [ min, max ] = bucketSelect.value.split( '|' );
        let params = FFUrl.set( 'min_price', min || '' );
        params     = FFUrl.set( 'max_price', max || '', params );

        FFUrl.navigate( params );
        return;
    }
```

Replace with:

```js
document.addEventListener( 'change', function ( event ) {
    const bucketSelect = event.target.closest( '.ff-price--buckets-dropdown' );
    if ( bucketSelect ) {
        const [ min, max ] = bucketSelect.value.split( '|' );
        let params = FFUrl.set( 'min_price', min || '' );
        params     = FFUrl.set( 'max_price', max || '', params );

        if ( 'submit' === bucketSelect.getAttribute( 'data-ff-commit-mode' ) ) {
            FFUrl.stage( params );
        } else {
            FFUrl.navigate( params );
        }
        return;
    }
```

- [ ] **Step 3: Add the Instant-mode auto-commit listener for Price Input/Slider**

Immediately after the `change` listener block from Step 2 (i.e. after its closing
`} );`), add a new, separate listener:

```js
document.addEventListener( 'change', function ( event ) {
    const rangeOrInput = event.target.closest( '.ff-price__range, .ff-price__input' );
    if ( ! rangeOrInput ) {
        return;
    }

    const wrapper = rangeOrInput.closest( '.ff-price--input, .ff-price--slider' );
    if ( ! wrapper || 'submit' === wrapper.getAttribute( 'data-ff-commit-mode' ) ) {
        return;
    }

    ffApplyPriceRange( wrapper );
} );
```

This only fires in Instant mode (Submit mode `return`s early, leaving the existing
Apply-button/Enter-key path as the only way to commit). It reuses `ffApplyPriceRange`,
already defined at the top of this file, which always calls `FFUrl.navigate` directly
— correct here, since Instant mode never stages.

- [ ] **Step 4: Add the Price-buckets-list click interceptor and submit-button click handler**

Find the start of the click listener:

```js
document.addEventListener( 'click', function ( event ) {
    const applyBtn = event.target.closest( '.ff-price__apply' );
```

Replace with:

```js
document.addEventListener( 'click', function ( event ) {
    const commitBtn = event.target.closest( '.ff-filter__submit, .ff-orderby__submit, .ff-apply' );
    if ( commitBtn ) {
        FFUrl.commit();
        return;
    }

    const bucketLink = event.target.closest( '.ff-price__bucket' );
    if ( bucketLink ) {
        const wrapper = bucketLink.closest( '[data-ff-commit-mode]' );
        if ( wrapper && 'submit' === wrapper.getAttribute( 'data-ff-commit-mode' ) ) {
            event.preventDefault();

            let params = FFUrl.set( 'min_price', bucketLink.getAttribute( 'data-ff-price-min' ) || '' );
            params     = FFUrl.set( 'max_price', bucketLink.getAttribute( 'data-ff-price-max' ) || '', params );
            FFUrl.stage( params );

            wrapper.querySelectorAll( '.ff-price__bucket--active' ).forEach( function ( active ) {
                active.classList.remove( 'ff-price__bucket--active' );
            } );
            bucketLink.classList.add( 'ff-price__bucket--active' );
        }
        return;
    }

    const applyBtn = event.target.closest( '.ff-price__apply' );
```

(The rest of the click listener — the `applyBtn` and `clearBtn` handling — is
unchanged.)

- [ ] **Step 5: Commit**

```bash
git add filter-forge/assets/js/ff-filters.js
git commit -m "Route filter commits through stage/navigate based on data-ff-commit-mode"
```

---

### Task 7: New global "Apply Filters" widget

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-apply.php`
- Modify: `filter-forge/includes/class-plugin.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_button_style_controls()` (already exists, used
  by every other widget). `FFUrl.commit()` (Task 2) via the `.ff-apply` click handler
  already added in Task 6, Step 4.
- Produces: `FF_Widget_Apply` class, registered as Elementor widget `ff-apply`.

- [ ] **Step 1: Create the widget class**

Create `filter-forge/includes/widgets/class-widget-apply.php`:

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Apply extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-apply';
    }

    public function get_title(): string {
        return __( 'Apply Filters - Forge', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'ff-icon-anvil';
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'ff_apply_content',
            array(
                'label' => __( 'Content', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_apply_label',
            array(
                'label'   => __( 'Label', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::TEXT,
                'default' => __( 'Apply Filters', 'filter-forge' ),
            )
        );

        $this->end_controls_section();

        $this->register_button_style_controls();
    }

    public function render(): void {
        $settings = $this->get_settings_for_display();

        printf(
            '<button type="button" class="ff-apply">%s</button>',
            esc_html( $settings['ff_apply_label'] ?? __( 'Apply Filters', 'filter-forge' ) )
        );
    }
}
```

`FF_Widget_Apply` extends `FF_Widget_Base` (not `\Elementor\Widget_Base` directly, the
way `FF_Widget_Reset` does) specifically to reuse `register_button_style_controls()`
and the inherited `get_categories()` — it renders a `<button>`, so the shared button
style controls (already targeting `{{WRAPPER}} button:not(.ff-dropdown__trigger)`)
apply correctly with no new style controls needed.

- [ ] **Step 2: Register the widget**

In `filter-forge/includes/class-plugin.php`, find:

```php
    public function register_widgets( $widgets_manager ): void {
        require_once __DIR__ . '/widgets/class-widget-base.php';
        require_once __DIR__ . '/widgets/class-widget-filter.php';
        require_once __DIR__ . '/widgets/class-widget-price.php';
        require_once __DIR__ . '/widgets/class-widget-orderby.php';
        require_once __DIR__ . '/widgets/class-widget-reset.php';

        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
        $widgets_manager->register( new FF_Widget_Orderby() );
        $widgets_manager->register( new FF_Widget_Reset() );
    }
```

Replace with:

```php
    public function register_widgets( $widgets_manager ): void {
        require_once __DIR__ . '/widgets/class-widget-base.php';
        require_once __DIR__ . '/widgets/class-widget-filter.php';
        require_once __DIR__ . '/widgets/class-widget-price.php';
        require_once __DIR__ . '/widgets/class-widget-orderby.php';
        require_once __DIR__ . '/widgets/class-widget-reset.php';
        require_once __DIR__ . '/widgets/class-widget-apply.php';

        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
        $widgets_manager->register( new FF_Widget_Orderby() );
        $widgets_manager->register( new FF_Widget_Reset() );
        $widgets_manager->register( new FF_Widget_Apply() );
    }
```

`class-widget-base.php` is already required first (line 1 of this method), so
`FF_Widget_Apply extends FF_Widget_Base` resolves correctly.

- [ ] **Step 3: Confirm the plugin still boots**

```bash
npm run test:php -- --filter Test_FF_Plugin
```
Expected: PASS — `Test_FF_Plugin` exercises `FF_Plugin::instance()` and its hooks;
this confirms no fatal was introduced by the new `require_once`/registration.

- [ ] **Step 4: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-apply.php filter-forge/includes/class-plugin.php
git commit -m "Add global Apply Filters widget (FF_Widget_Apply)"
```

---

### Task 8: CSS for the new submit/apply buttons

**Files:**
- Modify: `filter-forge/assets/css/ff-filters.css`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed by later tasks — purely visual spacing.

- [ ] **Step 1: Add spacing rules**

Append to the end of `filter-forge/assets/css/ff-filters.css`:

```css

.ff-filter__submit,
.ff-orderby__submit,
.ff-apply {
    margin-top: 0.5em;
}
```

- [ ] **Step 2: Commit**

```bash
git add filter-forge/assets/css/ff-filters.css
git commit -m "Add spacing for new filter submit/apply buttons"
```

---

### Task 9: Manual verification pass

**Files:** none (verification only).

**Interfaces:** exercises the whole system built in Tasks 1–8.

This is the integration gate the spec calls for in place of automated
Elementor-editor-level tests (per `CLAUDE.md`, widget rendering is verified manually
against the running site, not with `WP_UnitTestCase`). Work through it on the running
wp-env site.

- [ ] **Step 1: Run the full PHPUnit suite**

```bash
npm run test:php
```
Expected: all tests pass, including the new `Test_FF_Widget_Base` tests from Task 1.

- [ ] **Step 2: Start the site**

```bash
npm run env:start
```
Visit `http://localhost:8888/wp-admin/plugins.php`, confirm WooCommerce, Elementor,
and Filter Forge are all active and no PHP notices/fatals appear in
`wp-content/debug.log`.

- [ ] **Step 3: Build a test page with mixed commit modes**

Edit an existing WooCommerce archive page (or the Shop page) with Elementor. Add:
- A Filter widget (checkbox style, any taxonomy), Commit Mode = **Instant**.
- A Filter widget (checkbox style, a different taxonomy or the same one with a
  different Filter Key), Commit Mode = **On Submit**.
- A Price widget, Mode = Predefined Buckets (list style), Commit Mode = **On Submit**.
- An Orderby widget, Commit Mode = **On Submit**.
- A global Apply Filters widget, placed anywhere on the page.

- [ ] **Step 4: Verify Instant mode is unchanged (regression check)**

Check a box on the Instant-mode Filter widget. Confirm the page reloads immediately
and the grid updates — identical to pre-existing behavior.

- [ ] **Step 5: Verify Submit-mode staging doesn't reload**

Check a box on the Submit-mode Filter widget. Confirm:
- The address bar updates to include the new param.
- The page does **not** reload (grid/product count stays the same, no network request
  in dev tools).
- A local "Apply" button is visible on that widget.

Select a bucket on the Price widget and an option on the Orderby dropdown the same
way — confirm both stage into the address bar without reloading, and each renders its
own local submit button.

- [ ] **Step 6: Verify local submit buttons commit everything staged**

With the Submit-mode Filter checkbox, Price bucket, and Orderby selection all staged
from Step 5, click the *Price widget's* local Apply button. Confirm the page reloads
and the URL/grid reflect **all three** staged changes, not just the price range —
confirming the accepted page-wide commit behavior (design spec §2).

- [ ] **Step 7: Verify the global Apply Filters widget**

Repeat Step 5 (stage a Submit-mode filter change without committing), then click the
global Apply Filters widget instead of a local button. Confirm it commits the staged
change identically. Click it again with nothing staged — confirm it's a harmless
reload (same URL, same grid).

- [ ] **Step 8: Verify back-button history behavior matches Instant mode**

From a page with a couple of Instant-mode commits already in browser history, perform
one Submit-mode commit (stage + Apply). Click the browser Back button once. Confirm it
lands on the *previous filter state* (one commit back), not on whatever page was open
before any filtering started — confirming `FFUrl.commit()`'s `navigate()` delegation
(not `reload()`) preserved normal history behavior.

- [ ] **Step 9: Verify Price Input/Slider modes in both commit modes**

Temporarily switch the Price widget to Mode = Slider:
- Commit Mode = Instant: confirm no Apply button renders, dragging a handle doesn't
  commit mid-drag, and releasing the handle commits immediately.
- Commit Mode = On Submit: confirm the Apply button renders and behaves exactly as it
  did before this feature (dragging doesn't commit, only clicking Apply or pressing
  Enter does).

- [ ] **Step 10: Verify Price Buckets (list) mode in Submit mode**

With the Price widget's Buckets (list) style and Commit Mode = On Submit, click a
bucket link. Confirm the browser does **not** navigate (no full page load), the
clicked bucket visually becomes active (`.ff-price__bucket--active`), and the change
is staged into the address bar. Click Apply (or the global widget) to confirm it
commits correctly.

- [ ] **Step 11: Verify existing relationship/reset behavior is unaffected**

Using a parent/child Filter widget pair (both left in Instant mode), confirm
reset-on-parent-change and hide-until-parent-selected still work exactly as before.
Click the existing Reset widget from a page with several staged and committed filters
— confirm it still returns to the canonical URL with no query string.

- [ ] **Step 12: Record results and fix any issues found**

Note anything that didn't behave as expected. Fix issues directly, re-run the relevant
PHPUnit tests if a fix touches tested code (Task 1), then re-verify manually.

- [ ] **Step 13: Commit any fixes made during verification**

```bash
git add -A
git commit -m "Fix issues found during manual verification of filter commit mode"
```
