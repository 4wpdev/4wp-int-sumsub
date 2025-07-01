<?php
/**
 * Uninstall script for 4wp Integration Sumsub plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( ! function_exists( 'delete_option' ) ) {
    return;
}

/**
 * Remove plugin options from database
 */
function forwp_int_sumsub_cleanup() {
    // Plugin options to remove
    $options = [
        '_4wp_int_sumsub_app_token',
        '_4wp_int_sumsub_secret_key',
        '_4wp_int_sumsub_level_name',
        '_4wp_int_sumsub_settings',
    ];
    
    // Remove regular options
    foreach ( $options as $option ) {
        delete_option( $option );
    }
    
    // Remove multisite options (if multisite)
    if ( is_multisite() ) {
        foreach ( $options as $option ) {
            delete_site_option( $option );
        }
    }
    
    // Remove any transients
    $transients = [
        '_4wp_int_sumsub_cache',
        '_4wp_int_sumsub_token_cache',
    ];
    
    foreach ( $transients as $transient ) {
        delete_transient( $transient );
        if ( is_multisite() ) {
            delete_site_transient( $transient );
        }
    }
    
    // Clean up user meta (if any verification data stored per user)
    $user_meta_count = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 
        '_4wp_int_sumsub_%' 
    ) );

    if ( $user_meta_count > 0 ) {
        $wpdb->query( $wpdb->prepare( 
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 
            '_4wp_int_sumsub_%' 
        ) );
    }
}

// Execute cleanup
forwp_int_sumsub_cleanup();