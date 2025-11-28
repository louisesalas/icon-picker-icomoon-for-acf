<?php
/**
 * ACF IcoMoon Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database and file system.
 *
 * @package ACF_IcoMoon_Integration
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options from the database
 */
delete_option( 'acf_icomoon_icons' );
delete_option( 'acf_icomoon_sprite_url' );
delete_option( 'acf_icomoon_sprite_path' );

/**
 * Delete uploaded files
 */
$upload_dir = wp_upload_dir();
$icomoon_dir = $upload_dir['basedir'] . '/acf-icomoon';

if ( is_dir( $icomoon_dir ) ) {
    // Delete all files in the directory
    $files = array(
        $icomoon_dir . '/selection.json',
        $icomoon_dir . '/sprite.svg',
    );

    foreach ( $files as $file ) {
        if ( file_exists( $file ) ) {
            wp_delete_file( $file );
        }
    }

    // Remove the directory if empty
    if ( is_dir( $icomoon_dir ) && count( scandir( $icomoon_dir ) ) === 2 ) {
        rmdir( $icomoon_dir );
    }
}

/**
 * For multisite, delete options from all sites
 */
if ( is_multisite() ) {
    $sites = get_sites( array( 'fields' => 'ids' ) );
    
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        
        delete_option( 'acf_icomoon_icons' );
        delete_option( 'acf_icomoon_sprite_url' );
        delete_option( 'acf_icomoon_sprite_path' );
        
        // Clean up uploaded files for each site
        $site_upload_dir = wp_upload_dir();
        $site_icomoon_dir = $site_upload_dir['basedir'] . '/acf-icomoon';
        
        if ( is_dir( $site_icomoon_dir ) ) {
            $site_files = array(
                $site_icomoon_dir . '/selection.json',
                $site_icomoon_dir . '/sprite.svg',
            );

            foreach ( $site_files as $file ) {
                if ( file_exists( $file ) ) {
                    wp_delete_file( $file );
                }
            }

            if ( is_dir( $site_icomoon_dir ) && count( scandir( $site_icomoon_dir ) ) === 2 ) {
                rmdir( $site_icomoon_dir );
            }
        }
        
        restore_current_blog();
    }
}

