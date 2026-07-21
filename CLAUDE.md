# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Filter Forge: a WordPress plugin providing native Elementor Pro widgets that filter
WooCommerce products on archive pages (Shop, category/tag/attribute archives) by
modifying the main WordPress query — with zero coupling to product rendering.
Elementor's own Loop Grid/Products widget (in Main Query mode) renders results
unmodified, on a normal page reload (no AJAX in v1).

Design and implementation are fully documented in:
- `docs/superpowers/specs/2026-07-14-filter-forge-design.md` — the approved
  architecture, including the reasoning behind several non-obvious decisions (see
  below).
- `docs/superpowers/plans/2026-07-14-filter-forge-implementation.md` — the
  task-by-task TDD implementation plan, with exact file paths, code, and test
  commands for every class in the plugin. This is the first place to look before
  writing new code in `filter-forge/`.

Read both before making architectural changes — several early design ideas were
deliberately discarded during planning (documented inline in the spec) once their
implementation cost became clear.

## Commands

All commands run from the repository root (`Product Filter/`), not from inside
`filter-forge/`. The dev environment is Docker-based (`@wordpress/env`); Docker
Desktop must be running first.

```bash
npm install                    # one-time: installs @wordpress/env
npm run env:start              # starts WordPress + WooCommerce + Elementor in Docker
npm run env:stop               # stops the containers (state is preserved)
npm run env:destroy            # tears down containers and volumes entirely

# Install/update PHP dependencies inside the test container (composer.json/lock
# live in filter-forge/, but composer itself only exists inside the container):
npx wp-env run tests-cli --env-cwd=wp-content/plugins/filter-forge composer install

npm run test:php               # runs the full PHPUnit suite inside tests-cli
```

**Running a single test class or method** (append PHPUnit args after `--`; use
`--filter`, not a bare file path — a lone path argument makes PHPUnit expect the file
to define a class matching the file's own name, which our `Test_FF_*` classes don't):
```bash
npm run test:php -- --filter Test_FF_Filter_State
npm run test:php -- --filter test_get_returns_sanitized_value
```

The dev site is at `http://localhost:8888`, the dedicated test site (used only by
PHPUnit, separate DB) at `http://localhost:8889`.

**Windows/Git Bash note:** when running `wp-env run` (or any command with
`/absolute/paths`) directly via `npx wp-env run <container> -- <cmd>` rather than
through an `npm run` script, prefix it with `MSYS_NO_PATHCONV=1` — Git Bash's MSYS
layer otherwise rewrites POSIX-looking paths like `/var/www/html/...` into a mangled
Windows path before Docker ever sees them.

**The plugin does not load `vendor/autoload.php` at runtime — new files need an
explicit `require_once`.** `composer.json`'s `classmap` autoloading was used early on
during development, but `filter-forge.php` no longer loads Composer's autoloader at
all: on the dev site, `vendor/` (1,193 files — PHPUnit and its ~27 transitive
dev-only dependencies, none of which the live plugin needs) sits on the
bind-mounted Windows filesystem, and stat-ing all of it through Docker's slow
cross-filesystem I/O on *every single page load* was the dominant cause of the dev
site feeling sluggish. Fix: `includes/class-plugin.php` now `require_once`s every
service/provider/admin file explicitly (in dependency order — interfaces before their
implementations), and widget files are `require_once`d lazily inside
`register_widgets()`, since they extend `\Elementor\Widget_Base` and must not load
before Elementor does. When adding a new class file under `includes/`, add its
`require_once` to `class-plugin.php` (or to `register_widgets()` for widgets) — there
is no autoload step to run. `vendor/` still exists solely so `vendor/bin/phpunit`
can run itself and its own dependencies; it is otherwise dead weight on the dev site.

**wp-env plugin folder naming:** plugins declared in `.wp-env.json` by zip URL (e.g.
`https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip`) get extracted
into a folder named after the zip's URL slug (`woocommerce.latest-stable`), not the
plugin's own slug (`woocommerce`). Anything that needs to locate WooCommerce's or
Elementor's main plugin file (see `filter-forge/tests/bootstrap.php`) must `glob()`
for it rather than hardcode the path.

## Architecture

### Directory layout (`filter-forge/`)

```
filter-forge.php              # plugin bootstrap
includes/
  class-plugin.php            # FF_Plugin singleton: builds/holds shared services,
                               #   registers hooks and Elementor widgets
  widgets/                    # Elementor widget classes (FF_Widget_Base + 3 widgets)
  providers/                  # FF_Option_Provider implementations (taxonomy, meta)
  services/                   # query filters, filter state, counts, relationships
  admin/                      # requirements notice
assets/{js,css}/               # vanilla JS/CSS, no build step, no framework
tests/                        # WP_UnitTestCase tests, one per class, `test-*.php`
```

### Core principle: reuse WooCommerce's native query layer

WooCommerce already has its own `pre_get_posts` handling for attribute filters
(`filter_pa_*`), price (`min_price`/`max_price`), stock status, and rating. Filter
Forge never duplicates that — it only adds `pre_get_posts` logic for what
WooCommerce has no native support for (non-attribute taxonomies, custom-field/meta
filters). Concretely:

