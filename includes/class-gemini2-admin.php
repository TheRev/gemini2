<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_Admin {

    private $api_client;
    private $content_formatter;

    public function __construct( Gemini2_API_Client $api_client, Gemini2_Content_Formatter $content_formatter ) {
        $this->api_client = $api_client; // Though not directly used in this class yet, good for future
        $this->content_formatter = $content_formatter;
    }

    /**
     * Initialize WordPress hooks for admin functionalities.
     */
    public function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add the plugin's options page to the admin menu.
     */
    public function add_options_page() {
        add_options_page(
            'Gemini2 Settings',
            'Gemini2',
            'manage_options',
            'gemini2-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the settings page HTML.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Gemini2 API Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'gemini2_settings_group' ); // Changed group name
                do_settings_sections( 'gemini2-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register plugin settings, sections, and fields.
     */
    public function register_settings() {
        register_setting( 'gemini2_settings_group', 'gemini2_api_key' ); // Group name matches settings_fields

        add_settings_section(
            'gemini2_main_section',
            '', // Title (optional)
            null, // Callback (optional)
            'gemini2-settings' // Page slug
        );

        add_settings_field(
            'gemini2_api_key',
            'Google Gemini API Key',
            array( $this, 'render_api_key_field' ),
            'gemini2-settings', // Page slug
            'gemini2_main_section' // Section ID
        );
    }

    /**
     * Render the API key input field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'gemini2_api_key', '' );
        echo '<input type="text" name="gemini2_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
    }

    /**
     * Add meta boxes to the 'business' custom post type.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gemini_meta_box',
            'Gemini AI Business Description',
            array( $this, 'render_meta_box' ),
            'business', // Target CPT
            'normal',
            'high'
        );
    }

    /**
     * Render the content of the meta box.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'gemini_meta_box_action', 'gemini_meta_box_nonce' ); // Action and nonce name updated

        $last_searched_time = get_post_meta( $post->ID, '_gemini_last_searched', true );
        $raw_results_text   = get_post_meta( $post->ID, '_gemini_last_results', true );
        ?>
        <div class="gemini-container" style="padding-bottom:10px;">
            <div style="margin-bottom:15px;">
                <button id="gemini-trigger" class="button button-primary button-large">
                    Generate Business Description with AI
                </button>
                <span id="gemini-status-message" style="margin-left: 10px;"></span>
            </div>

            <?php if ( $raw_results_text ) : ?>
                <div style="margin-top:15px;">
                    <h4>Last Raw AI Output:</h4>
                    <div style="padding:10px;background:#fff;border:1px solid #eee;max-height:150px;overflow-y:auto;font-family:monospace;font-size:12px;white-space:pre-wrap;margin-bottom:10px;"><?php
                        echo '<strong>--- Raw AI Text Start ---</strong><br>';
                        echo esc_html( $raw_results_text );
                        echo '<br><strong>--- Raw AI Text End ---</strong>';
                    ?></div>
                    <h4>Formatted HTML (for editor insertion):</h4>
                    <div style="padding:10px;background:#f9f9f9;border:1px solid #eee;max-height:200px;overflow-y:auto;font-family:monospace;font-size:12px;white-space:pre-wrap;"><?php
                        // Use the content formatter class
                        $unwrapped_html = $this->content_formatter->format( $raw_results_text );
                        $wrapped_html_for_display = '<div class="gemini-review">' . "\n" . $unwrapped_html . "\n" . '</div>';
                        echo esc_html( $wrapped_html_for_display );
                    ?></div>
                </div>
            <?php endif; ?>

            <?php if ( $last_searched_time ) : ?>
                <p style="font-size:12px;color:#555;margin-top:8px;">Last generated: <?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $last_searched_time ) ) ); //Ensure consistent formatting ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin-specific scripts and styles.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        global $post;
        $current_screen = get_current_screen();

        // Only load on the 'business' CPT edit screen
        if ( $current_screen && $current_screen->post_type === 'business' && $current_screen->base === 'post' ) {
            wp_enqueue_script(
                'gemini-admin-js', // Renamed handle
                GEMINI2_PLUGIN_URL . 'js/gemini-admin.js',
                array( 'jquery', 'wp-blocks', 'wp-data', 'wp-edit-post' ),
                GEMINI2_PLUGIN_VERSION . '.' . time(), // Cache busting
                true
            );

            $post_id_for_js = ( isset( $post ) && is_object( $post ) ) ? $post->ID : ( isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0 );

            wp_localize_script( 'gemini-admin-js', 'geminiAdminAjax', array( // Renamed object
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'gemini_ajax_nonce' ), // New nonce action
                'post_id'  => $post_id_for_js,
            ) );

            wp_enqueue_style( 'dashicons' );
        }
    }
}
?>
