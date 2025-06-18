<?php

namespace AutomateWooSubscriptionUtilities\Admin;

/**
 * Admin Page Class
 * 
 * Handles the admin page for viewing overdue subscriptions and audit management.
 */
class Admin_Page {

	/**
	 * Initialize the admin page
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_automatewoo_subscription_utilities_remove_overdue', [ __CLASS__, 'ajax_remove_overdue' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_manual_audit' ] );
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'automatewoo',
			__( 'Subscription Audit', 'automatewoo-subscription-utilities' ),
			__( 'Subscription Audit', 'automatewoo-subscription-utilities' ),
			'manage_woocommerce',
			'automatewoo-subscription-audit',
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'automatewoo_page_automatewoo-subscription-audit' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'automatewoo-subscription-utilities-admin', plugin_dir_url( AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_FILE ) . 'assets/css/admin.css', [], '1.0.0' );
		wp_enqueue_script( 'automatewoo-subscription-utilities-admin', plugin_dir_url( AUTOMATEWOO_SUBSCRIPTION_UTILITIES_PLUGIN_FILE ) . 'assets/js/admin.js', [ 'jquery' ], '1.0.0', true );
		
		wp_localize_script( 'automatewoo-subscription-utilities-admin', 'automatewoo_subscription_utilities', [
			'nonce' => wp_create_nonce( 'automatewoo_subscription_utilities_nonce' ),
		] );
	}

	/**
	 * Render admin page
	 */
	public static function render_admin_page() {
		$overdue_subscriptions = get_option( 'automatewoo_subscription_utilities_overdue_subscriptions', [] );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overdue';
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscription Audit', 'automatewoo-subscription-utilities' ); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<a href="?page=automatewoo-subscription-audit&tab=overdue" class="nav-tab <?php echo $current_tab === 'overdue' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overdue Subscriptions', 'automatewoo-subscription-utilities' ); ?>
					<?php if ( ! empty( $overdue_subscriptions ) ) : ?>
						<span class="count">(<?php echo count( $overdue_subscriptions ); ?>)</span>
					<?php endif; ?>
				</a>
				<a href="?page=automatewoo-subscription-audit&tab=audit" class="nav-tab <?php echo $current_tab === 'audit' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Audit Tools', 'automatewoo-subscription-utilities' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php if ( $current_tab === 'overdue' ) : ?>
					<?php self::render_overdue_tab( $overdue_subscriptions ); ?>
				<?php elseif ( $current_tab === 'audit' ) : ?>
					<?php self::render_audit_tab(); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overdue subscriptions tab
	 */
	private static function render_overdue_tab( $overdue_subscriptions ) {
		?>
		<div class="overdue-subscriptions">
			<?php if ( empty( $overdue_subscriptions ) ) : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'No overdue subscriptions found.', 'automatewoo-subscription-utilities' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'The following subscriptions are overdue and require attention:', 'automatewoo-subscription-utilities' ); ?></p>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Subscription ID', 'automatewoo-subscription-utilities' ); ?></th>
							<th><?php esc_html_e( 'Overdue Date', 'automatewoo-subscription-utilities' ); ?></th>
							<th><?php esc_html_e( 'Days Overdue', 'automatewoo-subscription-utilities' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'automatewoo-subscription-utilities' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $overdue_subscriptions as $subscription_id => $data ) : ?>
							<?php
							$subscription = wcs_get_subscription( $subscription_id );
							$days_overdue = floor( ( current_time( 'timestamp' ) - strtotime( $data['date'] ) ) / DAY_IN_SECONDS );
							$admin_url = admin_url( 'post.php?post=' . $subscription_id . '&action=edit' );
							?>
							<tr>
								<td>
									<strong>#<?php echo esc_html( $subscription_id ); ?></strong>
									<?php if ( $subscription ) : ?>
										<br>
										<small><?php echo esc_html( $subscription->get_formatted_billing_full_name() ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $data['date'] ); ?></td>
								<td>
									<span class="days-overdue <?php echo $days_overdue > 7 ? 'critical' : 'warning'; ?>">
										<?php echo esc_html( $days_overdue ); ?> <?php echo esc_html( _n( 'day', 'days', $days_overdue, 'automatewoo-subscription-utilities' ) ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( $admin_url ); ?>" class="button button-small" target="_blank">
										<?php esc_html_e( 'Edit Subscription', 'automatewoo-subscription-utilities' ); ?>
									</a>
									<button class="button button-small remove-overdue" data-subscription-id="<?php echo esc_attr( $subscription_id ); ?>">
										<?php esc_html_e( 'Mark Resolved', 'automatewoo-subscription-utilities' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render audit tools tab
	 */
	private static function render_audit_tab() {
		// Show success message if audit was completed
		if ( isset( $_GET['audit_completed'] ) && $_GET['audit_completed'] === '1' ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . esc_html__( 'Manual audit completed successfully. Check the logs for detailed results.', 'automatewoo-subscription-utilities' ) . '</p>';
			echo '</div>';
		}
		
		?>
		<div class="audit-tools">
			<div class="card">
				<h2><?php esc_html_e( 'Manual Audit', 'automatewoo-subscription-utilities' ); ?></h2>
				<p><?php esc_html_e( 'Run a manual audit of all active subscriptions. This will check for missing or incorrect scheduled actions and fix them automatically.', 'automatewoo-subscription-utilities' ); ?></p>
				
				<form method="post" action="">
					<?php wp_nonce_field( 'automatewoo_subscription_audit_manual', 'automatewoo_subscription_audit_nonce' ); ?>
					<input type="hidden" name="action" value="run_manual_audit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Run Manual Audit', 'automatewoo-subscription-utilities' ); ?>
					</button>
				</form>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Audit Statistics', 'automatewoo-subscription-utilities' ); ?></h2>
				<?php
				global $wpdb;
				$total_active = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_subscription' AND post_status = 'wc-active'" );
				$overdue_count = count( get_option( 'automatewoo_subscription_utilities_overdue_subscriptions', [] ) );
				?>
				<ul>
					<li><strong><?php esc_html_e( 'Total Active Subscriptions:', 'automatewoo-subscription-utilities' ); ?></strong> <?php echo esc_html( $total_active ); ?></li>
					<li><strong><?php esc_html_e( 'Overdue Subscriptions:', 'automatewoo-subscription-utilities' ); ?></strong> <?php echo esc_html( $overdue_count ); ?></li>
					<li><strong><?php esc_html_e( 'Last Audit Run:', 'automatewoo-subscription-utilities' ); ?></strong> <?php echo esc_html( get_option( 'automatewoo_subscription_utilities_last_audit', __( 'Never', 'automatewoo-subscription-utilities' ) ) ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle manual audit form submission
	 */
	public static function handle_manual_audit() {
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'run_manual_audit' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['automatewoo_subscription_audit_nonce'], 'automatewoo_subscription_audit_manual' ) ) {
			wp_die( __( 'Security check failed.', 'automatewoo-subscription-utilities' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'automatewoo-subscription-utilities' ) );
		}

		// Run manual audit
		self::run_manual_audit();

		// Redirect back to the page with success message
		wp_redirect( add_query_arg( 'audit_completed', '1', admin_url( 'admin.php?page=automatewoo-subscription-audit&tab=audit' ) ) );
		exit;
	}

	/**
	 * Run manual audit of all active subscriptions
	 */
	private static function run_manual_audit() {
		global $wpdb;

		$subscription_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_type = 'shop_subscription' 
			AND post_status = 'wc-active'
			ORDER BY ID ASC"
		);

		$audited_count = 0;

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wcs_get_subscription( $subscription_id );
			if ( ! $subscription ) {
				continue;
			}

			$audited_count++;

			// Trigger the comprehensive audit for this subscription
			\AutomateWooSubscriptionUtilities\Triggers\Comprehensive_Subscription_Audit::trigger_manual_audit( $subscription_id );
		}

		// Update last audit time
		update_option( 'automatewoo_subscription_utilities_last_audit', current_time( 'mysql' ) );

		// Log the manual audit
		wc_get_logger()->info(
			sprintf(
				'Manual audit completed: %d subscriptions triggered for audit',
				$audited_count
			),
			array( 'source' => 'automatewoo-subscription-utilities-manual-audit' )
		);
	}

