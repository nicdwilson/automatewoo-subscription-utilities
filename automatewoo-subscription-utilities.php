<?php
/**
 * Plugin Name: AutomateWoo Subscription Utilities
 * Description: A collection of utilities for AutomateWoo and WooCommerce Subscriptions.
 * Version: 1.0.0
 * Author: @nicw, WooCommerce Growth Team
 */


// Ensure this is not accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Is Woo Subscriptions active?
if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
	return;
}


// Define the function to update the next payment date for active subscriptions
function reset_next_payment_one_hour( $workflow ) {

	try {

		// Get the subscription from the workflow
		$subscription = $workflow->data_layer()->get_subscription();


		wc_get_logger()->debug(
			sprintf(
				'Running schedule reset on subscription ID: %s',
				$subscription->get_id()
			),
			array(
				'source'    => 'automatewoo-reset-subscription-timings',
				'data'      => '',
				'backtrace' => false,
			)
		);

		// Get the current next payment date
		$next_payment = $subscription->get_date( 'next_payment' );

		if ( $next_payment ) {
			// Convert the next payment date to a timestamp
			$next_payment_timestamp = strtotime( 'now' );

			// Add 2 minutes to the timestamp
			$new_next_payment_timestamp = $next_payment_timestamp + 62 * MINUTE_IN_SECONDS;

			// Convert the new timestamp back to a MySQL datetime format
			$new_next_payment_date = date( 'Y-m-d H:i:s', $new_next_payment_timestamp );

			// Update the subscription with the new next payment date
			$subscription->update_dates( array( 'next_payment' => $new_next_payment_date ) );

			// Save the subscription
			$subscription->save();
		}

	} catch ( Exception $ex ) {

		wc_get_logger()->debug(
			sprintf(
				'Error: %s',
				$ex->getMessage()
			),
			array(
				'source'    => 'automatewoo-reset-subscription-timings-errors',
				'data'      => '',
				'backtrace' => false,
			)
		);
	}
}

// Define the function to update the next payment date for active subscriptions
function add_three_minutes_to_next_payment( $workflow ) {

	try {

		// Get the subscription from the workflow
		$subscription = $workflow->data_layer()->get_subscription();


		wc_get_logger()->debug(
			sprintf(
				'Adding three minutes to subscription ID: %s',
				$subscription->get_id()
			),
			array(
				'source'    => 'automatewoo-reset-subscription-timings',
				'data'      => '',
				'backtrace' => false,
			)
		);

		// Get the current next payment date
		$next_payment = $subscription->get_date( 'next_payment' );

		if ( $next_payment ) {
			// Convert the next payment date to a timestamp
			$next_payment_timestamp = strtotime( $next_payment );

			// Add 2 minutes to the timestamp
			$new_next_payment_timestamp = $next_payment_timestamp + 3 * MINUTE_IN_SECONDS;

			// Convert the new timestamp back to a MySQL datetime format
			$new_next_payment_date = date( 'Y-m-d H:i:s', $new_next_payment_timestamp );

			// Update the subscription with the new next payment date
			$subscription->update_dates( array( 'next_payment' => $new_next_payment_date ) );

			// Save the subscription
			$subscription->save();
		}

	} catch ( Exception $ex ) {

		wc_get_logger()->debug(
			sprintf(
				'Error: %s',
				$ex->getMessage()
			),
			array(
				'source'    => 'automatewoo-reset-subscription-timings-errors',
				'data'      => '',
				'backtrace' => false,
			)
		);
	}
}

//Still needs to be fit into a workflow.
// Currently, trigger this by targeting a single subscription with a mnaul flow
// It checks if a scheduled action exists for a subscription, and will write an audit
// report to two separate logs, one for success, one for fails.

function do_subscription_audit( $workflow ) {

	try {

		// Get the subscription from the workflow
		$subscription    = $workflow->data_layer()->get_subscription();
		$subscription_id = $subscription->get_id();

		$next_payment_scheduled = as_next_scheduled_action( 'woocommerce_scheduled_subscription_payment', [ 'subscription_id' => $subscription_id ] );
		$expiration_scheduled   = as_next_scheduled_action( 'woocommerce_scheduled_subscription_expiration', [ 'subscription_id' => $subscription_id ] );

		if ( ! $next_payment_scheduled && ! $expiration_scheduled ) {

			wc_get_logger()->debug(

			/**
			 * Write an edit link in here and life will get a lot easier
			 */
				sprintf(
					'Subscription ID %s is missing a scheduled payment',
					$subscription_id
				),
				array(
					'source'    => 'automatewoo-audit-failure',
					'data'      => '',
					'backtrace' => false,
				)
			);
		} else {

			wc_get_logger()->debug(
			/**
			 * Write an edit link in here and life will get a lot easier
			 */
				sprintf(
					'Subscription ID %s is has a scheduled payment',
					$subscription_id
				),
				array(
					'source'    => 'automatewoo-audit-success',
					'data'      => '',
					'backtrace' => false,
				)
			);
		}
	} catch ( Exception $ex ) {

		wc_get_logger()->debug(
			sprintf(
				'Error: %s',
				$ex->getMessage()
			),
			array(
				'source'    => 'automatewoo-subscription-audit-errors',
				'data'      => '',
				'backtrace' => false,
			)
		);
	}
}