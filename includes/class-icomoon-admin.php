<?php
/**
 * IcoMoon Admin Class
 *
 * Handles the admin settings page for IcoMoon icon management.
 *
 * @package IPIACF
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IPIACF_Admin
 *
 * Admin settings and icon management interface.
 */
class IPIACF_Admin {

    /**
     * Parser instance
     *
     * @var IPIACF_Parser
     */
    private IPIACF_Parser $parser;

    /**
     * Sanitizer instance
     *
     * @var IPIACF_Sanitizer
     */
    private IPIACF_Sanitizer $sanitizer;

    /**
     * Option group name
     *
     * @var string
     */
    private string $option_group = 'ipiacf_settings';

    /**
     * Settings page slug
     *
     * @var string
     */
    private string $page_slug = 'ipiacf-icomoon-icons';

    /**
     * Constructor
     *
     * @param IPIACF_Parser     $parser    Parser instance
     * @param IPIACF_Sanitizer  $sanitizer Sanitizer instance
     */
    public function __construct( IPIACF_Parser $parser, IPIACF_Sanitizer $sanitizer ) {
        $this->parser = $parser;
        $this->sanitizer = $sanitizer;
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'handle_file_upload' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_ipiacf_clear_icons', array( $this, 'ajax_clear_icons' ) );
    }

