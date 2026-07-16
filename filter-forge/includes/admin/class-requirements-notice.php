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
