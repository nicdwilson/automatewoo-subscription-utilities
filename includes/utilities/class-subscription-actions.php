<?php

namespace AutomateWooSubscriptionUtilities\Utilities;

/**
 * Subscription Actions Utilities
 * 
 * Utility functions for subscription actions that can be used outside of AutomateWoo workflows.
 */
class Subscription_Actions {

	/**
	 * Reset subscription payment date to a specified time from now
	 *
	 * @param \WC_Subscription $subscription
	 * @param int $minutes Minutes from now
	 * @return bool Success status
	 */
	public static function reset_payment_date( $subscription, $minutes = 60 ) {
		if ( ! $subscription ) {
			return false;
		}

		try {
			$minutes = absint( $minutes );
			
			// Get current timestamp
			$current_timestamp = current_time( 'timestamp' );
			
			// Add specified minutes
			$new_timestamp = $current_timestamp + ( $minutes * MINUTE_IN_SECONDS );
			
			// Convert to MySQL datetime format
			$new_payment_date = date( 'Y-m-d H:i:s', $new_timestamp );
			
			// Update the subscription
			$subscription->update_dates( array( 'next_payment' => $new_payment_date ) );
			$subscription->save();
			
			// Log the action
			wc_get_logger()->info(
				sprintf(
					'Reset payment date for subscription ID %s to %s (current time + %d minutes)',
					$subscription->get_id(),
					$new_payment_date,
					$minutes
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
			
			return true;
			
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error resetting payment date for subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
			return false;
		}
	}

	/**
	 * Add time to existing subscription payment date
	 *
	 * @param \WC_Subscription $subscription
	 * @param int $minutes Minutes to add
	 * @return bool Success status
	 */
	public static function add_payment_time( $subscription, $minutes = 3 ) {
		if ( ! $subscription ) {
			return false;
		}

		try {
			$minutes = absint( $minutes );
			
			// Get the current next payment date
			$next_payment = $subscription->get_date( 'next_payment' );
			
			if ( ! $next_payment ) {
				wc_get_logger()->warning(
					sprintf(
						'No next payment date found for subscription ID %s',
						$subscription->get_id()
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
				return false;
			}
			
			// Convert the next payment date to a timestamp
			$next_payment_timestamp = strtotime( $next_payment );
			
			// Add specified minutes to the timestamp
			$new_next_payment_timestamp = $next_payment_timestamp + ( $minutes * MINUTE_IN_SECONDS );
			
			// Convert the new timestamp back to a MySQL datetime format
			$new_next_payment_date = date( 'Y-m-d H:i:s', $new_next_payment_timestamp );
			
			// Update the subscription with the new next payment date
			$subscription->update_dates( array( 'next_payment' => $new_next_payment_date ) );
			$subscription->save();
			
			// Log the action
			wc_get_logger()->info(
				sprintf(
					'Added %d minutes to payment date for subscription ID %s: %s -> %s',
					$minutes,
					$subscription->get_id(),
					$next_payment,
					$new_next_payment_date
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
			
			return true;
			
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error adding time to payment date for subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
			return false;
		}
	}
} 