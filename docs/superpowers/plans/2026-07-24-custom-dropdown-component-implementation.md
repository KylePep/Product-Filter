# Custom Dropdown Component Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two native `<select>` dropdowns in the plugin (Filter widget's "Dropdown" display style, Price widget's bucket "Dropdown" display style) with a single reusable, fully stylable, keyboard-accessible custom listbox component, without changing any PHP render output or the existing filtering/URL-navigation JS.

**Architecture:** A new vanilla JS file (`assets/js/ff-dropdown.js`) progressively enhances every `select.ff-filter--dropdown` / `select.ff-price--buckets-dropdown` on the page at load time: it visually hides the real `<select>` and layers a custom trigger-button + `role="listbox"` popup on top, built from that select's own `<option>` list. Picking an option sets the hidden select's `.value` and dispatches a native `change` event on it, so the existing `ff-filters.js` change-listener drives the URL exactly as it does today — zero changes to that file. New structural CSS in `ff-filters.css` gives the component a base look; new Elementor Style tab controls (a shared `register_dropdown_style_controls()` method on `FF_Widget_Base`, called from both widgets) make every visual aspect configurable.

**Tech Stack:** Vanilla JS (no build step, no framework — matches `ff-filters.js`/`ff-url.js`), plain CSS, PHP 8.3 / Elementor Controls API (same as the sibling Style-tab-controls plan).

## Global Constraints

- No changes to `FF_Widget_Filter::render_dropdown()` or `FF_Widget_Price::render_buckets_dropdown()` — this is a pure client-side enhancement layer over existing markup.
- No changes to `assets/js/ff-filters.js`'s change-event handling — the new component integrates purely by mutating the existing native `<select>`'s value and dispatching `change` on it.
- No searchable/filterable combobox, no mobile-native-picker fallback, no changes to any other input type (checkbox/radio, number inputs, range slider) — all explicitly out of scope per the design doc.
- No automated tests exist or are being added for widget rendering or JS behavior (Elementor widgets need editor context; this plugin has no JS test runner). Per-task verification is `php -l` for PHP files and `node --check` for the JS file (Node v20 confirmed available locally on PATH). Full manual verification (mouse, keyboard-only, screen-reader sanity, no-JS fallback) is done by the user afterward against the design doc's checklist, not part of this plan.
- New Style tab controls default to blank/unset (no `default` value) except where noted, so the *only* baseline look before any control is touched comes from the new structural CSS added in Task 1 — not from inline PHP styling.
- Every file path referenced below is relative to the repository root `c:\source\WordPress Plugin\Product Filter`.

---

### Task 1: Structural CSS for the dropdown component

**Files:**
- Modify: `filter-forge/assets/css/ff-filters.css`

**Interfaces:**
- Produces: CSS classes `.ff-dropdown`, `.ff-dropdown__native`, `.ff-dropdown__trigger`, `.ff-dropdown__panel`, `.ff-dropdown__option`, `.ff-dropdown__option--active` — consumed by the JS built in Task 2 and the Elementor selectors added in Task 4.

- [ ] **Step 1: Append the new rules**

Open `filter-forge/assets/css/ff-filters.css`. Append the following after the final existing rule (`.ff-price__range::-moz-range-thumb { ... }`, which currently ends at line 252):

```css

.ff-dropdown {
    position: relative;
}

.ff-dropdown__native {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
}

.ff-dropdown__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5em;
    width: 100%;
    padding: 0.5em 0.75em;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    text-align: left;
}

.ff-dropdown__trigger::after {
    content: "";
    flex: 0 0 auto;
    width: 0.5em;
    height: 0.5em;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    margin-top: -0.25em;
}

.ff-dropdown__panel {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 20;
    margin: 0.25em 0 0;
    padding: 0.25em 0;
    list-style: none;
    background: #fff;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    overflow-y: auto;
    max-height: 16em;
}

.ff-dropdown__option {
    padding: 0.5em 0.75em;
    cursor: pointer;
}

.ff-dropdown__option--active {
    background: #f0f0f1;
}

.ff-dropdown__option[aria-selected="true"] {
    font-weight: 600;
}
```

- [ ] **Step 2: Sanity-check brace balance**

This project has no CSS linter or build step, so there's no formal syntax checker. As a lightweight proxy, confirm the file's `{`/`}` counts match:

```bash
grep -o '{' filter-forge/assets/css/ff-filters.css | wc -l
grep -o '}' filter-forge/assets/css/ff-filters.css | wc -l
```

