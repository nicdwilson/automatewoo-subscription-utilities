<?php

namespace AutomateWooSubscriptionUtilities\Triggers;

use AutomateWoo\Customer_Factory;
use AutomateWoo\DateTime;
use AutomateWoo\Fields;
use AutomateWoo\Traits\IntegerValidator;
use AutomateWoo\Triggers\AbstractBatchedDailyTrigger;
use AutomateWoo\Workflow;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Daily Subscription Audit Trigger
 *
 * Fires once per day per active subscription whose next payment date falls within
 * a rolling window (default: next 7 days). The trigger only emits the data layer —
 * the actual audit work is done by workflow actions (typically `Subscription_Audit`).
 *
 * Two configurable fields:
 *   - window_days     : how far ahead to look for upcoming renewals (default 7).
 *   - audit_interval  : minimum days between audits of the same subscription (default 1).
 *
 * Structurally a stripped-down copy of AutomateWoo's Subscription_Before_Renewal trigger,
 * adapted for a rolling-window query instead of a single target date.
 */
class Daily_Subscription_Audit extends AbstractBatchedDailyTrigger {

	use IntegerValidator;

	/**
	 * @var string[]
	 */
	public $supplied_data_items = [ 'subscription', 'customer' ];

	/**
	 * Set the trigger's admin metadata (title / description / group).
	 */
	public function load_admin_details() {
		$this->title        = __( 'Daily Subscription Audit', 'automatewoo-subscription-utilities' );
		$this->description  = __( 'Runs daily and processes active subscriptions whose next payment date falls within the configured window. For each subscription the trigger dispatches workflows so attached actions (for example, Audit Subscription Scheduled Actions) can run. Each subscription is audited at most once per audit interval.', 'automatewoo-subscription-utilities' );
		$this->description .= ' ' . $this->get_description_text_workflow_not_immediate();
		$this->group        = __( 'Subscription Audits', 'automatewoo-subscription-utilities' );
	}

	/**
	 * Load the workflow-configurable fields.
	 */
	public function load_fields() {
		$window = ( new Fields\Positive_Number() )
			->set_name( 'window_days' )
			->set_title( __( 'Window (days)', 'automatewoo-subscription-utilities' ) )
			->set_description( __( 'Audit active subscriptions whose next payment date falls within this many days from today (inclusive). Lower values reduce the daily batch size.', 'automatewoo-subscription-utilities' ) )
			->set_required();

		$interval = ( new Fields\Positive_Number() )
			->set_name( 'audit_interval' )
			->set_title( __( 'Audit interval (days)', 'automatewoo-subscription-utilities' ) )
			->set_description( __( 'Minimum number of days between audits of the same subscription. Use 1 to audit each at-risk subscription daily; use a higher value (e.g. 7) to audit each subscription at most once per week.', 'automatewoo-subscription-utilities' ) )
			->set_required();

		$this->add_field( $window );
		$this->add_field( $interval );
		$this->add_field( $this->get_field_time_of_day() );
	}

	/**
	 * Get a batch of subscription items for the workflow.
	 *
	 * Items are shaped as [ 'subscription' => $id ] (single-key) so AutomateWoo's
	 * BatchedWorkflows cursor extraction can derive the next $after_id from the
	 * last item in the batch.
	 *
	 * @param Workflow $workflow
	 * @param int      $after_id Cursor — only subscriptions with ID > this value are returned.
	 * @param int      $limit    Max items per batch.
	 *
	 * @return array[] Array of items.
	 */
	public function get_batch_for_workflow( Workflow $workflow, int $after_id, int $limit ): array {
		$items = [];

		foreach ( $this->get_subscriptions_for_workflow( $workflow, $after_id, $limit ) as $subscription_id ) {
			$items[] = [
				'subscription' => $subscription_id,
			];
		}

		return $items;
	}

	/**
	 * Process a single subscription item: load it, look up the customer, and dispatch
	 * the workflow. Side-effect free — actions attached to the workflow do the work.
	 *
	 * @param Workflow $workflow
	 * @param array    $item
	 */
	public function process_item_for_workflow( Workflow $workflow, array $item ) {
		if ( ! isset( $item['subscription'] ) ) {
			return;
		}

		$subscription = wcs_get_subscription( (int) $item['subscription'] );

		if ( ! $subscription ) {
			return;
		}

		$workflow->maybe_run(
			[
				'subscription' => $subscription,
				'customer'     => Customer_Factory::get_by_user_id( $subscription->get_user_id() ),
			]
		);
	}

