# Orderby Widget — Design Spec

**Date:** 2026-07-24
**Status:** Approved for planning

## 1. Purpose

Replace a competing plugin's native `<select name="ordr">` sort-order dropdown with a
Filter Forge Elementor widget, `FF_Widget_Orderby`, that renders the same custom
accessible dropdown UI (`ff-dropdown.js`) already used by the Filter and Price
widgets' dropdown modes — instead of a plain unstyled `<select>`.

## 2. Architecture — zero custom query code

WooCommerce's `WC_Query::order_by()` already reads `$_GET['orderby']` on the main
product query (on `is_supported_archive()` pages) and translates it via its own
`get_catalog_ordering_args()` — `menu_order`, `popularity`, `rating`, `date`, `price`,
`price-desc` are all handled natively. This widget therefore needs no
`FF_*_Filter`/`pre_get_posts` service class of its own: it only renders a `<select>`
carrying the native `orderby` param and lets WooCommerce's existing main-query
handling do the sorting, the same "reuse WooCommerce's native query layer" principle
the Price widget already follows for `min_price`/`max_price`.

## 3. Option list

Sourced directly from WooCommerce's own `woocommerce_catalog_orderby` filter — the
same hook the native `woocommerce_catalog_orderby()` template function applies —
rather than a Filter-Forge-defined array:

```php
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
```

This keeps the option set (including labels/translations) automatically in sync with
site config and with any other plugin that hooks `woocommerce_catalog_orderby` to add
its own sort option. No Elementor repeater control for customizing options — a fixed,
WooCommerce-sourced list only, per the approved design discussion (YAGNI: this plugin
does not need per-instance sort customization).

## 4. Selected value

```php
$current = FF_Plugin::instance()->filter_state->get( 'orderby' );
if ( null === $current ) {
    $current = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
}
```

