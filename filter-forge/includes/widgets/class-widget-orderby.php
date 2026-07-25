<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Orderby extends FF_Widget_Base {

    public function get_name(): string {
        return 'ff-orderby';
    }

    public function get_title(): string {
        return __( 'Sort By - Forge', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'ff-icon-anvil';
    }

    protected function register_controls(): void {
        $this->register_header_controls();

        $this->register_text_style_controls();
        $this->register_header_style_controls();
        $this->register_dropdown_style_controls( array() );
    }

    public function render(): void {
        if ( ! FF_Query_Manager::is_supported_archive() ) {
            $this->render_unsupported_page_notice();
            return;
        }

        $this->render_header();

        $options = $this->get_orderby_options();
        $current = FF_Plugin::instance()->filter_state->get( 'orderby' );

        if ( null === $current ) {
            $current = (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
        }

        echo '<select class="ff-orderby ff-orderby--dropdown" data-ff-param="orderby">';

        foreach ( $options as $value => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $value ),
                selected( $value, $current, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
    }

    /**
     * Sourced from WooCommerce's own filter (the same one its native
     * woocommerce_catalog_orderby() template function applies) so the option
     * set/labels/translations always match site config and any other plugin's
     * additions, instead of a Filter-Forge-defined list that could drift.
     */
    private function get_orderby_options(): array {
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

        return $options;
    }

    private function render_unsupported_page_notice(): void {
        if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return;
        }

        echo '<p class="ff-orderby__notice">' . esc_html__( 'Filter Forge: this widget only renders on a WooCommerce archive page (Shop, category, tag, or attribute archive).', 'filter-forge' ) . '</p>';
    }
}
