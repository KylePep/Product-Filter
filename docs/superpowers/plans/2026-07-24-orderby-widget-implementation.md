# Orderby Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a new Elementor widget, `FF_Widget_Orderby` ("Sort By - Forge"), that
renders WooCommerce's native product sort options through the plugin's existing
custom accessible dropdown component (`ff-dropdown.js`) instead of a plain `<select>`.

**Architecture:** Zero custom query-filtering code — WooCommerce's own
`WC_Query::order_by()` already reads `$_GET['orderby']` on the main product query and
applies it (native `menu_order`/`popularity`/`rating`/`date`/`price`/`price-desc`
values). The widget only renders a `<select data-ff-param="orderby">` whose options
come from WooCommerce's own `woocommerce_catalog_orderby` filter, then reuses the
plugin's existing generic dropdown-enhancement JS and generic `[data-ff-param]`
change handler — both already handle any `<select>` with no changes needed beyond
adding one CSS class to their selector lists.

**Tech Stack:** PHP (Elementor widget class), vanilla JS (no framework/build step),
plain CSS.

## Global Constraints

- No AJAX — selecting an option reloads the page via `FFUrl.navigate()`, same as
  every other Filter Forge control.
- No build step for JS/CSS — edit `assets/js/ff-dropdown.js` and
  `assets/css/ff-filters.css` directly.
- Per `CLAUDE.md`, Elementor widget classes (`includes/widgets/`) are **not**
  PHPUnit-tested — `\Elementor\Widget_Base` needs editor context PHPUnit can't
  provide. Task 1 substitutes `php -l` syntax checks for the "write failing test"
  cycle; the final task is a full manual verification pass against the running
  wp-env site.
- New files under `includes/` need an explicit `require_once` in `class-plugin.php`
  — there is no autoload step. Widget files are `require_once`d lazily inside
  `register_widgets()`, matching the existing three widgets.
- Options are sourced live from WooCommerce's `woocommerce_catalog_orderby` filter,
  not a Filter-Forge-defined array — see the approved design spec
  (`docs/superpowers/specs/2026-07-24-orderby-widget-design.md`), §3.
- No Elementor repeater for customizing the option list, no custom `ordr`-style
  param, no non-native sort values (e.g. oldest-first) — per the approved design
  spec, §12.

---

### Task 1: Create `FF_Widget_Orderby` and register it

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-orderby.php`
- Modify: `filter-forge/includes/class-plugin.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_header_controls()`,
  `::render_header()`, `::register_text_style_controls()`,
  `::register_header_style_controls()`, `::register_dropdown_style_controls( array $condition )`
  (all existing, in `filter-forge/includes/widgets/class-widget-base.php`);
  `FF_Query_Manager::is_supported_archive()`; `FF_Plugin::instance()->filter_state->get( string $key ): ?string`.
- Produces: widget name `ff-orderby`, registered in `FF_Plugin::register_widgets()`.
  Nothing else in this plan depends on this task's internals beyond the CSS class
  name `ff-orderby--dropdown` on the rendered `<select>`, which Task 2 consumes.

- [ ] **Step 1: Write the widget class**

Create `filter-forge/includes/widgets/class-widget-orderby.php`:

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Orderby extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-orderby';
    }

    public function get_title(): string {
        return __( 'Sort By - Forge', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'eicon-sort';
    }

    protected function register_controls(): void {
        $this->register_header_controls();

        $this->register_text_style_controls();
        $this->register_header_style_controls();
        $this->register_dropdown_style_controls( array() );
    }

    public function render(): void {
        if ( ! FF_Query_Manager::is_supported_archive() ) {
            $this->render_unsupported_page_notice();
            return;
        }

        $this->render_header();

        $options = $this->get_orderby_options();
        $current = FF_Plugin::instance()->filter_state->get( 'orderby' );

        if ( null === $current ) {
            $current = (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
        }

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

    /**
     * Sourced from WooCommerce's own filter (the same one its native
     * woocommerce_catalog_orderby() template function applies) so the option
     * set/labels/translations always match site config and any other plugin's
     * additions, instead of a Filter-Forge-defined list that could drift.
     */
    private function get_orderby_options(): array {
        $options = apply_filters(
            'woocommerce_catalog_orderby',
            array(
                'menu_order' => __( 'Default sorting', 'woocommerce' ),
                'popularity' => __( 'Sort by popularity', 'woocommerce' ),
                'rating'     => __( 'Sort by average rating', 'woocommerce' ),
                'date'       => __( 'Sort by latest', 'woocommerce' ),
                'price'      => __( 'Sort by price: low to high', 'woocommerce' ),
                'price-desc' => __( 'Sort by price: high to low', 'woocommerce' ),
            )
        );

        if ( ! wc_review_ratings_enabled() ) {
            unset( $options['rating'] );
        }

        return $options;
    }

    private function render_unsupported_page_notice(): void {
        if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return;
        }

        echo '<p class="ff-orderby__notice">' . esc_html__( 'Filter Forge: this widget only renders on a WooCommerce archive page (Shop, category, tag, or attribute archive).', 'filter-forge' ) . '</p>';
    }
}
```

