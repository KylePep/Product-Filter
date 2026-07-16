# Filter Forge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Filter Forge — a WordPress plugin providing native Elementor Pro
widgets (Filter, Price, Reset) that filter WooCommerce products on archive pages by
modifying the main query, with zero coupling to product rendering.

**Architecture:** A `FF_Plugin` singleton wires together a small set of services
(`FF_Filter_State`, `FF_Query_Manager`, `FF_Count_Service`, `FF_Relationship_Resolver`)
and registers three Elementor widgets. Query modification happens via `pre_get_posts`
on the WooCommerce main query, reusing WooCommerce's own native query vars
(`filter_pa_*`, `min_price`/`max_price`, `filter_stock_status`, `rating_filter`)
wherever they exist, adding custom `ff_`-prefixed params only where WooCommerce has no
native equivalent (non-attribute taxonomies, custom-field meta). See the approved spec:
`docs/superpowers/specs/2026-07-14-filter-forge-design.md`.

**Tech Stack:** PHP 7.4+, WordPress, WooCommerce, Elementor (Pro on live sites; the
free Elementor plugin is sufficient for dev/test since only the free widget-registration
API is used), PHPUnit + WP core test suite, `@wordpress/env` (wp-env/Docker) for local
development and CI-less testing, vanilla JS (no build step, no framework) for the
front end.

## Global Constraints

- PHP class/function prefix: `FF_`. CSS/JS class prefix: `ff-`. Text domain: `filter-forge`.
- No AJAX / no-reload filtering — page reload only, on every filter change (not batched).
- No support for filtering non-main-query Loop Grids — only the main query on
  WooCommerce archive pages (Shop, category/tag/attribute archives).
- One global filter set per page — no Elementor Query-ID-style linking between widgets.
- Reuse WooCommerce's native query vars wherever they exist; custom `ff_`-prefixed
  query logic only where WooCommerce has none.
- Relationship links between filters are stored by human-readable **Filter Key**, not
  a stable internal ID (accepted v1 trade-off — renaming a referenced key silently
  breaks the relationship, caught only by an editor-only notice).
- No custom cache abstraction — use WordPress's built-in `wp_cache_get`/`wp_cache_set`.
- No DI container — shared services live as public properties on the `FF_Plugin`
  singleton.
- No automated Elementor-editor-level browser tests — widget rendering is verified
  manually against a real wp-env site with WooCommerce + Elementor + sample products.

---

## Repository Layout

```
Product Filter/                          (git repo root)
├── docs/superpowers/{specs,plans}/       (already exists)
├── filter-forge/                         (the plugin — this is what ships)
│   ├── filter-forge.php
│   ├── composer.json
│   ├── phpunit.xml.dist
│   ├── includes/
│   │   ├── class-plugin.php
│   │   ├── widgets/
│   │   │   ├── class-widget-base.php
│   │   │   ├── class-widget-filter.php
│   │   │   ├── class-widget-price.php
│   │   │   └── class-widget-reset.php
│   │   ├── providers/
│   │   │   ├── interface-option-provider.php
│   │   │   ├── class-taxonomy-provider.php
│   │   │   └── class-meta-provider.php
│   │   ├── services/
│   │   │   ├── class-filter-state.php
│   │   │   ├── class-query-manager.php
│   │   │   ├── class-category-filter.php
│   │   │   ├── class-meta-filter.php
│   │   │   ├── interface-count-provider.php
│   │   │   ├── class-count-service.php
│   │   │   └── class-relationship-resolver.php
│   │   └── admin/
│   │       └── class-requirements-notice.php
│   ├── assets/
│   │   ├── js/
│   │   │   ├── ff-url.js
│   │   │   └── ff-filters.js
│   │   └── css/
│   │       └── ff-filters.css
│   ├── languages/
│   └── tests/
│       ├── bootstrap.php
│       └── test-*.php
├── .wp-env.json
└── package.json
```

**A naming detail resolved during planning (not in the original spec text, but implied
by it):** the "Taxonomy" source type in the Filter widget (§4.1 of the spec) covers
WooCommerce attributes (`pa_*`), categories, tags, and any custom product taxonomy
(e.g. `product_brand`). The query param each one uses depends on whether WooCommerce
already understands it natively:

- **WooCommerce attribute taxonomies** (anything returned by
  `wc_get_attribute_taxonomy_names()`) use the **native** `filter_{taxonomy}` param
  (e.g. `filter_pa_color`) — WooCommerce's own `pre_get_posts` handling applies, Filter
  Forge does nothing.
- **Every other taxonomy** (`product_cat`, `product_tag`, custom ones like
  `product_brand`) uses a Filter-Forge-owned `ff_tax_{taxonomy}` param, handled by
  `FF_Category_Filter` (Task 6).
- **Meta/custom-field filters** use `ff_{meta_key}` directly (§ URL shape in the spec),
  handled by `FF_Meta_Filter` (Task 7). `FF_Meta_Filter` treats *any* `ff_` param that
  isn't `ff_tax_*` as a meta-key filter, so the two conventions never collide.

---

### Task 1: Dev environment, plugin scaffold, and test harness

**Files:**
- Create: `.wp-env.json`
- Create: `package.json`
- Create: `filter-forge/filter-forge.php`
- Create: `filter-forge/composer.json`
- Create: `filter-forge/phpunit.xml.dist`
- Create: `filter-forge/tests/bootstrap.php`
- Create: `filter-forge/includes/class-plugin.php`
- Test: `filter-forge/tests/test-class-plugin.php`

**Interfaces:**
- Produces: `FF_Plugin::dependencies_met(): bool`, `FF_Plugin::boot(): void` — every
  later task's classes get wired into `FF_Plugin::boot()`.

- [ ] **Step 1: Create the wp-env config**

`.wp-env.json`:
```json
{
    "core": null,
    "plugins": [
        "./filter-forge",
        "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip",
        "https://downloads.wordpress.org/plugin/elementor.latest-stable.zip"
    ],
    "config": {
        "WP_DEBUG": true
    }
}
```

`package.json`:
```json
{
    "name": "filter-forge-dev",
    "private": true,
    "devDependencies": {
        "@wordpress/env": "^10.0.0"
    },
    "scripts": {
        "env:start": "wp-env start",
        "env:stop": "wp-env stop",
        "env:destroy": "wp-env destroy",
        "test:php": "wp-env run tests-cli --env-cwd=wp-content/plugins/filter-forge vendor/bin/phpunit"
    }
}
```

- [ ] **Step 2: Create the plugin bootstrap file**

`filter-forge/filter-forge.php`:
```php
<?php
/**
 * Plugin Name: Filter Forge
 * Description: Configurable WooCommerce product filters as native Elementor widgets.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: filter-forge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FF_PLUGIN_FILE', __FILE__ );
define( 'FF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FF_VERSION', '0.1.0' );

if ( file_exists( FF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once FF_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once FF_PLUGIN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'FF_Plugin', 'boot' ) );
```

- [ ] **Step 3: Create composer.json for autoloading and dev dependencies**

`filter-forge/composer.json`:
```json
{
    "name": "filter-forge/filter-forge",
    "description": "Configurable WooCommerce product filters as native Elementor widgets.",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "yoast/phpunit-polyfills": "^2.0"
    },
    "autoload": {
        "classmap": [
            "includes/"
        ]
    },
    "autoload-dev": {
        "classmap": [
            "tests/"
        ]
    }
}
```

- [ ] **Step 4: Create the PHPUnit config and bootstrap**

`filter-forge/phpunit.xml.dist`:
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
>
    <testsuites>
        <testsuite name="Filter Forge">
            <directory prefix="test-" suffix=".php">./tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

`filter-forge/tests/bootstrap.php`:
```php
<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

define(
    'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
    dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunit-polyfills-autoload.php'
);

require_once $_tests_dir . '/includes/functions.php';

function _ff_manually_load_plugin() {
    // wp-env names extracted plugin folders after the zip URL slug
    // (e.g. "woocommerce.latest-stable"), not the plugin's own slug, so the
    // main file is located by glob rather than a hardcoded path.
    $woocommerce_main = glob( WP_CONTENT_DIR . '/plugins/woocommerce*/woocommerce.php' );
    $elementor_main    = glob( WP_CONTENT_DIR . '/plugins/elementor*/elementor.php' );

    require $woocommerce_main[0];
    require $elementor_main[0];
    require dirname( __DIR__ ) . '/filter-forge.php';
}
tests_add_filter( 'muplugins_loaded', '_ff_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
```

