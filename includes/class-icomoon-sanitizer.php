<?php
/**
 * IcoMoon SVG Sanitizer Class
 *
 * Handles sanitization of SVG content to prevent XSS and other security issues.
 *
 * @package IPIACF
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IPIACF_Sanitizer
 *
 * Sanitizes SVG content by removing potentially malicious elements and attributes.
 */
class IPIACF_Sanitizer {

    /**
     * Allowed SVG tags
     *
     * @var array
     */
    private array $allowed_tags = array(
        'svg',
        'symbol',
        'defs',
        'g',
        'path',
        'circle',
        'rect',
        'ellipse',
        'line',
        'polyline',
        'polygon',
        'title',
        'desc',
        'use',
        'clipPath',
        'mask',
        'linearGradient',
        'radialGradient',
        'stop',
        'pattern',
    );

    /**
     * Allowed SVG attributes
     *
     * @var array
     */
    private array $allowed_attrs = array(
        'xmlns',
        'viewBox',
        'width',
        'height',
        'id',
        'class',
        'fill',
        'stroke',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'stroke-dasharray',
        'stroke-dashoffset',
        'opacity',
        'fill-opacity',
        'stroke-opacity',
        'd',
        'x',
        'y',
        'x1',
        'x2',
        'y1',
        'y2',
        'cx',
        'cy',
        'r',
        'rx',
        'ry',
        'points',
        'transform',
        'style',
        'gradientUnits',
        'gradientTransform',
        'spreadMethod',
        'offset',
        'stop-color',
        'stop-opacity',
        'clip-path',
        'mask',
        'aria-hidden',
        'role',
        'focusable',
    );

    /**
     * Dangerous patterns to remove from style attributes
     *
     * @var array
     */
    private array $dangerous_style_patterns = array(
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/@import/i',
        '/behavior\s*:/i',
        '/-moz-binding/i',
    );

