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
