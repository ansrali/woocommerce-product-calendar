<?php
/**
 * Plugin Name: Woocommerce product date selector
 * Description: The plugin allows customers to select a future payment date and delivery date for the woocommerce subscription order.
 * Version:     1.0
 * Author: 		A Ali
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if WooCommerce is active
 **/
if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	deactivate_plugins( plugin_dir_path( __FILE__ ) . 'woocommerce-product-calendar.php', false );
	die ( 'Please activate WooCommerce plugin.' );
}

/**
 * Require plugin class
 **/
require_once( plugin_dir_path( __FILE__ ) . 'class-woocommerce-product-calendar.php' );

// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
register_activation_hook( __FILE__, array( 'Woocommerce_product_calendar', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Woocommerce_product_calendar', 'deactivate' ) );

Woocommerce_product_calendar::get_instance();