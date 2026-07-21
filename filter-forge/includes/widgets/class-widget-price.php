<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Price extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-price';
    }

    public function get_title(): string {
        return __( 'Price Filter - Forge', 'filter-forge' );
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
                'default' => 'input',
                'options' => array(
                    'input'   => __( 'Min/Max Inputs', 'filter-forge' ),
                    'slider'  => __( 'Slider', 'filter-forge' ),
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

        $this->add_control(
            'ff_price_bucket_style',
            array(
                'label'     => __( 'Bucket Display Style', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => 'list',
                'options'   => array(
                    'list'     => __( 'List', 'filter-forge' ),
                    'dropdown' => __( 'Dropdown', 'filter-forge' ),
                ),
                'condition' => array( 'ff_price_mode' => 'buckets' ),
            )
        );

        $this->add_control(
            'ff_bucket_icon_active',
            array(
                'label'       => __( 'Active Icon', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::ICONS,
                'description' => __( 'Optional. Shown after the label when this bucket is selected. Set both this and the Inactive Icon to show one; leave either empty for text only.', 'filter-forge' ),
                'condition'   => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
            )
        );

        $this->add_control(
            'ff_bucket_icon_inactive',
            array(
                'label'       => __( 'Inactive Icon', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::ICONS,
                'description' => __( 'Shown after the label when this bucket is not selected.', 'filter-forge' ),
                'condition'   => array(
                    'ff_price_mode'         => 'buckets',
                    'ff_price_bucket_style' => 'list',
                ),
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

        $this->render_header();

        $current_min = $plugin->filter_state->get( 'min_price' );
        $current_max = $plugin->filter_state->get( 'max_price' );
        $show_clear  = 'yes' === ( $settings['ff_show_clear'] ?? '' );

        $mode = $settings['ff_price_mode'] ?? 'input';

        if ( 'buckets' === $mode ) {
            $style = $settings['ff_price_bucket_style'] ?? 'list';
            if ( 'dropdown' === $style ) {
                $this->render_buckets_dropdown( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            } else {
                $this->render_buckets_list( $settings['ff_price_buckets'] ?? array(), $current_min, $current_max );
            }
        } elseif ( 'slider' === $mode ) {
            $this->render_slider_range( $current_min, $current_max );
        } else {
            $this->render_min_max_inputs( $current_min, $current_max );
        }

        if ( $show_clear && ( null !== $current_min || null !== $current_max ) ) {
            echo '<button type="button" class="ff-price__clear" data-ff-param="min_price" data-ff-param-secondary="max_price">'
                . esc_html__( 'Clear', 'filter-forge' ) . '</button>';
        }
    }

    /**
     * @return array{0: string, 1: string} [min, max] as literal strings, matching
     * how FF_Filter_State reads them back from the query string.
     */
    private function bucket_min_max( array $bucket ): array {
        $min = isset( $bucket['min'] ) ? (string) $bucket['min'] : '';
        $max = isset( $bucket['max'] ) && '' !== $bucket['max'] ? (string) $bucket['max'] : '';

        return array( $min, $max );
    }

    private function render_buckets_list( array $buckets, ?string $current_min, ?string $current_max ): void {
        $settings       = $this->get_settings_for_display();
        $active_icon    = $settings['ff_bucket_icon_active'] ?? array();
        $inactive_icon  = $settings['ff_bucket_icon_inactive'] ?? array();
        $active_color   = $settings['ff_bucket_active_color'] ?? '#2271b1';
        $inactive_color = $settings['ff_bucket_inactive_color'] ?? '#3c3c3c';

        // Both icons are required -- a half-configured pair would leave one state with no visual at all.
        $has_icon = ! empty( $active_icon['value'] ) && ! empty( $inactive_icon['value'] );

        echo '<ul class="ff-price ff-price--buckets">';

        foreach ( $buckets as $bucket ) {
            [ $min, $max ] = $this->bucket_min_max( $bucket );

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
            $color     = $is_active ? $active_color : $inactive_color;

            printf(
                '<li><a href="%1$s" class="ff-price__bucket%2$s" style="color:%3$s !important;">%4$s',
                esc_url( $url ),
                $is_active ? ' ff-price__bucket--active' : '',
                esc_attr( $color ),
                esc_html( $bucket['label'] ?? '' )
            );

            if ( $has_icon ) {
                \Elementor\Icons_Manager::render_icon(
                    $is_active ? $active_icon : $inactive_icon,
                    array(
                        'class'       => 'ff-price__bucket-icon',
                        'style'       => 'color:' . esc_attr( $color ) . ' !important;',
                        'aria-hidden' => 'true',
                    )
                );
            }

            echo '</a></li>';
        }

        echo '</ul>';
    }

    private function render_buckets_dropdown( array $buckets, ?string $current_min, ?string $current_max ): void {
        echo '<select class="ff-price ff-price--buckets-dropdown">';

        printf( '<option value="">%s</option>', esc_html__( 'All Prices', 'filter-forge' ) );

        foreach ( $buckets as $bucket ) {
            [ $min, $max ] = $this->bucket_min_max( $bucket );
            $is_active     = $current_min === $min && $current_max === $max;

            printf(
                '<option value="%1$s|%2$s" %3$s>%4$s</option>',
                esc_attr( $min ),
                esc_attr( $max ),
                selected( $is_active, true, false ),
                esc_html( $bucket['label'] ?? '' )
            );
        }

        echo '</select>';
    }

    private function get_price_bounds(): object {
        global $wpdb;

        $bounds = $wpdb->get_row(
            "SELECT MIN(meta_value + 0) AS min_price, MAX(meta_value + 0) AS max_price
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_price'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'"
        );

        return (object) array(
            'min_price' => $bounds->min_price ?? '0',
            'max_price' => $bounds->max_price ?? '0',
        );
    }

    /**
     * Literal min/max number input pair -- matches the design's "render
     * links/inputs with literal min_price/max_price values" approach used by
     * every other filter in the plugin.
     */
    private function render_min_max_inputs( ?string $current_min, ?string $current_max ): void {
        $bounds = $this->get_price_bounds();

        printf(
            '<div class="ff-price ff-price--input">
                <label>%1$s <input type="number" class="ff-price__input" data-ff-price-role="min" min="%3$s" max="%4$s" step="any" value="%5$s" /></label>
                <label>%2$s <input type="number" class="ff-price__input" data-ff-price-role="max" min="%3$s" max="%4$s" step="any" value="%6$s" /></label>
                <button type="button" class="ff-price__apply">%7$s</button>
            </div>',
            esc_html__( 'Min', 'filter-forge' ),
            esc_html__( 'Max', 'filter-forge' ),
            esc_attr( $bounds->min_price ),
            esc_attr( $bounds->max_price ),
            esc_attr( $current_min ?? $bounds->min_price ),
            esc_attr( $current_max ?? $bounds->max_price ),
            esc_html__( 'Apply', 'filter-forge' )
        );
    }

    /**
     * Real dual-handle range slider: two overlapping native range inputs plus a
     * server-positioned colored overlay div (so the range looks correct even
     * before JS runs); JS keeps the overlay/live text in sync while dragging.
     */
    private function render_slider_range( ?string $current_min, ?string $current_max ): void {
        $settings = $this->get_settings_for_display();
        $bounds   = $this->get_price_bounds();

        $bound_min = (float) $bounds->min_price;
        $bound_max = (float) $bounds->max_price;
        $span      = max( $bound_max - $bound_min, 1 );

        $value_min = null !== $current_min ? (float) $current_min : $bound_min;
        $value_max = null !== $current_max ? (float) $current_max : $bound_max;

        $left  = ( $value_min - $bound_min ) / $span * 100;
        $width = ( $value_max - $value_min ) / $span * 100;

        $track_color  = $settings['ff_slider_track_color'] ?? '#dcdcde';
        $range_color  = $settings['ff_slider_range_color'] ?? '#2271b1';
        $handle_color = $settings['ff_slider_handle_color'] ?? '#ffffff';

        printf(
            '<div class="ff-price ff-price--slider" style="--ff-slider-track:%1$s;--ff-slider-range:%2$s;--ff-slider-handle:%3$s;">
                <div class="ff-price__slider-values">
                    <span data-ff-slider-display="min">%4$s</span> &ndash; <span data-ff-slider-display="max">%5$s</span>
                </div>
                <div class="ff-price__slider-track">
                    <div class="ff-price__slider-range" style="left:%6$s%%;width:%7$s%%;"></div>
                    <input type="range" class="ff-price__range" data-ff-price-role="min" min="%8$s" max="%9$s" step="1" value="%10$s" />
                    <input type="range" class="ff-price__range" data-ff-price-role="max" min="%8$s" max="%9$s" step="1" value="%11$s" />
                </div>
                <button type="button" class="ff-price__apply">%12$s</button>
            </div>',
            esc_attr( $track_color ),
            esc_attr( $range_color ),
            esc_attr( $handle_color ),
            esc_html( (string) round( $value_min ) ),
            esc_html( (string) round( $value_max ) ),
            esc_attr( (string) round( $left, 4 ) ),
            esc_attr( (string) round( $width, 4 ) ),
            esc_attr( (string) round( $bound_min ) ),
            esc_attr( (string) round( $bound_max ) ),
            esc_attr( (string) round( $value_min ) ),
            esc_attr( (string) round( $value_max ) ),
            esc_html__( 'Apply', 'filter-forge' )
        );
    }
}