- **WooCommerce attribute taxonomies** (`wc_get_attribute_taxonomy_names()`) use the
  **native** `filter_{taxonomy}` param — WooCommerce's own handling applies, Filter
  Forge does nothing. `FF_Category_Filter::resolve_param()` is the single place that
  decides native-vs-custom for a given taxonomy.
- **Every other taxonomy** (`product_tag`, custom ones like `product_brand`) uses
  `ff_tax_{taxonomy}`, handled by `FF_Category_Filter`. **Exception:** `product_cat`
  gets the bare, unprefixed `category` param instead of `ff_tax_product_cat` — it's
  WooCommerce's own core taxonomy, and site nav/links commonly assume `?category=`
  already works. This alias is a hardcoded special case in
  `FF_Category_Filter::NATIVE_PARAM_ALIASES`, not a general mechanism — a new taxonomy
  doesn't get one without deliberately adding it there.
- `FF_Taxonomy_Provider::get_options()` excludes the taxonomy term of the archive
  page currently being viewed (and its ancestors) from the option list — e.g. on
  `/product-category/airsoft-guns/`, the Category filter widget for `product_cat`
  won't list "Airsoft Guns" itself, since the archive already narrows to it and
  re-offering it as a filter option is redundant.
- **Meta/custom-field filters** use `ff_{meta_key}` directly — the param name *is*
  the meta key. `FF_Meta_Filter` treats any `ff_`-prefixed param that isn't
  `ff_tax_*` as a meta filter. This means `pre_get_posts` (which fires before
  Elementor renders the page) never needs to know in advance which meta keys a
  page's widgets use, or parse Elementor's page-content JSON to find out — it just
  processes whatever `ff_*` params are present on the request.
- **Price** needs no custom query class at all: both the Price widget's Slider and
  Predefined Buckets modes render links/inputs with literal `min_price`/`max_price`
  values computed at render time. WooCommerce's native price handling covers both.

`FF_Query_Manager::is_supported_archive()` gates all of this to WooCommerce archive
pages (Shop/category/tag/attribute) on the main query only — it's also called
directly by the Filter and Price widgets to suppress themselves (with an editor-only
notice) on unsupported pages, since a page-reload filter has nothing to modify if
it's not the main product query.

### Shared services, no DI container

`FF_Plugin` (singleton, `FF_Plugin::instance()`) constructs and holds
`filter_state`, `query_manager`, `count_service`, and `relationship_resolver` once,
at boot. Everything else — widgets, the requirements notice — reads these off
`FF_Plugin::instance()->...` rather than constructing its own copies. This is a
deliberate choice to get DI-like testability without a DI container, since a
container was judged more machinery than this plugin needs.

`FF_Filter_State` is the single point of truth for the current request's filter
selections — nothing else reads `$_GET` directly. Its `with_override()` method
(clone with one key replaced/removed) is what makes faceted option counting possible:
counting reuses `FF_Category_Filter`/`FF_Meta_Filter` directly against a scoped
`FF_Filter_State`, rather than re-implementing query-building logic in the widget.

### Filter relationships vs. faceted counting — do not conflate these

Two independent mechanisms, easy to confuse:

- **Faceted counting** (`FF_Count_Service`, always on, no configuration): every
  filter's option counts are computed against products matching all *other*
  currently-active filters. This is what makes selecting a category automatically
  narrow another filter's counts — no "parent" designation needed. **Known v1
  limitation:** this only cross-references Filter Forge's own custom filters
  (`ff_tax_*`/`ff_*` meta) against each other; it does not fold in currently-active
  *native* WooCommerce filters (attributes/price/stock/rating) when computing
  another filter's counts, since replicating WooCommerce's internal query logic in
  the count probe was judged not worth the complexity for v1.
- **Parent/child relationship config** (opt-in per widget pair, via a plain-text
  **Filter Key** / **Parent Filter Key**, not a stable widget ID — a deliberate
  simplicity-over-robustness trade-off): only governs (a) resetting a child's own
  selection when its parent changes (handled client-side, in JS, at click time —
  there is no server-side "reset" logic) and (b) hiding a child widget entirely
  until its parent has a selection (`FF_Relationship_Resolver::should_render()`,
  server-side).
- **Deferred, not implemented:** an orphaned Parent Filter Key (typo, or the parent
  widget got deleted) has no editor-time warning in v1 — that would require
  Elementor editor-side JS scanning the page's widgets, judged more work than a v1
  nice-to-have justifies. Current behavior: if "hide until parent selected" is on
  and the key never matches a real widget, the child just stays permanently hidden.

### Testing

Every service/provider/query class is unit tested with `WP_UnitTestCase` (real
WordPress + WooCommerce + Elementor loaded, real DB) — see `tests/test-*.php`, one
file per class. Elementor widget classes (`includes/widgets/`) are **not**
unit tested — `\Elementor\Widget_Base` needs the editor's document/controls-stack
context to instantiate meaningfully, which isn't practical in `WP_UnitTestCase`.
Widget rendering, JS interaction, and full end-to-end filtering behavior are
verified manually against the running wp-env site instead (see the plan's Task 17
for the exact checklist).
