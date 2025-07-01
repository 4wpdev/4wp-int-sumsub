<?php
/**
 * AJAX handler for SumSub token generation
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function forwp_sumsub_get_token() {
    // Security check
    if ( ! check_ajax_referer( 'forwp_sumsub_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'error' => 'Security check failed' ] );
        return;
    }
    
    
    $autoload_path = FORWP_SUMSUB_PATH . 'vendor/autoload.php';
    
    if ( ! file_exists( $autoload_path ) ) {
        wp_send_json_error( [ 'error' => 'Autoload not found at: ' . $autoload_path ] );
        return;
    }
    
    require_once $autoload_path;
    
    // Get SumSub credentials from options
    $APP_TOKEN = get_option( '_4wp_int_sumsub_app_token', '' );
    $SECRET_KEY = get_option( '_4wp_int_sumsub_secret_key', '' );
    $LEVEL_NAME = get_option( '_4wp_int_sumsub_level_name', 'basic-kyc-level' );
    
    // Fallback to old option names
    if ( empty( $APP_TOKEN ) ) {
        $APP_TOKEN = get_option( 'sumsub_app_token', '' );
    }
    if ( empty( $SECRET_KEY ) ) {
        $SECRET_KEY = get_option( 'sumsub_secret_key', '' );
    }
    if ( empty( $LEVEL_NAME ) || $LEVEL_NAME === 'basic-kyc-level' ) {
        $level_fallback = get_option( 'sumsub_level_name', '' );
        if ( ! empty( $level_fallback ) ) {
            $LEVEL_NAME = $level_fallback;
        }
    }
    
    // Check if credentials are set
    if ( empty( $APP_TOKEN ) || empty( $SECRET_KEY ) ) {
        wp_send_json_error( [ 
            'error' => 'SumSub credentials not configured. Please check plugin settings.' 
        ] );
        return;
    }
    
    // Generate external user ID
    $current_user_id = get_current_user_id();
    $externalUserId = $current_user_id > 0 
        ? 'wp_user_' . $current_user_id . '_' . time()
        : 'guest_user_' . uniqid() . '_' . time();
    
    try {
        $sumsub = new \App\Sumsub( $APP_TOKEN, $SECRET_KEY );
        
        $applicantId = $sumsub->createApplicant( $externalUserId, $LEVEL_NAME );
        $tokenData = $sumsub->getAccessToken( $externalUserId, $LEVEL_NAME );

        wp_send_json_success( [
            'token' => $tokenData['token'],
            'externalUserId' => $externalUserId,
            'applicantId' => $applicantId,
            'levelName' => $LEVEL_NAME,
            'timestamp' => time(),
        ] );
        
    } catch ( Throwable $e ) {
        // Log the error for debugging
        error_log( 'SumSub API Error: ' . $e->getMessage() );
        
        wp_send_json_error( [ 
            'error' => 'Verification system error. Please try again.' 
        ] );
    }
}

// Register AJAX actions
add_action( 'wp_ajax_forwp_sumsub_get_token', 'forwp_sumsub_get_token' );
add_action( 'wp_ajax_nopriv_forwp_sumsub_get_token', 'forwp_sumsub_get_token' );

/**
 * Check SumSub verification status via AJAX
 */
function forwp_sumsub_check_status() {
    // Security check
    if ( ! check_ajax_referer( 'forwp_sumsub_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'error' => 'Security check failed' ] );
        return;
    }
    
    $applicant_id = sanitize_text_field( $_POST['applicantId'] ?? '' );
    
    if ( empty( $applicant_id ) ) {
        wp_send_json_error( [ 'error' => 'Missing applicant ID' ] );
        return;
    }
    
    // Load SumSub autoloader
    require_once FORWP_SUMSUB_PATH . 'vendor/autoload.php';
    
    $APP_TOKEN = get_option( '_4wp_int_sumsub_app_token', '' );
    $SECRET_KEY = get_option( '_4wp_int_sumsub_secret_key', '' );
    
    // Fallback to old option names
    if ( empty( $APP_TOKEN ) ) {
        $APP_TOKEN = get_option( 'sumsub_app_token', '' );
    }
    if ( empty( $SECRET_KEY ) ) {
        $SECRET_KEY = get_option( 'sumsub_secret_key', '' );
    }
    
    if ( empty( $APP_TOKEN ) || empty( $SECRET_KEY ) ) {
        wp_send_json_error( [ 'error' => 'SumSub credentials not configured' ] );
        return;
    }
    
    try {
        $sumsub = new \App\Sumsub( $APP_TOKEN, $SECRET_KEY );
        $status = $sumsub->getApplicantStatus( $applicant_id );
        
        wp_send_json_success( [
            'status' => $status,
            'applicantId' => $applicant_id,
        ] );
        
    } catch ( Throwable $e ) {
        error_log( 'SumSub Status Check Error: ' . $e->getMessage() );
        wp_send_json_error( [ 'error' => 'Could not check verification status' ] );
    }
}

add_action( 'wp_ajax_forwp_sumsub_check_status', 'forwp_sumsub_check_status' );
add_action( 'wp_ajax_nopriv_forwp_sumsub_check_status', 'forwp_sumsub_check_status' );