<?php
/**
 * Plugin Name:       WP SEO
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Allows adding meta descriptions to posts and pages from the editor.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Unlock The Move FZ-LLC
 * Author URI:        https://unlockthemove.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-seo-meta-descriptions
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin path and URL constants
define( 'WPSMD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSMD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include class files
require_once WPSMD_PLUGIN_PATH . 'includes/admin/class-wpsmd-admin.php';
require_once WPSMD_PLUGIN_PATH . 'includes/admin/class-wpsmd-settings.php'; // Added settings page
require_once WPSMD_PLUGIN_PATH . 'includes/frontend/class-wpsmd-frontend.php';

/**
 * Initialize the plugin.
 */
function wpsmd_load_textdomain() {
    load_plugin_textdomain(
        'wp-seo-meta-descriptions',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'wpsmd_load_textdomain' );

function wpsmd_init() {
    if ( is_admin() ) {
        new WPSMD_Admin();
    } 
    new WPSMD_Frontend(); // Frontend class should always be instantiated for wp_head hook
}
add_action( 'plugins_loaded', 'wpsmd_init', 11 ); // Run after textdomain is loaded

// TODO: Add function to enqueue admin scripts and styles for AI functionality
// TODO: Add AJAX handlers for AI functionality

?>