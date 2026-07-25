<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Filter extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-filter';
    }

    public function get_title(): string {
        return __( 'Filter - Forge', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'ff-icon-anvil';
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
            'ff_option_icon_active',
            array(
                'label'       => __( 'Active Icon', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::ICONS,
                'description' => __( 'Shown when this option is selected. Set both this and the Inactive Icon to replace the checkbox/radio input with icons; leave either empty to keep the normal input.', 'filter-forge' ),
                'condition'   => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );

        $this->add_control(
            'ff_option_icon_inactive',
            array(
                'label'       => __( 'Inactive Icon', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::ICONS,
                'description' => __( 'Shown when this option is not selected.', 'filter-forge' ),
                'condition'   => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
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

        $this->add_control(
            'ff_show_clear',
            array(
                'label'   => __( 'Show Clear button', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
            )
        );

        $this->end_controls_section();

        $this->register_header_controls();
        $this->register_relationship_controls();

        $this->register_text_style_controls();
        $this->register_button_style_controls();
        $this->register_header_style_controls();
        $this->register_dropdown_style_controls( array( 'ff_display_style' => 'dropdown' ) );

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

        $this->start_controls_section(
            'ff_style_option_hover',
            array(
                'label'     => __( 'Option Hover/Focus', 'filter-forge' ),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array( 'ff_display_style' => array( 'checkbox', 'radio' ) ),
            )
        );

        $this->add_control(
            'ff_option_hover_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter--checkbox li label:hover, {{WRAPPER}} .ff-filter--checkbox li label:has(input:focus-visible), {{WRAPPER}} .ff-filter--radio li label:hover, {{WRAPPER}} .ff-filter--radio li label:has(input:focus-visible)' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_option_hover_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter--checkbox li label:hover, {{WRAPPER}} .ff-filter--checkbox li label:has(input:focus-visible), {{WRAPPER}} .ff-filter--radio li label:hover, {{WRAPPER}} .ff-filter--radio li label:has(input:focus-visible)' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
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

        $this->render_header();

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
        $style        = $settings['ff_display_style'] ?? 'checkbox';

        $visible_options = array();
        foreach ( $options as $option ) {
            $count = $show_counts || $hide_zero
                ? $this->count_for_option( $param, $option['value'] )
                : 1;

            if ( $hide_zero && 0 === $count ) {
                continue;
            }

            $option['count']    = $count;
            $visible_options[] = $option;
        }

        $wrapper_attrs = ' data-ff-filter-key="' . esc_attr( $filter_key ) . '"'
            . ' data-ff-param="' . esc_attr( $param ) . '"'
            . ' data-ff-parent-key="' . esc_attr( $relationship['parent_key'] ) . '"'
            . ' data-ff-reset-on-change="' . ( $relationship['reset_on_change'] ? 'yes' : 'no' ) . '"';

        if ( 'dropdown' === $style ) {
            $this->render_dropdown( $param, $visible_options, $selected, $show_counts, $wrapper_attrs );
        } else {
            $this->render_list( $style, $param, $visible_options, $selected, $show_counts, $wrapper_attrs );
        }

        $show_clear = 'yes' === ( $settings['ff_show_clear'] ?? '' );

        if ( $show_clear && ! empty( $selected ) ) {
            printf(
                '<button type="button" class="ff-filter__clear" data-ff-param="%1$s">%2$s</button>',
                esc_attr( $param ),
                esc_html__( 'Clear', 'filter-forge' )
            );
        }
    }

    /**
     * checkbox/swatch/toggle are all multi-select (same input type, styled
     * differently via CSS); radio is single-select, grouped by `name` so the
     * browser enforces exclusivity.
     */
    private function render_list( string $style, string $param, array $options, array $selected, bool $show_counts, string $wrapper_attrs ): void {
        $input_type = 'radio' === $style ? 'radio' : 'checkbox';
        $name_attr  = 'radio' === $style ? ' name="ff-radio-' . esc_attr( $param ) . '"' : '';

        $active_icon    = array();
        $inactive_icon  = array();
        $active_color   = '#ffffff';
        $inactive_color = '#555555';

        if ( in_array( $style, array( 'checkbox', 'radio' ), true ) ) {
            $settings       = $this->get_settings_for_display();
            $active_icon    = $settings['ff_option_icon_active'] ?? array();
            $inactive_icon  = $settings['ff_option_icon_inactive'] ?? array();
            $active_color   = $settings['ff_option_icon_active_color'] ?? $active_color;
            $inactive_color = $settings['ff_option_icon_inactive_color'] ?? $inactive_color;
        }

        // Both icons are required -- a half-configured pair would leave one state with no visual at all.
        $has_icon = ! empty( $active_icon['value'] ) && ! empty( $inactive_icon['value'] );

        echo '<ul class="ff-filter ff-filter--' . esc_attr( $style ) . ( $has_icon ? ' ff-filter--icon' : '' ) . '"' . $wrapper_attrs . '>';

        foreach ( $options as $option ) {
            if ( ! $has_icon ) {
                printf(
                    '<li><label><input type="%1$s"%2$s value="%3$s" data-ff-param="%4$s" %5$s /> %6$s%7$s</label></li>',
                    esc_attr( $input_type ),
                    $name_attr,
                    esc_attr( $option['value'] ),
                    esc_attr( $param ),
                    checked( in_array( $option['value'], $selected, true ), true, false ),
                    esc_html( $option['label'] ),
                    $show_counts ? ' (' . (int) $option['count'] . ')' : ''
                );
                continue;
            }

            $is_checked = in_array( $option['value'], $selected, true );

            echo '<li><label class="ff-filter__option">';

            printf(
                '<input type="%1$s"%2$s value="%3$s" data-ff-param="%4$s" class="ff-filter__input--icon-mode" %5$s />',
                esc_attr( $input_type ),
                $name_attr,
                esc_attr( $option['value'] ),
                esc_attr( $param ),
                checked( $is_checked, true, false )
            );

            \Elementor\Icons_Manager::render_icon(
                $is_checked ? $active_icon : $inactive_icon,
                array(
                    'class'       => 'ff-filter__option-icon',
                    'style'       => 'color:' . esc_attr( $is_checked ? $active_color : $inactive_color ) . ' !important;',
                    'aria-hidden' => 'true',
                )
            );

            printf(
                ' %1$s%2$s</label></li>',
                esc_html( $option['label'] ),
                $show_counts ? ' (' . (int) $option['count'] . ')' : ''
            );
        }

        echo '</ul>';
    }

    private function render_dropdown( string $param, array $options, array $selected, bool $show_counts, string $wrapper_attrs ): void {
        $selected_value = $selected[0] ?? '';

        echo '<select class="ff-filter ff-filter--dropdown"' . $wrapper_attrs . ' data-ff-param="' . esc_attr( $param ) . '">';

        printf( '<option value="">%s</option>', esc_html__( 'All', 'filter-forge' ) );

        foreach ( $options as $option ) {
            printf(
                '<option value="%1$s" %2$s>%3$s%4$s</option>',
                esc_attr( $option['value'] ),
                selected( $option['value'], $selected_value, false ),
                esc_html( $option['label'] ),
                $show_counts ? ' (' . (int) $option['count'] . ')' : ''
            );
        }

        echo '</select>';
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
