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

// Just in case...
try{


    // Define the function to update the next payment date for active subscriptions
    function reset_next_payment_one_hour( $workflow ) {

        
        try{
            
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
            
        }catch( Exception $ex ){
            
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

        try{
            
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
            
        }catch( Exception $ex ){
            
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

}catch( Exception $ex ){
    
    wc_get_logger()->debug(
        sprintf(
            'Error: %s',
            $ex->getMessage()
        ),
        array(
            'source'    => 'fatal-errors',
            'data'      => '',
            'backtrace' => false,
        )
    );
}