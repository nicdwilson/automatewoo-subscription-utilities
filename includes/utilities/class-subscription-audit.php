<?php

namespace AutomateWooSubscriptionUtilities\Utilities;

/**
 * Subscription Audit Utilities
 * 
 * Utility functions for auditing subscriptions that can be used outside of AutomateWoo workflows.
 */
class Subscription_Audit {

	/**
	 * Audit a subscription for scheduled actions
	 *
	 * @param \WC_Subscription $subscription
	 * @return array Audit results
	 */
	public static function audit_subscription( $subscription ) {
		if ( ! $subscription ) {
			return array(
				'success' => false,
				'message' => 'No subscription provided',
				'data' => array()
			);
		}

		try {
			$subscription_id = $subscription->get_id();
			
			// Check for scheduled actions
			$next_payment_scheduled = as_next_scheduled_action( 'woocommerce_scheduled_subscription_payment', [ 'subscription_id' => $subscription_id ] );
			$expiration_scheduled   = as_next_scheduled_action( 'woocommerce_scheduled_subscription_expiration', [ 'subscription_id' => $subscription_id ] );
			
			// Build admin edit links
			$admin_url = admin_url( 'post.php?post=' . $subscription_id . '&action=edit' );
			$edit_link = sprintf( '<a href="%s" target="_blank">Edit Subscription</a>', esc_url( $admin_url ) );
			
			$audit_data = array(
				'subscription_id' => $subscription_id,
				'has_payment_scheduled' => (bool) $next_payment_scheduled,
				'has_expiration_scheduled' => (bool) $expiration_scheduled,
				'payment_scheduled_time' => $next_payment_scheduled,
				'expiration_scheduled_time' => $expiration_scheduled,
				'edit_link' => $edit_link,
				'admin_url' => $admin_url
			);
			
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
				
				return array(
					'success' => false,
					'message' => 'Missing scheduled actions',
					'data' => $audit_data
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
				
				return array(
					'success' => true,
					'message' => 'Scheduled actions found: ' . implode( ', ', $scheduled_actions ),
					'data' => $audit_data
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
			
			return array(
				'success' => false,
				'message' => 'Error during audit: ' . $e->getMessage(),
				'data' => array()
			);
		}
	}

	/**
	 * Audit multiple subscriptions
	 *
	 * @param array $subscription_ids Array of subscription IDs
	 * @return array Audit results for all subscriptions
	 */
	public static function audit_multiple_subscriptions( $subscription_ids ) {
		$results = array();
		
		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wcs_get_subscription( $subscription_id );
			if ( $subscription ) {
				$results[ $subscription_id ] = self::audit_subscription( $subscription );
			} else {
				$results[ $subscription_id ] = array(
					'success' => false,
					'message' => 'Subscription not found',
					'data' => array()
				);
			}
		}
		
		return $results;
	}

	/**
	 * Get summary of audit results
	 *
	 * @param array $audit_results Results from audit_multiple_subscriptions
	 * @return array Summary statistics
	 */
	public static function get_audit_summary( $audit_results ) {
		$summary = array(
			'total_subscriptions' => count( $audit_results ),
			'successful_audits' => 0,
			'failed_audits' => 0,
			'missing_actions' => 0,
			'has_payment_scheduled' => 0,
			'has_expiration_scheduled' => 0
		);
		
		foreach ( $audit_results as $result ) {
			if ( $result['success'] ) {
				$summary['successful_audits']++;
				
				if ( $result['data']['has_payment_scheduled'] ) {
					$summary['has_payment_scheduled']++;
				}
				if ( $result['data']['has_expiration_scheduled'] ) {
					$summary['has_expiration_scheduled']++;
				}
			} else {
				$summary['failed_audits']++;
				if ( strpos( $result['message'], 'Missing scheduled actions' ) !== false ) {
					$summary['missing_actions']++;
				}
			}
		}
		
		return $summary;
	}
} 