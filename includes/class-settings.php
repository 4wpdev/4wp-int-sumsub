<?php
namespace Forwp\SumsubIntegration;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {
    
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'init_settings' ] );
    }
    
    public function add_admin_menu() {
        add_options_page(
            __( 'SumSub Settings', '4wp-int-sumsub' ),
            __( 'SumSub Integration', '4wp-int-sumsub' ), 
            'manage_options',
            'forwp-sumsub-settings',
            [ $this, 'render_settings_page' ]
        );
    }
    
    public function init_settings() {
        register_setting( 'forwp_sumsub_settings', '_4wp_int_sumsub_app_token' );
        register_setting( 'forwp_sumsub_settings', '_4wp_int_sumsub_secret_key' );
        register_setting( 'forwp_sumsub_settings', '_4wp_int_sumsub_level_name' );
        
        add_settings_section(
            'forwp_sumsub_main_section',
            __( 'SumSub API Configuration', '4wp-int-sumsub' ),
            [ $this, 'section_callback' ],
            'forwp_sumsub_settings'
        );
        
        add_settings_field(
            '_4wp_int_sumsub_app_token',
            __( 'App Token', '4wp-int-sumsub' ),
            [ $this, 'app_token_callback' ],
            'forwp_sumsub_settings',
            'forwp_sumsub_main_section'
        );
        
        add_settings_field(
            '_4wp_int_sumsub_secret_key',
            __( 'Secret Key', '4wp-int-sumsub' ),
            [ $this, 'secret_key_callback' ],
            'forwp_sumsub_settings',
            'forwp_sumsub_main_section'
        );
        
        add_settings_field(
            '_4wp_int_sumsub_level_name',
            __( 'Level Name', '4wp-int-sumsub' ),
            [ $this, 'level_name_callback' ],
            'forwp_sumsub_settings',
            'forwp_sumsub_main_section'
        );
    }
    
    public function section_callback() {
        echo '<p>' . esc_html__( 'Enter your SumSub API credentials below:', '4wp-int-sumsub' ) . '</p>';
    }
    
    public function app_token_callback() {
        $value = get_option( '_4wp_int_sumsub_app_token', '' );
        echo '<input type="text" name="_4wp_int_sumsub_app_token" value="' . esc_attr( $value ) . '" size="50" />';
        echo '<p class="description">' . esc_html__( 'Your SumSub App Token from the dashboard', '4wp-int-sumsub' ) . '</p>';
    }
    
    public function secret_key_callback() {
        $value = get_option( '_4wp_int_sumsub_secret_key', '' );
        echo '<input type="password" name="_4wp_int_sumsub_secret_key" value="' . esc_attr( $value ) . '" size="50" />';
        echo '<p class="description">' . esc_html__( 'Your SumSub Secret Key (will be hidden)', '4wp-int-sumsub' ) . '</p>';
    }
    
    public function level_name_callback() {
        $value = get_option( '_4wp_int_sumsub_level_name', 'basic-kyc-level' );
        echo '<input type="text" name="_4wp_int_sumsub_level_name" value="' . esc_attr( $value ) . '" size="30" />';
        echo '<p class="description">' . 
             sprintf( 
                 esc_html__( 'Default: %s. This should match your SumSub level configuration.', '4wp-int-sumsub' ), 
                 '<code>basic-kyc-level</code>' 
             ) . 
             '</p>';
    }
    
    public function render_settings_page() {
        // Check if form was submitted and show success message
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error(
                'forwp_sumsub_messages',
                'forwp_sumsub_message',
                __( 'Settings saved successfully!', '4wp-int-sumsub' ),
                'success'
            );
        }
        
        // Show any error/success messages
        settings_errors( 'forwp_sumsub_messages' );
        ?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="card">
        <h2><?php esc_html_e( 'API Configuration', '4wp-int-sumsub' ); ?></h2>
        <p><?php esc_html_e( 'Configure your SumSub API credentials to enable identity verification in your forms.', '4wp-int-sumsub' ); ?>
        </p>

        <form method="post" action="options.php">
            <?php
                    settings_fields( 'forwp_sumsub_settings' );
                    do_settings_sections( 'forwp_sumsub_settings' );
                    submit_button( __( 'Save Settings', '4wp-int-sumsub' ) );
                    ?>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e( 'Quick Start', '4wp-int-sumsub' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Get your API credentials from SumSub dashboard', '4wp-int-sumsub' ); ?></li>
            <li><?php esc_html_e( 'Fill in the form above and save settings', '4wp-int-sumsub' ); ?></li>
            <li><?php esc_html_e( 'Add the shortcode [sumsub_verification] to any page or form', '4wp-int-sumsub' ); ?>
            </li>
            <li><?php esc_html_e( 'For Gravity Forms: the verification will be automatically added to forms', '4wp-int-sumsub' ); ?>
            </li>
        </ol>
    </div>

    <div class="card">
        <h2><?php esc_html_e( 'Support & Documentation', '4wp-int-sumsub' ); ?></h2>
        <p>
            <?php 
                    printf(
                        esc_html__( 'Need help? Visit our %s or %s for assistance.', '4wp-int-sumsub' ),
                        '<a href="https://4wp.dev/docs/sumsub-integration" target="_blank">' . esc_html__( 'documentation', '4wp-int-sumsub' ) . '</a>',
                        '<a href="https://4wp.dev/support" target="_blank">' . esc_html__( 'support page', '4wp-int-sumsub' ) . '</a>'
                    );
                    ?>
        </p>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}

.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #dcdcde;
}

.card ol {
    padding-left: 20px;
}

.card li {
    margin-bottom: 8px;
}
</style>
<?php
    }
}