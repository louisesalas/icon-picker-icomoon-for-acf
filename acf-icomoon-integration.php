<?php
/**
 * Plugin Name: ACF IcoMoon Integration
 * Plugin URI: https://example.com/acf-icomoon-integration
 * Description: Adds IcoMoon icon picker support for Advanced Custom Fields. Upload your IcoMoon selection.json or SVG sprite and use icons in ACF fields.
 * Version: 1.0.1
 * Author: Louise Salas
 * Author URI: https://github.com/louisesalas
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-icomoon
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
 * Plugin constants
 */
define( 'ACF_ICOMOON_VERSION', '1.0.1' );
define( 'ACF_ICOMOON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACF_ICOMOON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACF_ICOMOON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if ACF is installed and active
 *
 * @return bool
 */
function acf_icomoon_is_acf_active(): bool {
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
function acf_icomoon_admin_notice_missing_acf(): void {
    if ( ! is_admin() ) {
        return;
    }

    $class = 'notice notice-error is-dismissible';
    $message = sprintf(
        /* translators: %s: Plugin name */
        __( 'The plugin "%s" requires Advanced Custom Fields (ACF) to be installed and activated. Please install and activate ACF to use this plugin.', 'acf-icomoon' ),
        __( 'ACF IcoMoon Integration', 'acf-icomoon' )
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
function acf_icomoon_activate(): void {
    // Check if ACF is active before activating
    if ( ! acf_icomoon_is_acf_active() ) {
        deactivate_plugins( ACF_ICOMOON_PLUGIN_BASENAME );
        wp_die(
            esc_html__( 'This plugin requires Advanced Custom Fields (ACF) to be installed and activated.', 'acf-icomoon' ),
            esc_html__( 'Plugin Activation Error', 'acf-icomoon' ),
            array( 'back_link' => true )
        );
    }

    // Set default options with autoload enabled for frequently accessed options
    if ( false === get_option( 'acf_icomoon_icons' ) ) {
        add_option( 'acf_icomoon_icons', array(), '', 'yes' );
    }
    if ( false === get_option( 'acf_icomoon_sprite_url' ) ) {
        add_option( 'acf_icomoon_sprite_url', '', '', 'yes' );
    }
    if ( false === get_option( 'acf_icomoon_sprite_path' ) ) {
        add_option( 'acf_icomoon_sprite_path', '', '', 'no' );
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation handler
 *
 * @return void
 */
function acf_icomoon_deactivate(): void {
    // Clean up if needed
    flush_rewrite_rules();
}

// Register activation and deactivation hooks outside the class
// to ensure they work even when ACF is not installed
register_activation_hook( __FILE__, 'acf_icomoon_activate' );
register_deactivation_hook( __FILE__, 'acf_icomoon_deactivate' );

/**
 * Main plugin class
 * 
 * Handles initialization and loading of all plugin components.
 */
final class ACF_IcoMoon_Integration {

    /**
     * Single instance of the plugin
     *
     * @var ACF_IcoMoon_Integration|null
     */
    private static ?ACF_IcoMoon_Integration $instance = null;

    /**
     * Admin handler instance
     *
     * @var ACF_IcoMoon_Admin|null
     */
    public ?ACF_IcoMoon_Admin $admin = null;

    /**
     * Parser handler instance
     *
     * @var ACF_IcoMoon_Parser|null
     */
    public ?ACF_IcoMoon_Parser $parser = null;

    /**
     * Frontend handler instance
     *
     * @var ACF_IcoMoon_Frontend|null
     */
    public ?ACF_IcoMoon_Frontend $frontend = null;

    /**
     * Get the single instance of the plugin
     *
     * @return ACF_IcoMoon_Integration
     */
    public static function get_instance(): ACF_IcoMoon_Integration {
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
        require_once ACF_ICOMOON_PLUGIN_DIR . 'includes/class-icomoon-parser.php';
        require_once ACF_ICOMOON_PLUGIN_DIR . 'includes/class-icomoon-admin.php';
        require_once ACF_ICOMOON_PLUGIN_DIR . 'includes/class-icomoon-frontend.php';
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        
        // Note: Activation and deactivation hooks are registered outside the class
        // to ensure they work even when ACF is not installed
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function init(): void {
        // Initialize parser
        $this->parser = new ACF_IcoMoon_Parser();

        // Initialize admin if in admin area
        if ( is_admin() ) {
            $this->admin = new ACF_IcoMoon_Admin( $this->parser );
        }

        // Initialize frontend
        $this->frontend = new ACF_IcoMoon_Frontend();

        // Load ACF field type when ACF is ready
        add_action( 'acf/include_field_types', array( $this, 'include_field_types' ) );
    }

    /**
     * Load plugin text domain for translations
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'acf-icomoon',
            false,
            dirname( ACF_ICOMOON_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Include ACF field types
     *
     * @return void
     */
    public function include_field_types(): void {
        require_once ACF_ICOMOON_PLUGIN_DIR . 'includes/class-icomoon-field.php';
        new ACF_IcoMoon_Field();
    }

}

/**
 * Initialize the plugin
 *
 * @return ACF_IcoMoon_Integration|null
 */
function acf_icomoon(): ?ACF_IcoMoon_Integration {
    static $admin_notice_registered = false;
    
    // Check if ACF is active before initializing
    if ( ! acf_icomoon_is_acf_active() ) {
        // Register admin notice only once to prevent duplicate notices
        // when helper functions call this function multiple times
        if ( ! $admin_notice_registered && is_admin() ) {
            add_action( 'admin_notices', 'acf_icomoon_admin_notice_missing_acf' );
            $admin_notice_registered = true;
        }
        return null;
    }

    return ACF_IcoMoon_Integration::get_instance();
}

// Start the plugin only if ACF is available
acf_icomoon();

/**
 * Helper function to get an icon SVG
 *
 * @param string $icon_name The icon name (e.g., 'home' or 'icon-home')
 * @param array  $atts      Optional attributes (class, width, height, etc.)
 * @return string The SVG HTML
 */
function icomoon_get_icon( string $icon_name, array $atts = array() ): string {
    $instance = acf_icomoon();
    
    if ( ! $instance || ! $instance->frontend ) {
        return '';
    }
    
    return $instance->frontend->get_icon( $icon_name, $atts );
}

/**
 * Echo an icon SVG
 *
 * @param string $icon_name The icon name
 * @param array  $atts      Optional attributes
 * @return void
 */
function icomoon_icon( string $icon_name, array $atts = array() ): void {
    echo icomoon_get_icon( $icon_name, $atts );
}

/**
 * Check if IcoMoon icons are available
 *
 * @return bool
 */
function icomoon_has_icons(): bool {
    $icons = get_option( 'acf_icomoon_icons', array() );
    return ! empty( $icons );
}

/**
 * Get all available icon names
 *
 * @return array
 */
function icomoon_get_icons(): array {
    return get_option( 'acf_icomoon_icons', array() );
}

