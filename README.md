# AutomateWoo Subscription Utilities

A collection of utilities for AutomateWoo and WooCommerce Subscriptions that provides enhanced functionality for managing subscription payment dates and auditing subscription scheduled actions.

## Description

This plugin extends AutomateWoo with additional actions for managing WooCommerce Subscriptions:

- **Reset Scheduled Action**: Reset a subscription's scheduled payment action by adding 3 minutes to the existing renewal time
- **Remove End Date**: Remove the end date from a subscription to make it ongoing
- **Audit Subscription Scheduled Actions**: Check if a subscription has scheduled payment and expiration actions

![Annotation on 2025-06-20 at 17-18-59](https://github.com/user-attachments/assets/ca9d23a5-7b40-40ce-b4f9-f402989f5327)


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

3. **Reset End Date - Add 3 Minutes**
   - Adds 3 minutes to the existing end date
   - Why? Nudging the end date recreates the stored expiration Scheduled Action in Action Scheduler — useful when the expiration scheduled action is missing and the subscription would otherwise stay active indefinitely
   - Skipped if the subscription has no end date set
   - Recommended usage: run as a manual workflow on subscriptions identified by the audit

4. **Audit Subscription Scheduled Actions**
   - Checks if a subscription has scheduled payment and expiration actions
   - Logs results to WooCommerce logs with admin edit links
   - Recommended usage: Run as a manual workflow
   - Recommended usage: target only active subscriptions (these are the only subscriptions expected to have scheduled payment actions)

## Logging

The plugin logs all actions to WooCommerce logs with the following sources:

- `automatewoo-subscription-utilities` - General actions
- `automatewoo-subscription-utilities-audit-success` - Successful audits
- `automatewoo-subscription-utilities-audit-failure` - Failed audits
- `automatewoo-subscription-utilities-audit-errors` - Audit errors

## Changelog

### 1.2.0
- Added `Reset End Date - Add 3 Minutes` AutomateWoo action. Mirror of `Reset Scheduled Action` but for the end date — nudges `end` forward so Action Scheduler recreates a missing `woocommerce_scheduled_subscription_expiration` action.

### 1.1.0
- Renamed actions: `Reset Payment Date` → `Reset Scheduled Action`, `Add Payment Time` → `Remove End Date`.
- `Remove End Date` now skips when the end date is already in the past, and uses HPOS-safe date handling.
- Overhauled audit system with comprehensive logging, admin UI/notices, and a daily audit trigger.
- Audit "Edit Subscription" links now use the HPOS-aware order edit URL.
- Removed unused utility classes; the action classes are the canonical entry points.
- Added a GitHub Actions release workflow that publishes a production zip (with `vendor/`) on each release.

### 1.0.0
- Initial release.

## Support

For support, please contact the WooCommerce Growth Team.

## License

This plugin is licensed under the same license as AutomateWoo.
