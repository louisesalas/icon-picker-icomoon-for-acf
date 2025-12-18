<?php
/**
 * ACF IcoMoon Field Type
 *
 * Custom ACF field type for selecting IcoMoon icons.
 *
 * @package IPIACF
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IPIACF_Field
 *
 * Registers and handles the IcoMoon Icon Picker field type for ACF.
 */
class IPIACF_Field extends acf_field {

    /**
     * Constructor
     */
    public function __construct() {
        // Field type settings
        $this->name     = 'icomoon_icon';
        $this->label    = __( 'IcoMoon Icon Picker', 'icon-picker-icomoon-for-acf' );
        $this->category = 'content';
        $this->defaults = array(
            'return_format' => 'name',
            'allow_null'    => 0,
            'multiple'      => 0,
        );

        parent::__construct();
    }

    /**
     * Render field settings in ACF field group editor
     *
     * @param array $field The field settings array
     * @return void
     */
    public function render_field_settings( $field ): void {
        // Return Format
        acf_render_field_setting( $field, array(
            'label'        => __( 'Return Format', 'icon-picker-icomoon-for-acf' ),
            'instructions' => __( 'Specify the format of the returned value.', 'icon-picker-icomoon-for-acf' ),
            'type'         => 'radio',
            'name'         => 'return_format',
            'layout'       => 'horizontal',
            'choices'      => array(
                'name'     => __( 'Icon Name', 'icon-picker-icomoon-for-acf' ),
                'svg'      => __( 'SVG Use Tag', 'icon-picker-icomoon-for-acf' ),
                'class'    => __( 'CSS Class', 'icon-picker-icomoon-for-acf' ),
                'array'    => __( 'Icon Array', 'icon-picker-icomoon-for-acf' ),
            ),
        ) );

        // Allow Null
        acf_render_field_setting( $field, array(
            'label'        => __( 'Allow Null?', 'icon-picker-icomoon-for-acf' ),
            'instructions' => '',
            'name'         => 'allow_null',
            'type'         => 'true_false',
            'ui'           => 1,
        ) );

        // Multiple Selection
        acf_render_field_setting( $field, array(
            'label'        => __( 'Select Multiple?', 'icon-picker-icomoon-for-acf' ),
            'instructions' => __( 'Allow selection of multiple icons.', 'icon-picker-icomoon-for-acf' ),
            'name'         => 'multiple',
            'type'         => 'true_false',
            'ui'           => 1,
        ) );
    }

