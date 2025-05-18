<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add menu
add_action('admin_menu', function() {
    add_options_page(
        'Gemini2 Settings',
        'Gemini2',
        'manage_options',
        'gemini2-settings',
        'gemini2_render_settings_page'
    );
});

// Render form
function gemini2_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Gemini2 API Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gemini2_settings');
            do_settings_sections('gemini2-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register setting
add_action('admin_init', function() {
    register_setting('gemini2_settings', 'gemini2_api_key');
    add_settings_section('gemini2_main_section', '', null, 'gemini2-settings');
    add_settings_field(
        'gemini2_api_key',
        'Google Gemini API key',
        function() {
            echo '<input type="text" name="gemini2_api_key" value="' . esc_attr( get_option('gemini2_api_key', '') ) . '" size="50" />';
        },
        'gemini2-settings',
        'gemini2_main_section'
    );
});
