<?php
/**
 * IcoMoon Parser Class
 *
 * Handles parsing of IcoMoon selection.json and SVG sprite files.
 *
 * @package ACF_IcoMoon_Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ACF_IcoMoon_Parser
 *
 * Parses IcoMoon files and extracts icon information.
 */
class ACF_IcoMoon_Parser {

    /**
     * Parse a selection.json file and extract icon names
     *
     * @param string $file_path Path to the selection.json file
     * @return array|WP_Error Array of icon data or WP_Error on failure
     */
    public function parse_selection_json( string $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 
                'file_not_found', 
                __( 'The selection.json file was not found.', 'acf-icomoon' ) 
            );
        }

        $content = file_get_contents( $file_path );
        
        if ( false === $content ) {
            return new WP_Error( 
                'file_read_error', 
                __( 'Could not read the selection.json file.', 'acf-icomoon' ) 
            );
        }

        $data = json_decode( $content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 
                'json_parse_error', 
                sprintf( 
                    /* translators: %s: JSON error message */
                    __( 'JSON parse error: %s', 'acf-icomoon' ), 
                    json_last_error_msg() 
                ) 
            );
        }

        return $this->extract_icons_from_selection( $data );
    }

    /**
     * Extract icon information from selection.json data
     *
     * @param array $data Parsed JSON data
     * @return array Array of icon information
     */
    private function extract_icons_from_selection( array $data ): array {
        $icons = array();
        $prefix = $data['preferences']['fontPref']['prefix'] ?? 'icon-';

        if ( ! isset( $data['icons'] ) || ! is_array( $data['icons'] ) ) {
            return $icons;
        }

        foreach ( $data['icons'] as $icon ) {
            if ( isset( $icon['properties']['name'] ) ) {
                $name = $icon['properties']['name'];
                
                // Handle comma-separated names (IcoMoon sometimes lists aliases)
                $names = explode( ',', $name );
                $primary_name = trim( $names[0] );

                $icons[] = array(
                    'name'     => $primary_name,
                    'class'    => $prefix . $primary_name,
                    'unicode'  => isset( $icon['properties']['code'] ) 
                        ? dechex( $icon['properties']['code'] ) 
                        : '',
                    'tags'     => $icon['icon']['tags'] ?? array(),
                    'aliases'  => array_map( 'trim', array_slice( $names, 1 ) ),
                );
            }
        }

        return $icons;
    }

    /**
     * Parse an SVG sprite file and extract icon IDs
     *
     * @param string $file_path Path to the SVG sprite file
     * @return array|WP_Error Array of icon data or WP_Error on failure
     */
    public function parse_svg_sprite( string $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 
                'file_not_found', 
                __( 'The SVG sprite file was not found.', 'acf-icomoon' ) 
            );
        }

        $content = file_get_contents( $file_path );
        
        if ( false === $content ) {
            return new WP_Error( 
                'file_read_error', 
                __( 'Could not read the SVG sprite file.', 'acf-icomoon' ) 
            );
        }

        return $this->extract_icons_from_sprite( $content );
    }

    /**
     * Extract icon IDs from SVG sprite content
     *
     * @param string $content SVG sprite content
     * @return array Array of icon information
     */
    private function extract_icons_from_sprite( string $content ): array {
        $icons = array();

        // Use DOMDocument to parse SVG
        libxml_use_internal_errors( true );
        
        $dom = new DOMDocument();
        $dom->loadXML( $content );
        
        libxml_clear_errors();

        // Find all symbol elements
        $symbols = $dom->getElementsByTagName( 'symbol' );

        foreach ( $symbols as $symbol ) {
            $id = $symbol->getAttribute( 'id' );
            
            if ( ! empty( $id ) ) {
                // Remove common prefixes like 'icon-' for the clean name
                $clean_name = preg_replace( '/^icon-/', '', $id );
                
                $icons[] = array(
                    'name'    => $clean_name,
                    'id'      => $id,
                    'class'   => 'icon-' . $clean_name,
                    'viewBox' => $symbol->getAttribute( 'viewBox' ),
                );
            }
        }

        return $icons;
    }

    /**
     * Extract SVG paths for inline rendering (from selection.json)
     *
     * @param array $data Parsed selection.json data
     * @return array Array of icon SVG paths
     */
    public function extract_svg_paths( array $data ): array {
        $paths = array();
        $grid = $data['height'] ?? 1024;

        if ( ! isset( $data['icons'] ) || ! is_array( $data['icons'] ) ) {
            return $paths;
        }

        foreach ( $data['icons'] as $icon ) {
            if ( isset( $icon['properties']['name'], $icon['icon']['paths'] ) ) {
                $names = explode( ',', $icon['properties']['name'] );
                $primary_name = trim( $names[0] );
                
                $paths[ $primary_name ] = array(
                    'paths'   => $icon['icon']['paths'],
                    'width'   => $icon['icon']['width'] ?? $grid,
                    'grid'    => $grid,
                    'attrs'   => $icon['icon']['attrs'] ?? array(),
                );
            }
        }

        return $paths;
    }

    /**
     * Generate SVG sprite from selection.json data
     *
     * @param string $file_path Path to selection.json
     * @return string|WP_Error SVG sprite content or WP_Error
     */
    public function generate_sprite_from_selection( string $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 
                'file_not_found', 
                __( 'The selection.json file was not found.', 'acf-icomoon' ) 
            );
        }

        $content = file_get_contents( $file_path );
        $data = json_decode( $content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_parse_error', json_last_error_msg() );
        }

        $svg_paths = $this->extract_svg_paths( $data );
        
        if ( empty( $svg_paths ) ) {
            return new WP_Error( 
                'no_paths', 
                __( 'No SVG paths found in selection.json.', 'acf-icomoon' ) 
            );
        }

        return $this->build_sprite( $svg_paths );
    }

    /**
     * Build SVG sprite from paths data
     *
     * @param array $paths_data Array of icon paths
     * @return string SVG sprite content
     */
    private function build_sprite( array $paths_data ): string {
        $symbols = '';

        foreach ( $paths_data as $name => $data ) {
            $width = $data['width'];
            $grid = $data['grid'];
            $viewBox = "0 0 {$width} {$grid}";
            
            $paths = '';
            foreach ( $data['paths'] as $index => $path ) {
                $attrs = '';
                if ( isset( $data['attrs'][ $index ] ) ) {
                    foreach ( $data['attrs'][ $index ] as $attr => $value ) {
                        $attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
                    }
                }
                $paths .= sprintf( '<path d="%s"%s></path>', esc_attr( $path ), $attrs );
            }
            
            $symbols .= sprintf(
                '<symbol id="icon-%s" viewBox="%s">%s</symbol>',
                esc_attr( $name ),
                esc_attr( $viewBox ),
                $paths
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">%s</svg>',
            $symbols
        );
    }

    /**
     * Validate uploaded file
     *
     * @param array  $file     The uploaded file data from $_FILES
     * @param string $type     Type of file: 'json' or 'svg'
     * @return true|WP_Error True on success, WP_Error on failure
     */
    public function validate_upload( array $file, string $type ) {
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 
                'upload_error', 
                __( 'There was an error uploading the file.', 'acf-icomoon' ) 
            );
        }

        // Validate file type
        $allowed_types = array(
            'json' => array( 'application/json', 'text/plain' ),
            'svg'  => array( 'image/svg+xml', 'text/plain', 'application/octet-stream' ),
        );

        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        // For JSON files, also check extension since MIME detection isn't always reliable
        if ( 'json' === $type ) {
            $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( 'json' !== $extension ) {
                return new WP_Error( 
                    'invalid_type', 
                    __( 'Please upload a valid .json file.', 'acf-icomoon' ) 
                );
            }
        }

        // For SVG files, check extension
        if ( 'svg' === $type ) {
            $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( 'svg' !== $extension ) {
                return new WP_Error( 
                    'invalid_type', 
                    __( 'Please upload a valid .svg file.', 'acf-icomoon' ) 
                );
            }
        }

        // Check file size (max 5MB)
        $max_size = 5 * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 
                'file_too_large', 
                __( 'The file is too large. Maximum size is 5MB.', 'acf-icomoon' ) 
            );
        }

        return true;
    }

    /**
     * Get icons from saved option
     *
     * @return array
     */
    public function get_saved_icons(): array {
        return get_option( 'acf_icomoon_icons', array() );
    }

    /**
     * Save icons to option
     *
     * @param array $icons Array of icon data
     * @return bool
     */
    public function save_icons( array $icons ): bool {
        return update_option( 'acf_icomoon_icons', $icons );
    }

    /**
     * Clear all saved icon data
     *
     * @return bool
     */
    public function clear_icons(): bool {
        delete_option( 'acf_icomoon_sprite_url' );
        delete_option( 'acf_icomoon_sprite_path' );
        return update_option( 'acf_icomoon_icons', array() );
    }
}