    /**
     * Add settings page to admin menu
     *
     * @return void
     */
    public function add_settings_page(): void {
        add_options_page(
            __( 'IcoMoon Icons', 'icon-picker-icomoon-for-acf' ),
            __( 'IcoMoon Icons', 'icon-picker-icomoon-for-acf' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our settings page and ACF field pages
        if ( 'settings_page_' . $this->page_slug !== $hook && 
             strpos( $hook, 'acf' ) === false &&
             strpos( $hook, 'post' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ipiacf-admin',
            IPIACF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IPIACF_VERSION
        );

        wp_enqueue_script(
            'ipiacf-admin',
            IPIACF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            IPIACF_VERSION,
            true
        );

        // Only localize on the settings page - ACF field handles its own localization
        if ( 'settings_page_' . $this->page_slug === $hook ) {
            wp_localize_script( 'ipiacf-admin', 'ipiacfData', array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'ipiacf_nonce' ),
                'spriteUrl'   => get_option( 'ipiacf_sprite_url', '' ),
                'icons'       => $this->parser->get_saved_icons(),
                'strings'     => array(
                    'confirmClear' => __( 'Are you sure you want to remove all icons? This cannot be undone.', 'icon-picker-icomoon-for-acf' ),
                    'clearing'     => __( 'Clearing...', 'icon-picker-icomoon-for-acf' ),
                    'cleared'      => __( 'Icons cleared successfully.', 'icon-picker-icomoon-for-acf' ),
                    'error'        => __( 'An error occurred. Please try again.', 'icon-picker-icomoon-for-acf' ),
                    'search'       => __( 'Search icons...', 'icon-picker-icomoon-for-acf' ),
                    'noResults'    => __( 'No icons found.', 'icon-picker-icomoon-for-acf' ),
                ),
            ) );
        }
    }

    /**
     * Get sanitized file uploads from $_FILES superglobal
     *
     * @return array Associative array of sanitized file data, keyed by field name
     */
    private function get_sanitized_files(): array {
        $sanitized_files = array();
        $file_fields = array( 'icomoon_selection', 'icomoon_sprite' );

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_file_upload() before calling this method
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below
        foreach ( $file_fields as $field_name ) {
            if ( isset( $_FILES[ $field_name ] ) && is_array( $_FILES[ $field_name ] ) ) {
                $file = $_FILES[ $field_name ];
                
                // Check if file was uploaded without errors
                if ( isset( $file['error'] ) && $file['error'] === UPLOAD_ERR_OK ) {
                    $sanitized_file = $this->sanitize_file_upload( $file );
                    if ( $sanitized_file ) {
                        $sanitized_files[ $field_name ] = $sanitized_file;
                    }
                }
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return $sanitized_files;
    }

    /**
     * Sanitize file upload array
     *
     * @param array $file Raw file upload data from $_FILES
     * @return array|false Sanitized file array or false if invalid
     */
    private function sanitize_file_upload( array $file ) {
        // Validate required keys exist
        $required_keys = array( 'name', 'type', 'tmp_name', 'error', 'size' );
        foreach ( $required_keys as $key ) {
            if ( ! isset( $file[ $key ] ) ) {
                return false;
            }
        }

        // Return sanitized file array
        return array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => sanitize_mime_type( $file['type'] ),
            'tmp_name' => $file['tmp_name'], // Path from system, don't sanitize
            'error'    => absint( $file['error'] ),
            'size'     => absint( $file['size'] ),
        );
    }

    /**
     * Handle file upload
     *
     * @return void
     */
    public function handle_file_upload(): void {
        // Check if form was submitted
        if ( ! isset( $_POST['ipiacf_upload_nonce'] ) ) {
            return;
        }

        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['ipiacf_upload_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'ipiacf_upload' ) ) {
            add_settings_error(
                'ipiacf',
                'nonce_error',
                __( 'Security check failed. Please try again.', 'icon-picker-icomoon-for-acf' ),
                'error'
            );
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'ipiacf',
                'permission_error',
                __( 'You do not have permission to perform this action.', 'icon-picker-icomoon-for-acf' ),
                'error'
            );
            return;
        }

        $upload_dir = wp_upload_dir();
        $icomoon_dir = $upload_dir['basedir'] . '/ipiacf-icomoon';

        // Create directory if it doesn't exist
        if ( ! file_exists( $icomoon_dir ) ) {
            wp_mkdir_p( $icomoon_dir );
        }

        // Sanitize and validate file uploads
        $files_data = $this->get_sanitized_files();
        
        // Handle selection.json upload
        if ( ! empty( $files_data['icomoon_selection'] ) ) {
            $this->process_selection_upload( $files_data['icomoon_selection'], $icomoon_dir, $upload_dir );
        }

        // Handle SVG sprite upload
        if ( ! empty( $files_data['icomoon_sprite'] ) ) {
            $this->process_sprite_upload( $files_data['icomoon_sprite'], $icomoon_dir, $upload_dir );
        }
    }

    /**
     * Process selection.json file upload
     *
     * @param array  $file       Uploaded file data
     * @param string $target_dir Target directory path
     * @param array  $upload_dir WordPress upload directory info
     * @return void
     */
    private function process_selection_upload( array $file, string $target_dir, array $upload_dir ): void {
        // Validate file
        $validation = $this->parser->validate_upload( $file, 'json' );
        
        if ( is_wp_error( $validation ) ) {
            add_settings_error(
                'ipiacf',
                'validation_error',
                $validation->get_error_message(),
                'error'
            );
            return;
        }

        // Move uploaded file using WordPress Filesystem API
        $target_path = $target_dir . '/selection.json';
        
        // Initialize the WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        // Copy the uploaded file to the target location
        if ( ! $wp_filesystem->move( $file['tmp_name'], $target_path, true ) ) {
            add_settings_error(
                'ipiacf',
                'move_error',
                __( 'Failed to save the uploaded file.', 'icon-picker-icomoon-for-acf' ),
                'error'
            );
            return;
        }

        // Parse the file
        $icons = $this->parser->parse_selection_json( $target_path );
        
        if ( is_wp_error( $icons ) ) {
            add_settings_error(
                'ipiacf',
                'parse_error',
                $icons->get_error_message(),
                'error'
            );
            return;
        }

        // Save icons
        $this->parser->save_icons( $icons );

        // Generate and save sprite if no sprite is uploaded
        if ( empty( get_option( 'ipiacf_sprite_url' ) ) ) {
            $sprite = $this->parser->generate_sprite_from_selection( $target_path );
            
            if ( ! is_wp_error( $sprite ) ) {
                $sprite_path = $target_dir . '/sprite.svg';
                $write_result = $wp_filesystem->put_contents( $sprite_path, $sprite, FS_CHMOD_FILE );
                
                if ( false === $write_result ) {
                    add_settings_error(
                        'ipiacf',
                        'sprite_write_error',
                        __( 'Failed to write the SVG sprite file. Please check directory permissions.', 'icon-picker-icomoon-for-acf' ),
                        'warning'
                    );
                } else {
                    $sprite_url = str_replace( 
                        $upload_dir['basedir'], 
                        $upload_dir['baseurl'], 
                        $sprite_path 
                    );
                    
                    update_option( 'ipiacf_sprite_url', $sprite_url );
                    update_option( 'ipiacf_sprite_path', $sprite_path );
                }
            }
        }

        add_settings_error(
            'ipiacf',
            'upload_success',
            sprintf(
                /* translators: %d: number of icons */
                __( 'Successfully imported %d icons from selection.json.', 'icon-picker-icomoon-for-acf' ),
                count( $icons )
            ),
            'success'
        );
    }

    /**
     * Process SVG sprite file upload
     *
     * @param array  $file       Uploaded file data
     * @param string $target_dir Target directory path
     * @param array  $upload_dir WordPress upload directory info
     * @return void
     */
    private function process_sprite_upload( array $file, string $target_dir, array $upload_dir ): void {
        // Validate file
        $validation = $this->parser->validate_upload( $file, 'svg' );
        
        if ( is_wp_error( $validation ) ) {
            add_settings_error(
                'ipiacf',
                'validation_error',
                $validation->get_error_message(),
                'error'
            );
            return;
        }

        // Validate SVG content before moving
        $svg_validation = $this->sanitizer->validate_svg_file( $file['tmp_name'] );
        
        if ( is_wp_error( $svg_validation ) ) {
            add_settings_error(
                'ipiacf',
                'svg_validation_error',
                $svg_validation->get_error_message(),
                'error'
            );
            return;
        }

        // Initialize the WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;

        // Read and sanitize the SVG content
        $svg_content = $wp_filesystem->get_contents( $file['tmp_name'] );
        
        if ( false === $svg_content ) {
            add_settings_error(
                'ipiacf',
                'read_error',
                __( 'Failed to read the uploaded file.', 'icon-picker-icomoon-for-acf' ),
                'error'
            );
            return;
        }
        
        $sanitized_svg = $this->sanitizer->sanitize_svg( $svg_content );
        
        if ( is_wp_error( $sanitized_svg ) ) {
            add_settings_error(
                'ipiacf',
                'sanitization_error',
                $sanitized_svg->get_error_message(),
                'error'
            );
            return;
        }

        // Write sanitized content to target file
        $target_path = $target_dir . '/sprite.svg';
        
        // Validate target path
        $path_validation = $this->parser->validate_file_path( $target_path );
        
        if ( is_wp_error( $path_validation ) ) {
            add_settings_error(
                'ipiacf',
                'path_error',
                $path_validation->get_error_message(),
                'error'
            );
            return;
        }
        
        // $wp_filesystem already initialized above
        $write_result = $wp_filesystem->put_contents( $target_path, $sanitized_svg, FS_CHMOD_FILE );
        
        if ( false === $write_result ) {
            add_settings_error(
                'ipiacf',
                'write_error',
                __( 'Failed to save the sanitized file.', 'icon-picker-icomoon-for-acf' ),
                'error'
            );
            return;
        }

        // Parse the sprite
        $icons = $this->parser->parse_svg_sprite( $target_path );
        
        if ( is_wp_error( $icons ) ) {
            add_settings_error(
                'ipiacf',
                'parse_error',
                $icons->get_error_message(),
                'error'
            );
            return;
        }

        // Save sprite URL and path
        $sprite_url = str_replace( 
            $upload_dir['basedir'], 
            $upload_dir['baseurl'], 
            $target_path 
        );
        
        update_option( 'ipiacf_sprite_url', $sprite_url );
        update_option( 'ipiacf_sprite_path', $target_path );

        // Save icons if none exist or merge with existing
        $existing_icons = $this->parser->get_saved_icons();
        
        if ( empty( $existing_icons ) ) {
            $this->parser->save_icons( $icons );
        }

        add_settings_error(
            'ipiacf',
            'upload_success',
            sprintf(
                /* translators: %d: number of icons */
                __( 'Successfully imported SVG sprite with %d icons.', 'icon-picker-icomoon-for-acf' ),
                count( $icons )
            ),
            'success'
        );
    }

    /**
     * AJAX handler to clear all icons
     *
     * @return void
     */
    public function ajax_clear_icons(): void {
        check_ajax_referer( 'ipiacf_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Permission denied.', 'icon-picker-icomoon-for-acf' ) 
            ) );
        }

        // Clear options
        $this->parser->clear_icons();

        // Delete files
        $upload_dir = wp_upload_dir();
        $icomoon_dir = $upload_dir['basedir'] . '/ipiacf-icomoon';

        $selection_file = $icomoon_dir . '/selection.json';
        $sprite_file = $icomoon_dir . '/sprite.svg';

        if ( file_exists( $selection_file ) ) {
            wp_delete_file( $selection_file );
        }
        if ( file_exists( $sprite_file ) ) {
            wp_delete_file( $sprite_file );
        }

        wp_send_json_success( array( 
            'message' => __( 'All icons have been cleared.', 'icon-picker-icomoon-for-acf' ) 
        ) );
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        $icons = $this->parser->get_saved_icons();
        $sprite_url = get_option( 'ipiacf_sprite_url', '' );
        ?>
        <div class="wrap ipiacf-admin">
            <h1><?php esc_html_e( 'IcoMoon Icons', 'icon-picker-icomoon-for-acf' ); ?></h1>
            
            <?php settings_errors( 'ipiacf' ); ?>

            <div class="ipiacf-grid">
                <!-- Upload Section -->
                <div class="ipiacf-card">
                    <h2><?php esc_html_e( 'Upload IcoMoon Files', 'icon-picker-icomoon-for-acf' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Upload your IcoMoon selection.json file or SVG sprite to import icons.', 'icon-picker-icomoon-for-acf' ); ?>
                    </p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ipiacf_upload', 'ipiacf_upload_nonce' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="icomoon_selection">
                                        <?php esc_html_e( 'selection.json', 'icon-picker-icomoon-for-acf' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="file" 
                                           name="icomoon_selection" 
                                           id="icomoon_selection" 
                                           accept=".json,application/json">
                                    <p class="description">
                                        <?php esc_html_e( 'Download this file from IcoMoon App after creating your icon set.', 'icon-picker-icomoon-for-acf' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="icomoon_sprite">
                                        <?php esc_html_e( 'SVG Sprite', 'icon-picker-icomoon-for-acf' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="file" 
                                           name="icomoon_sprite" 
                                           id="icomoon_sprite" 
                                           accept=".svg,image/svg+xml">
                                    <p class="description">
                                        <?php esc_html_e( 'The symbol-defs.svg file from your IcoMoon download.', 'icon-picker-icomoon-for-acf' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Upload & Import', 'icon-picker-icomoon-for-acf' ); ?>
                            </button>
                            
                            <?php if ( ! empty( $icons ) ) : ?>
                                <button type="button" 
                                        class="button button-secondary ipiacf-clear-btn" 
                                        id="ipiacf-clear">
                                    <?php esc_html_e( 'Clear All Icons', 'icon-picker-icomoon-for-acf' ); ?>
                                </button>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- Status Section -->
                <div class="ipiacf-card">
                    <h2><?php esc_html_e( 'Current Status', 'icon-picker-icomoon-for-acf' ); ?></h2>
                    
                    <table class="ipiacf-status-table">
                        <tr>
                            <td><?php esc_html_e( 'Icons Loaded:', 'icon-picker-icomoon-for-acf' ); ?></td>
                            <td><strong><?php echo esc_html( count( $icons ) ); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Sprite URL:', 'icon-picker-icomoon-for-acf' ); ?></td>
                            <td>
                                <?php if ( ! empty( $sprite_url ) ) : ?>
                                    <code><?php echo esc_html( $sprite_url ); ?></code>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Not uploaded', 'icon-picker-icomoon-for-acf' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Icons Preview Section -->
            <?php if ( ! empty( $icons ) ) : ?>
                <div class="ipiacf-card ipiacf-preview-section">
                    <h2><?php esc_html_e( 'Icon Preview', 'icon-picker-icomoon-for-acf' ); ?></h2>
                    
                    <div class="ipiacf-search-wrap">
                        <input type="text" 
                               id="ipiacf-search" 
                               class="ipiacf-search" 
                               placeholder="<?php esc_attr_e( 'Search icons...', 'icon-picker-icomoon-for-acf' ); ?>">
                        <span class="ipiacf-count">
                            <?php 
                            printf( 
                                /* translators: %d: number of icons */
                                esc_html__( '%d icons', 'icon-picker-icomoon-for-acf' ), 
                                esc_html( count( $icons ) )
                            ); 
                            ?>
                        </span>
                    </div>

                    <div class="ipiacf-icons-grid" id="ipiacf-icons-grid">
                        <?php foreach ( $icons as $icon ) : ?>
                            <div class="ipiacf-icon-item" 
                                 data-name="<?php echo esc_attr( $icon['name'] ); ?>"
                                 title="<?php echo esc_attr( $icon['name'] ); ?>">
                                <span class="ipiacf-icon-preview">
                                    <?php if ( ! empty( $sprite_url ) ) : ?>
                                        <svg class="icomoon-icon" aria-hidden="true">
                                            <use href="<?php echo esc_url( $sprite_url ); ?>#icon-<?php echo esc_attr( $icon['name'] ); ?>"></use>
                                        </svg>
                                    <?php else : ?>
                                        <span class="<?php echo esc_attr( $icon['class'] ?? 'icon-' . $icon['name'] ); ?>"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="ipiacf-icon-name">
                                    <?php echo esc_html( $icon['name'] ); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="ipiacf-no-results" id="ipiacf-no-results" style="display: none;">
                        <?php esc_html_e( 'No icons found matching your search.', 'icon-picker-icomoon-for-acf' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Usage Instructions -->
            <div class="ipiacf-card">
                <h2><?php esc_html_e( 'Usage Instructions', 'icon-picker-icomoon-for-acf' ); ?></h2>
                
                <h3><?php esc_html_e( 'In ACF Fields', 'icon-picker-icomoon-for-acf' ); ?></h3>
                <p><?php esc_html_e( 'Create a new field with type "IcoMoon Icon Picker" to allow users to select icons.', 'icon-picker-icomoon-for-acf' ); ?></p>

                <h3><?php esc_html_e( 'In Theme Templates', 'icon-picker-icomoon-for-acf' ); ?></h3>
                <pre><code>&lt;?php 
// Output an icon by name
ipiacf_icon( 'home' );

// Get icon HTML as string
$icon = ipiacf_get_icon( 'home', [
    'class' => 'my-custom-class',
    'width' => '24',
    'height' => '24'
] );

// Using with ACF
$icon_name = get_field( 'my_icon_field' );
if ( $icon_name ) {
    ipiacf_icon( $icon_name );
}
?&gt;</code></pre>
            </div>
        </div>
        <?php
    }
}
