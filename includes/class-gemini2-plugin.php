<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_Plugin {

    public function __construct() {
        // Constructor can be used for initial setup if needed
    }

    /**
     * Initialize the plugin by loading and running its components.
     */
    public function run() {
        $this->load_dependencies();
        $this->initialize_components();
        // $this->add_kses_filters(); // Removed KSES filter call
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Autoloader should handle these
    }

    /**
     * Instantiate and initialize the plugin's components.
     */
    private function initialize_components() {
        $api_client = new Gemini2_API_Client();
        $content_formatter = new Gemini2_Content_Formatter();
        $asset_manager = new Gemini2_Asset_Manager();
        $asset_manager->init_hooks();

        // Admin functionalities
        if ( is_admin() ) {
            $admin = new Gemini2_Admin( $api_client, $content_formatter );
            $admin->init_hooks();

            $ajax_handler = new Gemini2_AJAX_Handler( $api_client, $content_formatter );
            $ajax_handler->init_hooks();
        }

        // Shortcode functionalities
        $shortcode_handler = new Gemini2_Shortcode_Handler( $content_formatter );
        $shortcode_handler->init_hooks();
    }

    // Removed allow_dashicons_in_kses() method and add_kses_filters()
}
?>