- [ ] **Step 5: Create `FF_Plugin` with a stubbed `dependencies_met()`**

`filter-forge/includes/class-plugin.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Plugin {

    public static function dependencies_met(): bool {
        return false; // Replaced with the real check in the next step.
    }

    public static function boot(): void {
        if ( ! self::dependencies_met() ) {
            return;
        }
    }
}
```

- [ ] **Step 6: Write the failing test**

`filter-forge/tests/test-class-plugin.php`:
```php
<?php

class Test_FF_Plugin extends WP_UnitTestCase {

    public function test_dependencies_met_is_true_when_woocommerce_and_elementor_are_loaded() {
        $this->assertTrue( class_exists( 'WooCommerce' ) );
        $this->assertGreaterThan( 0, did_action( 'elementor/loaded' ) );
        $this->assertTrue( FF_Plugin::dependencies_met() );
    }
}
```

- [ ] **Step 7: Start the environment, install dependencies, run the test, confirm it fails**

Run:
```bash
npm install
npm run env:start
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/filter-forge composer install
npm run test:php
```
Expected: FAIL — `dependencies_met()` returns `false` unconditionally.

- [ ] **Step 8: Implement the real check**

In `filter-forge/includes/class-plugin.php`, replace the stub:
```php
    public static function dependencies_met(): bool {
        return class_exists( 'WooCommerce' ) && did_action( 'elementor/loaded' );
    }
```

- [ ] **Step 9: Run the test again, confirm it passes**

Run: `npm run test:php`
Expected: PASS (1 test, 2 assertions... — assertion count will grow across tasks).

- [ ] **Step 10: Commit**

```bash
git add .wp-env.json package.json filter-forge/
git commit -m "Scaffold Filter Forge plugin, wp-env, and PHPUnit harness"
```

---

### Task 2: `FF_Filter_State`

**Files:**
- Create: `filter-forge/includes/services/class-filter-state.php`
- Test: `filter-forge/tests/test-class-filter-state.php`

**Interfaces:**
- Consumes: nothing (foundational).
- Produces: `FF_Filter_State::__construct( ?array $source = null )`,
  `get( string $key ): ?string`, `get_list( string $key ): array`,
  `has( string $key ): bool`, `all(): array`,
  `with_override( string $key, ?string $value ): FF_Filter_State`. Every later query
  and widget task reads through this class instead of `$_GET`.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-filter-state.php`:
```php
<?php

class Test_FF_Filter_State extends WP_UnitTestCase {

    public function test_get_returns_sanitized_value() {
        $state = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $this->assertSame( 'pistols', $state->get( 'ff_tax_product_cat' ) );
    }

    public function test_get_returns_null_for_missing_key() {
        $state = new FF_Filter_State( array() );
        $this->assertNull( $state->get( 'missing' ) );
    }

    public function test_get_list_splits_comma_separated_values() {
        $state = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols,rifles' ) );
        $this->assertSame( array( 'pistols', 'rifles' ), $state->get_list( 'ff_tax_product_cat' ) );
    }

    public function test_has_returns_false_for_empty_string() {
        $state = new FF_Filter_State( array( 'ff_brand' => '' ) );
        $this->assertFalse( $state->has( 'ff_brand' ) );
    }

    public function test_ignores_non_scalar_values() {
        $state = new FF_Filter_State( array( 'weird' => array( 'a' ) ) );
        $this->assertNull( $state->get( 'weird' ) );
    }

    public function test_with_override_replaces_a_key_without_mutating_the_original() {
        $state    = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols', 'ff_brand' => 'krytac' ) );
        $modified = $state->with_override( 'ff_tax_product_cat', 'rifles' );

        $this->assertSame( 'pistols', $state->get( 'ff_tax_product_cat' ) );
        $this->assertSame( 'rifles', $modified->get( 'ff_tax_product_cat' ) );
        $this->assertSame( 'krytac', $modified->get( 'ff_brand' ) );
    }

