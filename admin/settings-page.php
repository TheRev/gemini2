<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add menu
add_action('admin_menu', function() {
    add_options_page(
        __( 'Gemini2 Settings', 'gemini2-business-lookup' ), // Translated
        __( 'Gemini2', 'gemini2-business-lookup' ),          // Translated
        'manage_options',
        'gemini2-settings',
        'gemini2_render_settings_page'
    );
});

// Render form
function gemini2_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Gemini2 API Settings & Content Configuration', 'gemini2-business-lookup' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gemini2_settings');
            do_settings_sections('gemini2-settings');
            submit_button( __( 'Save Changes', 'gemini2-business-lookup' ) ); // Translated button text
            ?>
        </form>
    </div>
    <?php
}

// Register setting
add_action('admin_init', function() {
    // API Key Setting
    register_setting('gemini2_settings', 'gemini2_api_key');

    // New CPT and Meta Field Settings
    register_setting('gemini2_settings', 'gemini2_cpt_slug');
    register_setting('gemini2_settings', 'gemini2_meta_field_name');
    register_setting('gemini2_settings', 'gemini2_meta_field_city');

    add_settings_section(
        'gemini2_main_section', 
        __( 'API Configuration', 'gemini2-business-lookup' ), // Translated
        null, 
        'gemini2-settings'
    );
    add_settings_field(
        'gemini2_api_key',
        __( 'Google Gemini API key', 'gemini2-business-lookup' ), // Translated
        function() {
            echo '<input type="text" name="gemini2_api_key" value="' . esc_attr( get_option('gemini2_api_key', '') ) . '" size="50" />';
            echo '<p class="description">' . esc_html__( 'Enter your Google Gemini API key.', 'gemini2-business-lookup' ) . '</p>'; // Translated
        },
        'gemini2-settings',
        'gemini2_main_section'
    );

    // Section for CPT and Meta Field Configuration
    add_settings_section(
        'gemini2_content_section',
        __( 'Content Configuration', 'gemini2-business-lookup' ), // Translated
        function() {
            echo '<p>' . esc_html__( 'Configure the Custom Post Type and meta fields used by Gemini2 to fetch business information.', 'gemini2-business-lookup' ) . '</p>'; // Translated
        },
        'gemini2-settings'
    );

    add_settings_field(
        'gemini2_cpt_slug',
        __( 'Target Custom Post Type Slug', 'gemini2-business-lookup' ), // Translated
        function() {
            echo '<input type="text" name="gemini2_cpt_slug" value="' . esc_attr( gemini2_get_option('gemini2_cpt_slug', 'business') ) . '" size="30" />'; // Used gemini2_get_option for consistency
            echo '<p class="description">' . 
                sprintf(
                    /* translators: %s: default CPT slug */
                    esc_html__( 'Enter the slug of the Custom Post Type you use for businesses (e.g., "business", "listing"). Default: %s.', 'gemini2-business-lookup' ),
                    '<code>business</code>'
                ) . '</p>'; // Translated
        },
        'gemini2-settings',
        'gemini2_content_section'
    );

    add_settings_field(
        'gemini2_meta_field_name',
        __( 'Business Name Meta Field Key', 'gemini2-business-lookup' ), // Translated
        function() {
            echo '<input type="text" name="gemini2_meta_field_name" value="' . esc_attr( gemini2_get_option('gemini2_meta_field_name', '_gpd_display_name') ) . '" size="30" />'; // Used gemini2_get_option
            echo '<p class="description">' . 
                sprintf(
                    /* translators: %s: default meta key */
                    esc_html__( 'Enter the meta field key used to store the business name. Default: %s.', 'gemini2-business-lookup' ),
                    '<code>_gpd_display_name</code>'
                ) . '</p>'; // Translated
        },
        'gemini2-settings',
        'gemini2_content_section'
    );

    add_settings_field(
        'gemini2_meta_field_city',
        __( 'Business City/Location Meta Field Key', 'gemini2-business-lookup' ), // Translated
        function() {
            echo '<input type="text" name="gemini2_meta_field_city" value="' . esc_attr( gemini2_get_option('gemini2_meta_field_city', '_gpd_locality') ) . '" size="30" />'; // Used gemini2_get_option
            echo '<p class="description">' . 
                sprintf(
                    /* translators: %s: default meta key */
                    esc_html__( 'Enter the meta field key used to store the business city or location. Default: %s.', 'gemini2-business-lookup' ),
                    '<code>_gpd_locality</code>'
                ) . '</p>'; // Translated
        },
        'gemini2-settings',
        'gemini2_content_section'
    );
});
