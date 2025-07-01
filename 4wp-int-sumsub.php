<?php
/**
 * Plugin Name: 4wp Integration Sumsub with Contact Forms
 * Plugin URI: https://4wp.com/plugins/sumsub-integration
 * Description: Integration plugin for SumSub verification in WordPress (CF7, GF, etc.)
 * Version: 0.1.1
 * Author: 4wp.dev
 * Author URI: https://4wp.dev
 * Text Domain: 4wp-int-sumsub
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

namespace Forwp\SumsubIntegration;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    wp_die( 'This plugin requires PHP 7.4 or higher.' ); 
}

// Get plugin data and define constants
if ( ! function_exists( 'get_plugin_data' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_data = get_plugin_data( __FILE__ );
define( 'FORWP_SUMSUB_VERSION', $plugin_data['Version'] );
define( 'FORWP_SUMSUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_SUMSUB_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 autoloader for plugin classes
spl_autoload_register( function ( $class ) {
    $prefix = __NAMESPACE__ . '\\';
    $base_dir = __DIR__ . '/includes/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

// Main plugin class
final class Plugin {
    
    public function __construct() {
        $this->init_hooks();        
        $this->load_dependencies(); 
        $this->init_integrations(); 
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once FORWP_SUMSUB_PATH . 'includes/forwp-sumsub-ajax.php';
        require_once FORWP_SUMSUB_PATH . 'includes/shortcode-sumsub.php';
    }
    
    /**
     * Initialize form integrations
     */
    private function init_integrations() {
        // Always load settings
        if ( class_exists( __NAMESPACE__ . '\\Settings' ) ) {
            new Settings();
        }
        
        // Gravity Forms integration
        if ( class_exists( 'GFAPI' ) && class_exists( __NAMESPACE__ . '\\Gf_Integration' ) ) {
            new Gf_Integration();
        }
        
        // Contact Form 7 integration (when implemented)
        // if ( class_exists( 'WPCF7' ) && class_exists( __NAMESPACE__ . '\\Cf7_Integration' ) ) {
        //     new Cf7_Integration();
        // }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 
            '4wp-int-sumsub', 
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages' 
        );
    }
}

// Initialize plugin
new Plugin();