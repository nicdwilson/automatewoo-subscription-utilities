# AutomateWoo Subscription Utilities

A collection of utilities for AutomateWoo and WooCommerce Subscriptions that provides enhanced functionality for managing subscription payment dates and auditing subscription scheduled actions.

## Description

This plugin extends AutomateWoo with additional actions for managing WooCommerce Subscriptions:

- **Reset Scheduled Action**: Reset a subscription's scheduled payment action by adding 3 minutes to the existing renewal time
- **Remove End Date**: Remove the end date from a subscription to make it ongoing
- **Audit Subscription Scheduled Actions**: Check if a subscription has scheduled payment and expiration actions

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- WooCommerce Subscriptions
- AutomateWoo
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/automatewoo-subscription-utilities` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The new actions will be available in AutomateWoo workflows

## Usage

### AutomateWoo Actions

Once the plugin is activated, you'll find three new actions in your AutomateWoo workflows:

1. **Reset Scheduled Action - Add 3 Minutes**
   - Resets the scheduled payment action by adding 3 minutes to the existing renewal time
   - Why? Resetting the payment date recreates the stored Scheduled Action in Action Scheduler
   - Recommended usage: use this as a manual workflow to ensure all subscriptions have Scheduled Actions

2. **Remove End Date**
   - Removes the end date from a subscription to make it ongoing
   - Useful for extending subscriptions indefinitely
   - Recommended usage: run as a manual workflow

3. **Audit Subscription Scheduled Actions**
   - Checks if a subscription has scheduled payment and expiration actions
   - Logs results to WooCommerce logs with admin edit links
   - Recommended usage: Run as a manual workflow
   - Recommended usage: target only active subscriptions (these are the only subscriptions expected to have scheduled payment actions)

### Programmatic Usage

You can also use the utility functions directly in your code:

```php
// Reset scheduled payment action (add 3 minutes)
$subscription = wcs_get_subscription( 123 );
\AutomateWooSubscriptionUtilities\Utilities\Subscription_Actions::reset_scheduled_action( $subscription );

// Remove end date from subscription
\AutomateWooSubscriptionUtilities\Utilities\Subscription_Actions::remove_end_date( $subscription );

// Audit a subscription
$audit_result = \AutomateWooSubscriptionUtilities\Utilities\Subscription_Audit::audit_subscription( $subscription );

// Audit multiple subscriptions
$subscription_ids = [ 123, 456, 789 ];
$audit_results = \AutomateWooSubscriptionUtilities\Utilities\Subscription_Audit::audit_multiple_subscriptions( $subscription_ids );
$summary = \AutomateWooSubscriptionUtilities\Utilities\Subscription_Audit::get_audit_summary( $audit_results );
```

## Logging

The plugin logs all actions to WooCommerce logs with the following sources:

- `automatewoo-subscription-utilities` - General actions
- `automatewoo-subscription-utilities-audit-success` - Successful audits
- `automatewoo-subscription-utilities-audit-failure` - Failed audits
- `automatewoo-subscription-utilities-audit-errors` - Audit errors

## Changelog

### 1.0.0
- Initial release
- Added Reset Scheduled Action action
- Added Remove End Date action
- Added Audit Subscription Scheduled Actions action
- Added utility classes for programmatic usage
- Added comprehensive logging
- Updated to WordPress Coding Standards naming conventions

## Support

For support, please contact the WooCommerce Growth Team.

## License

This plugin is licensed under the same license as AutomateWoo.