- [ ] **Step 2: Syntax-check the new file**

Run: `php -l "filter-forge/includes/widgets/class-widget-orderby.php"`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-orderby.php`

- [ ] **Step 3: Register the widget in `class-plugin.php`**

In `filter-forge/includes/class-plugin.php`, find:

```php
    public function register_widgets( $widgets_manager ): void {
        require_once __DIR__ . '/widgets/class-widget-base.php';
        require_once __DIR__ . '/widgets/class-widget-filter.php';
        require_once __DIR__ . '/widgets/class-widget-price.php';
        require_once __DIR__ . '/widgets/class-widget-reset.php';

        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
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

        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
        $widgets_manager->register( new FF_Widget_Orderby() );
        $widgets_manager->register( new FF_Widget_Reset() );
    }
```

- [ ] **Step 4: Syntax-check `class-plugin.php`**

Run: `php -l "filter-forge/includes/class-plugin.php"`
Expected: `No syntax errors detected in filter-forge/includes/class-plugin.php`

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-orderby.php filter-forge/includes/class-plugin.php
git commit -m "Add Orderby widget rendering WooCommerce's native sort options"
```

---

### Task 2: Enhance the orderby `<select>` into the custom dropdown

**Files:**
- Modify: `filter-forge/assets/js/ff-dropdown.js`
- Modify: `filter-forge/assets/css/ff-filters.css`

**Interfaces:**
- Consumes: the `select.ff-orderby--dropdown` class name from Task 1's rendered
  markup, and the existing `enhance( select )` function / `commit()` behavior in
  `ff-dropdown.js` (unchanged — it already dispatches a `change` event on the native
  select, which the existing generic handler in `ff-filters.js` picks up via that
  select's `data-ff-param="orderby"` attribute; no changes to `ff-filters.js` are
  needed or made by this task).
- Produces: nothing consumed by later tasks (Task 3 is manual verification only).

- [ ] **Step 1: Add the new selector to `ff-dropdown.js`'s enhance list**

In `filter-forge/assets/js/ff-dropdown.js`, find:

```javascript
    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'select.ff-filter--dropdown, select.ff-price--buckets-dropdown' ).forEach( enhance );
    } );
```

Replace with:

```javascript
    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'select.ff-filter--dropdown, select.ff-price--buckets-dropdown, select.ff-orderby--dropdown' ).forEach( enhance );
    } );
```

- [ ] **Step 2: Sanity-check the JS parses**

Run: `node --check filter-forge/assets/js/ff-dropdown.js`
Expected: no output (silence means the file parsed cleanly).

- [ ] **Step 3: Add the base layout rule for the new class in `ff-filters.css`**

In `filter-forge/assets/css/ff-filters.css`, find:

