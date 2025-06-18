<?php

namespace AutomateWooSubscriptionUtilities\Actions;

use AutomateWoo\Action;

/**
 * Subscription Audit Action
 * 
 * Audits a subscription to check for scheduled actions and logs the results.
 */
class Subscription_Audit extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title = __( 'Audit Subscription Scheduled Actions', 'automatewoo-subscription-utilities' );
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Check if a subscription has scheduled payment and expiration actions, and log the results.', 'automatewoo-subscription-utilities' );
		$this->required_data_items = [ 'subscription' ];
	}

	/**
	 * Run the action
	 */
	public function run() {
		$subscription = $this->workflow->data_layer()->get_subscription();

		if ( ! $subscription ) {
			return;
		}

		try {
			$subscription_id = $subscription->get_id();
			
			// Check for scheduled actions
			$next_payment_scheduled = as_next_scheduled_action( 'woocommerce_scheduled_subscription_payment', [ 'subscription_id' => $subscription_id ] );
			$expiration_scheduled   = as_next_scheduled_action( 'woocommerce_scheduled_subscription_expiration', [ 'subscription_id' => $subscription_id ] );
			
			// Build admin edit links
			$admin_url = admin_url( 'post.php?post=' . $subscription_id . '&action=edit' );
			$edit_link = sprintf( '<a href="%s" target="_blank">Edit Subscription</a>', esc_url( $admin_url ) );
			
			if ( ! $next_payment_scheduled && ! $expiration_scheduled ) {
				// Log failure - missing scheduled actions
				wc_get_logger()->warning(
					sprintf(
						'Subscription ID %s is missing scheduled payment and expiration actions. %s',
						$subscription_id,
						$edit_link
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit-failure' )
				);
			} else {
				// Log success - has scheduled actions
				$scheduled_actions = [];
				if ( $next_payment_scheduled ) {
					$scheduled_actions[] = 'payment';
				}
				if ( $expiration_scheduled ) {
					$scheduled_actions[] = 'expiration';
				}
				
				wc_get_logger()->info(
					sprintf(
						'Subscription ID %s has scheduled actions: %s. %s',
						$subscription_id,
						implode( ', ', $scheduled_actions ),
						$edit_link
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit-success' )
				);
			}
			
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error auditing subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities-audit-errors' )
			);
		}
	}
} 