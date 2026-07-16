<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Plugin {

    public static function dependencies_met(): bool {
        return class_exists( 'WooCommerce' ) && did_action( 'elementor/loaded' );
    }

    public static function boot(): void {
        if ( ! self::dependencies_met() ) {
            return;
        }
    }
}