	/**
	 * AJAX handler for removing overdue subscription
	 */
	public static function ajax_remove_overdue() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'automatewoo_subscription_utilities_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'automatewoo-subscription-utilities' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'automatewoo-subscription-utilities' ) );
		}

		$subscription_id = intval( $_POST['subscription_id'] );
		if ( ! $subscription_id ) {
			wp_send_json_error( __( 'Invalid subscription ID.', 'automatewoo-subscription-utilities' ) );
		}

		// Remove from overdue list
		$overdue_subscriptions = get_option( 'automatewoo_subscription_utilities_overdue_subscriptions', [] );
		if ( isset( $overdue_subscriptions[ $subscription_id ] ) ) {
			unset( $overdue_subscriptions[ $subscription_id ] );
			update_option( 'automatewoo_subscription_utilities_overdue_subscriptions', $overdue_subscriptions );
			
			// Remove admin notice
			$notice_key = "subscription_overdue_{$subscription_id}";
			\AutomateWoo\AdminNotices::remove_notice( $notice_key );
			
			wp_send_json_success( __( 'Subscription marked as resolved.', 'automatewoo-subscription-utilities' ) );
		} else {
			wp_send_json_error( __( 'Subscription not found in overdue list.', 'automatewoo-subscription-utilities' ) );
		}
	}
} 