    /**
     * Sanitize SVG content
     *
     * @param string $svg_content The SVG content to sanitize
     * @return string|WP_Error Sanitized SVG content or WP_Error on failure
     */
    public function sanitize_svg( string $svg_content ) {
        if ( empty( $svg_content ) ) {
            return new WP_Error( 'empty_svg', __( 'SVG content is empty.', 'icon-picker-icomoon-for-acf' ) );
        }

        // Remove any content before the opening SVG tag and after closing SVG tag
        $svg_content = $this->extract_svg_content( $svg_content );

        // Check for DOCTYPE declarations (potential XXE)
        if ( preg_match( '/<!DOCTYPE/i', $svg_content ) ) {
            return new WP_Error( 
                'doctype_not_allowed', 
                __( 'SVG files with DOCTYPE declarations are not allowed for security reasons.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Check for entity declarations
        if ( preg_match( '/<!ENTITY/i', $svg_content ) ) {
            return new WP_Error( 
                'entity_not_allowed', 
                __( 'SVG files with ENTITY declarations are not allowed for security reasons.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Configure libxml to disable external entities and network access
        $libxml_options = LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA;
        
        // Disable external entity loading (only needed for PHP < 8.0)
        $previous_entity_loader = null;
        if ( PHP_VERSION_ID < 80000 ) {
            $previous_entity_loader = libxml_disable_entity_loader( true );
        }
        libxml_use_internal_errors( true );

        $dom = new DOMDocument();
        $dom->substituteEntities = false;
        $dom->resolveExternals = false;

        // Load the SVG
        $loaded = $dom->loadXML( $svg_content, $libxml_options );

        // Restore previous entity loader state (only for PHP < 8.0)
        if ( PHP_VERSION_ID < 80000 && null !== $previous_entity_loader ) {
            libxml_disable_entity_loader( $previous_entity_loader );
        }
        
        if ( ! $loaded ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            return new WP_Error( 
                'invalid_svg', 
                __( 'Invalid SVG file. Could not parse XML content.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        libxml_clear_errors();

        // Remove dangerous elements and attributes
        $this->sanitize_dom( $dom );

        // Get sanitized content
        $sanitized = $dom->saveXML();

        if ( false === $sanitized ) {
            return new WP_Error( 
                'sanitization_failed', 
                __( 'Failed to sanitize SVG content.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        return $sanitized;
    }

    /**
     * Extract SVG content from full document
     *
     * @param string $content Full content
     * @return string SVG content
     */
    private function extract_svg_content( string $content ): string {
        // Find the SVG opening and closing tags
        if ( preg_match( '/<svg[^>]*>.*<\/svg>/is', $content, $matches ) ) {
            return $matches[0];
        }
        
        return $content;
    }

    /**
     * Sanitize DOM document by removing dangerous elements and attributes
     *
     * @param DOMDocument $dom The DOM document to sanitize
     * @return void
     */
    private function sanitize_dom( DOMDocument $dom ): void {
        $xpath = new DOMXPath( $dom );

        // Remove all script elements
        $scripts = $xpath->query( '//script' );
        foreach ( $scripts as $script ) {
            $script->parentNode->removeChild( $script );
        }

        // Remove all elements with event handler attributes (onclick, onerror, etc.)
        $elements_with_events = $xpath->query( '//*[@*[starts-with(name(), "on")]]' );
        foreach ( $elements_with_events as $element ) {
            $element->parentNode->removeChild( $element );
        }

        // Process all elements
        $all_elements = $xpath->query( '//*' );
        $elements_to_remove = array();

        foreach ( $all_elements as $element ) {
            $tag_name = strtolower( $element->tagName );

            // Remove disallowed tags
            if ( ! in_array( $tag_name, $this->allowed_tags, true ) ) {
                $elements_to_remove[] = $element;
                continue;
            }

            // Sanitize attributes
            $this->sanitize_attributes( $element );
        }

        // Remove flagged elements
        foreach ( $elements_to_remove as $element ) {
            if ( $element->parentNode ) {
                $element->parentNode->removeChild( $element );
            }
        }
    }

    /**
     * Sanitize element attributes
     *
     * @param DOMElement $element The element to sanitize
     * @return void
     */
    private function sanitize_attributes( DOMElement $element ): void {
        $attributes_to_remove = array();

        // Check each attribute
        foreach ( $element->attributes as $attr ) {
            $attr_name = strtolower( $attr->name );
            $attr_value = $attr->value;

            // Remove event handlers
            if ( strpos( $attr_name, 'on' ) === 0 ) {
                $attributes_to_remove[] = $attr_name;
                continue;
            }

            // Check for javascript: protocol in href or xlink:href
            if ( in_array( $attr_name, array( 'href', 'xlink:href' ), true ) ) {
                if ( preg_match( '/^\s*javascript:/i', $attr_value ) ) {
                    $attributes_to_remove[] = $attr_name;
                    continue;
                }
            }

            // Remove disallowed attributes
            if ( ! in_array( $attr_name, $this->allowed_attrs, true ) && 
                 ! preg_match( '/^data-/', $attr_name ) ) {
                $attributes_to_remove[] = $attr_name;
                continue;
            }

            // Sanitize style attribute
            if ( $attr_name === 'style' ) {
                $sanitized_style = $this->sanitize_style( $attr_value );
                
                if ( empty( $sanitized_style ) ) {
                    $attributes_to_remove[] = $attr_name;
                } else {
                    $element->setAttribute( $attr_name, $sanitized_style );
                }
            }
        }

        // Remove flagged attributes
        foreach ( $attributes_to_remove as $attr_name ) {
            $element->removeAttribute( $attr_name );
        }
    }

    /**
     * Sanitize style attribute value
     *
     * @param string $style The style value
     * @return string Sanitized style
     */
    private function sanitize_style( string $style ): string {
        // Check for dangerous patterns
        foreach ( $this->dangerous_style_patterns as $pattern ) {
            if ( preg_match( $pattern, $style ) ) {
                return '';
            }
        }

        // Remove any url() with javascript: protocol
        $style = preg_replace( '/url\s*\(\s*[\'"]?\s*javascript:/i', '', $style );
        
        return $style;
    }

    /**
     * Validate SVG file content before processing
     *
     * @param string $file_path Path to the SVG file
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_svg_file( string $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found.', 'icon-picker-icomoon-for-acf' ) );
        }

        $content = file_get_contents( $file_path );
        
        if ( false === $content ) {
            return new WP_Error( 'file_read_error', __( 'Could not read file.', 'icon-picker-icomoon-for-acf' ) );
        }

        // Check file size (already checked in parser, but double-check)
        $size = strlen( $content );
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ( $size > $max_size ) {
            return new WP_Error( 
                'file_too_large', 
                __( 'SVG file is too large. Maximum size is 5MB.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Check for basic SVG structure
        if ( ! preg_match( '/<svg[^>]*>/i', $content ) ) {
            return new WP_Error( 
                'not_svg', 
                __( 'File does not appear to be a valid SVG.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Check for dangerous content
        if ( preg_match( '/<script/i', $content ) ) {
            return new WP_Error( 
                'script_not_allowed', 
                __( 'SVG files containing script tags are not allowed.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        return true;
    }
}
