# Elementor Style Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add native Elementor Style tab controls to the Filter Forge widgets (Filter, Price, Reset) so the appearance currently achieved with hand-written `.filter-forge` custom CSS — text color, button styling, header divider, reset link color — is configurable from the widget's own Style tab, with no functional/query changes.

**Architecture:** Three new shared style-control-registration methods added to `FF_Widget_Base` (`register_text_style_controls()`, `register_button_style_controls()`, `register_header_style_controls()`), called from `FF_Widget_Filter` and `FF_Widget_Price`. All new controls use Elementor's native `selectors` mechanism (plus `Group_Control_Typography`/`Group_Control_Border`/`Group_Control_Box_Shadow`) so Elementor's own CSS pipeline handles it — no new inline-style PHP. Existing Content-tab color controls that are genuinely stylistic (icon colors, bucket colors, slider colors) are relocated into new Style-tab sections in their respective widgets, with their rendering logic in `render()` left untouched. `FF_Widget_Reset` gets its own small Style section since it doesn't extend `FF_Widget_Base`.

**Tech Stack:** PHP 8.3, WordPress, Elementor Pro widget controls API (`\Elementor\Controls_Manager`, `\Elementor\Group_Control_Typography`, `\Elementor\Group_Control_Border`, `\Elementor\Group_Control_Box_Shadow`).

## Global Constraints

- No changes to `FF_Filter_State`, query classes, providers, or count logic (styling only).
- No changes to the *rendering logic* of controls being relocated (icon colors, bucket colors, slider CSS custom properties) — only which section/tab registers them moves. Control IDs must stay identical so saved values on existing pages survive.
- No new PHP classes or files. All changes confined to the four files under `filter-forge/includes/widgets/`.
- No automated widget tests exist or are being added (`Widget_Base` needs Elementor editor context — see `CLAUDE.md`). Verification per task is `php -l` (PHP CLI is available locally at `C:\php-8.3.11\php`, already confirmed on PATH). Full manual verification in the Elementor editor is done by the user afterward, not part of this plan.
- New style controls default to blank/unset (no `default` value, or an explicit empty `''` option for `SELECT` controls, labeled "Default") so nothing regresses on pages that don't touch them — `assets/css/ff-filters.css`'s current hardcoded look remains the baseline until a value is chosen.
- Every new file path referenced below is relative to the repository root `c:\source\WordPress Plugin\Product Filter`.

---

