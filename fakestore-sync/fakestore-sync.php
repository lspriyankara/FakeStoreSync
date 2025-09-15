<?php
/*
Plugin Name: FakeStore Sync
Description: Sync products from FakeStoreAPI (https://fakestoreapi.com) into WooCommerce.
Version: 0.1
Author: LPS
Text Domain: fakestore-sync
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * LPS - autoloader
 */
spl_autoload_register( function( $class ) {
    $prefix = 'FakeStoreSync\\';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file = FSS_PLUGIN_DIR . 'includes/class-' . strtolower( $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * LPS - Initialize plugin
 */
function fss_init_plugin() {
    // Admin UI only needed in WP admin
    if ( is_admin() ) {
        $admin = new FakeStoreSync\Admin();
        $admin->init();
    }

    // Create Sync class instance (has helper functions)
    $sync = new FakeStoreSync\Sync();
    $sync->init();

    // Push updates back to FakeStore API
    $push = new FakeStoreSync\Push();
    $push->init();
}
add_action( 'plugins_loaded', 'fss_init_plugin' );

/**
 * LPS - Activation hook
 */
function fss_activate() {
    $defaults = array(
        'api_url' => 'https://fakestoreapi.com',
    );

    if ( get_option( 'fss_settings' ) === false ) {
        add_option( 'fss_settings', $defaults );
    } else {
        $current = get_option( 'fss_settings', array() );
        $new = wp_parse_args( $current, $defaults );
        update_option( 'fss_settings', $new );
    }

    if ( get_option( 'fss_last_sync' ) === false ) {
        add_option( 'fss_last_sync', '' );
    }
    if ( get_option( 'fss_last_counts' ) === false ) {
        add_option( 'fss_last_counts', array( 'imported' => 0, 'updated' => 0 ) );
    }
}
register_activation_hook( __FILE__, 'fss_activate' );

/**
 * LPS - Deactivation hook
 */
function fss_deactivate() {
    // nothing to clean up by default
}
register_deactivation_hook( __FILE__, 'fss_deactivate' );
