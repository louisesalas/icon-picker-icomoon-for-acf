<?php
/**
 * Plugin Name: Icon Picker using IcoMoon for ACF
 * Plugin URI: https://github.com/louisesalas/icon-picker-icomoon-for-acf
 * Description: Adds IcoMoon icon picker support for Advanced Custom Fields. Upload your IcoMoon selection.json or SVG sprite and use icons in ACF fields.
 * Version: 1.0.4
 * Author: Louise Salas
 * Author URI: https://louisesalas.netlify.app/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: icon-picker-icomoon-for-acf
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants - Using unique IPIACF prefix (Icon Picker IcoMoon ACF)
 */
define( 'IPIACF_VERSION', '1.0.4' );
define( 'IPIACF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IPIACF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IPIACF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if ACF is installed and active
 *
 * @return bool
 */
function ipiacf_is_acf_active(): bool {
    // Check if ACF function exists (works for both ACF Pro and free)
    if ( function_exists( 'acf' ) || class_exists( 'ACF' ) ) {
        return true;
    }

    // Fallback: Check if ACF plugin is active
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) 
        || is_plugin_active( 'advanced-custom-fields/acf.php' );
}

/**
 * Display admin notice if ACF is not installed
 *
 * @return void
 */
function ipiacf_admin_notice_missing_acf(): void {
    if ( ! is_admin() ) {
        return;
    }

    $class = 'notice notice-error is-dismissible';
    $message = sprintf(
        /* translators: %s: Plugin name */
        __( 'The plugin "%s" requires Advanced Custom Fields (ACF) to be installed and activated. Please install and activate ACF to use this plugin.', 'icon-picker-icomoon-for-acf' ),
        __( 'Icon Picker using IcoMoon for ACF', 'icon-picker-icomoon-for-acf' )
    );

    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

/**
 * Plugin activation handler
 * 
 * This function is registered as the activation hook and must work
 * even when ACF is not installed to prevent activation without ACF.
 *
 * @return void
 */
function ipiacf_activate(): void {
    // Check if ACF is active before activating
    if ( ! ipiacf_is_acf_active() ) {
        deactivate_plugins( IPIACF_PLUGIN_BASENAME );
        wp_die(
            esc_html__( 'This plugin requires Advanced Custom Fields (ACF) to be installed and activated.', 'icon-picker-icomoon-for-acf' ),
            esc_html__( 'Plugin Activation Error', 'icon-picker-icomoon-for-acf' ),
            array( 'back_link' => true )
        );
    }

    // Set default options with autoload enabled for frequently accessed options
    if ( false === get_option( 'ipiacf_icons' ) ) {
        add_option( 'ipiacf_icons', array(), '', 'yes' );
    }
    if ( false === get_option( 'ipiacf_sprite_url' ) ) {
        add_option( 'ipiacf_sprite_url', '', '', 'yes' );
    }
    if ( false === get_option( 'ipiacf_sprite_path' ) ) {
        add_option( 'ipiacf_sprite_path', '', '', 'no' );
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation handler
 *
 * @return void
 */
function ipiacf_deactivate(): void {
    // Clean up if needed
    flush_rewrite_rules();
}

// Register activation and deactivation hooks outside the class
// to ensure they work even when ACF is not installed
register_activation_hook( __FILE__, 'ipiacf_activate' );
register_deactivation_hook( __FILE__, 'ipiacf_deactivate' );

/**
 * Main plugin class
 * 
 * Handles initialization and loading of all plugin components.
 */
final class IPIACF_Integration {

    /**
     * Single instance of the plugin
     *
     * @var IPIACF_Integration|null
     */
    private static ?IPIACF_Integration $instance = null;

    /**
     * Admin handler instance
     *
     * @var IPIACF_Admin|null
     */
    public ?IPIACF_Admin $admin = null;

    /**
     * Parser handler instance
     *
     * @var IPIACF_Parser|null
     */
    public ?IPIACF_Parser $parser = null;

    /**
     * Sanitizer handler instance
     *
     * @var IPIACF_Sanitizer|null
     */
    public ?IPIACF_Sanitizer $sanitizer = null;

    /**
     * Frontend handler instance
     *
     * @var IPIACF_Frontend|null
     */
    public ?IPIACF_Frontend $frontend = null;

    /**
     * Get the single instance of the plugin
     *
     * @return IPIACF_Integration
     */
    public static function get_instance(): IPIACF_Integration {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required class files
     *
     * @return void
     */
    private function load_dependencies(): void {
        require_once IPIACF_PLUGIN_DIR . 'includes/class-icomoon-sanitizer.php';
        require_once IPIACF_PLUGIN_DIR . 'includes/class-icomoon-parser.php';
        require_once IPIACF_PLUGIN_DIR . 'includes/class-icomoon-admin.php';
        require_once IPIACF_PLUGIN_DIR . 'includes/class-icomoon-frontend.php';
        require_once IPIACF_PLUGIN_DIR . 'includes/helper-functions.php';
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        
        // Note: Activation and deactivation hooks are registered outside the class
        // to ensure they work even when ACF is not installed
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function init(): void {
        // Initialize sanitizer
        $this->sanitizer = new IPIACF_Sanitizer();

        // Initialize parser
        $this->parser = new IPIACF_Parser();

        // Initialize admin if in admin area
        if ( is_admin() ) {
            $this->admin = new IPIACF_Admin( $this->parser, $this->sanitizer );
        }

        // Initialize frontend
        $this->frontend = new IPIACF_Frontend();

        // Load ACF field type when ACF is ready
        add_action( 'acf/include_field_types', array( $this, 'include_field_types' ) );
    }

    /**
     * Include ACF field types
     *
     * @return void
     */
    public function include_field_types(): void {
        require_once IPIACF_PLUGIN_DIR . 'includes/class-icomoon-field.php';
        new IPIACF_Field();
    }

}

/**
 * Initialize the plugin
 *
 * @return IPIACF_Integration|null
 */
function ipiacf(): ?IPIACF_Integration {
    static $admin_notice_registered = false;
    
    // Check if ACF is active before initializing
    if ( ! ipiacf_is_acf_active() ) {
        // Register admin notice only once to prevent duplicate notices
        // when helper functions call this function multiple times
        if ( ! $admin_notice_registered && is_admin() ) {
            add_action( 'admin_notices', 'ipiacf_admin_notice_missing_acf' );
            $admin_notice_registered = true;
        }
        return null;
    }

    return IPIACF_Integration::get_instance();
}

// Start the plugin only if ACF is available
ipiacf();
