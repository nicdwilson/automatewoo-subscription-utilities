<?php

namespace AutomateWooSubscriptionUtilities\Actions;

use AutomateWoo\Action;

/**
 * Reset Scheduled Action Action
 * 
 * Resets a subscription's scheduled payment action by adding 3 minutes to the existing renewal time.
 */
class Reset_Scheduled_Action extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title = __( 'Reset Scheduled Action - Add 3 Minutes', 'automatewoo-subscription-utilities' );
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Reset the next scheduled payment action by adding 3 minutes to the existing renewal time.', 'automatewoo-subscription-utilities' );
		$this->required_data_items = [ 'subscription' ];
	}

	/**
	 * Run the action
	 */
	public function run() {
		$subscription = $this->workflow->data_layer()->get_subscription();
		$minutes = 3;

		if ( ! $subscription ) {
			return;
		}
		
		try {
			// Get the current next payment date
			$next_payment = $subscription->get_date( 'next_payment' );

			if ( $next_payment ) {
				// Convert the next payment date to a timestamp
				$next_payment_timestamp = strtotime( $next_payment );

				// Add 3 minutes to the timestamp
				$new_next_payment_timestamp = $next_payment_timestamp + ( $minutes * MINUTE_IN_SECONDS );

				// Convert the new timestamp back to a MySQL datetime format
				$new_next_payment_date = date( 'Y-m-d H:i:s', $new_next_payment_timestamp );

				// Update the subscription with the new next payment date
				$subscription->update_dates( array( 'next_payment' => $new_next_payment_date ) );

				// Save the subscription
				$subscription->save();

				// Log the action
				wc_get_logger()->info(
					sprintf(
						'Reset scheduled payment action for subscription ID %s to %s (original time + %d minutes)',
						$subscription->get_id(),
						$new_next_payment_date,
						$minutes
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
			} else {
				wc_get_logger()->warning(
					sprintf(
						'No next payment date found for subscription ID %s - cannot reset scheduled action',
						$subscription->get_id()
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
			}
			
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error resetting scheduled payment action for subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
		}
	}
} 