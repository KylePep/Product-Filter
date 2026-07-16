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