```css
select.ff-filter--dropdown {
    display: block;
    width: 100%;
    max-width: 100%;
}
```

Replace with:

```css
select.ff-filter--dropdown,
select.ff-orderby--dropdown {
    display: block;
    width: 100%;
    max-width: 100%;
}
```

- [ ] **Step 4: Add the new class to the early native-select-hide rule**

In the same file, find:

```css
.ff-js select.ff-filter--dropdown,
.ff-js select.ff-price--buckets-dropdown {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
}
```

Replace with:

```css
.ff-js select.ff-filter--dropdown,
.ff-js select.ff-price--buckets-dropdown,
.ff-js select.ff-orderby--dropdown {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
}
```

- [ ] **Step 5: Sanity-check the CSS file is well-formed**

Run: `node -e "require('fs').readFileSync('filter-forge/assets/css/ff-filters.css','utf8')" && echo OK`
Expected: `OK`

- [ ] **Step 6: Commit**

```bash
git add filter-forge/assets/js/ff-dropdown.js filter-forge/assets/css/ff-filters.css
git commit -m "Enhance the orderby dropdown with the shared custom dropdown component"
```

---

### Task 3: Manual end-to-end verification against the running site

**Files:** none (verification only).

**Interfaces:** none — this task consumes the finished feature from Tasks 1–2 and
produces nothing further.

- [ ] **Step 1: Start the dev environment (skip if already running)**

Run: `npm run env:start`
Expected: containers start; site reachable at `http://localhost:8888`.

- [ ] **Step 2: Add the widget to a WooCommerce archive page**

In the Elementor editor, edit the Shop page (or a product category archive), remove
or leave in place any existing native WooCommerce/other-plugin sort dropdown, and add
the "Sort By - Forge" widget from the Filter Forge category. Publish/update the page.

- [ ] **Step 3: Verify it renders as the custom dropdown, not a raw select**

Load the published page on the front end. Confirm:
- A styled trigger button with a caret is shown (not a plain browser `<select>`).
- Clicking it opens a panel listing WooCommerce's native sort options (Default
  sorting, Popularity, Rating [if the site has ratings enabled], Latest, Price low to
  high, Price high to low).
- If reviews/ratings are disabled in WooCommerce settings (WooCommerce → Settings →
  Products → Reviews), confirm "Sort by average rating" is absent from the list.

- [ ] **Step 4: Verify selecting an option sorts the grid**

Select "Price: high to low". Confirm:
- The page reloads with `?orderby=price-desc` in the URL.
- The Elementor Loop Grid / Products widget (Main Query mode) now shows products in
  descending price order.
- Reopening the dropdown shows "Price: high to low" as the current selection
  (`aria-selected="true"`, bold per the existing dropdown option styling), and the
  underlying native `<select>`'s value matches.

- [ ] **Step 5: Verify keyboard interaction matches the existing dropdown widgets**

With the trigger focused, press Enter/Space to open the panel, use Arrow Up/Down to
move between options, press Enter to commit a selection, and press Escape to close
without changing anything. Confirm this behaves identically to the Filter widget's
existing dropdown display style (same `ff-dropdown.js` enhancer).

- [ ] **Step 6: Verify it composes with other Filter Forge filters**

On a page with both this widget and at least one Filter widget (e.g. a category or
attribute filter), select a filter option, then change the sort order. Confirm both
the filter's query param and `orderby` are present in the URL simultaneously, and the
grid reflects both the filter and the sort order together.

- [ ] **Step 7: Verify the unsupported-page notice**

In the Elementor editor, add the widget to a non-archive page (e.g. a regular Page).
Confirm the "Filter Forge: this widget only renders on a WooCommerce archive page..."
notice appears in the editor. Then check that same page on the live front end (not
in the editor) and confirm the widget renders nothing there.

- [ ] **Step 8: Note completion**

No commit for this task (verification only). If any check in Steps 3–7 fails, file it
as a bug against the specific task whose code is responsible and fix there before
considering the plan complete.
