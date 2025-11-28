<?php
/**
 * IcoMoon Admin Class
 *
 * Handles the admin settings page for IcoMoon icon management.
 *
 * @package ACF_IcoMoon_Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ACF_IcoMoon_Admin
 *
 * Admin settings and icon management interface.
 */
class ACF_IcoMoon_Admin {

    /**
     * Parser instance
     *
     * @var ACF_IcoMoon_Parser
     */
    private ACF_IcoMoon_Parser $parser;

    /**
     * Option group name
     *
     * @var string
     */
    private string $option_group = 'acf_icomoon_settings';

    /**
     * Settings page slug
     *
     * @var string
     */
    private string $page_slug = 'acf-icomoon-icons';

    /**
     * Constructor
     *
     * @param ACF_IcoMoon_Parser $parser Parser instance
     */
    public function __construct( ACF_IcoMoon_Parser $parser ) {
        $this->parser = $parser;
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
        add_action( 'wp_ajax_acf_icomoon_clear_icons', array( $this, 'ajax_clear_icons' ) );
    }

    /**
     * Add settings page to admin menu
     *
     * @return void
     */
    public function add_settings_page(): void {
        add_options_page(
            __( 'IcoMoon Icons', 'acf-icomoon' ),
            __( 'IcoMoon Icons', 'acf-icomoon' ),
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
            'acf-icomoon-admin',
            ACF_ICOMOON_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ACF_ICOMOON_VERSION
        );

        wp_enqueue_script(
            'acf-icomoon-admin',
            ACF_ICOMOON_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            ACF_ICOMOON_VERSION,
            true
        );

        // Only localize on the settings page - ACF field handles its own localization
        if ( 'settings_page_' . $this->page_slug === $hook ) {
            wp_localize_script( 'acf-icomoon-admin', 'acfIcoMoon', array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'acf_icomoon_nonce' ),
                'spriteUrl'   => get_option( 'acf_icomoon_sprite_url', '' ),
                'icons'       => $this->parser->get_saved_icons(),
                'strings'     => array(
                    'confirmClear' => __( 'Are you sure you want to remove all icons? This cannot be undone.', 'acf-icomoon' ),
                    'clearing'     => __( 'Clearing...', 'acf-icomoon' ),
                    'cleared'      => __( 'Icons cleared successfully.', 'acf-icomoon' ),
                    'error'        => __( 'An error occurred. Please try again.', 'acf-icomoon' ),
                    'search'       => __( 'Search icons...', 'acf-icomoon' ),
                    'noResults'    => __( 'No icons found.', 'acf-icomoon' ),
                ),
            ) );
        }
    }

    /**
     * Handle file upload
     *
     * @return void
     */
    public function handle_file_upload(): void {
        // Check if form was submitted
        if ( ! isset( $_POST['acf_icomoon_upload_nonce'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( wp_unslash( $_POST['acf_icomoon_upload_nonce'] ), 'acf_icomoon_upload' ) ) {
            add_settings_error(
                'acf_icomoon',
                'nonce_error',
                __( 'Security check failed. Please try again.', 'acf-icomoon' ),
                'error'
            );
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'acf_icomoon',
                'permission_error',
                __( 'You do not have permission to perform this action.', 'acf-icomoon' ),
                'error'
            );
            return;
        }

        $upload_dir = wp_upload_dir();
        $icomoon_dir = $upload_dir['basedir'] . '/acf-icomoon';

        // Create directory if it doesn't exist
        if ( ! file_exists( $icomoon_dir ) ) {
            wp_mkdir_p( $icomoon_dir );
        }

        // Handle selection.json upload
        if ( isset( $_FILES['icomoon_selection'] ) && $_FILES['icomoon_selection']['error'] === UPLOAD_ERR_OK ) {
            $this->process_selection_upload( $_FILES['icomoon_selection'], $icomoon_dir, $upload_dir );
        }

        // Handle SVG sprite upload
        if ( isset( $_FILES['icomoon_sprite'] ) && $_FILES['icomoon_sprite']['error'] === UPLOAD_ERR_OK ) {
            $this->process_sprite_upload( $_FILES['icomoon_sprite'], $icomoon_dir, $upload_dir );
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
                'acf_icomoon',
                'validation_error',
                $validation->get_error_message(),
                'error'
            );
            return;
        }

        // Move uploaded file
        $target_path = $target_dir . '/selection.json';
        
        if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
            add_settings_error(
                'acf_icomoon',
                'move_error',
                __( 'Failed to save the uploaded file.', 'acf-icomoon' ),
                'error'
            );
            return;
        }

        // Parse the file
        $icons = $this->parser->parse_selection_json( $target_path );
        
        if ( is_wp_error( $icons ) ) {
            add_settings_error(
                'acf_icomoon',
                'parse_error',
                $icons->get_error_message(),
                'error'
            );
            return;
        }

        // Save icons
        $this->parser->save_icons( $icons );

        // Generate and save sprite if no sprite is uploaded
        if ( empty( get_option( 'acf_icomoon_sprite_url' ) ) ) {
            $sprite = $this->parser->generate_sprite_from_selection( $target_path );
            
            if ( ! is_wp_error( $sprite ) ) {
                $sprite_path = $target_dir . '/sprite.svg';
                $write_result = file_put_contents( $sprite_path, $sprite );
                
                if ( false === $write_result ) {
                    add_settings_error(
                        'acf_icomoon',
                        'sprite_write_error',
                        __( 'Failed to write the SVG sprite file. Please check directory permissions.', 'acf-icomoon' ),
                        'warning'
                    );
                } else {
                    $sprite_url = str_replace( 
                        $upload_dir['basedir'], 
                        $upload_dir['baseurl'], 
                        $sprite_path 
                    );
                    
                    update_option( 'acf_icomoon_sprite_url', $sprite_url );
                    update_option( 'acf_icomoon_sprite_path', $sprite_path );
                }
            }
        }

        add_settings_error(
            'acf_icomoon',
            'upload_success',
            sprintf(
                /* translators: %d: number of icons */
                __( 'Successfully imported %d icons from selection.json.', 'acf-icomoon' ),
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
                'acf_icomoon',
                'validation_error',
                $validation->get_error_message(),
                'error'
            );
            return;
        }

        // Move uploaded file
        $target_path = $target_dir . '/sprite.svg';
        
        if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
            add_settings_error(
                'acf_icomoon',
                'move_error',
                __( 'Failed to save the uploaded file.', 'acf-icomoon' ),
                'error'
            );
            return;
        }

        // Parse the sprite
        $icons = $this->parser->parse_svg_sprite( $target_path );
        
        if ( is_wp_error( $icons ) ) {
            add_settings_error(
                'acf_icomoon',
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
        
        update_option( 'acf_icomoon_sprite_url', $sprite_url );
        update_option( 'acf_icomoon_sprite_path', $target_path );

        // Save icons if none exist or merge with existing
        $existing_icons = $this->parser->get_saved_icons();
        
        if ( empty( $existing_icons ) ) {
            $this->parser->save_icons( $icons );
        }

        add_settings_error(
            'acf_icomoon',
            'upload_success',
            sprintf(
                /* translators: %d: number of icons */
                __( 'Successfully imported SVG sprite with %d icons.', 'acf-icomoon' ),
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
        check_ajax_referer( 'acf_icomoon_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Permission denied.', 'acf-icomoon' ) 
            ) );
        }

        // Clear options
        $this->parser->clear_icons();

        // Delete files
        $upload_dir = wp_upload_dir();
        $icomoon_dir = $upload_dir['basedir'] . '/acf-icomoon';

        $selection_file = $icomoon_dir . '/selection.json';
        $sprite_file = $icomoon_dir . '/sprite.svg';

        if ( file_exists( $selection_file ) ) {
            wp_delete_file( $selection_file );
        }
        if ( file_exists( $sprite_file ) ) {
            wp_delete_file( $sprite_file );
        }

        wp_send_json_success( array( 
            'message' => __( 'All icons have been cleared.', 'acf-icomoon' ) 
        ) );
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        $icons = $this->parser->get_saved_icons();
        $sprite_url = get_option( 'acf_icomoon_sprite_url', '' );
        ?>
        <div class="wrap acf-icomoon-admin">
            <h1><?php esc_html_e( 'IcoMoon Icons', 'acf-icomoon' ); ?></h1>
            
            <?php settings_errors( 'acf_icomoon' ); ?>

            <div class="acf-icomoon-grid">
                <!-- Upload Section -->
                <div class="acf-icomoon-card">
                    <h2><?php esc_html_e( 'Upload IcoMoon Files', 'acf-icomoon' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Upload your IcoMoon selection.json file or SVG sprite to import icons.', 'acf-icomoon' ); ?>
                    </p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'acf_icomoon_upload', 'acf_icomoon_upload_nonce' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="icomoon_selection">
                                        <?php esc_html_e( 'selection.json', 'acf-icomoon' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="file" 
                                           name="icomoon_selection" 
                                           id="icomoon_selection" 
                                           accept=".json,application/json">
                                    <p class="description">
                                        <?php esc_html_e( 'Download this file from IcoMoon App after creating your icon set.', 'acf-icomoon' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="icomoon_sprite">
                                        <?php esc_html_e( 'SVG Sprite', 'acf-icomoon' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="file" 
                                           name="icomoon_sprite" 
                                           id="icomoon_sprite" 
                                           accept=".svg,image/svg+xml">
                                    <p class="description">
                                        <?php esc_html_e( 'The symbol-defs.svg file from your IcoMoon download.', 'acf-icomoon' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Upload & Import', 'acf-icomoon' ); ?>
                            </button>
                            
                            <?php if ( ! empty( $icons ) ) : ?>
                                <button type="button" 
                                        class="button button-secondary acf-icomoon-clear-btn" 
                                        id="acf-icomoon-clear">
                                    <?php esc_html_e( 'Clear All Icons', 'acf-icomoon' ); ?>
                                </button>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- Status Section -->
                <div class="acf-icomoon-card">
                    <h2><?php esc_html_e( 'Current Status', 'acf-icomoon' ); ?></h2>
                    
                    <table class="acf-icomoon-status-table">
                        <tr>
                            <td><?php esc_html_e( 'Icons Loaded:', 'acf-icomoon' ); ?></td>
                            <td><strong><?php echo count( $icons ); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Sprite URL:', 'acf-icomoon' ); ?></td>
                            <td>
                                <?php if ( ! empty( $sprite_url ) ) : ?>
                                    <code><?php echo esc_html( $sprite_url ); ?></code>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Not uploaded', 'acf-icomoon' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Icons Preview Section -->
            <?php if ( ! empty( $icons ) ) : ?>
                <div class="acf-icomoon-card acf-icomoon-preview-section">
                    <h2><?php esc_html_e( 'Icon Preview', 'acf-icomoon' ); ?></h2>
                    
                    <div class="acf-icomoon-search-wrap">
                        <input type="text" 
                               id="acf-icomoon-search" 
                               class="acf-icomoon-search" 
                               placeholder="<?php esc_attr_e( 'Search icons...', 'acf-icomoon' ); ?>">
                        <span class="acf-icomoon-count">
                            <?php 
                            printf( 
                                /* translators: %d: number of icons */
                                esc_html__( '%d icons', 'acf-icomoon' ), 
                                count( $icons ) 
                            ); 
                            ?>
                        </span>
                    </div>

                    <div class="acf-icomoon-icons-grid" id="acf-icomoon-icons-grid">
                        <?php foreach ( $icons as $icon ) : ?>
                            <div class="acf-icomoon-icon-item" 
                                 data-name="<?php echo esc_attr( $icon['name'] ); ?>"
                                 title="<?php echo esc_attr( $icon['name'] ); ?>">
                                <span class="acf-icomoon-icon-preview">
                                    <?php if ( ! empty( $sprite_url ) ) : ?>
                                        <svg class="icomoon-icon" aria-hidden="true">
                                            <use href="<?php echo esc_url( $sprite_url ); ?>#icon-<?php echo esc_attr( $icon['name'] ); ?>"></use>
                                        </svg>
                                    <?php else : ?>
                                        <span class="<?php echo esc_attr( $icon['class'] ?? 'icon-' . $icon['name'] ); ?>"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="acf-icomoon-icon-name">
                                    <?php echo esc_html( $icon['name'] ); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="acf-icomoon-no-results" id="acf-icomoon-no-results" style="display: none;">
                        <?php esc_html_e( 'No icons found matching your search.', 'acf-icomoon' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Usage Instructions -->
            <div class="acf-icomoon-card">
                <h2><?php esc_html_e( 'Usage Instructions', 'acf-icomoon' ); ?></h2>
                
                <h3><?php esc_html_e( 'In ACF Fields', 'acf-icomoon' ); ?></h3>
                <p><?php esc_html_e( 'Create a new field with type "IcoMoon Icon Picker" to allow users to select icons.', 'acf-icomoon' ); ?></p>

                <h3><?php esc_html_e( 'In Theme Templates', 'acf-icomoon' ); ?></h3>
                <pre><code>&lt;?php 
// Output an icon by name
icomoon_icon( 'home' );

// Get icon HTML as string
$icon = icomoon_get_icon( 'home', [
    'class' => 'my-custom-class',
    'width' => '24',
    'height' => '24'
] );

// Using with ACF
$icon_name = get_field( 'my_icon_field' );
if ( $icon_name ) {
    icomoon_icon( $icon_name );
}
?&gt;</code></pre>
            </div>
        </div>
        <?php
    }
}