`FF_Filter_State::get()` already reads arbitrary GET keys generically (confirmed by
the Price widget's existing `min_price`/`max_price` reads) — no change to
`FF_Filter_State` needed.

## 5. Markup

```html
<select class="ff-orderby ff-orderby--dropdown" data-ff-param="orderby">
    <option value="menu_order" selected>Default sorting</option>
    <option value="popularity">Sort by popularity</option>
    <option value="rating">Sort by average rating</option>
    <option value="date">Sort by latest</option>
    <option value="price">Sort by price: low to high</option>
    <option value="price-desc">Sort by price: high to low</option>
</select>
```

Same shape as `FF_Widget_Filter::render_dropdown()`, minus the relationship/filter-key
wrapper attributes (`data-ff-filter-key`, `data-ff-parent-key`,
`data-ff-reset-on-change`) — sorting has no parent/child relationship concept, so
`FF_Widget_Base::register_relationship_controls()` is not used by this widget. No "All"
placeholder option (unlike the Filter widget's dropdown) — sorting always has a
concrete selected value, never an empty one.

## 6. Client-side wiring — no new JS logic

- `assets/js/ff-dropdown.js`: add `select.ff-orderby--dropdown` to the
  `querySelectorAll` enhance-selector list (currently
  `'select.ff-filter--dropdown, select.ff-price--buckets-dropdown'`). This is the only
  JS change — the same `enhance()` function already builds the accessible
  trigger/panel/keyboard-nav UI for any matching `<select>`.
- `assets/js/ff-filters.js`: **no change**. The existing generic
  `document.addEventListener('change', ...)` handler already special-cases only
  `.ff-price--buckets-dropdown` (which encodes `min|max` into its value); every other
  `[data-ff-param]` element — including any `<select>`, via the
  `input.tagName === 'SELECT'` branch — is handled by the generic path: read
  `data-ff-param`, `FFUrl.set()` that param to the select's value, `FFUrl.navigate()`.
  Since `commit()` in `ff-dropdown.js` dispatches `change` on the real (hidden) native
  `<select>` — which carries `data-ff-param="orderby"` — this fires correctly with zero
  additions.

## 7. CSS

`assets/css/ff-filters.css` gets the new selector added alongside the two existing
dropdown selectors, in both places they currently appear:

```css
select.ff-filter--dropdown,
select.ff-price--buckets-dropdown,
select.ff-orderby--dropdown {
    display: block;
    width: 100%;
    max-width: 100%;
}

/* early native-select-hide rule, keyed off .ff-js (see FF_Plugin::print_early_js_class) */
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

No other new rules — `.ff-dropdown`, `.ff-dropdown__trigger`, `.ff-dropdown__panel`,
`.ff-dropdown__option` etc. are shared/generic and already apply to any enhanced
dropdown regardless of which widget rendered the underlying `<select>`.

## 8. Elementor controls

**Content tab:**
- `register_header_controls()` only (optional "Sort by" label above the dropdown, off
  by default — same opt-in pattern as the Filter/Price widgets' headers).
- No source-type picker, no display-style picker (dropdown is the only shape this
  widget renders), no "Show Clear" control (sorting always has a value; the existing
  `FF_Widget_Reset` widget already resets `orderby` back to none along with every
  other param, since it links to the canonical URL with the entire query string
  stripped — no widget-specific clear behavior needed).
- No `register_relationship_controls()` — sorting has no parent/child filter concept.

**Style tab:**
- `register_text_style_controls()`
- `register_header_style_controls()`
- `register_dropdown_style_controls( array() )` — called with an empty condition array
  since, unlike the Filter/Price widgets, the dropdown here isn't one of several
  selectable display styles; it's unconditionally the only output this widget
  produces.

## 9. Gating

Same `FF_Query_Manager::is_supported_archive()` check used by `FF_Widget_Filter` and
`FF_Widget_Price`: if the current page isn't a supported WooCommerce archive, the
widget renders nothing on the live site and an editor-only notice
("Filter Forge: this widget only renders on a WooCommerce archive page...") in the
Elementor editor.

## 10. Files touched

- **New:** `includes/widgets/class-widget-orderby.php` (`FF_Widget_Orderby`, widget
  name `ff-orderby`, title "Sort By - Forge", icon `eicon-sort`).
- `includes/class-plugin.php`: `require_once` the new widget file and register it in
  `register_widgets()`, alongside the other three.
- `assets/js/ff-dropdown.js`: add the new selector to the enhance list (§6).
- `assets/css/ff-filters.css`: add the new selector to the two existing dropdown rules
  (§7).

## 11. Testing

No PHPUnit test file. Per `CLAUDE.md`, Elementor widget classes are not unit tested —
`\Elementor\Widget_Base` needs editor/document context `WP_UnitTestCase` doesn't
provide. Verified manually against the running wp-env site instead:

- On a WooCommerce archive page, the widget renders the custom dropdown (trigger +
  caret), not a raw `<select>`.
- Options match the site's native "Sort by" choices (including Popularity/Rating
  presence matching whatever the site's rating-reviews setting is).
- Selecting an option reloads the page with `?orderby=<value>` and the product grid
  re-sorts accordingly — confirming WooCommerce's native handling picks it up with no
  Filter Forge query code involved.
- Keyboard interaction (arrow keys, Enter, Escape, type-ahead) works identically to
  the existing Filter-widget dropdown, since it's the same `ff-dropdown.js` enhancer.
- Combining with other Filter Forge filters (e.g. a category filter) preserves both
  the filter's param and `orderby` across navigation (`FFUrl.set` only touches the one
  key it's given).
- On a non-archive page, the widget is empty on the live site and shows the
  unsupported-page notice in the Elementor editor.

## 12. Out of scope

- No Elementor repeater for customizing the option list — fixed, WooCommerce-sourced
  options only (per §3, and the approved design discussion).
- No custom `ordr`-style param or non-native sort values (e.g. oldest-first) — only
  what WooCommerce's own `orderby` handling natively understands (per the approved
  design discussion).
- No changes to `FF_Widget_Reset` — it already clears `orderby` implicitly by
  stripping the whole query string.
