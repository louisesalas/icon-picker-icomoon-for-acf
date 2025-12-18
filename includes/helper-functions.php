<?php
/**
 * Helper Functions
 *
 * Global helper functions for using IcoMoon icons throughout WordPress.
 *
 * @package IPIACF
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper function to get an icon SVG
 *
 * @param string $icon_name The icon name (e.g., 'home' or 'icon-home')
 * @param array  $atts      Optional attributes (class, width, height, etc.)
 * @return string The SVG HTML
 */
function ipiacf_get_icon( string $icon_name, array $atts = array() ): string {
    $instance = ipiacf();
    
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
function ipiacf_icon( string $icon_name, array $atts = array() ): void {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo ipiacf_get_icon( $icon_name, $atts );
}

/**
 * Check if IcoMoon icons are available
 *
 * @return bool
 */
function ipiacf_has_icons(): bool {
    $icons = get_option( 'ipiacf_icons', array() );
    return ! empty( $icons );
}

/**
 * Get all available icon names
 *
 * @return array
 */
function ipiacf_get_icons(): array {
    return get_option( 'ipiacf_icons', array() );
}
