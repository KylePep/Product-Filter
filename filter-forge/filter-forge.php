<?php
/**
 * Plugin Name: Filter Forge
 * Description: Configurable WooCommerce product filters as native Elementor widgets.
 * Version: 0.1.6
 * Requires PHP: 7.4
 * Text Domain: filter-forge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FF_PLUGIN_FILE', __FILE__ );
define( 'FF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FF_VERSION', '0.1.6' );

require_once FF_PLUGIN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'FF_Plugin', 'boot' ) );