Expected: both commands print the same number.

- [ ] **Step 3: Commit**

```bash
git add filter-forge/assets/css/ff-filters.css
git commit -m "Add structural CSS for custom dropdown component

Base (non-configurable) layout for .ff-dropdown and its trigger/panel/
option parts. Not yet consumed by any JS or PHP -- those come next."
```

---

### Task 2: `ff-dropdown.js` component

**Files:**
- Create: `filter-forge/assets/js/ff-dropdown.js`

**Interfaces:**
- Consumes: CSS classes from Task 1 (`.ff-dropdown`, `.ff-dropdown__native`, `.ff-dropdown__trigger`, `.ff-dropdown__panel`, `.ff-dropdown__option`, `.ff-dropdown__option--active`).
- Produces: on `DOMContentLoaded`, enhances every `select.ff-filter--dropdown, select.ff-price--buckets-dropdown` in the document. No exported functions/globals — this is a self-invoking script, same pattern as `ff-filters.js`.

- [ ] **Step 1: Write the file**

```javascript
( function () {
    'use strict';

    var instanceCount = 0;

    function enhance( select ) {
        instanceCount += 1;
        var idPrefix = 'ff-dropdown-' + instanceCount;

        var wrapper = document.createElement( 'div' );
        wrapper.className = 'ff-dropdown';
        select.insertAdjacentElement( 'beforebegin', wrapper );
        wrapper.appendChild( select );

        // The trigger/panel become the accessible interface; the select
        // stays in the DOM (for the no-JS fallback and so existing
        // change-event listeners keep working) but is no longer the thing
        // a user or screen reader interacts with directly.
        select.classList.add( 'ff-dropdown__native' );
        select.setAttribute( 'aria-hidden', 'true' );
        select.setAttribute( 'tabindex', '-1' );

        var trigger = document.createElement( 'button' );
        trigger.type = 'button';
        trigger.className = 'ff-dropdown__trigger';
        trigger.setAttribute( 'aria-haspopup', 'listbox' );
        trigger.setAttribute( 'aria-expanded', 'false' );
        trigger.id = idPrefix + '-trigger';

        var panel = document.createElement( 'ul' );
        panel.className = 'ff-dropdown__panel';
        panel.setAttribute( 'role', 'listbox' );
        panel.setAttribute( 'tabindex', '-1' );
        panel.id = idPrefix + '-panel';
        panel.hidden = true;

        trigger.setAttribute( 'aria-controls', panel.id );

        var options = Array.prototype.map.call( select.options, function ( option, index ) {
            var li = document.createElement( 'li' );
            li.className = 'ff-dropdown__option';
            li.setAttribute( 'role', 'option' );
            li.id = idPrefix + '-option-' + index;
            li.textContent = option.textContent;
            li.dataset.value = option.value;
            li.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );
            panel.appendChild( li );
            return li;
        } );

        function selectedIndex() {
            var index = select.selectedIndex;
            return index >= 0 ? index : 0;
        }

        function updateTriggerLabel() {
            var option = options[ selectedIndex() ];
            trigger.textContent = option ? option.textContent : '';
        }

        var activeIndex = selectedIndex();

        function setActive( index ) {
            if ( index < 0 || index >= options.length ) {
                return;
            }
            activeIndex = index;
            options.forEach( function ( li, i ) {
                li.classList.toggle( 'ff-dropdown__option--active', i === index );
            } );
            panel.setAttribute( 'aria-activedescendant', options[ index ].id );
            options[ index ].scrollIntoView( { block: 'nearest' } );
        }

        function isOpen() {
            return ! panel.hidden;
        }

        function openPanel() {
            panel.hidden = false;
            trigger.setAttribute( 'aria-expanded', 'true' );
            setActive( selectedIndex() );
            panel.focus();
        }

        function closePanel() {
            panel.hidden = true;
            trigger.setAttribute( 'aria-expanded', 'false' );
        }

        function commit( index ) {
            var option = options[ index ];
            if ( ! option ) {
                return;
            }
            select.value = option.dataset.value;
            options.forEach( function ( li, i ) {
                li.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
            } );
            updateTriggerLabel();
            select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        }

        trigger.addEventListener( 'click', function () {
            if ( isOpen() ) {
                closePanel();
            } else {
                openPanel();
            }
        } );

        trigger.addEventListener( 'keydown', function ( event ) {
            if ( isOpen() ) {
                return;
            }
            if ( 'Enter' === event.key || ' ' === event.key || 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
                event.preventDefault();
                openPanel();
            }
        } );

        var typeAhead = '';
        var typeAheadTimer = null;

        function jumpToTypeAhead( char ) {
            typeAhead += char.toLowerCase();

            if ( typeAheadTimer ) {
                clearTimeout( typeAheadTimer );
            }
            typeAheadTimer = setTimeout( function () {
                typeAhead = '';
            }, 600 );

            var count = options.length;
            for ( var offset = 1; offset <= count; offset += 1 ) {
                var index = ( activeIndex + offset ) % count;
                var label = options[ index ].textContent.trim().toLowerCase();
                if ( 0 === label.indexOf( typeAhead ) ) {
                    setActive( index );
                    return;
                }
            }
        }

        panel.addEventListener( 'keydown', function ( event ) {
            switch ( event.key ) {
                case 'ArrowDown':
                    event.preventDefault();
                    setActive( Math.min( activeIndex + 1, options.length - 1 ) );
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    setActive( Math.max( activeIndex - 1, 0 ) );
                    break;
                case 'Home':
                    event.preventDefault();
                    setActive( 0 );
                    break;
                case 'End':
                    event.preventDefault();
                    setActive( options.length - 1 );
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    commit( activeIndex );
                    closePanel();
                    trigger.focus();
                    break;
                case 'Escape':
                    event.preventDefault();
                    closePanel();
                    trigger.focus();
                    break;
                case 'Tab':
                    closePanel();
                    break;
                default:
                    if ( 1 === event.key.length ) {
                        jumpToTypeAhead( event.key );
                    }
                    break;
            }
        } );

        panel.addEventListener( 'click', function ( event ) {
            var li = event.target.closest( '.ff-dropdown__option' );
            if ( ! li ) {
                return;
            }
            commit( options.indexOf( li ) );
            closePanel();
            trigger.focus();
        } );

        document.addEventListener( 'click', function ( event ) {
            // Compare against this instance's own wrapper (not just "is
            // there any .ff-dropdown ancestor") so opening a second
            // dropdown while this one is open still closes this one.
            if ( isOpen() && event.target.closest( '.ff-dropdown' ) !== wrapper ) {
                closePanel();
            }
        } );

        wrapper.appendChild( trigger );
        wrapper.appendChild( panel );

        updateTriggerLabel();
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'select.ff-filter--dropdown, select.ff-price--buckets-dropdown' ).forEach( enhance );
    } );
}() );
```

