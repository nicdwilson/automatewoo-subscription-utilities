<?php

namespace AutomateWooSubscriptionUtilities\Triggers;

use AutomateWoo\Trigger;
use AutomateWoo\Triggers\AbstractBatchedDailyTrigger;
use AutomateWoo\Workflow;
use AutomateWoo\Customer_Factory;
use AutomateWoo\AdminNotices;

defined( 'ABSPATH' ) || exit;

/**
 * Daily Subscription Audit Trigger
 * 
 * Runs a daily audit of all subscriptions to check for issues with scheduled actions.
 * This is a batched daily trigger that performs the audit work directly.
 */
class Daily_Subscription_Audit extends AbstractBatchedDailyTrigger {

	/**
	 * @var string[]
	 */
	public $supplied_data_items = [ 'subscription', 'customer' ];

	/**
	 * Load admin details.
	 */
	public function load_admin_details() {
		$this->title = __( 'Daily Subscription Audit', 'automatewoo-subscription-utilities' );
		$this->description = __( 'Runs a daily audit of all active subscriptions to check for missing or incorrect scheduled actions. This trigger will process subscriptions in batches to avoid performance issues.', 'automatewoo-subscription-utilities' );
		$this->description .= ' ' . $this->get_description_text_workflow_not_immediate();
		$this->group = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		$this->add_field( $this->get_field_time_of_day() );
	}

	/**
	 * Get a batch of active subscriptions to process.
	 *
	 * @param Workflow $workflow
	 * @param int      $offset The batch query offset.
	 * @param int      $limit  The max items for the query.
	 *
	 * @return array[] Array of items in array format.
	 */
	public function get_batch_for_workflow( Workflow $workflow, int $offset, int $limit ): array {
		global $wpdb;

		$items = [];

		// Get active subscriptions
		$subscription_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
				WHERE post_type = 'shop_subscription' 
				AND post_status = 'wc-active'
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wcs_get_subscription( $subscription_id );
			if ( $subscription && $subscription->get_customer_id() ) {
				$items[] = [
					'subscription' => $subscription_id,
					'customer'     => $subscription->get_customer_id(),
				];
			}
		}

		return $items;
	}

	/**
	 * Process a single subscription for the workflow.
	 *
	 * @param Workflow $workflow
	 * @param array    $item
	 */
	public function process_item_for_workflow( Workflow $workflow, array $item ) {
		if ( ! isset( $item['subscription'] ) || ! isset( $item['customer'] ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $item['subscription'] );
		$customer = Customer_Factory::get_by_user_id( $item['customer'] );

		if ( ! $subscription || ! $customer ) {
			return;
		}

		// Perform the audit directly in the trigger
		$this->perform_subscription_audit( $subscription );

		$workflow->maybe_run(
			[
				'subscription' => $subscription,
				'customer'     => $customer,
			]
		);
	}

	/**
	 * Validate workflow to ensure it only runs once per subscription per day.
	 *
	 * @param Workflow $workflow
	 *
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		// Workflow should only run once per subscription per day
		if ( $workflow->has_run_for_data_item( 'subscription' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Perform the actual subscription audit
	 *
	 * @param \WC_Subscription $subscription
	 */
	private function perform_subscription_audit( $subscription ) {
		$subscription_id = $subscription->get_id();
		$current_timestamp = current_time( 'timestamp' );

		try {
			// Check if subscription is active
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

			// Check that the renewal date is in the future
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

			// Check if renewal date is in the past
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

			// Check if there is a scheduled action that matches the renewal date
			$scheduled_action = as_next_scheduled_action( 
				'woocommerce_scheduled_subscription_payment', 
				[ 'subscription_id' => $subscription_id ] 
			);

			if ( ! $scheduled_action ) {
				// No scheduled action found - subscription does not pass
				wc_get_logger()->warning(
					sprintf(
						'Subscription ID %s has no scheduled payment action - will recreate',
						$subscription_id
					),
					array( 'source' => 'automatewoo-subscription-utilities-audit' )
				);

				// Recreate the scheduled action by adding 3 minutes to renewal date
				$this->recreate_scheduled_action( $subscription, $next_payment_timestamp );
			} else {
				// Check if the scheduled action time matches the renewal date (within 1 minute tolerance)
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
					// Subscription passes - everything is correct
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

		// Log the action
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