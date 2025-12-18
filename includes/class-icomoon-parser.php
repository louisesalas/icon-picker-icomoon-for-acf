<?php
/**
 * IcoMoon Parser Class
 *
 * Handles parsing of IcoMoon selection.json and SVG sprite files.
 *
 * @package IPIACF
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IPIACF_Parser
 *
 * Parses IcoMoon files and extracts icon information.
 */
class IPIACF_Parser {

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
                __( 'The selection.json file was not found.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        $content = file_get_contents( $file_path );
        
        if ( false === $content ) {
            return new WP_Error( 
                'file_read_error', 
                __( 'Could not read the selection.json file.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        $data = json_decode( $content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 
                'json_parse_error', 
                sprintf( 
                    /* translators: %s: JSON error message */
                    __( 'JSON parse error: %s', 'icon-picker-icomoon-for-acf' ), 
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
                __( 'The SVG sprite file was not found.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        $content = file_get_contents( $file_path );
        
        if ( false === $content ) {
            return new WP_Error( 
                'file_read_error', 
                __( 'Could not read the SVG sprite file.', 'icon-picker-icomoon-for-acf' ) 
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

        // Use DOMDocument to parse SVG with security settings
        // LIBXML_NONET prevents network access, LIBXML_NOENT disables entity substitution
        $libxml_options = LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA;
        
        libxml_use_internal_errors( true );
        
        $dom = new DOMDocument();
        $dom->substituteEntities = false;
        $dom->resolveExternals = false;
        $dom->loadXML( $content, $libxml_options );
        
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
                __( 'The selection.json file was not found.', 'icon-picker-icomoon-for-acf' ) 
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
                __( 'No SVG paths found in selection.json.', 'icon-picker-icomoon-for-acf' ) 
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
                __( 'There was an error uploading the file.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Validate file type
        $allowed_types = array(
            'json' => array(
                'extensions' => array( 'json' ),
                'mimes'      => array( 'application/json', 'text/plain', 'text/json' ),
            ),
            'svg'  => array(
                'extensions' => array( 'svg' ),
                'mimes'      => array( 'image/svg+xml', 'text/plain', 'application/octet-stream' ),
            ),
        );

        if ( ! isset( $allowed_types[ $type ] ) ) {
            return new WP_Error( 
                'invalid_type', 
                __( 'Invalid file type specified.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        
        // Check extension
        if ( ! in_array( $extension, $allowed_types[ $type ]['extensions'], true ) ) {
            return new WP_Error( 
                'invalid_type', 
                sprintf(
                    /* translators: %s: file extension */
                    __( 'Please upload a valid .%s file.', 'icon-picker-icomoon-for-acf' ),
                    $allowed_types[ $type ]['extensions'][0]
                )
            );
        }

        // Check MIME type
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        if ( ! in_array( $mime_type, $allowed_types[ $type ]['mimes'], true ) ) {
            return new WP_Error( 
                'invalid_mime', 
                __( 'The file MIME type is not allowed.', 'icon-picker-icomoon-for-acf' ) 
            );
        }

        // Check file size (max 5MB)
        $max_size = 5 * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 
                'file_too_large', 
                __( 'The file is too large. Maximum size is 5MB.', 'icon-picker-icomoon-for-acf' ) 
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
        return get_option( 'ipiacf_icons', array() );
    }

    /**
     * Save icons to option
     *
     * @param array $icons Array of icon data
     * @return bool
     */
    public function save_icons( array $icons ): bool {
        return update_option( 'ipiacf_icons', $icons );
    }

    /**
     * Clear all saved icon data
     *
     * @return bool
     */
    public function clear_icons(): bool {
        delete_option( 'ipiacf_sprite_url' );
        delete_option( 'ipiacf_sprite_path' );
        return update_option( 'ipiacf_icons', array() );
    }

    /**
     * Validate that a file path is within the WordPress uploads directory
     *
     * @param string $file_path The file path to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_file_path( string $file_path ) {
        // Get the uploads directory
        $upload_dir = wp_upload_dir();
        $base_dir = realpath( $upload_dir['basedir'] );
        
        // Get the real path of the file
        $real_path = realpath( $file_path );
        
        // If realpath returns false, the file doesn't exist or path is invalid
        if ( false === $real_path ) {
            // For new files that don't exist yet, check the directory
            $dir_path = dirname( $file_path );
            $real_dir = realpath( $dir_path );
            
            if ( false === $real_dir ) {
                return new WP_Error(
                    'invalid_path',
                    __( 'Invalid file path.', 'icon-picker-icomoon-for-acf' )
                );
            }
            
            // Check if the directory is within uploads
            if ( strpos( $real_dir, $base_dir ) !== 0 ) {
                return new WP_Error(
                    'path_traversal',
                    __( 'File path must be within the WordPress uploads directory.', 'icon-picker-icomoon-for-acf' )
                );
            }
            
            return true;
        }
        
        // Check if the file is within the uploads directory
        if ( strpos( $real_path, $base_dir ) !== 0 ) {
            return new WP_Error(
                'path_traversal',
                __( 'File path must be within the WordPress uploads directory.', 'icon-picker-icomoon-for-acf' )
            );
        }
        
        return true;
    }
}
