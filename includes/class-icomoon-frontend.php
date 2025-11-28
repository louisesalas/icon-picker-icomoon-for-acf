<?php
/**
 * IcoMoon Frontend Class
 *
 * Handles frontend rendering and asset loading for IcoMoon icons.
 *
 * @package ACF_IcoMoon_Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ACF_IcoMoon_Frontend
 *
 * Frontend functionality including helper functions and asset loading.
 */
class ACF_IcoMoon_Frontend {

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
        if ( ! icomoon_has_icons() ) {
            return;
        }

        // Add inline styles for icons
        $css = $this->get_icon_css();
        
        if ( ! empty( $css ) ) {
            wp_register_style( 'acf-icomoon-icons', false );
            wp_enqueue_style( 'acf-icomoon-icons' );
            wp_add_inline_style( 'acf-icomoon-icons', $css );
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

        $sprite_path = get_option( 'acf_icomoon_sprite_path', '' );
        
        if ( empty( $sprite_path ) || ! file_exists( $sprite_path ) ) {
            return;
        }

        // Output the sprite inline (hidden) for use with <use> elements
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
        $sprite_url = get_option( 'acf_icomoon_sprite_url', '' );

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

        $sprite_path = get_option( 'acf_icomoon_sprite_path', '' );

        if ( empty( $sprite_path ) || ! file_exists( $sprite_path ) ) {
            return '';
        }

        // Load and parse sprite
        libxml_use_internal_errors( true );
        
        $dom = new DOMDocument();
        $dom->load( $sprite_path );
        
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
        $icons = get_option( 'acf_icomoon_icons', array() );

        foreach ( $icons as $icon ) {
            if ( $icon['name'] === $icon_name ) {
                return true;
            }
        }

        return false;
    }
}

