# AutomateWoo Subscription Utilities

A collection of utilities for AutomateWoo and WooCommerce Subscriptions that provides enhanced functionality for managing subscription payment dates and auditing subscription scheduled actions.

## Description

This plugin extends AutomateWoo with additional actions and triggers for managing WooCommerce Subscriptions:

- **Reset Scheduled Action**: Reset a subscription's scheduled payment action by adding 3 minutes to the existing renewal time
- **Remove End Date**: Remove the end date from a subscription to make it ongoing (skips if end date is already in the past)
- **Audit Subscription Scheduled Actions**: Check if a subscription has scheduled payment and expiration actions
- **Perform Subscription Audit**: Action that performs the actual audit work (renewal date validation, scheduled action verification, automatic fixes)
- **Daily Subscription Audit Trigger**: Runs daily to audit all active subscriptions
- **Comprehensive Subscription Audit Trigger**: Triggers when subscriptions need auditing (status changes, payment failures, etc.)

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- WooCommerce Subscriptions
- AutomateWoo
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/automatewoo-subscription-utilities` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The new actions and triggers will be available in AutomateWoo workflows

## Usage

### AutomateWoo Actions

Once the plugin is activated, you'll find four new actions in your AutomateWoo workflows:

1. **Reset Scheduled Action - Add 3 Minutes**
   - Adds 3 minutes to the existing renewal time
   - Useful for testing or emergency rescheduling

2. **Remove End Date**
   - Removes the end date from a subscription to make it ongoing
   - Will skip if the end date is already in the past
   - Useful for extending subscriptions indefinitely

3. **Audit Subscription Scheduled Actions**
   - Checks if a subscription has scheduled payment and expiration actions
   - Logs results to WooCommerce logs with admin edit links

4. **Perform Subscription Audit**
   - Performs the actual audit work on a subscription
   - Validates subscription is active
   - Checks renewal date is in the future
   - Verifies scheduled action exists and matches renewal date
   - Automatically recreates scheduled actions if needed
   - Creates admin notices for overdue subscriptions
   - Logs all actions with detailed information

### AutomateWoo Triggers

1. **Daily Subscription Audit**
   - Runs daily at a configurable time
   - Processes all active subscriptions in batches
   - Perfect for pairing with the Perform Subscription Audit action
   - Ensures only one audit per subscription per day

2. **Comprehensive Subscription Audit**
   - Triggers when subscriptions need auditing
   - Fires on subscription status changes, payment failures, scheduled payments, renewals, and date updates
   - Perfect for pairing with the Perform Subscription Audit action
   - Provides real-time monitoring of subscription health

### Setting Up the Audit System

#### Option 1: Daily Scheduled Audit
1. Create a new AutomateWoo workflow
2. Set the trigger to "Daily Subscription Audit"
3. Configure the time of day when the audit should run
4. Add the "Perform Subscription Audit" action
5. Save and activate the workflow

#### Option 2: Event-Based Audit
1. Create a new AutomateWoo workflow
2. Set the trigger to "Comprehensive Subscription Audit"
3. Add the "Perform Subscription Audit" action
4. Save and activate the workflow

The system will then:
- **Daily Option**: Run daily at the specified time and check all active subscriptions
- **Event Option**: Trigger automatically when subscription events occur
- Automatically fix missing or incorrect scheduled actions
- Log all activities
- Create admin notices for overdue subscriptions

### Log Sources

The plugin logs all actions to WooCommerce logs with the following sources:

- `automatewoo-subscription-utilities` - General actions
- `automatewoo-subscription-utilities-audit` - Audit warnings and info
- `automatewoo-subscription-utilities-audit-success` - Successful audits
- `automatewoo-subscription-utilities-audit-failure` - Failed audits
- `automatewoo-subscription-utilities-audit-errors` - Audit errors
- `automatewoo-subscription-utilities-audit-overdue` - Overdue subscriptions
- `automatewoo-subscription-utilities-audit-fix` - Automatic fixes applied

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

// Manually trigger audit for a subscription
\AutomateWooSubscriptionUtilities\Triggers\Comprehensive_Subscription_Audit::trigger_manual_audit( 123 );
```

## Changelog

### 1.0.0
- Initial release
- Added Reset Scheduled Action action
- Added Remove End Date action
- Added Audit Subscription Scheduled Actions action
- Added Perform Subscription Audit action
- Added Daily Subscription Audit trigger
- Added Comprehensive Subscription Audit trigger
- Added utility classes for programmatic usage
- Added comprehensive logging
- Added admin notice system for overdue subscriptions
- Updated to WordPress Coding Standards naming conventions
- Fixed: Comprehensive Subscription Audit is now correctly implemented as a trigger

## Support

For support, please contact the WooCommerce Growth Team.

## License

This plugin is licensed under the same license as AutomateWoo.
