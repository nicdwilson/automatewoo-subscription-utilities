<?php

namespace AutomateWooSubscriptionUtilities\Actions;

use AutomateWoo\Action;

/**
 * Remove End Date Action
 * 
 * Removes the end date from a subscription to make it ongoing.
 */
class Remove_End_Date extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title = __( 'Remove End Date', 'automatewoo-subscription-utilities' );
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Remove the end date from a subscription to make it ongoing. Will skip if the end date is already in the past.', 'automatewoo-subscription-utilities' );
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
			// Get the current end date
			$current_end_date = $subscription->get_date( 'end' );
			
			if ( $current_end_date ) {
				// Check if the end date is in the past
				$end_timestamp = strtotime( $current_end_date );
				$current_timestamp = current_time( 'timestamp' );
				
				if ( $end_timestamp < $current_timestamp ) {
					// End date is in the past, subscription has already ended
					wc_get_logger()->info(
						sprintf(
							'Subscription ID %s has already ended (end date: %s) - skipping remove end date action',
							$subscription->get_id(),
							$current_end_date
						),
						array( 'source' => 'automatewoo-subscription-utilities' )
					);
					return;
				}
				
				// Remove the end date by setting it to null/empty
				$subscription->update_dates( array( 'end' => 0 ) );
				$subscription->save();
				
				// Log the action
				wc_get_logger()->info(
					sprintf(
						'Removed end date from subscription ID %s (was: %s)',
						$subscription->get_id(),
						$current_end_date
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
			} else {
				wc_get_logger()->info(
					sprintf(
						'Subscription ID %s already has no end date set',
						$subscription->get_id()
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
			}
			
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error removing end date from subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
		}
	}
} 