    /**
     * Render the field input
     *
     * @param array $field The field settings and value
     * @return void
     */
    public function render_field( $field ): void {
        $icons      = get_option( 'ipiacf_icons', array() );
        $sprite_url = get_option( 'ipiacf_sprite_url', '' );
        $value      = $field['value'];
        $multiple   = ! empty( $field['multiple'] );
        
        // Ensure value is an array for multiple selection
        if ( $multiple && ! is_array( $value ) ) {
            $value = $value ? array( $value ) : array();
        }

        // Wrap the field
        ?>
        <div class="ipiacf-field-wrap" 
             data-multiple="<?php echo $multiple ? '1' : '0'; ?>"
             data-sprite-url="<?php echo esc_url( $sprite_url ); ?>">
            
            <!-- Hidden input(s) for value storage -->
            <?php if ( $multiple ) : ?>
                <input type="hidden" 
                       name="<?php echo esc_attr( $field['name'] ); ?>" 
                       value="">
                <?php if ( is_array( $value ) ) : ?>
                    <?php foreach ( $value as $v ) : ?>
                        <input type="hidden" 
                               name="<?php echo esc_attr( $field['name'] ); ?>[]" 
                               value="<?php echo esc_attr( $v ); ?>"
                               class="ipiacf-value">
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else : ?>
                <input type="hidden" 
                       name="<?php echo esc_attr( $field['name'] ); ?>" 
                       value="<?php echo esc_attr( $value ); ?>"
                       class="ipiacf-value">
            <?php endif; ?>

            <!-- Selected Icons Preview -->
            <div class="ipiacf-selected">
                <div class="ipiacf-selected-icons">
                    <?php 
                    $selected_icons = $multiple ? (array) $value : ( $value ? array( $value ) : array() );
                    foreach ( $selected_icons as $icon_name ) :
                        if ( empty( $icon_name ) ) continue;
                    ?>
                        <span class="ipiacf-selected-item" data-icon="<?php echo esc_attr( $icon_name ); ?>">
                            <?php if ( ! empty( $sprite_url ) ) : ?>
                                <svg class="icomoon-icon" aria-hidden="true">
                                    <use href="<?php echo esc_url( $sprite_url ); ?>#icon-<?php echo esc_attr( $icon_name ); ?>"></use>
                                </svg>
                            <?php endif; ?>
                            <span class="ipiacf-selected-name"><?php echo esc_html( $icon_name ); ?></span>
                            <button type="button" class="ipiacf-remove" title="<?php esc_attr_e( 'Remove', 'icon-picker-icomoon-for-acf' ); ?>">&times;</button>
                        </span>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button ipiacf-toggle">
                    <?php echo empty( $selected_icons ) || empty( $selected_icons[0] ) 
                        ? esc_html__( 'Select Icon', 'icon-picker-icomoon-for-acf' ) 
                        : esc_html__( 'Change Icon', 'icon-picker-icomoon-for-acf' ); ?>
                </button>
            </div>

            <!-- Icon Picker Modal -->
            <div class="ipiacf-picker" style="display: none;">
                <?php if ( empty( $icons ) ) : ?>
                    <div class="ipiacf-no-icons">
                        <p><?php esc_html_e( 'No icons available.', 'icon-picker-icomoon-for-acf' ); ?></p>
                        <?php if ( current_user_can( 'manage_options' ) ) : ?>
                            <p>
                                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ipiacf-icomoon-icons' ) ); ?>">
                                    <?php esc_html_e( 'Upload IcoMoon icons', 'icon-picker-icomoon-for-acf' ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <!-- Search -->
                    <div class="ipiacf-picker-search">
                        <input type="text" 
                               class="ipiacf-search-input" 
                               placeholder="<?php esc_attr_e( 'Search icons...', 'icon-picker-icomoon-for-acf' ); ?>">
                    </div>

                    <!-- Icons Grid -->
                    <div class="ipiacf-picker-grid">
                        <?php foreach ( $icons as $icon ) : 
                            $is_selected = in_array( $icon['name'], $selected_icons, true );
                        ?>
                            <div class="ipiacf-picker-item <?php echo $is_selected ? 'is-selected' : ''; ?>" 
                                 data-icon="<?php echo esc_attr( $icon['name'] ); ?>"
                                 title="<?php echo esc_attr( $icon['name'] ); ?>">
                                <?php if ( ! empty( $sprite_url ) ) : ?>
                                    <svg class="icomoon-icon" aria-hidden="true">
                                        <use href="<?php echo esc_url( $sprite_url ); ?>#icon-<?php echo esc_attr( $icon['name'] ); ?>"></use>
                                    </svg>
                                <?php else : ?>
                                    <span class="<?php echo esc_attr( $icon['class'] ?? 'icon-' . $icon['name'] ); ?>"></span>
                                <?php endif; ?>
                                <span class="ipiacf-picker-name"><?php echo esc_html( $icon['name'] ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="ipiacf-picker-no-results" style="display: none;">
                        <?php esc_html_e( 'No icons found.', 'icon-picker-icomoon-for-acf' ); ?>
                    </p>

                    <!-- Actions -->
                    <div class="ipiacf-picker-actions">
                        <?php if ( ! empty( $field['allow_null'] ) ) : ?>
                            <button type="button" class="button ipiacf-clear-selection">
                                <?php esc_html_e( 'Clear Selection', 'icon-picker-icomoon-for-acf' ); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="button button-primary ipiacf-close">
                            <?php esc_html_e( 'Done', 'icon-picker-icomoon-for-acf' ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Format value for frontend use
     *
     * @param mixed $value   The field value
     * @param int   $post_id The post ID
     * @param array $field   The field settings
     * @return mixed Formatted value
     */
    public function format_value( $value, $post_id, $field ) {
        if ( empty( $value ) ) {
            return $value;
        }

        $sprite_url = get_option( 'ipiacf_sprite_url', '' );
        $multiple   = ! empty( $field['multiple'] );
        $format     = $field['return_format'] ?? 'name';

        // Handle multiple values
        if ( $multiple && is_array( $value ) ) {
            return array_map( function( $v ) use ( $format, $sprite_url ) {
                return $this->format_single_value( $v, $format, $sprite_url );
            }, $value );
        }

        return $this->format_single_value( $value, $format, $sprite_url );
    }

    /**
     * Format a single icon value
     *
     * @param string $value      The icon name
     * @param string $format     The return format
     * @param string $sprite_url The sprite URL
     * @return mixed Formatted value
     */
    private function format_single_value( string $value, string $format, string $sprite_url ) {
        switch ( $format ) {
            case 'svg':
                if ( ! empty( $sprite_url ) ) {
                    return sprintf(
                        '<svg class="icomoon-icon icon-%s" aria-hidden="true"><use href="%s#icon-%s"></use></svg>',
                        esc_attr( $value ),
                        esc_url( $sprite_url ),
                        esc_attr( $value )
                    );
                }
                return '';

            case 'class':
                return 'icon-' . $value;

            case 'array':
                return array(
                    'name'       => $value,
                    'class'      => 'icon-' . $value,
                    'sprite_url' => $sprite_url,
                    'svg'        => ! empty( $sprite_url ) 
                        ? sprintf(
                            '<svg class="icomoon-icon icon-%s" aria-hidden="true"><use href="%s#icon-%s"></use></svg>',
                            esc_attr( $value ),
                            esc_url( $sprite_url ),
                            esc_attr( $value )
                        )
                        : '',
                );

            case 'name':
            default:
                return $value;
        }
    }

    /**
     * Validate value before save
     *
     * @param bool  $valid   Whether the value is valid
     * @param mixed $value   The field value
     * @param array $field   The field settings
     * @param string $input  The input name
     * @return bool|string True if valid, error message if not
     */
    public function validate_value( $valid, $value, $field, $input ) {
        // Check required
        if ( empty( $field['allow_null'] ) && empty( $value ) ) {
            return __( 'Please select an icon.', 'icon-picker-icomoon-for-acf' );
        }

        return $valid;
    }

    /**
     * Track if scripts have been localized
     *
     * @var bool
     */
    private static bool $scripts_localized = false;

    /**
     * This action is called in the admin_enqueue_scripts action on the edit screen
     * where your field is created.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts(): void {
        // Scripts and styles are already enqueued by the admin class
        // but we ensure they're available on all ACF pages
        
        wp_enqueue_style(
            'ipiacf-admin',
            IPIACF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IPIACF_VERSION
        );

        wp_enqueue_script(
            'ipiacf-admin',
            IPIACF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'acf-input' ),
            IPIACF_VERSION,
            true
        );

        // Only localize script data once to avoid duplicate data
        if ( ! self::$scripts_localized ) {
            wp_localize_script( 'ipiacf-admin', 'ipiacfData', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'ipiacf_nonce' ),
                'spriteUrl' => get_option( 'ipiacf_sprite_url', '' ),
                'icons'     => get_option( 'ipiacf_icons', array() ),
                'strings'   => array(
                    'selectIcon'   => __( 'Select Icon', 'icon-picker-icomoon-for-acf' ),
                    'changeIcon'   => __( 'Change Icon', 'icon-picker-icomoon-for-acf' ),
                    'confirmClear' => __( 'Are you sure you want to remove all icons? This cannot be undone.', 'icon-picker-icomoon-for-acf' ),
                    'clearing'     => __( 'Clearing...', 'icon-picker-icomoon-for-acf' ),
                    'cleared'      => __( 'Icons cleared successfully.', 'icon-picker-icomoon-for-acf' ),
                    'error'        => __( 'An error occurred. Please try again.', 'icon-picker-icomoon-for-acf' ),
                    'search'       => __( 'Search icons...', 'icon-picker-icomoon-for-acf' ),
                    'noResults'    => __( 'No icons found.', 'icon-picker-icomoon-for-acf' ),
                ),
            ) );
            self::$scripts_localized = true;
        }
    }
}
