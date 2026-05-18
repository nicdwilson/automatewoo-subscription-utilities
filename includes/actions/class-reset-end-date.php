<?php

namespace AutomateWooSubscriptionUtilities\Actions;

use AutomateWoo\Action;

/**
 * Reset End Date Action
 *
 * Resets a subscription's scheduled expiration action by adding 3 minutes
 * to the existing end date. Useful when the expiration scheduled action
 * is missing from Action Scheduler — nudging the end date forces it to
 * be recreated.
 */
class Reset_End_Date extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title              = __( 'Reset End Date - Add 3 Minutes', 'automatewoo-subscription-utilities' );
		$this->group              = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
		$this->description        = __( 'Reset the scheduled expiration action by adding 3 minutes to the existing end date. Useful when the expiration scheduled action is missing from Action Scheduler. Skipped if the subscription has no end date.', 'automatewoo-subscription-utilities' );
		$this->required_data_items = [ 'subscription' ];
	}

	/**
	 * Run the action
	 */
	public function run() {
		$subscription = $this->workflow->data_layer()->get_subscription();
		$minutes      = 3;

		if ( ! $subscription ) {
			return;
		}

		try {
			// get_time() returns a UTC unix timestamp (0 if the date is unset).
			$end_timestamp = (int) $subscription->get_time( 'end' );

			if ( ! $end_timestamp ) {
				wc_get_logger()->warning(
					sprintf(
						'No end date found for subscription ID %s - cannot reset scheduled expiration action',
						$subscription->get_id()
					),
					array( 'source' => 'automatewoo-subscription-utilities' )
				);
				return;
			}

			$new_end_timestamp = $end_timestamp + ( $minutes * MINUTE_IN_SECONDS );

			// Pass the UTC unix timestamp directly via the 'gmt' timezone arg.
			$subscription->update_dates( array( 'end' => $new_end_timestamp ), 'gmt' );
			$subscription->save();

			wc_get_logger()->info(
				sprintf(
					'Reset scheduled expiration action for subscription ID %s to %s UTC (original time + %d minutes)',
					$subscription->get_id(),
					gmdate( 'Y-m-d H:i:s', $new_end_timestamp ),
					$minutes
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);

		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error resetting scheduled expiration action for subscription ID %s: %s',
					$subscription->get_id(),
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities' )
			);
		}
	}
}
