# Filter Forge — Design Spec

**Date:** 2026-07-14
**Status:** Approved for planning

## 1. Purpose & Goals

Filter Forge is a WordPress plugin providing native Elementor Pro widgets for filtering
WooCommerce products, built to replace paid/free filter plugins (e.g. Filter Everything,
JetSmartFilters) that are hard to style and lack configurable parent/child filter
relationships. It targets the plugin author's own client sites (built with Elementor Pro
base tier + WooCommerce), intended for reuse across multiple client installs.

Core goals:
- Full Elementor style controls on every filter input (typography, color, spacing,
  responsive) — the primary pain point with existing free plugins.
- Zero coupling to product rendering. The plugin only modifies the WordPress main query;
  Elementor's own Loop Grid / Products widget (in Archive / Main Query mode) renders
  results unmodified, on a normal page reload.
- Configurable parent/child relationships between filters — resetting a child's
  selection when its parent changes, and hiding a child filter until its parent has an
  active selection. (Automatic per-option narrowing via faceted counting is not part of
  this relationship system — see §5.)
- Reuse WooCommerce's native query-var filtering conventions wherever they already
  exist; add custom query logic only where WooCommerce has no native support.

### Out of scope for v1
- AJAX / no-reload filtering.
- Filtering non-main-query Loop Grids (standalone curated grids on arbitrary pages).
- A private plugin-update server / auto-updates (manual install/update per site for now).
- An "active filters" chip/tag display widget.
- Automated Elementor-editor-level browser tests.

## 2. Naming

- Plugin name: **Filter Forge**
- Text domain: `filter-forge`
- PHP class/function prefix: `FF_`
- CSS/JS class prefix: `ff-`

## 3. Plugin Architecture

```
filter-forge/
├── filter-forge.php                      # bootstrap, plugin header, requirements check
├── includes/
│   ├── class-plugin.php                  # singleton bootstrap, hook registration
│   ├── widgets/
│   │   ├── class-widget-filter.php       # attribute / category / tag / custom-field
│   │   ├── class-widget-price.php        # slider or predefined buckets
│   │   └── class-widget-reset.php        # reset-to-archive-root
│   ├── query/
│   │   ├── class-query-router.php        # reads $_GET on the frontend, dispatches
│   │   ├── class-native-query.php        # pass-through/reference to WC's own filter_pa_*,
│   │   │                                 #   min_price/max_price, filter_stock_status,
│   │   │                                 #   rating_filter handling
│   │   └── class-custom-query.php        # pre_get_posts additions: product_cat/tag as
│   │                                     #   filters, ACF/meta filters, price-bucket
│   │                                     #   translation to min_price/max_price
│   ├── relationships/
│   │   └── class-relationship-resolver.php  # parent/child: reset-on-change, hide-until-selected
│   ├── counts/
│   │   └── class-option-counts.php       # per-option product counts under current filter
│   │                                     #   state, request-scoped cache
│   └── admin/
│       └── class-requirements-notice.php # admin notice if WC/Elementor Pro missing
├── assets/
│   ├── src/                              # JS/SCSS source
│   └── build/                            # compiled output
├── languages/
└── filter-forge.pot
```

**Requirements gate:** on `plugins_loaded`, check WooCommerce and Elementor Pro are both
active. If either is missing, show an admin notice and do not register any widgets or
hooks — no fatals.

**Hooking strategy:** on `pre_get_posts`, only for the main query on a supported
WooCommerce archive (`is_post_type_archive('product')` or `is_tax()` on a WooCommerce
product taxonomy), Filter Forge adds its *custom* query modifications (category/tag as
filters beyond the archive's own term, custom-field/meta filters, price-bucket
translation). WooCommerce's own `pre_get_posts` handlers are left to act on native query
vars — Filter Forge does not duplicate that logic.

## 4. Widgets

### 4.1 Filter widget (attribute / category / tag / custom field)

One widget class covers all "list-shaped" filter sources, since they share ~90% of
behavior (option list, counts, display styles, relationship config).

Controls:
- **Source**: Attribute (+ which `pa_` taxonomy) / Category / Tag / Custom Field
  (+ meta key, comparison type: exact match / numeric range / boolean)
- **Display Style**: Checkbox list, Radio (single-select), Dropdown, Swatches
  (color/image — only for sources with image/color data), Toggle (binary meta, e.g.
  on-sale)
- **Show counts** toggle
- **Hide zero-result options** toggle
- **Filter Key**: auto-slugged identifier (editable), e.g. `color`. Since the plugin
  uses a single global filter set per page (no Elementor Query-ID-style linking),
  Filter Key is how relationships between widgets are declared.
- **Parent Filter Key**: select another Filter Key present in the page design.
- Relationship toggles (shown once a Parent Filter Key is set):
  - **Reset on parent change** — clears this filter's own selected value when the
    parent's value changes.
  - **Hide until parent has a selection** — this widget doesn't render at all until
    its designated parent has an active value.

### 4.2 Price widget

- **Mode**: Slider (dynamic min/max derived from actual product prices) or Predefined
  Buckets (repeater of label + min + max; the last row supports an open-ended
  "& above").
