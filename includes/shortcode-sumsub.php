<?php
/**
 * SumSub verification shortcode
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register shortcode
add_shortcode( 'sumsub_verification', function( $atts ) {
    $atts = shortcode_atts( [
        'class' => 'sumsub-verification-container',
        'style' => 'margin: 20px 0;'
    ], $atts, 'sumsub_verification' );
    
    $container_id = 'sumsub-websdk-container-' . uniqid();
    
    $html = '<div class="' . esc_attr( $atts['class'] ) . '" style="' . esc_attr( $atts['style'] ) . '">';
    $html .= '<div id="' . esc_attr( $container_id ) . '" class="sumsub-websdk-container" style="display:none;"></div>';
    $html .= '<input type="hidden" name="sumsub_verified" value="" />';
    $html .= '<input type="hidden" name="sumsub_applicant_id" value="" />';
    $html .= '<input type="hidden" name="sumsub_container_id" value="' . esc_attr( $container_id ) . '" />';
    $html .= '</div>';
    
    return $html;
});