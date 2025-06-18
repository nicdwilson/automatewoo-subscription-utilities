<?php

namespace AutomateWooSubscriptionUtilities\Actions;

use AutomateWoo\Action;
use AutomateWoo\AdminNotices;

/**
 * Perform Subscription Audit Action
 * 
 * Performs the actual audit work on a subscription including:
 * - Check if renewal date is in the future
 * - Check if scheduled action exists and matches renewal date
 * - Recreate scheduled action if needed
 * - Log overdue subscriptions and create admin notices
 */
class Perform_Subscription_Audit extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title = __( 'Perform Subscription Audit', 'automatewoo-subscription-utilities' );
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Performs a comprehensive audit of a subscription including renewal date validation, scheduled action verification, and automatic fixes.', 'automatewoo-subscription-utilities' );
		$this->required_data_items = [ 'subscription' ];
	}

	/**
	 * Run the comprehensive audit
	 */
	public function run() {
		$subscription = $this->workflow->data_layer()->get_subscription();

		if ( ! $subscription ) {
			return;
		}

		$subscription_id = $subscription->get_id();
		$current_timestamp = current_time( 'timestamp' );

		try {
			// Step 3: Check if subscription is active
			if ( $subscription->get_status() !== 'active' ) {
				wc_get_logger()->info(
					sprintf(
						'Subscription ID %s is not active (status: %s) - skipping audit',
						$subscription_id,
						$subscription->get_status()
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit' )
				);
				return;
			}

			// Step 4: Check that the renewal date is in the future
			$next_payment_date = $subscription->get_date( 'next_payment' );
			
			if ( ! $next_payment_date ) {
				wc_get_logger()->warning(
					sprintf(
						'Subscription ID %s has no next payment date set',
						$subscription_id
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit' )
				);
				return;
			}

			$next_payment_timestamp = strtotime( $next_payment_date );

			// Step 9: Check if renewal date is in the past
			if ( $next_payment_timestamp < $current_timestamp ) {
				// Subscription is overdue
				wc_get_logger()->error(
					sprintf(
						'Subscription ID %s is overdue - next payment date: %s',
						$subscription_id,
						$next_payment_date
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit-overdue' )
				);

				// Create admin notice
				$this->create_overdue_admin_notice( $subscription_id, $next_payment_date );
				return;
			}

			// Step 5: Check if there is a scheduled action that matches the renewal date
			$scheduled_action = as_next_scheduled_action( 
				'woocommerce_scheduled_subscription_payment', 
				[ 'subscription_id' => $subscription_id ] 
			);

			if ( ! $scheduled_action ) {
				// Step 6: No scheduled action found - subscription does not pass
				wc_get_logger()->warning(
					sprintf(
						'Subscription ID %s has no scheduled payment action - will recreate',
						$subscription_id
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit' )
				);

				// Step 7: Recreate the scheduled action by adding 3 minutes to renewal date
				$this->recreate_scheduled_action( $subscription, $next_payment_timestamp );
			} else {
				// Check if the scheduled action time matches the renewal date (within 1 minute tolerance)
				$scheduled_time = get_site_option( 'action_scheduler_lock_duration', 60 );
				$tolerance = 60; // 1 minute tolerance

				if ( abs( $scheduled_action - $next_payment_timestamp ) > $tolerance ) {
					// Scheduled action time doesn't match renewal date
					wc_get_logger()->warning(
						sprintf(
							'Subscription ID %s scheduled action time (%s) does not match renewal date (%s) - will recreate',
							$subscription_id,
							date( 'Y-m-d H:i:s', $scheduled_action ),
							$next_payment_date
						),
						array( 'source' => 'automatewoo-subscription-utilities-audit' )
					);

					// Cancel existing scheduled action
					as_unschedule_action( 'woocommerce_scheduled_subscription_payment', [ 'subscription_id' => $subscription_id ] );

					// Recreate with correct time
					$this->recreate_scheduled_action( $subscription, $next_payment_timestamp );
				} else {
					// Step 6: Subscription passes - everything is correct
					wc_get_logger()->info(
						sprintf(
							'Subscription ID %s audit passed - scheduled action exists and matches renewal date',
							$subscription_id
						),
						array( 'source' => 'automatewoo-subscription-utilities-audit-success' )
					);
				}
			}

		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf(
					'Error during comprehensive audit of subscription ID %s: %s',
					$subscription_id,
					$e->getMessage()
				),
				array( 'source' => 'automatewoo-subscription-utilities-audit-errors' )
			);
		}
	}

	/**
	 * Recreate the scheduled action by adding 3 minutes to the renewal date
	 *
	 * @param \WC_Subscription $subscription
	 * @param int $renewal_timestamp
	 */
	private function recreate_scheduled_action( $subscription, $renewal_timestamp ) {
		$subscription_id = $subscription->get_id();
		
		// Add 3 minutes to the renewal timestamp
		$new_scheduled_time = $renewal_timestamp + ( 3 * MINUTE_IN_SECONDS );
		
		// Schedule the payment action
		as_schedule_single_action(
			$new_scheduled_time,
			'woocommerce_scheduled_subscription_payment',
			[ 'subscription_id' => $subscription_id ]
		);

		// Step 8: Log the action
		wc_get_logger()->info(
			sprintf(
				'Recreated scheduled payment action for subscription ID %s - scheduled for %s (original renewal + 3 minutes)',
				$subscription_id,
				date( 'Y-m-d H:i:s', $new_scheduled_time )
			),
			array( 'source' => 'automatewoo-subscription-utilities-audit-fix' )
		);
	}

	/**
	 * Create an admin notice for overdue subscriptions
	 *
	 * @param int $subscription_id
	 * @param string $overdue_date
	 */
	private function create_overdue_admin_notice( $subscription_id, $overdue_date ) {
		$notice_key = "subscription_overdue_{$subscription_id}";
		
		// Add the notice
		AdminNotices::add_notice( $notice_key );
		
		// Store the overdue subscription data
		$overdue_subscriptions = get_option( 'automatewoo_subscription_utilities_overdue_subscriptions', [] );
		$overdue_subscriptions[ $subscription_id ] = [
			'date' => $overdue_date,
			'timestamp' => current_time( 'timestamp' ),
		];
		update_option( 'automatewoo_subscription_utilities_overdue_subscriptions', $overdue_subscriptions );

		// Hook to output the notice
		add_action( 'automatewoo/admin_notice/' . $notice_key, function() use ( $subscription_id, $overdue_date ) {
			$admin_url = admin_url( 'post.php?post=' . $subscription_id . '&action=edit' );
			$edit_link = sprintf( '<a href="%s" target="_blank">Edit Subscription</a>', esc_url( $admin_url ) );
			
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Subscription Overdue:', 'automatewoo-subscription-utilities' ) . '</strong> ';
			echo sprintf(
				esc_html__( 'Subscription ID %d is overdue (next payment date: %s). %s', 'automatewoo-subscription-utilities' ),
				$subscription_id,
				$overdue_date,
				$edit_link
			);
			echo '</p>';
			echo '</div>';
		});
	}
} 