	/**
	 * Resolve the workflow's window option and query subscriptions in that window.
	 *
	 * @param Workflow $workflow
	 * @param int      $after_id
	 * @param int      $limit
	 *
	 * @return int[] Array of subscription IDs.
	 */
	protected function get_subscriptions_for_workflow( Workflow $workflow, int $after_id, int $limit ) {
		$window_days = (int) $workflow->get_trigger_option( 'window_days' );
		$this->validate_positive_integer( $window_days );

		$start = new DateTime();
		$start->convert_to_site_time();
		$end = clone $start;
		$end->add( new \DateInterval( "P{$window_days}D" ) );

		$start->set_time_to_day_start();
		$end->set_time_to_day_end();

		$start->convert_to_utc_time();
		$end->convert_to_utc_time();

		return $this->query_subscriptions_in_window( $start, $end, [ 'wc-active' ], $after_id, $limit );
	}

	/**
	 * Query subscriptions whose `_schedule_next_payment` falls in [$start, $end].
	 *
	 * Uses wcs_get_orders_with_meta_query() when available (HPOS-aware), falling back
	 * to WP_Query against wp_posts on pre-HPOS WCS installs. Cursor pagination (ID >
	 * $after_id) is required by AbstractBatchedDailyTrigger, so we apply it via
	 * field_query on HPOS and a posts_where filter pre-HPOS.
	 *
	 * @param DateTime $start    UTC.
	 * @param DateTime $end      UTC.
	 * @param array    $statuses Subscription statuses.
	 * @param int      $after_id Cursor; 0 to start from the beginning.
	 * @param int      $limit
	 *
	 * @return int[]
	 */
	protected function query_subscriptions_in_window( DateTime $start, DateTime $end, array $statuses, int $after_id, int $limit ) {
		if ( function_exists( 'wcs_get_orders_with_meta_query' ) ) {
			$args = [
				'type'          => 'shop_subscription',
				'status'        => $statuses,
				'return'        => 'ids',
				'limit'         => $limit,
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'no_found_rows' => true,
				'meta_query'    => [
					[
						'key'     => '_schedule_next_payment',
						'compare' => 'BETWEEN',
						'value'   => [ $start->to_mysql_string(), $end->to_mysql_string() ],
					],
				],
			];

			$where_filter = null;

			if ( $after_id > 0 ) {
				if ( function_exists( 'wcs_is_custom_order_tables_usage_enabled' ) && wcs_is_custom_order_tables_usage_enabled() ) {
					$args['field_query'] = [
						[
							'field'   => 'id',
							'value'   => $after_id,
							'compare' => '>',
						],
					];
				} else {
					$where_filter = function ( $where ) use ( $after_id ) {
						global $wpdb;
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after_id );
						return $where;
					};
					add_filter( 'posts_where', $where_filter );
				}
			}

			try {
				return wcs_get_orders_with_meta_query( $args );
			} finally {
				if ( $where_filter ) {
					remove_filter( 'posts_where', $where_filter );
				}
			}
		}

		// Fallback for pre-HPOS WCS installs.
		$query_args = [
			'post_type'      => 'shop_subscription',
			'post_status'    => $statuses,
			'fields'         => 'ids',
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'           => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => '_schedule_next_payment',
					'compare' => '>=',
					'value'   => $start->to_mysql_string(),
				],
				[
					'key'     => '_schedule_next_payment',
					'compare' => '<=',
					'value'   => $end->to_mysql_string(),
				],
			],
		];

		$where_filter = null;

		if ( $after_id > 0 ) {
			$where_filter = function ( $where ) use ( $after_id ) {
				global $wpdb;
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after_id );
				return $where;
			};
			add_filter( 'posts_where', $where_filter );
		}

		try {
			$query = new WP_Query( $query_args );
			return $query->posts;
		} finally {
			if ( $where_filter ) {
				remove_filter( 'posts_where', $where_filter );
			}
		}
	}

	/**
	 * Validate the workflow before running. Rejects if the same subscription has been
	 * processed within the workflow's configured audit interval.
	 *
	 * @param Workflow $workflow
	 *
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		$subscription = $workflow->data_layer()->get_subscription();

		if ( ! $subscription ) {
			return false;
		}

		$interval = (int) $workflow->get_trigger_option( 'audit_interval' );
		$this->validate_positive_integer( $interval );

		if ( $workflow->has_run_for_data_item( 'subscription', $interval * DAY_IN_SECONDS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate before a queued workflow event fires. Only consulted for non-immediate
	 * timings, but kept here so workflows with delays don't act on subscriptions that
	 * have since been cancelled or paused.
	 *
	 * @param Workflow $workflow
	 *
	 * @return bool
	 */
	public function validate_before_queued_event( $workflow ) {
		$subscription = $workflow->data_layer()->get_subscription();

		if ( ! $subscription ) {
			return false;
		}

		if ( ! $subscription->has_status( 'active' ) ) {
			return false;
		}

		return true;
	}
}
