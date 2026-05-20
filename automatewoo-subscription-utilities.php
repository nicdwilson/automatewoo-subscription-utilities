<?php
/**
 * Plugin Name: AutomateWoo Subscription Utilities
 * Description: A collection of utilities for AutomateWoo and WooCommerce Subscriptions.
 * Version: 1.3.0
 * Author: @nicw, WooCommerce Growth Team
 * Text Domain: automatewoo-subscription-utilities
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'AUTOMATEWOO_SUBSCRIPTION_UTILITIES_VERSION', '1.3.0' );
define( 'AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_FILE', __FILE__ );
define( 'AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_DIR . 'vendor/autoload.php';
}

// Declare WooCommerce Features API compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_editor', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'hpos', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Initialize the plugin
add_action( 'plugins_loaded', function() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>' .
				 esc_html__( 'AutomateWoo Subscription Utilities requires WooCommerce to be installed and activated.', 'automatewoo-subscription-utilities' ) .
				 '</p></div>';
		} );
		return;
	}

	// Check if WooCommerce Subscriptions is active
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>' .
				 esc_html__( 'AutomateWoo Subscription Utilities requires WooCommerce Subscriptions to be installed and activated.', 'automatewoo-subscription-utilities' ) .
				 '</p></div>';
		} );
		return;
	}

	// Check if AutomateWoo is active
	if ( ! class_exists( 'AutomateWoo' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>' .
				 esc_html__( 'AutomateWoo Subscription Utilities requires AutomateWoo to be installed and activated.', 'automatewoo-subscription-utilities' ) .
				 '</p></div>';
		} );
		return;
	}

	// Initialize the main plugin class
	\AutomateWooSubscriptionUtilities\Plugin::init();
} );

// Activation hook
register_activation_hook( __FILE__, function() {
	// Create necessary database tables or options if needed
	\AutomateWooSubscriptionUtilities\Plugin::activate();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
	// Cleanup if needed
	\AutomateWooSubscriptionUtilities\Plugin::deactivate();
} );