- [ ] **Step 2: Syntax check**

```bash
node --check filter-forge/assets/js/ff-dropdown.js
```

Expected: no output, exit code 0 (Node's `--check` prints nothing on success).

- [ ] **Step 3: Commit**

```bash
git add filter-forge/assets/js/ff-dropdown.js
git commit -m "Add ff-dropdown.js custom listbox component

Progressive enhancement of select.ff-filter--dropdown and
select.ff-price--buckets-dropdown: hides the native select, builds a
button + role=listbox popup from its options, and drives it by setting
the hidden select's value and dispatching change -- ff-filters.js's
existing change handling is unmodified. Not yet enqueued."
```

---

### Task 3: Enqueue `ff-dropdown.js`

**Files:**
- Modify: `filter-forge/includes/class-plugin.php`

**Interfaces:**
- Consumes: `filter-forge/assets/js/ff-dropdown.js` (Task 2).

- [ ] **Step 1: Add the enqueue call**

In `enqueue_assets()`, change:

```php
    public function enqueue_assets(): void {
        wp_enqueue_style( 'ff-filters', FF_PLUGIN_URL . 'assets/css/ff-filters.css', array(), FF_VERSION );
        wp_enqueue_script( 'ff-url', FF_PLUGIN_URL . 'assets/js/ff-url.js', array(), FF_VERSION, true );
        wp_enqueue_script( 'ff-filters', FF_PLUGIN_URL . 'assets/js/ff-filters.js', array( 'ff-url' ), FF_VERSION, true );
    }
```

to:

```php
    public function enqueue_assets(): void {
        wp_enqueue_style( 'ff-filters', FF_PLUGIN_URL . 'assets/css/ff-filters.css', array(), FF_VERSION );
        wp_enqueue_script( 'ff-url', FF_PLUGIN_URL . 'assets/js/ff-url.js', array(), FF_VERSION, true );
        wp_enqueue_script( 'ff-filters', FF_PLUGIN_URL . 'assets/js/ff-filters.js', array( 'ff-url' ), FF_VERSION, true );
        wp_enqueue_script( 'ff-dropdown', FF_PLUGIN_URL . 'assets/js/ff-dropdown.js', array(), FF_VERSION, true );
    }
```

`ff-dropdown` has no dependency on `ff-url`/`ff-filters` — it only ever mutates a `<select>`'s value and dispatches `change`; it never touches `window.location` itself.

- [ ] **Step 2: Syntax check**

```bash
php -l filter-forge/includes/class-plugin.php
```

Expected: `No syntax errors detected in filter-forge/includes/class-plugin.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/class-plugin.php
git commit -m "Enqueue ff-dropdown.js"
```

---

### Task 4: Shared `register_dropdown_style_controls()` on `FF_Widget_Base`

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-base.php`

**Interfaces:**
- Produces: `protected function register_dropdown_style_controls( array $condition ): void` on `FF_Widget_Base`. Task 5 and Task 6 call this from their own `register_controls()`, each passing a different `$condition`.

- [ ] **Step 1: Add the method**

Insert immediately before the final closing `}` of the class (i.e., after `register_header_style_controls()`, which was added by the prior Style-tab-controls plan):

```php
    protected function register_dropdown_style_controls( array $condition ): void {
        $this->start_controls_section(
            'ff_style_dropdown',
            array(
                'label'     => __( 'Dropdown', 'filter-forge' ),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => $condition,
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_heading',
            array(
                'label' => __( 'Trigger', 'filter-forge' ),
                'type'  => \Elementor\Controls_Manager::HEADING,
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_dropdown_trigger_border',
                'selector' => '{{WRAPPER}} .ff-dropdown__trigger',
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_dropdown_trigger_typography',
                'selector' => '{{WRAPPER}} .ff-dropdown__trigger',
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_focus_color',
            array(
                'label'     => __( 'Focus Outline Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger:focus' => 'outline-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_panel_heading',
            array(
                'label'     => __( 'Panel', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_dropdown_panel_border',
                'selector' => '{{WRAPPER}} .ff-dropdown__panel',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_dropdown_panel_box_shadow',
                'selector' => '{{WRAPPER}} .ff-dropdown__panel',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_max_height',
            array(
                'label'     => __( 'Max Height', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array(
                    'px' => array( 'min' => 50, 'max' => 600 ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'max-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_heading',
            array(
                'label'     => __( 'Option', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'ff_dropdown_option_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_dropdown_option_typography',
                'selector' => '{{WRAPPER}} .ff-dropdown__option',
            )
        );

        $this->add_control(
            'ff_dropdown_option_hover_bg_color',
            array(
                'label'     => __( 'Hover Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option:hover, {{WRAPPER}} .ff-dropdown__option--active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_hover_text_color',
            array(
                'label'     => __( 'Hover Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option:hover, {{WRAPPER}} .ff-dropdown__option--active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_selected_bg_color',
            array(
                'label'     => __( 'Selected Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option[aria-selected="true"]' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_selected_text_color',
            array(
                'label'     => __( 'Selected Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option[aria-selected="true"]' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }
```

Note: keyboard-active (`.ff-dropdown__option--active`, set by `ff-dropdown.js` in Task 2) shares the same "Hover" color controls as mouse-hover — both represent "this is the currently highlighted option," so one pair of controls covers both without doubling the control count.

- [ ] **Step 2: Syntax check**

```bash
php -l filter-forge/includes/widgets/class-widget-base.php
```

Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-base.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-base.php
git commit -m "Add register_dropdown_style_controls() to FF_Widget_Base

Trigger/Panel/Option style controls for the custom dropdown component,
reusable by any widget that extends FF_Widget_Base. Not yet called
from any widget."
```

---

### Task 5: Wire dropdown style controls into `FF_Widget_Filter`

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-filter.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_dropdown_style_controls( array $condition ): void` (Task 4).

- [ ] **Step 1: Add the call**

In `register_controls()`, immediately after the existing:

```php
        $this->register_text_style_controls();
        $this->register_button_style_controls();
        $this->register_header_style_controls();
```

add:

```php
        $this->register_dropdown_style_controls( array( 'ff_display_style' => 'dropdown' ) );
```

so the Style tab's Dropdown section only appears when this widget's Display Style is set to "Dropdown".

- [ ] **Step 2: Syntax check**

```bash
php -l filter-forge/includes/widgets/class-widget-filter.php
```

Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-filter.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-filter.php
git commit -m "Add Dropdown style section to Filter widget"
```

---

### Task 6: Wire dropdown style controls into `FF_Widget_Price`

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-price.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_dropdown_style_controls( array $condition ): void` (Task 4).

- [ ] **Step 1: Add the call**

In `register_controls()`, immediately after the existing:

```php
        $this->register_text_style_controls();
        $this->register_button_style_controls();
```

add:

```php
        $this->register_dropdown_style_controls( array( 'ff_price_bucket_style' => 'dropdown' ) );
```

so the Style tab's Dropdown section only appears when this widget's Bucket Display Style is set to "Dropdown".

- [ ] **Step 2: Syntax check**

```bash
php -l filter-forge/includes/widgets/class-widget-price.php
```

Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-price.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php
git commit -m "Add Dropdown style section to Price widget"
```

---

### Task 7: Final regression check and rebuild the plugin zip

**Files:**
- None modified (verification + packaging only).

**Interfaces:**
- Consumes: all files from Tasks 1–6.

- [ ] **Step 1: Re-run all syntax checks**

```bash
php -l filter-forge/includes/class-plugin.php && \
php -l filter-forge/includes/widgets/class-widget-base.php && \
php -l filter-forge/includes/widgets/class-widget-filter.php && \
php -l filter-forge/includes/widgets/class-widget-price.php && \
node --check filter-forge/assets/js/ff-dropdown.js
```

Expected: all four `php -l` calls print `No syntax errors detected in ...`; `node --check` prints nothing.

- [ ] **Step 2: Build `filter-forge.zip`**

Follow the `building-plugin-zip` skill's procedure, updated to also include the new `ff-dropdown.js` file:

```bash
FOLDER=filter-forge

rm -rf /tmp/ff-zip-build
mkdir -p "/tmp/ff-zip-build/$FOLDER"
cp filter-forge/filter-forge.php "/tmp/ff-zip-build/$FOLDER/"
mkdir -p "/tmp/ff-zip-build/$FOLDER/assets/css" "/tmp/ff-zip-build/$FOLDER/assets/js"
cp filter-forge/assets/css/ff-filters.css "/tmp/ff-zip-build/$FOLDER/assets/css/"
cp filter-forge/assets/js/ff-filters.js filter-forge/assets/js/ff-url.js filter-forge/assets/js/ff-dropdown.js "/tmp/ff-zip-build/$FOLDER/assets/js/"
mkdir -p "/tmp/ff-zip-build/$FOLDER/includes/admin" "/tmp/ff-zip-build/$FOLDER/includes/providers" \
         "/tmp/ff-zip-build/$FOLDER/includes/services" "/tmp/ff-zip-build/$FOLDER/includes/widgets"
cp filter-forge/includes/class-plugin.php "/tmp/ff-zip-build/$FOLDER/includes/"
cp filter-forge/includes/admin/class-requirements-notice.php "/tmp/ff-zip-build/$FOLDER/includes/admin/"
cp filter-forge/includes/providers/*.php "/tmp/ff-zip-build/$FOLDER/includes/providers/"
cp filter-forge/includes/services/*.php "/tmp/ff-zip-build/$FOLDER/includes/services/"
cp filter-forge/includes/widgets/*.php "/tmp/ff-zip-build/$FOLDER/includes/widgets/"

rm -f "$FOLDER.zip"
php -d phar.readonly=0 .claude/skills/building-plugin-zip/make-zip.php \
    /tmp/ff-zip-build \
    "$(pwd)/$FOLDER.zip"
```

- [ ] **Step 3: Verify zip path separators and contents**

```bash
unzip -l filter-forge.zip | head -25
```

Expected: every path uses `/`, none use `\`, and `filter-forge/assets/js/ff-dropdown.js` is listed. If any `\` appears, delete `filter-forge.zip` and re-run Step 2 — do not hand off a zip with backslash separators.

- [ ] **Step 4: Hand off for manual testing**

No commit for this task (`filter-forge.zip` is a gitignored build artifact). Report to the user that `filter-forge.zip` is ready at the repo root, and point them at the "Testing / Verification" checklist in `docs/superpowers/specs/2026-07-24-custom-dropdown-component-design.md` (mouse, keyboard-only, URL-navigation still fires, new Style tab controls take effect, and the no-JS fallback still works).
