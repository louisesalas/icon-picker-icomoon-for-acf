<?php
/**
 * IcoMoon Frontend Class
 *
 * Handles frontend rendering and asset loading for IcoMoon icons.
 *
 * @package IPIACF
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IPIACF_Frontend
 *
 * Frontend functionality including helper functions and asset loading.
 */
class IPIACF_Frontend {

    /**
     * Whether the sprite has been enqueued
     *
     * @var bool
     */
    private bool $sprite_enqueued = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize frontend hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        // Enqueue frontend styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
        
        // Add sprite to footer if used
        add_action( 'wp_footer', array( $this, 'maybe_output_inline_sprite' ), 5 );
    }

    /**
     * Enqueue frontend styles
     *
     * @return void
     */
    public function enqueue_frontend_styles(): void {
        // Only enqueue if icons exist
        if ( ! ipiacf_has_icons() ) {
            return;
        }

        // Add inline styles for icons
        $css = $this->get_icon_css();
        
        if ( ! empty( $css ) ) {
            // Use plugin version if available, otherwise fall back to timestamp of last icon update
            $version = defined( 'IPIACF_VERSION' ) ? IPIACF_VERSION : get_option( 'ipiacf_last_update', '1.0.0' );
            
            wp_register_style( 'ipiacf-icons', false, array(), $version );
            wp_enqueue_style( 'ipiacf-icons' );
            wp_add_inline_style( 'ipiacf-icons', $css );
        }
    }

    /**
     * Get icon CSS
     *
     * @return string
     */
    private function get_icon_css(): string {
        return '
            .icomoon-icon {
                display: inline-block;
                width: 1em;
                height: 1em;
                stroke-width: 0;
                stroke: currentColor;
                fill: currentColor;
                vertical-align: middle;
            }
        ';
    }

    /**
     * Output inline sprite if icons have been used
     *
     * @return void
     */
    public function maybe_output_inline_sprite(): void {
        if ( ! $this->sprite_enqueued ) {
            return;
        }

        $sprite_path = get_option( 'ipiacf_sprite_path', '' );
        
        if ( empty( $sprite_path ) || ! file_exists( $sprite_path ) ) {
            return;
        }

        // Validate the sprite path is within uploads directory
        $upload_dir = wp_upload_dir();
        $base_dir = realpath( $upload_dir['basedir'] );
        $real_path = realpath( $sprite_path );
        
        if ( false === $real_path || strpos( $real_path, $base_dir ) !== 0 ) {
            return;
        }

        // Output the sprite inline (hidden) for use with <use> elements
        // The file has already been sanitized during upload, so we can safely output it
        $sprite_content = file_get_contents( $sprite_path );
        
        if ( ! empty( $sprite_content ) ) {
            // Make sure the SVG is hidden
            $sprite_content = preg_replace(
                '/<svg/',
                '<svg style="position:absolute;width:0;height:0;overflow:hidden;"',
                $sprite_content,
                1
            );
            
            echo "\n<!-- IcoMoon Sprite -->\n";
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $sprite_content;
            echo "\n<!-- /IcoMoon Sprite -->\n";
        }
    }

    /**
     * Get an icon SVG
     *
     * @param string $icon_name The icon name
     * @param array  $atts      Optional attributes
     * @return string The SVG HTML
     */
    public function get_icon( string $icon_name, array $atts = array() ): string {
        if ( empty( $icon_name ) ) {
            return '';
        }

        // Clean icon name (remove 'icon-' prefix if present)
        $icon_name = preg_replace( '/^icon-/', '', $icon_name );

        // Get sprite URL
        $sprite_url = get_option( 'ipiacf_sprite_url', '' );

        if ( empty( $sprite_url ) ) {
            return '';
        }

        // Mark sprite as used
        $this->sprite_enqueued = true;

        // Build attributes
        $defaults = array(
            'class'       => '',
            'width'       => null,
            'height'      => null,
            'aria-hidden' => 'true',
            'role'        => 'img',
            'focusable'   => 'false',
        );

        $atts = wp_parse_args( $atts, $defaults );

        // Add base class
        $classes = array( 'icomoon-icon', 'icon-' . $icon_name );
        
        if ( ! empty( $atts['class'] ) ) {
            $classes[] = $atts['class'];
        }
        
        $atts['class'] = implode( ' ', array_filter( $classes ) );

        // Build attribute string
        $attr_string = '';
        
        foreach ( $atts as $key => $value ) {
            if ( null === $value ) {
                continue;
            }
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }

        // Build SVG
        $svg = sprintf(
            '<svg%s><use href="%s#icon-%s"></use></svg>',
            $attr_string,
            esc_url( $sprite_url ),
            esc_attr( $icon_name )
        );

        return $svg;
    }

    /**
     * Get icon as inline SVG (no sprite reference)
     *
     * @param string $icon_name The icon name
     * @param array  $atts      Optional attributes
     * @return string The SVG HTML
     */
    public function get_inline_icon( string $icon_name, array $atts = array() ): string {
        if ( empty( $icon_name ) ) {
            return '';
        }

        // Clean icon name
        $icon_name = preg_replace( '/^icon-/', '', $icon_name );

        $sprite_path = get_option( 'ipiacf_sprite_path', '' );

        if ( empty( $sprite_path ) || ! file_exists( $sprite_path ) ) {
            return '';
        }

        // Validate the sprite path is within uploads directory
        $upload_dir = wp_upload_dir();
        $base_dir = realpath( $upload_dir['basedir'] );
        $real_path = realpath( $sprite_path );
        
        if ( false === $real_path || strpos( $real_path, $base_dir ) !== 0 ) {
            return '';
        }

        // Load and parse sprite with security settings
        $libxml_options = LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA;
        
        // Disable external entity loading to prevent XXE attacks (only needed for PHP < 8.0)
        $previous_entity_loader = null;
        if ( PHP_VERSION_ID < 80000 ) {
            $previous_entity_loader = libxml_disable_entity_loader( true );
        }
        libxml_use_internal_errors( true );
        
        $dom = new DOMDocument();
        $dom->substituteEntities = false;
        $dom->resolveExternals = false;
        $dom->load( $sprite_path, $libxml_options );
        
        // Restore previous entity loader state (only for PHP < 8.0)
        if ( PHP_VERSION_ID < 80000 && null !== $previous_entity_loader ) {
            libxml_disable_entity_loader( $previous_entity_loader );
        }
        libxml_clear_errors();

        // Sanitize icon name for XPath query (allow only alphanumeric, hyphens, underscores)
        $safe_icon_name = preg_replace( '/[^a-zA-Z0-9_-]/', '', $icon_name );

        // Find the symbol
        $xpath = new DOMXPath( $dom );
        $symbols = $xpath->query( "//symbol[@id='icon-{$safe_icon_name}']" );

        if ( $symbols->length === 0 ) {
            return '';
        }

        $symbol = $symbols->item( 0 );
        $viewBox = $symbol->getAttribute( 'viewBox' );

        // Get inner content
        $inner = '';
        foreach ( $symbol->childNodes as $child ) {
            $inner .= $dom->saveXML( $child );
        }

        // Build attributes
        $defaults = array(
            'class'       => '',
            'width'       => null,
            'height'      => null,
            'viewBox'     => $viewBox,
            'aria-hidden' => 'true',
            'role'        => 'img',
            'focusable'   => 'false',
            'xmlns'       => 'http://www.w3.org/2000/svg',
        );

        $atts = wp_parse_args( $atts, $defaults );

        // Add base class
        $classes = array( 'icomoon-icon', 'icon-' . $icon_name );
        
        if ( ! empty( $atts['class'] ) ) {
            $classes[] = $atts['class'];
        }
        
        $atts['class'] = implode( ' ', array_filter( $classes ) );

        // Build attribute string
        $attr_string = '';
        
        foreach ( $atts as $key => $value ) {
            if ( null === $value ) {
                continue;
            }
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }

        return sprintf( '<svg%s>%s</svg>', $attr_string, $inner );
    }

    /**
     * Render icon (echo version of get_icon)
     *
     * @param string $icon_name The icon name
     * @param array  $atts      Optional attributes
     * @return void
     */
    public function icon( string $icon_name, array $atts = array() ): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get_icon( $icon_name, $atts );
    }

    /**
     * Check if an icon exists
     *
     * @param string $icon_name The icon name to check
     * @return bool
     */
    public function icon_exists( string $icon_name ): bool {
        $icon_name = preg_replace( '/^icon-/', '', $icon_name );
        $icons = get_option( 'ipiacf_icons', array() );

        foreach ( $icons as $icon ) {
            if ( $icon['name'] === $icon_name ) {
                return true;
            }
        }

        return false;
    }
}
