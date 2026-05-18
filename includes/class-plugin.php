<?php

namespace AutomateWooSubscriptionUtilities;

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, hooks, and core functionality.
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Load text domain for internationalization
		add_action( 'init', array( $this, 'load_textdomain' ) );
		
		// Register AutomateWoo actions via filter after AutomateWoo has initialized
		add_action( 'automatewoo_init', array( $this, 'register_automatewoo_actions_hook' ) );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'automatewoo-subscription-utilities',
			false,
			dirname( plugin_basename( AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Register AutomateWoo actions hook
	 */
	public function register_automatewoo_actions_hook() {
		add_filter( 'automatewoo/actions', array( $this, 'register_automatewoo_actions' ) );
	}

	/**
	 * Register AutomateWoo actions
	 *
	 * @param array $includes Array of action includes
	 * @return array Modified array with our actions added
	 */
	public function register_automatewoo_actions( $includes ) {
		// Add our custom actions to the includes array
		$includes['subscription_reset_scheduled_action'] = 'AutomateWooSubscriptionUtilities\Actions\Reset_Scheduled_Action';
		$includes['subscription_remove_end_date'] = 'AutomateWooSubscriptionUtilities\Actions\Remove_End_Date';
		$includes['subscription_reset_end_date'] = 'AutomateWooSubscriptionUtilities\Actions\Reset_End_Date';
		$includes['subscription_audit'] = 'AutomateWooSubscriptionUtilities\Actions\Subscription_Audit';
		
		return $includes;
	}

	/**
	 * Plugin activation
	 */
	public static function activate() {
		// Create any necessary database tables or options
		// Log activation
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				'AutomateWoo Subscription Utilities plugin activated',
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
		}
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Cleanup if necessary
		// Log deactivation
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				'AutomateWoo Subscription Utilities plugin deactivated',
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
		}
	}
} 