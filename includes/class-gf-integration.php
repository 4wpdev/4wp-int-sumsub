<?php
namespace Forwp\SumsubIntegration;

class Gf_Integration {
    public function __construct() {
        if ( ! class_exists( 'GFForms' ) ) {
            return;
        }
        
        add_action( 'wp_enqueue_scripts', [ $this, 'conditionally_enqueue_assets' ] );
        add_filter( 'gform_validation', [ $this, 'validate_sumsub' ] );
    }

    public function conditionally_enqueue_assets() {
        if ( is_admin() ) {
            return;
        }
        
        if ( $this->has_gravity_forms_with_sumsub_tag() ) {
            $this->enqueue_sumsub_scripts();
        }
    }

    private function has_gravity_forms_with_sumsub_tag() {
        global $post;
        
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'sumsub_verification' ) ) {
            return true;
        }
        
        if ( is_front_page() || is_page() ) {
            return true;
        }
        
        return false;
    }

    private function enqueue_sumsub_scripts() {
        wp_enqueue_script(
            'sumsub-sdk',
            'https://static.sumsub.com/idensic/static/sns-websdk-builder.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'forwp-sumsub-gf-js',
            plugin_dir_url( __FILE__ ) . '../assets/js/forwp-sumsub-shortcode.js',
            [ 'jquery', 'sumsub-sdk' ],
            '1.0.0',
            true
        );

        wp_localize_script(
            'forwp-sumsub-gf-js',
            'sumsubVars',
            [
                'ajaxurl'               => admin_url( 'admin-ajax.php' ),
                'nonce'                 => wp_create_nonce( 'forwp_sumsub_nonce' ),
                'lang'                  => substr( get_locale(), 0, 2 ),
                'form_type'             => 'gravity_forms',
                // GF-специфічні повідомлення:
                'verification_required' => __( 'Complete identity verification to proceed with this form submission', '4wp-int-sumsub' ),
                'success'               => __( 'Identity verified successfully! You may now submit the form.', '4wp-int-sumsub' ),
                'send_error'            => __( 'Form submission failed. Please try again.', '4wp-int-sumsub' ),
                'verification_error'    => __( 'Identity verification failed. Please complete the verification process.', '4wp-int-sumsub' ),
                'sdk_error'             => __( 'Verification system error. Please refresh the page and try again.', '4wp-int-sumsub' ),
                'sdk_not_loaded'        => __( 'Verification system not loaded. Please refresh the page.', '4wp-int-sumsub' ),
            ]
        );

        wp_enqueue_style(
            'forwp-sumsub-css',
            plugin_dir_url( __FILE__ ) . '../assets/css/forwp-sumsub.css',
            [],
            '1.0.0'
        );
    }

    public function validate_sumsub( $validation_result ) {
        $form = $validation_result['form'];
        $target_page = rgpost( 'gform_target_page_number_' . $form['id'] );
        
        if ( ! empty( $target_page ) ) {
            return $validation_result;
        }
        
        if ( isset( $_POST['sumsub_container_id'] ) ) {
            $sumsub_verified = rgpost( 'sumsub_verified' );
            
            if ( empty( $sumsub_verified ) ) {
                $validation_result['is_valid'] = false;
                
                foreach ( $form['fields'] as &$field ) {
                    if ( $field->type !== 'hidden' ) {
                        $field->failed_validation = true;
                        $field->validation_message = __( 'Please complete SumSub verification before submitting the form.', '4wp-sumsub-integration' );
                        break;
                    }
                }
            }
        }
        
        return $validation_result;
    }
}