    public function test_with_override_removes_key_when_value_is_null() {
        $state    = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $modified = $state->with_override( 'ff_tax_product_cat', null );

        $this->assertFalse( $modified->has( 'ff_tax_product_cat' ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Filter_State` class not found.

- [ ] **Step 3: Implement `FF_Filter_State`**

`filter-forge/includes/services/class-filter-state.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Filter_State {

    private array $values = array();

    public function __construct( ?array $source = null ) {
        $source = $source ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        foreach ( $source as $key => $value ) {
            if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
                continue;
            }

            $this->values[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
        }
    }

    public function get( string $key ): ?string {
        return $this->values[ $key ] ?? null;
    }

    public function get_list( string $key ): array {
        $value = $this->get( $key );
        if ( null === $value || '' === $value ) {
            return array();
        }

        return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
    }

    public function has( string $key ): bool {
        return isset( $this->values[ $key ] ) && '' !== $this->values[ $key ];
    }

    public function all(): array {
        return $this->values;
    }

    public function with_override( string $key, ?string $value ): FF_Filter_State {
        $values = $this->values;

        if ( null === $value ) {
            unset( $values[ $key ] );
        } else {
            $values[ $key ] = $value;
        }

        return new self( $values );
    }
}
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/class-filter-state.php filter-forge/tests/test-class-filter-state.php
git commit -m "Add FF_Filter_State as the single point of truth for filter selections"
```

---

### Task 3: `FF_Option_Provider` interface + `FF_Taxonomy_Provider`

**Files:**
- Create: `filter-forge/includes/providers/interface-option-provider.php`
- Create: `filter-forge/includes/providers/class-taxonomy-provider.php`
- Test: `filter-forge/tests/test-class-taxonomy-provider.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `interface FF_Option_Provider { get_options( array $context ): array }`
  returning a list of `['value' => string, 'label' => string]`.
  `FF_Taxonomy_Provider implements FF_Option_Provider`, context key `taxonomy`.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-taxonomy-provider.php`:
```php
<?php

class Test_FF_Taxonomy_Provider extends WP_UnitTestCase {

    public function test_get_options_returns_terms_for_taxonomy() {
        $term = self::factory()->term->create_and_get(
            array(
                'taxonomy' => 'product_cat',
                'name'     => 'Airsoft Guns',
            )
        );

        $provider = new FF_Taxonomy_Provider();
        $options  = $provider->get_options( array( 'taxonomy' => 'product_cat' ) );

        $this->assertContains(
            array(
                'value' => $term->slug,
                'label' => 'Airsoft Guns',
            ),
            $options
        );
    }

    public function test_get_options_returns_empty_array_for_unknown_taxonomy() {
        $provider = new FF_Taxonomy_Provider();
        $this->assertSame( array(), $provider->get_options( array( 'taxonomy' => 'not_a_real_taxonomy' ) ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Taxonomy_Provider` not found.

- [ ] **Step 3: Implement the interface and provider**

`filter-forge/includes/providers/interface-option-provider.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface FF_Option_Provider {

    /**
     * @param array $context Provider-specific context (e.g. taxonomy name, meta key).
     * @return array<int, array{value: string, label: string}>
     */
    public function get_options( array $context ): array;
}
```

`filter-forge/includes/providers/class-taxonomy-provider.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Taxonomy_Provider implements FF_Option_Provider {

    public function get_options( array $context ): array {
        $taxonomy = $context['taxonomy'] ?? '';

        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return array();
        }

        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        return array_map(
            static function ( WP_Term $term ): array {
                return array(
                    'value' => $term->slug,
                    'label' => $term->name,
                );
            },
            $terms
        );
    }
}
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/providers/ filter-forge/tests/test-class-taxonomy-provider.php
git commit -m "Add FF_Option_Provider interface and FF_Taxonomy_Provider"
```

---

### Task 4: `FF_Meta_Provider`

**Files:**
- Create: `filter-forge/includes/providers/class-meta-provider.php`
- Test: `filter-forge/tests/test-class-meta-provider.php`

**Interfaces:**
- Consumes: `FF_Option_Provider` (Task 3).
- Produces: `FF_Meta_Provider implements FF_Option_Provider`, context key `meta_key`.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-meta-provider.php`:
```php
<?php

class Test_FF_Meta_Provider extends WP_UnitTestCase {

    public function test_get_options_returns_distinct_published_product_meta_values() {
        $product_a = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
        $product_b = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
        update_post_meta( $product_a, 'material', 'Nylon' );
        update_post_meta( $product_b, 'material', 'Nylon' );

        $provider = new FF_Meta_Provider();
        $options  = $provider->get_options( array( 'meta_key' => 'material' ) );

        $this->assertSame( array( array( 'value' => 'Nylon', 'label' => 'Nylon' ) ), $options );
    }

    public function test_get_options_ignores_unpublished_products() {
        $draft = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'draft' ) );
        update_post_meta( $draft, 'material', 'Draft-Only-Value' );

        $provider = new FF_Meta_Provider();
        $options  = $provider->get_options( array( 'meta_key' => 'material' ) );

        $this->assertSame( array(), $options );
    }

    public function test_get_options_returns_empty_array_for_missing_meta_key() {
        $provider = new FF_Meta_Provider();
        $this->assertSame( array(), $provider->get_options( array( 'meta_key' => '' ) ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Meta_Provider` not found.

- [ ] **Step 3: Implement `FF_Meta_Provider`**

`filter-forge/includes/providers/class-meta-provider.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Meta_Provider implements FF_Option_Provider {

    public function get_options( array $context ): array {
        global $wpdb;

        $meta_key = $context['meta_key'] ?? '';
        if ( '' === $meta_key ) {
            return array();
        }

        $values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
                AND pm.meta_value != ''
                ORDER BY pm.meta_value ASC",
                $meta_key
            )
        );

        return array_map(
            static function ( string $value ): array {
                return array(
                    'value' => $value,
                    'label' => $value,
                );
            },
            $values
        );
    }
}
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/providers/class-meta-provider.php filter-forge/tests/test-class-meta-provider.php
git commit -m "Add FF_Meta_Provider for custom-field filter options"
```

---

### Task 5: `FF_Count_Provider` interface + `FF_Count_Service`

**Files:**
- Create: `filter-forge/includes/services/interface-count-provider.php`
- Create: `filter-forge/includes/services/class-count-service.php`
- Test: `filter-forge/tests/test-class-count-service.php`

**Interfaces:**
- Consumes: nothing new (works directly against `WP_Query` args arrays).
- Produces: `interface FF_Count_Provider { get_count( array $query_args ): int }`,
  `FF_Count_Service implements FF_Count_Provider`. Widgets (Task 13) and the
  faceted-counting logic call `get_count()` with a full `WP_Query` args array.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-count-service.php`:
```php
<?php

class Test_FF_Count_Service extends WP_UnitTestCase {

    public function test_get_count_returns_number_of_matching_products() {
        $products = self::factory()->post->create_many(
            3,
            array( 'post_type' => 'product', 'post_status' => 'publish' )
        );
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Pistols' )
        );
        wp_set_object_terms( $products[0], array( $term->term_id ), 'product_cat' );
        wp_set_object_terms( $products[1], array( $term->term_id ), 'product_cat' );

        $service = new FF_Count_Service();
        $count   = $service->get_count(
            array(
                'post_type' => 'product',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array( $term->term_id ),
                    ),
                ),
            )
        );

        $this->assertSame( 2, $count );
    }

    public function test_get_count_reads_from_cache_on_second_call_with_same_args() {
        $service    = new FF_Count_Service();
        $query_args = array( 'post_type' => 'product' );

        $service->get_count( $query_args );
        wp_cache_set( 'ff_count_' . md5( wp_json_encode( $query_args ) ), 999, 'filter-forge' );

        $this->assertSame( 999, $service->get_count( $query_args ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Count_Service` not found.

- [ ] **Step 3: Implement the interface and service**

`filter-forge/includes/services/interface-count-provider.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface FF_Count_Provider {

    /**
     * @param array $query_args A WP_Query args array describing exactly which
     *                          products to count.
     */
    public function get_count( array $query_args ): int;
}
```

`filter-forge/includes/services/class-count-service.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Count_Service implements FF_Count_Provider {

    public function get_count( array $query_args ): int {
        $cache_key = 'ff_count_' . md5( wp_json_encode( $query_args ) );
        $cached    = wp_cache_get( $cache_key, 'filter-forge' );

        if ( false !== $cached ) {
            return (int) $cached;
        }

        $query_args['fields']         = 'ids';
        $query_args['posts_per_page'] = -1;
        $query_args['no_found_rows']  = false;

        $query = new WP_Query( $query_args );
        $count = (int) $query->found_posts;

        wp_cache_set( $cache_key, $count, 'filter-forge' );

        return $count;
    }
}
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/interface-count-provider.php filter-forge/includes/services/class-count-service.php filter-forge/tests/test-class-count-service.php
git commit -m "Add FF_Count_Service behind a swappable FF_Count_Provider interface"
```

---

### Task 6: `FF_Category_Filter`

**Files:**
- Create: `filter-forge/includes/services/class-category-filter.php`
- Test: `filter-forge/tests/test-class-category-filter.php`

**Interfaces:**
- Consumes: `FF_Filter_State` (Task 2).
- Produces: `FF_Category_Filter::__construct( FF_Filter_State $filter_state )`,
  `apply( WP_Query $query ): void`, static
  `FF_Category_Filter::resolve_param( string $taxonomy ): string` — used later by the
  Filter widget (Task 13) to decide which query param a given taxonomy uses.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-category-filter.php`:
```php
<?php

class Test_FF_Category_Filter extends WP_UnitTestCase {

    public function test_apply_adds_tax_query_for_ff_tax_prefixed_param() {
        self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'name' => 'Pistols', 'slug' => 'pistols' ) );

        $state  = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols,rifles' ) );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame(
            array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => array( 'pistols', 'rifles' ),
                ),
            ),
            $query->get( 'tax_query' )
        );
    }

    public function test_apply_does_nothing_when_no_taxonomy_filter_present() {
        $state  = new FF_Filter_State( array() );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'tax_query' ) );
    }

    public function test_apply_ignores_unknown_taxonomy_in_param_name() {
        $state  = new FF_Filter_State( array( 'ff_tax_not_a_real_taxonomy' => 'x' ) );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'tax_query' ) );
    }

    public function test_resolve_param_uses_ff_tax_prefix_for_non_attribute_taxonomies() {
        $this->assertSame( 'ff_tax_product_cat', FF_Category_Filter::resolve_param( 'product_cat' ) );
    }

    public function test_resolve_param_uses_native_filter_prefix_for_attribute_taxonomies() {
        $attribute_id = wc_create_attribute(
            array(
                'name' => 'Color',
                'slug' => 'color',
                'type' => 'select',
            )
        );
        $taxonomy = wc_attribute_taxonomy_name( 'color' );

        $this->assertSame( 'filter_' . $taxonomy, FF_Category_Filter::resolve_param( $taxonomy ) );

        wc_delete_attribute( $attribute_id );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Category_Filter` not found.

- [ ] **Step 3: Implement `FF_Category_Filter`**

`filter-forge/includes/services/class-category-filter.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Category_Filter {

    private FF_Filter_State $filter_state;

    public function __construct( FF_Filter_State $filter_state ) {
        $this->filter_state = $filter_state;
    }

    public function apply( WP_Query $query ): void {
        $tax_query = $query->get( 'tax_query' );
        if ( ! is_array( $tax_query ) ) {
            $tax_query = array();
        }

        foreach ( $this->filter_state->all() as $param => $value ) {
            if ( '' === $value || 0 !== strpos( $param, 'ff_tax_' ) ) {
                continue;
            }

            $taxonomy = substr( $param, strlen( 'ff_tax_' ) );
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $this->filter_state->get_list( $param ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            $query->set( 'tax_query', $tax_query );
        }
    }

    public static function resolve_param( string $taxonomy ): string {
        if ( in_array( $taxonomy, wc_get_attribute_taxonomy_names(), true ) ) {
            return 'filter_' . $taxonomy;
        }

        return 'ff_tax_' . $taxonomy;
    }
}
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/class-category-filter.php filter-forge/tests/test-class-category-filter.php
git commit -m "Add FF_Category_Filter for non-attribute taxonomy filtering"
```

---

### Task 7: `FF_Meta_Filter`

**Files:**
- Create: `filter-forge/includes/services/class-meta-filter.php`
- Test: `filter-forge/tests/test-class-meta-filter.php`

**Interfaces:**
- Consumes: `FF_Filter_State` (Task 2).
- Produces: `FF_Meta_Filter::__construct( FF_Filter_State $filter_state )`,
  `apply( WP_Query $query ): void`.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-meta-filter.php`:
```php
<?php

class Test_FF_Meta_Filter extends WP_UnitTestCase {

    public function test_apply_adds_meta_query_for_ff_prefixed_param() {
        $state  = new FF_Filter_State( array( 'ff_material' => 'nylon,abs' ) );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame(
            array(
                array(
                    'key'     => 'material',
                    'value'   => array( 'nylon', 'abs' ),
                    'compare' => 'IN',
                ),
            ),
            $query->get( 'meta_query' )
        );
    }

    public function test_apply_ignores_ff_tax_prefixed_params() {
        $state  = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'meta_query' ) );
    }

    public function test_apply_does_nothing_when_no_meta_filter_present() {
        $state  = new FF_Filter_State( array() );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'meta_query' ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Meta_Filter` not found.

- [ ] **Step 3: Implement `FF_Meta_Filter`**

`filter-forge/includes/services/class-meta-filter.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Meta_Filter {

    private FF_Filter_State $filter_state;

    public function __construct( FF_Filter_State $filter_state ) {
        $this->filter_state = $filter_state;
    }

    public function apply( WP_Query $query ): void {
        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        foreach ( $this->filter_state->all() as $param => $value ) {
            if ( '' === $value || 0 !== strpos( $param, 'ff_' ) || 0 === strpos( $param, 'ff_tax_' ) ) {
                continue;
            }

            $meta_key     = substr( $param, strlen( 'ff_' ) );
            $meta_query[] = array(
                'key'     => $meta_key,
                'value'   => $this->filter_state->get_list( $param ),
                'compare' => 'IN',
            );
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }
}
```

A param must start with `ff_` to be considered at all, and must **not** also start
with the more specific `ff_tax_` prefix — this is what keeps `FF_Meta_Filter` and
`FF_Category_Filter` from double-handling the same param. (An earlier draft of this
condition had the `strpos()` comparisons backwards — `0 !== strpos(...)` reads as
"does NOT start with," so OR-ing two such checks together skips almost everything
instead of narrowing to the right params. The tests below caught it immediately: the
`ff_material` case failed to produce a `meta_query` and the `ff_tax_product_cat` case
produced one it shouldn't have. Trust the tests over the prose if they ever disagree.)

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/class-meta-filter.php filter-forge/tests/test-class-meta-filter.php
git commit -m "Add FF_Meta_Filter for custom-field query filtering"
```

---

### Task 8: `FF_Query_Manager`

**Files:**
- Create: `filter-forge/includes/services/class-query-manager.php`
- Test: `filter-forge/tests/test-class-query-manager.php`

**Interfaces:**
- Consumes: `FF_Category_Filter` (Task 6), `FF_Meta_Filter` (Task 7).
- Produces: `FF_Query_Manager::__construct( FF_Category_Filter $category_filter, FF_Meta_Filter $meta_filter )`,
  `register(): void`, `maybe_apply( WP_Query $query ): void`.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-query-manager.php`:
```php
<?php

class Test_FF_Query_Manager extends WP_UnitTestCase {

    private function make_manager( array $get = array() ): FF_Query_Manager {
        $state = new FF_Filter_State( $get );
        return new FF_Query_Manager( new FF_Category_Filter( $state ), new FF_Meta_Filter( $state ) );
    }

    public function test_maybe_apply_applies_filters_on_product_category_archive() {
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Rifles', 'slug' => 'rifles' )
        );
        $this->go_to( get_term_link( $term ) );

        global $wp_query;
        $this->make_manager( array( 'ff_tax_product_cat' => 'rifles' ) )->maybe_apply( $wp_query );

        $this->assertNotEmpty( $wp_query->get( 'tax_query' ) );
    }

    public function test_maybe_apply_does_nothing_on_a_non_product_page() {
        $page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
        $this->go_to( get_permalink( $page_id ) );

        global $wp_query;
        $this->make_manager( array( 'ff_tax_product_cat' => 'rifles' ) )->maybe_apply( $wp_query );

        $this->assertEmpty( $wp_query->get( 'tax_query' ) );
    }

    public function test_maybe_apply_does_nothing_for_non_main_query() {
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Shotguns', 'slug' => 'shotguns' )
        );
        $this->go_to( get_term_link( $term ) );

        $secondary_query = new WP_Query( array( 'post_type' => 'product' ) );
        $this->make_manager( array( 'ff_tax_product_cat' => 'shotguns' ) )->maybe_apply( $secondary_query );

        $this->assertEmpty( $secondary_query->get( 'tax_query' ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Query_Manager` not found.

- [ ] **Step 3: Implement `FF_Query_Manager`**

`filter-forge/includes/services/class-query-manager.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Query_Manager {

    private FF_Category_Filter $category_filter;
    private FF_Meta_Filter $meta_filter;

    public function __construct( FF_Category_Filter $category_filter, FF_Meta_Filter $meta_filter ) {
        $this->category_filter = $category_filter;
        $this->meta_filter     = $meta_filter;
    }

    public function register(): void {
        add_action( 'pre_get_posts', array( $this, 'maybe_apply' ) );
    }

    public function maybe_apply( WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! self::is_supported_archive() ) {
            return;
        }

        $this->category_filter->apply( $query );
        $this->meta_filter->apply( $query );
    }

    public static function is_supported_archive(): bool {
        return is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy();
    }
}
```

`is_supported_archive()` is `public static` (not `private`) because the Filter and
Price widgets (Tasks 13–14) also call it to decide whether to render at all on a page
that isn't a supported WooCommerce archive.

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/class-query-manager.php filter-forge/tests/test-class-query-manager.php
git commit -m "Add FF_Query_Manager to gate custom query filters to supported archives"
```

---

### Task 9: `FF_Relationship_Resolver`

**Files:**
- Create: `filter-forge/includes/services/class-relationship-resolver.php`
- Test: `filter-forge/tests/test-class-relationship-resolver.php`

**Interfaces:**
- Consumes: `FF_Filter_State` (Task 2).
- Produces: `FF_Relationship_Resolver::should_render( array $config, FF_Filter_State $state ): bool`,
  where `$config` is `['parent_key' => string, 'hide_until_selected' => bool]`. Used
  by the Filter and Price widgets (Tasks 13–14) to decide whether to render at all.

- [ ] **Step 1: Write the failing tests**

`filter-forge/tests/test-class-relationship-resolver.php`:
```php
<?php

class Test_FF_Relationship_Resolver extends WP_UnitTestCase {

    public function test_should_render_true_when_no_parent_configured() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );

        $this->assertTrue( $resolver->should_render( array(), $state ) );
    }

    public function test_should_render_false_when_hidden_until_parent_selected_and_parent_is_empty() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => true );

        $this->assertFalse( $resolver->should_render( $config, $state ) );
    }

    public function test_should_render_true_when_hidden_until_parent_selected_and_parent_has_a_value() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array( 'category' => 'pistols' ) );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => true );

        $this->assertTrue( $resolver->should_render( $config, $state ) );
    }

    public function test_should_render_true_when_parent_configured_but_hide_until_selected_is_false() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => false );

        $this->assertTrue( $resolver->should_render( $config, $state ) );
    }
}
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Relationship_Resolver` not found.

- [ ] **Step 3: Implement `FF_Relationship_Resolver`**

`filter-forge/includes/services/class-relationship-resolver.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Relationship_Resolver {

    public function should_render( array $config, FF_Filter_State $state ): bool {
        $parent_key          = $config['parent_key'] ?? '';
        $hide_until_selected = ! empty( $config['hide_until_selected'] );

        if ( '' === $parent_key || ! $hide_until_selected ) {
            return true;
        }

        return $state->has( $parent_key );
    }
}
```

Reset-on-parent-change is a client-side, click-time behavior (Task 16's JS strips the
child's own URL param before navigating) — there is no server-side "reset" decision to
make, since by the time PHP runs, the new request already reflects whatever the JS
sent.

**Deferred from the spec's §8 error handling:** an orphaned Parent Filter Key (typo,
or the parent widget was deleted) does not get an editor-only notice in v1 — that
would require Elementor editor-side JS that scans the page's widgets to validate the
reference live in the editor, which is more upfront work than a v1 nice-to-have
justifies. Instead, if "hide until parent selected" is on and the parent key never
matches a real widget, `should_render()` returns `false` forever (the parent's value
never appears in `FF_Filter_State`), so the child just stays permanently hidden — a
safe default, but the site builder has to notice by testing the page rather than
being warned in the editor.

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/services/class-relationship-resolver.php filter-forge/tests/test-class-relationship-resolver.php
git commit -m "Add FF_Relationship_Resolver for hide-until-parent-selected logic"
```

---

### Task 10: `FF_Requirements_Notice`

**Files:**
- Create: `filter-forge/includes/admin/class-requirements-notice.php`
- Test: `filter-forge/tests/test-class-requirements-notice.php`

**Interfaces:**
- Consumes: `FF_Plugin::dependencies_met()` (Task 1).
- Produces: `FF_Requirements_Notice::register(): void`, `maybe_render(): void`.

- [ ] **Step 1: Write the failing test**

`filter-forge/tests/test-class-requirements-notice.php`:
```php
<?php

class Test_FF_Requirements_Notice extends WP_UnitTestCase {

    public function test_maybe_render_outputs_nothing_when_dependencies_are_met() {
        // In this test environment WooCommerce and Elementor are always active,
        // so this exercises the real "met" branch. The "missing dependency" branch
        // is verified manually (Task 17) by deactivating WooCommerce/Elementor on a
        // real site, since class_exists()/did_action() can't be faked mid-suite.
        $notice = new FF_Requirements_Notice();

        ob_start();
        $notice->maybe_render();
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `npm run test:php`
Expected: FAIL — `FF_Requirements_Notice` not found.

- [ ] **Step 3: Implement `FF_Requirements_Notice`**

`filter-forge/includes/admin/class-requirements-notice.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Requirements_Notice {

    public function register(): void {
        add_action( 'admin_notices', array( $this, 'maybe_render' ) );
    }

    public function maybe_render(): void {
        if ( FF_Plugin::dependencies_met() ) {
            return;
        }

        $missing = array();
        if ( ! class_exists( 'WooCommerce' ) ) {
            $missing[] = 'WooCommerce';
        }
        if ( ! did_action( 'elementor/loaded' ) ) {
            $missing[] = 'Elementor';
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: comma separated list of missing plugin names */
                    __( 'Filter Forge requires the following plugin(s) to be active: %s', 'filter-forge' ),
                    implode( ', ', $missing )
                )
            )
        );
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/admin/class-requirements-notice.php filter-forge/tests/test-class-requirements-notice.php
git commit -m "Add FF_Requirements_Notice admin notice for missing dependencies"
```

---

### Task 11: Wire everything together in `FF_Plugin`

**Files:**
- Modify: `filter-forge/includes/class-plugin.php`
- Test: `filter-forge/tests/test-class-plugin.php` (extend from Task 1)

**Interfaces:**
- Consumes: `FF_Filter_State`, `FF_Category_Filter`, `FF_Meta_Filter`,
  `FF_Query_Manager`, `FF_Count_Service`, `FF_Relationship_Resolver`,
  `FF_Requirements_Notice` (Tasks 2–10).
- Produces: `FF_Plugin::instance(): FF_Plugin` (singleton) with public properties
  `$filter_state`, `$query_manager`, `$count_service`, `$relationship_resolver` —
  every widget task reads these off `FF_Plugin::instance()`.

- [ ] **Step 1: Write the failing tests**

Append to `filter-forge/tests/test-class-plugin.php`:
```php
    public function test_instance_returns_the_same_object_every_call() {
        $this->assertSame( FF_Plugin::instance(), FF_Plugin::instance() );
    }

    public function test_instance_exposes_the_shared_services() {
        $plugin = FF_Plugin::instance();

        $this->assertInstanceOf( FF_Filter_State::class, $plugin->filter_state );
        $this->assertInstanceOf( FF_Query_Manager::class, $plugin->query_manager );
        $this->assertInstanceOf( FF_Count_Service::class, $plugin->count_service );
        $this->assertInstanceOf( FF_Relationship_Resolver::class, $plugin->relationship_resolver );
    }

    public function test_boot_registers_the_pre_get_posts_hook() {
        FF_Plugin::boot();
        $plugin = FF_Plugin::instance();

        $this->assertNotFalse(
            has_action( 'pre_get_posts', array( $plugin->query_manager, 'maybe_apply' ) )
        );
    }
```

- [ ] **Step 2: Run the tests, confirm the new ones fail**

Run: `npm run test:php`
Expected: FAIL — `FF_Plugin::instance()` not defined.

- [ ] **Step 3: Implement the full `FF_Plugin`**

Replace `filter-forge/includes/class-plugin.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/services/class-filter-state.php';
require_once __DIR__ . '/services/class-category-filter.php';
require_once __DIR__ . '/services/class-meta-filter.php';
require_once __DIR__ . '/services/class-query-manager.php';
require_once __DIR__ . '/services/interface-count-provider.php';
require_once __DIR__ . '/services/class-count-service.php';
require_once __DIR__ . '/services/class-relationship-resolver.php';
require_once __DIR__ . '/providers/interface-option-provider.php';
require_once __DIR__ . '/providers/class-taxonomy-provider.php';
require_once __DIR__ . '/providers/class-meta-provider.php';
require_once __DIR__ . '/admin/class-requirements-notice.php';

class FF_Plugin {

    private static ?FF_Plugin $instance = null;

    public FF_Filter_State $filter_state;
    public FF_Query_Manager $query_manager;
    public FF_Count_Service $count_service;
    public FF_Relationship_Resolver $relationship_resolver;

    public static function dependencies_met(): bool {
        return class_exists( 'WooCommerce' ) && did_action( 'elementor/loaded' );
    }

    public static function boot(): void {
        if ( ! self::dependencies_met() ) {
            ( new FF_Requirements_Notice() )->register();
            return;
        }

        self::instance();
    }

    public static function instance(): FF_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->filter_state          = new FF_Filter_State();
        $this->count_service          = new FF_Count_Service();
        $this->relationship_resolver  = new FF_Relationship_Resolver();

        $category_filter      = new FF_Category_Filter( $this->filter_state );
        $meta_filter           = new FF_Meta_Filter( $this->filter_state );
        $this->query_manager   = new FF_Query_Manager( $category_filter, $meta_filter );
        $this->query_manager->register();

        add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
    }

    public function register_widget_category( $elements_manager ): void {
        $elements_manager->add_category(
            'filter-forge',
            array(
                'title' => __( 'Filter Forge', 'filter-forge' ),
                'icon'  => 'eicon-filter',
            )
        );
    }

    public function register_widgets( $widgets_manager ): void {
        require_once __DIR__ . '/widgets/class-widget-base.php';
        require_once __DIR__ . '/widgets/class-widget-filter.php';
        require_once __DIR__ . '/widgets/class-widget-price.php';
        require_once __DIR__ . '/widgets/class-widget-reset.php';

        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
        $widgets_manager->register( new FF_Widget_Reset() );
    }
}
```

Note: the widget files are required lazily inside `register_widgets()` (only called
by Elementor once it's confirmed loaded) rather than at the top of this file, since
`FF_Widget_Base` extends `\Elementor\Widget_Base`, which doesn't exist until
Elementor has loaded — requiring it unconditionally would fatal on sites where
Elementor is inactive, defeating the whole purpose of the requirements gate. This
means Tasks 12–15 (which create those widget files) must exist as files before this
task's manual verification step, but referencing them here now is safe because
`register_widgets()` is never invoked until Elementor calls the
`elementor/widgets/register` action.

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `npm run test:php`
Expected: PASS. (Widget classes don't exist yet — that's fine, `register_widgets()`
isn't invoked by any test in this task.)

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/class-plugin.php filter-forge/tests/test-class-plugin.php
git commit -m "Wire shared services and Elementor widget registration into FF_Plugin"
```

---

### Task 12: `FF_Widget_Base` (shared relationship controls)

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-base.php`

**Interfaces:**
- Consumes: `\Elementor\Widget_Base`, `\Elementor\Controls_Manager` (Elementor core).
- Produces: `FF_Widget_Base` (abstract) with `protected register_relationship_controls(): void`
  and `protected get_relationship_config(): array` (returns
  `['parent_key' => string, 'reset_on_change' => bool, 'hide_until_selected' => bool]`,
  `parent_key` here matches the `$config['parent_key']` shape `FF_Relationship_Resolver`
  expects). Consumed by `FF_Widget_Filter` (Task 13) and `FF_Widget_Price` (Task 14).

No PHPUnit test for this task: `\Elementor\Widget_Base` requires Elementor's editor
document/controls-stack context to instantiate meaningfully, which isn't practical to
set up in `WP_UnitTestCase`. This matches the spec's own testing section — Elementor
widget behavior is verified manually in Task 17, against a real site.

- [ ] **Step 1: Implement `FF_Widget_Base`**

`filter-forge/includes/widgets/class-widget-base.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class FF_Widget_Base extends \Elementor\Widget_Base {

    public function get_categories(): array {
        return array( 'filter-forge' );
    }

    protected function register_relationship_controls(): void {
        $this->start_controls_section(
            'ff_relationships',
            array(
                'label' => __( 'Filter Relationships', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_filter_key',
            array(
                'label'       => __( 'Filter Key', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => __( 'A short identifier for this filter, e.g. "color". Other filters reference this to declare it as their parent.', 'filter-forge' ),
            )
        );

        $this->add_control(
            'ff_parent_filter_key',
            array(
                'label'       => __( 'Parent Filter Key', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => __( 'The Filter Key of another filter on this page that this filter depends on.', 'filter-forge' ),
            )
        );

        $this->add_control(
            'ff_reset_on_parent_change',
            array(
                'label'     => __( 'Reset on parent change', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'default'   => '',
                'condition' => array( 'ff_parent_filter_key!' => '' ),
            )
        );

        $this->add_control(
            'ff_hide_until_parent_selected',
            array(
                'label'     => __( 'Hide until parent has a selection', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'default'   => '',
                'condition' => array( 'ff_parent_filter_key!' => '' ),
            )
        );

        $this->end_controls_section();
    }

    protected function get_relationship_config(): array {
        $settings = $this->get_settings_for_display();

        return array(
            'parent_key'          => $settings['ff_parent_filter_key'] ?? '',
            'reset_on_change'     => 'yes' === ( $settings['ff_reset_on_parent_change'] ?? '' ),
            'hide_until_selected' => 'yes' === ( $settings['ff_hide_until_parent_selected'] ?? '' ),
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-base.php
git commit -m "Add FF_Widget_Base with shared filter-relationship controls"
```

---

### Task 13: `FF_Widget_Filter`

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-filter.php`

**Interfaces:**
- Consumes: `FF_Widget_Base` (Task 12), `FF_Taxonomy_Provider`/`FF_Meta_Provider`
  (Tasks 3–4), `FF_Category_Filter::resolve_param()` (Task 6),
  `FF_Query_Manager::is_supported_archive()` (Task 8),
  `FF_Plugin::instance()->filter_state` / `->count_service` / `->relationship_resolver`
  (Task 11).
- Produces: Elementor widget `ff-filter`, markup with `.ff-filter__clear` buttons and
  `data-ff-parent-key`/`data-ff-reset-on-change` attributes consumed by the JS in
  Task 16.

No PHPUnit test — same reasoning as Task 12. Verified manually in Task 17.

- [ ] **Step 1: Implement `FF_Widget_Filter`**

`filter-forge/includes/widgets/class-widget-filter.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Filter extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-filter';
    }

    public function get_title(): string {
        return __( 'Filter', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'eicon-filter';
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'ff_source',
            array(
                'label' => __( 'Filter Source', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_source_type',
            array(
                'label'   => __( 'Source Type', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'taxonomy',
                'options' => array(
                    'taxonomy' => __( 'Taxonomy', 'filter-forge' ),
                    'meta'     => __( 'Custom Field', 'filter-forge' ),
                ),
            )
        );

        $this->add_control(
            'ff_taxonomy',
            array(
                'label'     => __( 'Taxonomy', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => $this->get_taxonomy_options(),
                'condition' => array( 'ff_source_type' => 'taxonomy' ),
            )
        );

        $this->add_control(
            'ff_meta_key',
            array(
                'label'     => __( 'Meta Key', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::TEXT,
                'condition' => array( 'ff_source_type' => 'meta' ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'ff_display',
            array(
                'label' => __( 'Display', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_display_style',
            array(
                'label'   => __( 'Display Style', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'checkbox',
                'options' => array(
                    'checkbox' => __( 'Checkbox list', 'filter-forge' ),
                    'radio'    => __( 'Radio (single-select)', 'filter-forge' ),
                    'dropdown' => __( 'Dropdown', 'filter-forge' ),
                    'swatch'   => __( 'Swatches', 'filter-forge' ),
                    'toggle'   => __( 'Toggle', 'filter-forge' ),
                ),
            )
        );

        $this->add_control(
            'ff_show_counts',
            array(
                'label'   => __( 'Show counts', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'ff_hide_zero_results',
            array(
                'label'   => __( 'Hide zero-result options', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        $this->register_relationship_controls();
    }

    private function get_taxonomy_options(): array {
        $taxonomies = get_object_taxonomies( 'product', 'objects' );
        $options    = array();

        foreach ( $taxonomies as $taxonomy ) {
            if ( ! $taxonomy->public ) {
                continue;
            }
            $options[ $taxonomy->name ] = $taxonomy->label;
        }

        return $options;
    }

    public function render(): void {
        if ( ! FF_Query_Manager::is_supported_archive() ) {
            $this->render_unsupported_page_notice();
            return;
        }

        $settings = $this->get_settings_for_display();
        $plugin   = FF_Plugin::instance();

        if ( ! $plugin->relationship_resolver->should_render( $this->get_relationship_config(), $plugin->filter_state ) ) {
            return;
        }

        $source_type = $settings['ff_source_type'] ?? 'taxonomy';
        $taxonomy    = $settings['ff_taxonomy'] ?? '';
        $meta_key    = $settings['ff_meta_key'] ?? '';

        if ( 'meta' === $source_type ) {
            $provider = new FF_Meta_Provider();
            $context  = array( 'meta_key' => $meta_key );
            $param    = 'ff_' . $meta_key;
        } else {
            $provider = new FF_Taxonomy_Provider();
            $context  = array( 'taxonomy' => $taxonomy );
            $param    = FF_Category_Filter::resolve_param( $taxonomy );
        }

        $options      = $provider->get_options( $context );
        $selected     = $plugin->filter_state->get_list( $param );
        $show_counts  = 'yes' === ( $settings['ff_show_counts'] ?? 'yes' );
        $hide_zero    = 'yes' === ( $settings['ff_hide_zero_results'] ?? 'yes' );
        $filter_key   = $settings['ff_filter_key'] ?? '';
        $relationship = $this->get_relationship_config();

        echo '<ul class="ff-filter ff-filter--' . esc_attr( $settings['ff_display_style'] ?? 'checkbox' ) . '"'
            . ' data-ff-filter-key="' . esc_attr( $filter_key ) . '"'
            . ' data-ff-param="' . esc_attr( $param ) . '"'
            . ' data-ff-parent-key="' . esc_attr( $relationship['parent_key'] ) . '"'
            . ' data-ff-reset-on-change="' . ( $relationship['reset_on_change'] ? 'yes' : 'no' ) . '">';

        foreach ( $options as $option ) {
            $count = $show_counts || $hide_zero
                ? $this->count_for_option( $param, $option['value'] )
                : 1;

            if ( $hide_zero && 0 === $count ) {
                continue;
            }

            printf(
                '<li><label><input type="checkbox" value="%1$s" data-ff-param="%2$s" %3$s /> %4$s%5$s</label></li>',
                esc_attr( $option['value'] ),
                esc_attr( $param ),
                checked( in_array( $option['value'], $selected, true ), true, false ),
                esc_html( $option['label'] ),
                $show_counts ? ' (' . (int) $count . ')' : ''
            );
        }

        echo '</ul>';

        if ( ! empty( $selected ) ) {
            printf(
                '<button type="button" class="ff-filter__clear" data-ff-param="%1$s">%2$s</button>',
                esc_attr( $param ),
                esc_html__( 'Clear', 'filter-forge' )
            );
        }
    }

    private function render_unsupported_page_notice(): void {
        if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return;
        }

        echo '<p class="ff-filter__notice">' . esc_html__( 'Filter Forge: this widget only renders on a WooCommerce archive page (Shop, category, tag, or attribute archive).', 'filter-forge' ) . '</p>';
    }

    private function count_for_option( string $param, string $value ): int {
        $plugin       = FF_Plugin::instance();
        $scoped_state = $plugin->filter_state->with_override( $param, $value );

        $probe = new WP_Query();
        $probe->set( 'post_type', 'product' );
        $probe->set( 'post_status', 'publish' );

        ( new FF_Category_Filter( $scoped_state ) )->apply( $probe );
        ( new FF_Meta_Filter( $scoped_state ) )->apply( $probe );

        $query_args = array(
            'post_type'   => 'product',
            'post_status' => 'publish',
        );

        $tax_query = $probe->get( 'tax_query' );
        if ( ! empty( $tax_query ) ) {
            $query_args['tax_query'] = $tax_query;
        }

        $meta_query = $probe->get( 'meta_query' );
        if ( ! empty( $meta_query ) ) {
            $query_args['meta_query'] = $meta_query;
        }

        return $plugin->count_service->get_count( $query_args );
    }
}
```

**Known v1 limitation** (confirm acceptable during Task 17's manual pass): counting
only cross-references Filter Forge's own custom filters (`ff_tax_*`/`ff_*` meta)
against each other. It does not fold in currently-active **native** WooCommerce
filters (`filter_pa_*`, `min_price`/`max_price`, `filter_stock_status`,
`rating_filter`) when computing another filter's option counts, since replicating
WooCommerce's internal price/attribute query logic inside the count probe is
significant extra complexity for a v1. In practice this means: selecting a category
correctly narrows a custom meta filter's counts (and vice versa), but selecting a
native WooCommerce attribute won't yet narrow a category filter's counts. If this
turns out to matter in practice, it's a follow-up, not a blocker for v1.

- [ ] **Step 2: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-filter.php
git commit -m "Add FF_Widget_Filter (taxonomy/meta source, adaptive display style)"
```

---

### Task 14: `FF_Widget_Price`

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-price.php`

**Interfaces:**
- Consumes: `FF_Widget_Base` (Task 12), `FF_Query_Manager::is_supported_archive()`
  (Task 8), `FF_Plugin::instance()->filter_state` (Task 11).
- Produces: Elementor widget `ff-price`, markup with a `.ff-price__clear` button
  consumed by the JS in Task 16.

No PHPUnit test — same reasoning as Task 12/13.

- [ ] **Step 1: Implement `FF_Widget_Price`**

`filter-forge/includes/widgets/class-widget-price.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Price extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-price';
    }

    public function get_title(): string {
        return __( 'Price Filter', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'eicon-price-table';
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'ff_price_source',
            array(
                'label' => __( 'Price Filter', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

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

        $repeater = new \Elementor\Repeater();
        $repeater->add_control( 'label', array( 'label' => __( 'Label', 'filter-forge' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
        $repeater->add_control( 'min', array( 'label' => __( 'Min', 'filter-forge' ), 'type' => \Elementor\Controls_Manager::NUMBER ) );
        $repeater->add_control(
            'max',
            array(
                'label'       => __( 'Max (leave blank for "& above")', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'default'     => '',
            )
        );

        $this->add_control(
            'ff_price_buckets',
            array(
                'label'     => __( 'Buckets', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::REPEATER,
                'fields'    => $repeater->get_controls(),
                'condition' => array( 'ff_price_mode' => 'buckets' ),
            )
        );

        $this->end_controls_section();

        $this->register_relationship_controls();
    }

    public function render(): void {
        if ( ! FF_Query_Manager::is_supported_archive() ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p class="ff-price__notice">' . esc_html__( 'Filter Forge: this widget only renders on a WooCommerce archive page.', 'filter-forge' ) . '</p>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        $plugin   = FF_Plugin::instance();

        if ( ! $plugin->relationship_resolver->should_render( $this->get_relationship_config(), $plugin->filter_state ) ) {
            return;
        }

        $current_min = $plugin->filter_state->get( 'min_price' );
        $current_max = $plugin->filter_state->get( 'max_price' );

        if ( 'buckets' === ( $settings['ff_price_mode'] ?? 'slider' ) ) {
            $this->render_buckets( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
        } else {
            $this->render_slider( $current_min, $current_max );
        }

        if ( null !== $current_min || null !== $current_max ) {
            echo '<button type="button" class="ff-price__clear" data-ff-param="min_price" data-ff-param-secondary="max_price">'
                . esc_html__( 'Clear', 'filter-forge' ) . '</button>';
        }
    }

    private function render_buckets( array $buckets, ?string $current_min, ?string $current_max ): void {
        echo '<ul class="ff-price ff-price--buckets">';

        foreach ( $buckets as $bucket ) {
            $min = isset( $bucket['min'] ) ? (string) $bucket['min'] : '';
            $max = isset( $bucket['max'] ) && '' !== $bucket['max'] ? (string) $bucket['max'] : '';

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

            printf(
                '<li><a href="%1$s" class="%2$s">%3$s</a></li>',
                esc_url( $url ),
                $is_active ? 'ff-price__bucket--active' : '',
                esc_html( $bucket['label'] ?? '' )
            );
        }

        echo '</ul>';
    }

    private function render_slider( ?string $current_min, ?string $current_max ): void {
        global $wpdb;

        $bounds = $wpdb->get_row(
            "SELECT MIN(meta_value + 0) AS min_price, MAX(meta_value + 0) AS max_price
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_price'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'"
        );

        printf(
            '<div class="ff-price ff-price--slider" data-ff-min="%1$s" data-ff-max="%2$s" data-ff-current-min="%3$s" data-ff-current-max="%4$s"></div>',
            esc_attr( $bounds->min_price ?? '0' ),
            esc_attr( $bounds->max_price ?? '0' ),
            esc_attr( $current_min ?? $bounds->min_price ?? '0' ),
            esc_attr( $current_max ?? $bounds->max_price ?? '0' )
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php
git commit -m "Add FF_Widget_Price (slider and predefined-bucket modes)"
```

---

### Task 15: `FF_Widget_Reset`

**Files:**
- Create: `filter-forge/includes/widgets/class-widget-reset.php`
- Test: `filter-forge/tests/test-class-widget-reset.php` (only for the pure
  URL-stripping helper, extracted so it's independently testable)

**Interfaces:**
- Consumes: nothing new.
- Produces: `FF_Widget_Reset::canonical_url( string $requested_url ): string` (static,
  pure — strips all query args) plus the Elementor widget `ff-reset` that uses it.

- [ ] **Step 1: Write the failing test for the pure helper**

`filter-forge/tests/test-class-widget-reset.php`:
```php
<?php

class Test_FF_Widget_Reset extends WP_UnitTestCase {

    public function test_canonical_url_strips_all_query_args() {
        $this->assertSame(
            'https://example.com/product-category/airsoft-guns/',
            FF_Widget_Reset::canonical_url( 'https://example.com/product-category/airsoft-guns/?filter_pa_color=black&min_price=50&orderby=price' )
        );
    }

    public function test_canonical_url_returns_url_unchanged_when_no_query_args() {
        $this->assertSame(
            'https://example.com/shop/',
            FF_Widget_Reset::canonical_url( 'https://example.com/shop/' )
        );
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `npm run test:php`
Expected: FAIL — `FF_Widget_Reset` not found.
(This test file loads independently of Elementor since it only exercises the static
helper method, not `render()`/`register_controls()`.)

- [ ] **Step 3: Implement `FF_Widget_Reset`**

`filter-forge/includes/widgets/class-widget-reset.php`:
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Reset extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'ff-reset';
    }

    public function get_title(): string {
        return __( 'Reset Filters', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'eicon-refresh';
    }

    public function get_categories(): array {
        return array( 'filter-forge' );
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'ff_reset_content',
            array(
                'label' => __( 'Content', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_reset_label',
            array(
                'label'   => __( 'Label', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::TEXT,
                'default' => __( 'Reset Filters', 'filter-forge' ),
            )
        );

        $this->end_controls_section();
    }

    public static function canonical_url( string $requested_url ): string {
        $parts = wp_parse_url( $requested_url );
        $path  = ( $parts['scheme'] ?? '' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' );

        return $path;
    }

    public function render(): void {
        $settings = $this->get_settings_for_display();
        $url      = self::canonical_url( home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        printf(
            '<a class="ff-reset" href="%1$s">%2$s</a>',
            esc_url( $url ),
            esc_html( $settings['ff_reset_label'] ?? __( 'Reset Filters', 'filter-forge' ) )
        );
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `npm run test:php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-reset.php filter-forge/tests/test-class-widget-reset.php
git commit -m "Add FF_Widget_Reset with a pure, tested canonical-URL helper"
```

---

### Task 16: Front-end JS and CSS

**Files:**
- Create: `filter-forge/assets/js/ff-url.js`
- Create: `filter-forge/assets/js/ff-filters.js`
- Create: `filter-forge/assets/css/ff-filters.css`
- Modify: `filter-forge/includes/class-plugin.php` (enqueue)

**Interfaces:**
- Consumes: the `data-ff-param` / `data-ff-filter-key` / `data-ff-parent-key` /
  `data-ff-reset-on-change` attributes and `.ff-filter__clear` / `.ff-price__clear`
  buttons rendered by `FF_Widget_Filter` (Task 13) and `FF_Widget_Price` (Task 14).
- Produces: global `FFUrl` object (`get`, `set`, `remove`, `navigate`) used by
  `ff-filters.js`. No PHP consumes this — it's the final, browser-side piece.

No PHPUnit test — this is browser JS with no build step or JS test runner in this
plan, per the "no automated Elementor-editor-level browser tests" scope. Verified
manually in Task 17.

- [ ] **Step 1: Implement the URL helper**

`filter-forge/assets/js/ff-url.js`:
```js
window.FFUrl = ( function () {
    function get( key ) {
        return new URLSearchParams( window.location.search ).get( key );
    }

    function set( key, value ) {
        const params = new URLSearchParams( window.location.search );
        if ( value === '' || value === null ) {
            params.delete( key );
        } else {
            params.set( key, value );
        }
        return params;
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

- [ ] **Step 2: Implement the filter interaction wiring**

`filter-forge/assets/js/ff-filters.js`:
```js
document.addEventListener( 'change', function ( event ) {
    const input = event.target.closest( '[data-ff-param]' );
    if ( ! input ) {
        return;
    }

    const list = input.closest( '.ff-filter' );
    let params = new URLSearchParams( window.location.search );

    if ( input.type === 'checkbox' ) {
        const param    = input.getAttribute( 'data-ff-param' );
        const existing = ( params.get( param ) || '' ).split( ',' ).filter( Boolean );
        const value    = input.value;
        const next     = input.checked
            ? existing.concat( [ value ] )
            : existing.filter( function ( v ) { return v !== value; } );

        params = FFUrl.set( param, next.join( ',' ) );
    }

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

document.addEventListener( 'click', function ( event ) {
    const clearBtn = event.target.closest( '.ff-filter__clear, .ff-price__clear' );
    if ( ! clearBtn ) {
        return;
    }

    let params = FFUrl.remove( clearBtn.getAttribute( 'data-ff-param' ) );

    const secondary = clearBtn.getAttribute( 'data-ff-param-secondary' );
    if ( secondary ) {
        params = FFUrl.remove( secondary, params );
    }

    FFUrl.navigate( params );
} );
```

The `data-ff-parent-key` / `data-ff-reset-on-change` attributes and the
`.ff-filter__clear` / `.ff-price__clear` buttons this script reads are already emitted
by `FF_Widget_Filter::render()` and `FF_Widget_Price::render()` from Tasks 13–14 — no
further widget changes are needed here.

- [ ] **Step 3: Add minimal CSS**

`filter-forge/assets/css/ff-filters.css`:
```css
.ff-filter {
    list-style: none;
    margin: 0;
    padding: 0;
}

.ff-filter li {
    margin: 0 0 0.5em;
}

.ff-price--buckets {
    list-style: none;
    margin: 0;
    padding: 0;
}

.ff-price__bucket--active {
    font-weight: bold;
}
```

- [ ] **Step 4: Enqueue the assets**

Add to `filter-forge/includes/class-plugin.php`, inside the `__construct()` method
(after the existing `add_action` calls):
```php
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
```

And add the method:
```php
    public function enqueue_assets(): void {
        wp_enqueue_style( 'ff-filters', FF_PLUGIN_URL . 'assets/css/ff-filters.css', array(), FF_VERSION );
        wp_enqueue_script( 'ff-url', FF_PLUGIN_URL . 'assets/js/ff-url.js', array(), FF_VERSION, true );
        wp_enqueue_script( 'ff-filters', FF_PLUGIN_URL . 'assets/js/ff-filters.js', array( 'ff-url' ), FF_VERSION, true );
    }
```

- [ ] **Step 5: Run the full PHP suite once more to confirm no regressions**

Run: `npm run test:php`
Expected: PASS (all tests from Tasks 1–15 still pass; this task added no PHP tests).

- [ ] **Step 6: Commit**

```bash
git add filter-forge/assets/ filter-forge/includes/class-plugin.php
git commit -m "Add front-end JS/CSS: instant-navigate filtering, reset-on-change, and clear buttons"
```

---

### Task 17: Manual verification pass

**Files:** none (verification only).

**Interfaces:** exercises the whole system built in Tasks 1–16.

This is the integration gate the spec calls for in place of automated
Elementor-editor-level tests. Work through it on the running wp-env site.

- [ ] **Step 1: Start the site and confirm the plugin activates cleanly**

```bash
npm run env:start
```
Visit `http://localhost:8888/wp-admin/plugins.php`. Activate WooCommerce, Elementor,
and Filter Forge (Filter Forge should already be active as a mounted plugin). Confirm
no PHP notices/fatals in `wp-content/debug.log`.

- [ ] **Step 2: Deactivate WooCommerce, confirm the requirements notice appears**

Deactivate WooCommerce only. Confirm an admin notice reading "Filter Forge requires
the following plugin(s) to be active: WooCommerce" appears, and that no PHP fatal
occurs. Reactivate WooCommerce.

- [ ] **Step 3: Create sample data**

Via `wp-admin/edit.php?post_type=product`, create at least 6 products spanning:
- 2 categories (e.g. Pistols, Rifles)
- One `pa_color` attribute (e.g. Black, Tan) used on at least 2 products
- A custom field `material` (e.g. Nylon, ABS) set via Custom Fields on at least 2
  products
- Varying prices spanning at least 3 of your configured price buckets

- [ ] **Step 4: Build a test page**

Create a new page, edit with Elementor. Add:
- A Loop Grid widget, set to pull from the Archive/Main Query — or simply use the
  existing Shop page (edited with Elementor) if the site's Shop page already uses
  Elementor Pro's Products/Loop Grid widget bound to the main query.
- A Filter widget: Source Type = Taxonomy, Taxonomy = Category, Display Style =
  Checkbox, Filter Key = `category`.
- A Filter widget: Source Type = Custom Field, Meta Key = `material`, Display Style =
  Checkbox, Filter Key = `material`, Parent Filter Key = `category`, both relationship
  toggles on.
- A Price widget: Mode = Predefined Buckets, with 3–4 bucket rows.
- A Reset widget.

- [ ] **Step 5: Verify category + material filtering and faceted counts**

- Check a category checkbox → page reloads, grid shows only that category's products,
  URL contains `ff_tax_product_cat=<slug>`.
- Confirm the Material filter's counts reflect only products in the selected category
  (faceted counting from Task 13).
- Confirm a material with 0 matches in the selected category is hidden (hide-zero-result).

- [ ] **Step 6: Verify parent/child relationship behavior**

- With no category selected, confirm the Material filter widget does not render at
  all (hide-until-parent-selected).
- Select a category, confirm Material appears. Select a material value, then change
  the category selection — confirm the Material filter's own selection is cleared
  (reset-on-change) and the URL no longer contains the old `ff_material` value.

- [ ] **Step 7: Verify price buckets and native attribute filtering**

- Click a price bucket link, confirm the URL contains literal `min_price`/`max_price`
  and the grid updates to matching products.
- If a `pa_color` Filter widget is added (Source Type = Taxonomy, Taxonomy = Color),
  confirm its checkboxes read/write the native `filter_pa_color` param and that
  WooCommerce's own handling filters the grid correctly with zero Filter Forge query
  code involved.

- [ ] **Step 8: Verify Reset widget**

From a page with several filters and a price bucket active, click Reset. Confirm the
URL returns exactly to the canonical archive path with no query string, and the grid
shows the unfiltered result set.

- [ ] **Step 9: Record results and any follow-ups**

Note anything that didn't behave as expected. Given this is the first real
integration test of code that was written without a live WP/WooCommerce/Elementor
environment to run against, some fixes here are expected — that's what this step is
for. Fix issues directly, re-run the relevant PHPUnit tests if a fix touches tested
code, then re-verify manually.

- [ ] **Step 10: Commit any fixes made during verification**

```bash
git add -A
git commit -m "Fix issues found during manual integration verification"
```
