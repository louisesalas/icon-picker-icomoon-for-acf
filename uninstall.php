<?php
/**
 * IPIACF Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database and file system.
 *
 * @package IPIACF
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options from the database
 */
delete_option( 'ipiacf_icons' );
delete_option( 'ipiacf_sprite_url' );
delete_option( 'ipiacf_sprite_path' );

/**
 * Delete uploaded files
 */
// Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
global $wp_filesystem;

$ipiacf_upload_dir = wp_upload_dir();
$ipiacf_dir = $ipiacf_upload_dir['basedir'] . '/ipiacf-icomoon';

if ( $wp_filesystem->is_dir( $ipiacf_dir ) ) {
    // Delete all files in the directory
    $ipiacf_files = array(
        $ipiacf_dir . '/selection.json',
        $ipiacf_dir . '/sprite.svg',
    );

    foreach ( $ipiacf_files as $ipiacf_file ) {
        if ( $wp_filesystem->exists( $ipiacf_file ) ) {
            $wp_filesystem->delete( $ipiacf_file );
        }
    }

    // Remove the directory
    $wp_filesystem->rmdir( $ipiacf_dir );
}

/**
 * For multisite, delete options from all sites
 */
if ( is_multisite() ) {
    $ipiacf_sites = get_sites( array( 'fields' => 'ids' ) );
    
    foreach ( $ipiacf_sites as $ipiacf_site_id ) {
        switch_to_blog( $ipiacf_site_id );
        
        delete_option( 'ipiacf_icons' );
        delete_option( 'ipiacf_sprite_url' );
        delete_option( 'ipiacf_sprite_path' );
        
        // Clean up uploaded files for each site
        $ipiacf_site_upload_dir = wp_upload_dir();
        $ipiacf_site_icomoon_dir = $ipiacf_site_upload_dir['basedir'] . '/ipiacf-icomoon';
        
        if ( $wp_filesystem->is_dir( $ipiacf_site_icomoon_dir ) ) {
            $ipiacf_site_files = array(
                $ipiacf_site_icomoon_dir . '/selection.json',
                $ipiacf_site_icomoon_dir . '/sprite.svg',
            );

            foreach ( $ipiacf_site_files as $ipiacf_site_file ) {
                if ( $wp_filesystem->exists( $ipiacf_site_file ) ) {
                    $wp_filesystem->delete( $ipiacf_site_file );
                }
            }

            // Remove the directory
            $wp_filesystem->rmdir( $ipiacf_site_icomoon_dir );
        }
        
        restore_current_blog();
    }
}
