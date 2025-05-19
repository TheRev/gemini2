<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_Asset_Manager {

    public function __construct() {
        // Constructor can be used if dependencies are needed in the future
    }

    /**
     * Initialize WordPress hooks for asset management.
     */
    public function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_init', array( $this, 'add_editor_styles' ) );
    }

    /**
     * Enqueue styles and scripts for the frontend.
     *
     * Loads styles if the post content contains the shortcode or if it's a singular 'business' CPT.
     */
    public function enqueue_frontend_assets() {
        global $post;
        $load_styles = false;

        if ( is_singular() && isset($post) && is_a($post, 'WP_Post') ) {
            // Check for shortcode in content
            if ( has_shortcode( $post->post_content, 'gemini_review' ) || has_shortcode( $post->post_content, 'gemini_description' ) ) {
                $load_styles = true;
            }
            // Check if it's the 'business' CPT
            if ( get_post_type( $post ) === 'business' ) {
                $load_styles = true;
            }
        }

        // Allow themes/plugins to filter this condition
        $load_styles = apply_filters( 'gemini2_load_frontend_styles', $load_styles, $post );

        if ( $load_styles ) {
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_style(
                'gemini-review-styles', // Handle for the review styles
                GEMINI2_PLUGIN_URL . 'css/gemini-review-styles.css',
                array( 'dashicons' ), // Dependency
                GEMINI2_PLUGIN_VERSION . '.' . filemtime( GEMINI2_PLUGIN_DIR . 'css/gemini-review-styles.css' ) // Versioning for cache busting
            );
        }
    }

    /**
     * Add editor styles for the block editor (Gutenberg) and potentially classic editor.
     * This ensures that the review formatting looks similar in the editor.
     */
    public function add_editor_styles() {
        add_editor_style( GEMINI2_PLUGIN_URL . 'css/gemini-review-styles.css' );
        // If you need Dashicons in the editor specifically for these styles (e.g., for H3 icons),
        // you might need a separate small CSS to enqueue for the editor that loads Dashicons font,
        // or ensure Dashicons are available through another mechanism in the editor context.
        // For simplicity, add_editor_style often handles CSS well, but font loading can be tricky.
        // A common approach is to @import the Dashicons font CSS within your editor-specific CSS if needed,
        // or ensure your theme/other plugins make Dashicons available in the editor.
    }
}
?>