- Same Filter Key / Parent Filter Key / relationship toggles as the Filter widget, so
  Price can be a child of e.g. Category.
- Bucket mode translates the selected bucket into `min_price`/`max_price` before
  WooCommerce's own price-filter logic runs, so no divergent query path is needed.

### 4.3 Reset widget

- No source/style controls — just link/button style controls.
- Behavior: strips *all* query args (every filter, sort, and pagination) and navigates
  to the archive's canonical URL (e.g. `/product-category/airsoft-guns/`).

### 4.4 Binary filters and clearing

- Binary/toggle filters (e.g. "In stock only") need no separate clear affordance — the
  toggle itself conveys and reverses state.
- All other (non-binary) filter widgets provide their own per-widget clear affordance
  (e.g. an inline "clear" control within that specific widget).
- No global "active filters" chip/tag display widget in v1.

## 5. Relationship Model vs. Faceted Counting

These are two distinct mechanisms and should not be conflated:

- **Faceted counting (automatic, always on):** every filter widget computes its own
  option list/counts against products matching *all other currently active filters* —
  the same approach WooCommerce's own layered nav widget uses. This is what makes
  selecting Category "Pistols" automatically narrow the Brand filter's options/counts
  to only brands relevant to pistols (irrelevant brands show `(0)` and are hidden per
  the "hide zero-result options" toggle). This requires no parent/child configuration —
  it applies to every filter combination on the page by default.
- **Parent/child relationship config (explicit, opt-in per pair):** only governs
  (a) resetting a child's own selected value when its designated parent changes, and
  (b) hiding a child widget entirely until its designated parent has a selection.

## 6. Query Layer & Data Flow

Request flow on page load:

1. WordPress resolves the archive (Shop / category / tag / attribute archive).
2. `pre_get_posts` fires on the main query. WooCommerce's own handlers act on native
   vars (`filter_pa_*`, `min_price`/`max_price`, `filter_stock_status`,
   `rating_filter`) — Filter Forge does not touch these.
3. `class-custom-query.php` adds to the same main query: category/tag-as-filter params,
   custom-field/meta filters, and translates a selected price bucket into
   `min_price`/`max_price` ahead of WooCommerce's own logic.
4. Elementor's Loop Grid / Products widget (Main Query mode) renders normally, untouched.
5. Each Filter Forge widget, at render time, reads `$_GET` to: mark its own selected
   option(s), compute per-option counts via faceted counting (§5), hide zero-result
   options, and check its Parent Filter Key's presence/value to decide whether to
   render (hide-until-selected).

**URL shape:** a plain query string appended to the archive's canonical path, e.g.

```
/product-category/airsoft-guns/?filter_pa_color=black&min_price=50&max_price=200&ff_brand=krytac
```

Native WooCommerce vars keep WooCommerce's own names; Filter Forge's custom vars use an
`ff_` prefix to avoid collisions.

**Client-side JS (vanilla, no framework, no AJAX):** on any filter input change, build
the new query string from all currently-rendered filter widgets' current state, strip
params for any child whose configured parent just changed (reset-on-change), then
navigate via `window.location.href`.

**Counts performance:** counts are computed with `WP_Query` (`fields => 'ids'`) per
option, mirroring WooCommerce's own layered-nav counting approach, with a
request-scoped static cache keyed by taxonomy/meta + term so the same option's count
isn't recomputed if referenced by more than one widget on the page.

## 7. Query Scope

Filter Forge only supports filtering the **main query on WooCommerce archive pages**
(Shop, category/tag/attribute archives) in v1. It does not support filtering standalone
Elementor Loop Grids with custom (non-main) queries placed on arbitrary pages. A single
global filter set applies per page — there is no Query-ID-style linking to target
multiple grids on one page.

## 8. Error Handling & Edge Cases

- **Missing dependencies:** if WooCommerce or Elementor Pro is inactive, widgets don't
  register; an admin notice explains what's missing. No fatals.
- **Invalid/tampered query params:** unknown taxonomy/meta keys or malformed price
  values in `$_GET` are silently ignored rather than erroring.
- **Orphaned Parent Filter Key:** if a child references a Filter Key not present
  elsewhere on the page, the child behaves as if it has no parent (renders normally,
  no hide/reset logic), plus an editor-only (not front-end) notice so the site builder
  notices the misconfiguration.
- **Zero results after filtering:** left entirely to Elementor's Loop Grid's own empty
  state — Filter Forge does not build one, consistent with not touching rendering.
- **Non-archive / unsupported pages:** a Filter Forge widget placed where the main
  query isn't a supported product query renders an editor-only notice and outputs
  nothing on the live site.

## 9. Testing Approach

- **Unit tests (PHPUnit + WP test suite):** query-building logic
  (`class-custom-query.php`, price-bucket-to-min/max translation, and the relationship
  resolver's reset/hide decisions) — pure logic, testable without a browser.
- **Manual/integration verification:** final verification on a real WP install with
  Elementor Pro + WooCommerce + sample products, since this deeply integrates with
  Elementor's editor and WooCommerce's live query behavior. No automated
  Elementor-editor-level browser tests in v1.

## 10. Distribution

Manually installed per client site as a standard plugin zip; updates pushed manually.
No auto-update infrastructure in v1.
