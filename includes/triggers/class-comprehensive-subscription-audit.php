<?php

namespace AutomateWooSubscriptionUtilities\Triggers;

use AutomateWoo\Trigger;
use AutomateWoo\Workflow;
use AutomateWoo\Customer_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Comprehensive Subscription Audit Trigger
 * 
 * Triggers when a subscription needs comprehensive auditing.
 * This can be used to audit subscriptions on specific events or manually.
 */
class Comprehensive_Subscription_Audit extends Trigger {

	/**
	 * @var string[]
	 */
	public $supplied_data_items = [ 'subscription', 'customer' ];

	/**
	 * Load admin details.
	 */
	public function load_admin_details() {
		$this->title = __( 'Comprehensive Subscription Audit', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Triggers when a subscription needs comprehensive auditing. This can be used to audit subscriptions on specific events or manually triggered.', 'automatewoo-subscription-utilities' );
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		// No fields needed for this trigger
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		// Hook into subscription status changes
		add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status_change' ], 10, 3 );
		
		// Hook into payment failures
		add_action( 'woocommerce_subscription_payment_failed', [ $this, 'handle_payment_failure' ], 10, 1 );
		
		// Hook into scheduled payment processing
		add_action( 'woocommerce_scheduled_subscription_payment', [ $this, 'handle_scheduled_payment' ], 5, 1 );
		
		// Hook into subscription renewal
		add_action( 'woocommerce_subscription_renewal_payment_complete', [ $this, 'handle_renewal_complete' ], 10, 2 );
		
		// Hook into subscription date changes
		add_action( 'woocommerce_subscription_date_updated', [ $this, 'handle_date_update' ], 10, 3 );
	}

	/**
	 * Handle subscription status changes
	 *
	 * @param \WC_Subscription $subscription
	 * @param string $new_status
	 * @param string $old_status
	 */
	public function handle_subscription_status_change( $subscription, $new_status, $old_status ) {
		// Only trigger for active subscriptions
		if ( $new_status === 'active' ) {
			$this->maybe_run_workflow( $subscription );
		}
	}

	/**
	 * Handle payment failures
	 *
	 * @param \WC_Subscription $subscription
	 */
	public function handle_payment_failure( $subscription ) {
		$this->maybe_run_workflow( $subscription );
	}

	/**
	 * Handle scheduled payment processing
	 *
	 * @param int $subscription_id
	 */
	public function handle_scheduled_payment( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( $subscription ) {
			$this->maybe_run_workflow( $subscription );
		}
	}

	/**
	 * Handle renewal completion
	 *
	 * @param \WC_Subscription $subscription
	 * @param \WC_Order $renewal_order
	 */
	public function handle_renewal_complete( $subscription, $renewal_order ) {
		$this->maybe_run_workflow( $subscription );
	}

	/**
	 * Handle subscription date updates
	 *
	 * @param \WC_Subscription $subscription
	 * @param string $date_type
	 * @param string $datetime
	 */
	public function handle_date_update( $subscription, $date_type, $datetime ) {
		// Only trigger for next payment date changes
		if ( $date_type === 'next_payment' ) {
			$this->maybe_run_workflow( $subscription );
		}
	}

	/**
	 * Maybe run workflow for subscription
	 *
	 * @param \WC_Subscription $subscription
	 */
	private function maybe_run_workflow( $subscription ) {
		$customer = Customer_Factory::get_by_user_id( $subscription->get_customer_id() );
		
		if ( ! $customer ) {
			return;
		}

		// Get all workflows that use this trigger
		$workflows = $this->get_workflows();
		
		foreach ( $workflows as $workflow ) {
			$workflow->maybe_run(
				[
					'subscription' => $subscription,
					'customer'     => $customer,
				]
			);
		}
	}

	/**
	 * Validate workflow to ensure it only runs once per subscription per event
	 *
	 * @param Workflow $workflow
	 *
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		// Workflow should only run once per subscription per event
		if ( $workflow->has_run_for_data_item( 'subscription' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Manual trigger method for testing or manual audits
	 *
	 * @param int $subscription_id
	 */
	public static function trigger_manual_audit( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return;
		}

		$trigger = new self();
		$trigger->maybe_run_workflow( $subscription );
	}
} 