### Task 1: Shared style-control methods on `FF_Widget_Base`

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-base.php`

**Interfaces:**
- Produces: three new `protected` methods on `FF_Widget_Base` — `register_text_style_controls(): void`, `register_button_style_controls(): void`, `register_header_style_controls(): void`. Task 2 and Task 3 call all three from their own `register_controls()`.

- [ ] **Step 1: Add the three methods**

Open `filter-forge/includes/widgets/class-widget-base.php`. Insert the new methods right before the final closing `}` of the class (i.e., immediately after the existing `get_relationship_config()` method, which currently ends at line 162):

```php
    protected function register_text_style_controls(): void {
        $this->start_controls_section(
            'ff_style_text',
            array(
                'label' => __( 'Text', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}}' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_text_typography',
                'selector' => '{{WRAPPER}}',
            )
        );

        $this->end_controls_section();
    }

    protected function register_button_style_controls(): void {
        $this->start_controls_section(
            'ff_style_buttons',
            array(
                'label' => __( 'Buttons', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs( 'ff_button_style_tabs' );

        $this->start_controls_tab(
            'ff_button_style_normal',
            array( 'label' => __( 'Normal', 'filter-forge' ) )
        );

        $this->add_control(
            'ff_button_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_button_border',
                'selector' => '{{WRAPPER}} button',
            )
        );

        $this->add_control(
            'ff_button_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_button_box_shadow',
                'selector' => '{{WRAPPER}} button',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'ff_button_style_hover',
            array( 'label' => __( 'Hover', 'filter-forge' ) )
        );

        $this->add_control(
            'ff_button_text_color_hover',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_bg_color_hover',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_border_color_hover',
            array(
                'label'     => __( 'Border Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_button_box_shadow_hover',
                'selector' => '{{WRAPPER}} button:hover',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function register_header_style_controls(): void {
        $this->start_controls_section(
            'ff_style_header',
            array(
                'label' => __( 'Header', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_header_typography',
                'selector' => '{{WRAPPER}} .ff-filter__header',
            )
        );

        $this->add_control(
            'ff_header_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-filter__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_width',
            array(
                'label'     => __( 'Border Bottom Width', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array(
                    'px' => array( 'min' => 0, 'max' => 20 ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_style',
            array(
                'label'     => __( 'Border Bottom Style', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '',
                'options'   => array(
                    ''       => __( 'Default', 'filter-forge' ),
                    'none'   => __( 'None', 'filter-forge' ),
                    'solid'  => __( 'Solid', 'filter-forge' ),
                    'dashed' => __( 'Dashed', 'filter-forge' ),
                    'dotted' => __( 'Dotted', 'filter-forge' ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-style: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_color',
            array(
                'label'     => __( 'Border Bottom Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }
```

- [ ] **Step 2: Syntax check**

Run: `php -l filter-forge/includes/widgets/class-widget-base.php`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-base.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-base.php
git commit -m "Add shared Style-tab control methods to FF_Widget_Base

Text, Buttons, and Header sections, reusable by any widget that extends
FF_Widget_Base. Not yet called from any widget."
```

---

### Task 2: Wire `FF_Widget_Filter`'s Style tab

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-filter.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_text_style_controls()`, `register_button_style_controls()`, `register_header_style_controls()` (from Task 1).
- Produces: no new interfaces for later tasks (each widget's Style tab is independent).

- [ ] **Step 1: Remove the two icon-color controls from the Content tab**

In `register_controls()`, delete these two `add_control` calls (currently lines 98–126, inside the `ff_display` section, right after `ff_option_icon_active` and right after `ff_option_icon_inactive` respectively):

```php
        $this->add_control(
            'ff_option_icon_active_color',
            array(
                'label'     => __( 'Active Icon Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'condition' => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );

```

and

```php

        $this->add_control(
            'ff_option_icon_inactive_color',
            array(
                'label'     => __( 'Inactive Icon Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#555555',
                'condition' => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );
```

The `ff_display` section should now contain, in order: `ff_option_icon_active`, `ff_option_icon_inactive`, `ff_show_counts`, `ff_hide_zero_results`, `ff_show_clear` (the icon *picker* controls stay in Content; only their color controls are moving).

- [ ] **Step 2: Add the new Style tab**

Immediately after the existing line:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();
```

add:

```php

        $this->register_text_style_controls();
        $this->register_button_style_controls();
        $this->register_header_style_controls();

        $this->start_controls_section(
            'ff_style_option_icons',
            array(
                'label' => __( 'Option Icons', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_option_icon_active_color',
            array(
                'label'     => __( 'Active Icon Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'condition' => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );

        $this->add_control(
            'ff_option_icon_inactive_color',
            array(
                'label'     => __( 'Inactive Icon Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#555555',
                'condition' => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );

        $this->end_controls_section();
```

Note the two relocated controls are byte-for-byte identical to what was removed in Step 1 (same control IDs, defaults, and conditions) — only their section/tab changed, so any already-saved values on existing pages are unaffected.

- [ ] **Step 3: Syntax check**

Run: `php -l filter-forge/includes/widgets/class-widget-filter.php`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-filter.php`

- [ ] **Step 4: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-filter.php
git commit -m "Add Style tab to Filter widget; relocate icon colors into it

Text, Buttons, Header, and Option Icons sections. render_list() is
unchanged -- icon color rendering logic still reads the same two
settings keys, just registered under the Style tab now."
```

---

### Task 3: Wire `FF_Widget_Price`'s Style tab

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-price.php`

**Interfaces:**
- Consumes: `FF_Widget_Base::register_text_style_controls()`, `register_button_style_controls()`, `register_header_style_controls()` (from Task 1).
- Produces: no new interfaces for later tasks.

- [ ] **Step 1: Remove the five color controls from the Content tab**

In `register_controls()`, delete these five `add_control` calls (currently lines 106–160, inside the `ff_price_source` section): `ff_bucket_active_color`, `ff_bucket_inactive_color`, `ff_slider_track_color`, `ff_slider_range_color`, `ff_slider_handle_color`. The full blocks being removed:

```php
        $this->add_control(
            'ff_bucket_active_color',
            array(
                'label'     => __( 'Active Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#2271b1',
                'condition' => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
            )
        );

        $this->add_control(
            'ff_bucket_inactive_color',
            array(
                'label'     => __( 'Inactive Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#3c3c3c',
                'condition' => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
            )
        );

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

After this removal, the `ff_price_source` section contains, in order: `ff_price_mode`, `ff_price_buckets`, `ff_price_bucket_style`, `ff_bucket_icon_active`, `ff_bucket_icon_inactive`, `ff_show_clear`.

- [ ] **Step 2: Add the new Style tab**

Immediately after the existing line:

```php
        $this->register_header_controls();
        $this->register_relationship_controls();
```

add:

```php

        $this->register_text_style_controls();
        $this->register_button_style_controls();

        $this->start_controls_section(
            'ff_style_price_clear_spacing',
            array(
                'label' => __( 'Clear Button Spacing', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_price_clear_margin_top',
            array(
                'label'     => __( 'Top Spacing', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array(
                    'px' => array( 'min' => 0, 'max' => 60 ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-price__clear' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array( 'ff_show_clear' => 'yes' ),
            )
        );

        $this->end_controls_section();

        $this->register_header_style_controls();

        $this->start_controls_section(
            'ff_style_bucket_colors',
            array(
                'label'     => __( 'Bucket Colors', 'filter-forge' ),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array( 'ff_price_mode' => 'buckets' ),
            )
        );

        $this->add_control(
            'ff_bucket_active_color',
            array(
                'label'     => __( 'Active Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#2271b1',
                'condition' => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
            )
        );

        $this->add_control(
            'ff_bucket_inactive_color',
            array(
                'label'     => __( 'Inactive Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#3c3c3c',
                'condition' => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'ff_style_slider_colors',
            array(
                'label'     => __( 'Slider Colors', 'filter-forge' ),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array( 'ff_price_mode' => 'slider' ),
            )
        );

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

        $this->end_controls_section();
```

Note: the section-level `'condition'` on `ff_style_bucket_colors` / `ff_style_slider_colors` (hiding the whole section when the price mode doesn't apply) is new UI polish on top of what existed before — the individual controls inside already had their own equivalent conditions, so this doesn't change saved data or rendering, only whether the section header shows up in the editor panel for an irrelevant mode.

- [ ] **Step 3: Syntax check**

Run: `php -l filter-forge/includes/widgets/class-widget-price.php`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-price.php`

- [ ] **Step 4: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-price.php
git commit -m "Add Style tab to Price widget; relocate bucket/slider colors

Text, Buttons, Clear Button Spacing, Header, Bucket Colors, and Slider
Colors sections. render() logic for buckets/slider is unchanged -- same
settings keys, just registered under the Style tab now."
```

---

### Task 4: Add `FF_Widget_Reset`'s Style tab

**Files:**
- Modify: `filter-forge/includes/widgets/class-widget-reset.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–3 (`FF_Widget_Reset` extends `\Elementor\Widget_Base` directly, not `FF_Widget_Base`).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Add the Style section**

In `register_controls()`, immediately after the existing:

```php
        $this->end_controls_section();
    }
```

(the end of the `ff_reset_content` section — this is currently the last thing in the method), insert a new section before that closing `}` of the method, i.e. change it to:

```php
        $this->end_controls_section();

        $this->start_controls_section(
            'ff_reset_style',
            array(
                'label' => __( 'Style', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_reset_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_reset_text_color_hover',
            array(
                'label'     => __( 'Hover Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_reset_typography',
                'selector' => '{{WRAPPER}} .ff-reset',
            )
        );

        $this->add_control(
            'ff_reset_text_decoration',
            array(
                'label'     => __( 'Text Decoration', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '',
                'options'   => array(
                    ''             => __( 'Default', 'filter-forge' ),
                    'none'         => __( 'None', 'filter-forge' ),
                    'underline'    => __( 'Underline', 'filter-forge' ),
                    'overline'     => __( 'Overline', 'filter-forge' ),
                    'line-through' => __( 'Line Through', 'filter-forge' ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset' => 'text-decoration: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }
```

- [ ] **Step 2: Syntax check**

Run: `php -l filter-forge/includes/widgets/class-widget-reset.php`
Expected: `No syntax errors detected in filter-forge/includes/widgets/class-widget-reset.php`

- [ ] **Step 3: Commit**

```bash
git add filter-forge/includes/widgets/class-widget-reset.php
git commit -m "Add Style tab to Reset widget

Text Color, Hover Color, Typography, and Text Decoration controls for
the .ff-reset link."
```

---

### Task 5: Final regression check and build the plugin zip

**Files:**
- None modified (verification + packaging only).

**Interfaces:**
- Consumes: all four files modified in Tasks 1–4.

- [ ] **Step 1: Re-run `php -l` on all four files**

```bash
php -l filter-forge/includes/widgets/class-widget-base.php && \
php -l filter-forge/includes/widgets/class-widget-filter.php && \
php -l filter-forge/includes/widgets/class-widget-price.php && \
php -l filter-forge/includes/widgets/class-widget-reset.php
```

Expected: all four print `No syntax errors detected in ...`.

- [ ] **Step 2: Build `filter-forge.zip`**

Follow the `building-plugin-zip` skill's procedure exactly (it exists specifically to avoid a known `Compress-Archive` path-separator bug on this Windows setup):

```bash
FOLDER=filter-forge

rm -rf /tmp/ff-zip-build
mkdir -p "/tmp/ff-zip-build/$FOLDER"
cp filter-forge/filter-forge.php "/tmp/ff-zip-build/$FOLDER/"
mkdir -p "/tmp/ff-zip-build/$FOLDER/assets/css" "/tmp/ff-zip-build/$FOLDER/assets/js"
cp filter-forge/assets/css/ff-filters.css "/tmp/ff-zip-build/$FOLDER/assets/css/"
cp filter-forge/assets/js/ff-filters.js filter-forge/assets/js/ff-url.js "/tmp/ff-zip-build/$FOLDER/assets/js/"
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

- [ ] **Step 3: Verify zip path separators**

```bash
unzip -l filter-forge.zip | head -8
```

Expected: every path uses `/`, none use `\`. If any `\` appears, delete `filter-forge.zip` and re-run Step 2 — do not hand off a zip with backslash separators.

- [ ] **Step 4: Hand off for manual testing**

No commit for this task (nothing under version control changes — `filter-forge.zip` is a build artifact, not tracked in git). Report to the user that `filter-forge.zip` is ready at the repo root for them to install and manually verify against the checklist in `docs/superpowers/specs/2026-07-22-elementor-style-controls-design.md`'s "Testing / Verification" section.
