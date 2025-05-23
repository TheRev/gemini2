<?php
/*
Plugin Name: Gemini2 AI Business Lookup (OOP)
Description: Generate rich AI business reviews for dive operations using Google Gemini API.
Version: 3.0.0
Author: TheRev
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'GEMINI2_PLUGIN_VERSION', '3.0.0' );
define( 'GEMINI2_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEMINI2_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Dependency check: Deactivate and notify if Google Places Directory is not active.
 */
add_action( 'admin_init', function() {
    if ( ! is_plugin_active( 'google-places-directory/google-places-directory.php' ) ) {
        // Deactivate Gemini2 if dependency missing
        deactivate_plugins( plugin_basename( __FILE__ ) );
        // Add admin notice
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Gemini2 AI Business Lookup requires the Google Places Directory plugin to be active. This plugin has been deactivated.', 'gemini2' );
            echo '</p></div>';
        });
    }
});

/**
 * Basic autoloader, or you can use a more robust one like Composer's
 */
spl_autoload_register( 'gemini2_autoloader' );
function gemini2_autoloader( $class_name ) {
    if ( false === strpos( $class_name, 'Gemini2_' ) ) {
        return;
    }
    $file_parts = explode( '_', $class_name );
    unset( $file_parts[0] ); // Remove "Gemini2"
    $file_name = 'class-gemini2-' . strtolower( implode( '-', $file_parts ) ) . '.php';
    $file_path = GEMINI2_PLUGIN_DIR . 'includes/' . $file_name;
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
}

/**
 * Initializes the plugin.
 */
function gemini2_init() {
    // Instantiate the main plugin class
    $plugin = new Gemini2_Plugin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'gemini2_init' );

?>
