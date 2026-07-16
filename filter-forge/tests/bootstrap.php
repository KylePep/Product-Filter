<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

define(
    'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
    dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunit-polyfills-autoload.php'
);

require_once $_tests_dir . '/includes/functions.php';

function _ff_manually_load_plugin() {
    // wp-env names extracted plugin folders after the zip URL slug
    // (e.g. "woocommerce.latest-stable"), not the plugin's own slug, so the
    // main file is located by glob rather than a hardcoded path.
    $woocommerce_main = glob( WP_CONTENT_DIR . '/plugins/woocommerce*/woocommerce.php' );
    $elementor_main    = glob( WP_CONTENT_DIR . '/plugins/elementor*/elementor.php' );

    require $woocommerce_main[0];
    require $elementor_main[0];
    require dirname( __DIR__ ) . '/filter-forge.php';
}
tests_add_filter( 'muplugins_loaded', '_ff_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
