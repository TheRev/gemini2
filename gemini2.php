<?php
/*
Plugin Name: Gemini2 Business Lookup
Description: Generate rich AI business reviews for dive operations using Google Gemini API.
Version: 2.5.2 
Author: TheRev
Text Domain: gemini2-business-lookup
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) Settings page for API key:
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Helper function to get plugin options with defaults
function gemini2_get_option( $key, $default = '' ) {
    $defaults = [
        'gemini2_cpt_slug'        => 'business',
        'gemini2_meta_field_name' => '_gpd_display_name',
        'gemini2_meta_field_city' => '_gpd_locality',
        'gemini2_api_key'         => '',
    ];
    $saved_value = get_option( $key );
    if ( $saved_value !== false ) { 
        return $saved_value;
    }
    return isset($defaults[$key]) ? $defaults[$key] : $default;
}

// Admin notice for CPT configuration
add_action( 'admin_notices', 'gemini2_check_cpt_configuration_notice' );
function gemini2_check_cpt_configuration_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $configured_cpt_slug = gemini2_get_option('gemini2_cpt_slug');
    if ( ! post_type_exists( $configured_cpt_slug ) ) {
        $settings_url = admin_url( 'options-general.php?page=gemini2-settings' );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: 1: Strong tag start, 2: Plugin name, 3: Strong tag end, 4: Configured CPT slug, 5: Link to settings page */
                    esc_html__( '%1$s%2$s Plugin Warning:%3$s The configured Custom Post Type %4$s does not appear to be registered. Please %5$s or ensure the CPT is active.', 'gemini2-business-lookup' ),
                    '<strong>',
                    'Gemini2',
                    '</strong>',
                    '<code>' . esc_html( $configured_cpt_slug ) . '</code>',
                    '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'check your Gemini2 settings', 'gemini2-business-lookup' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}

// Function to add editor styles
function gemini_add_editor_styles() {
    add_editor_style( plugins_url( 'css/gemini-review-styles.css', __FILE__ ) );
}
add_action( 'admin_init', 'gemini_add_editor_styles' );

// Helper function to format raw AI content to HTML ( 그대로 )
function gemini_format_review_content_to_html( $raw_content ) {
    if ( empty( $raw_content ) ) {
        return '<!-- ' . esc_html__( 'Raw content was empty', 'gemini2-business-lookup' ) . ' -->';
    }

    $lines = explode( "\n", $raw_content );
    $html_output = '<!-- ' . esc_html__( 'Starting gemini_format_review_content_to_html', 'gemini2-business-lookup' ) . ' -->';
    $in_list_type = null; 
    $current_section_key = null; 

    $section_headers_map = [
        'Introduction' => ['icon' => '📘', 'title' => __( 'Introduction', 'gemini2-business-lookup' )],
        'Product Range' => ['icon' => '🛍️', 'title' => __( 'Product Range', 'gemini2-business-lookup' )],
        'Pricing' => ['icon' => '💰', 'title' => __( 'Pricing', 'gemini2-business-lookup' )],
        'Customer Service' => ['icon' => '🤝', 'title' => __( 'Customer Service', 'gemini2-business-lookup' )],
        'Training & Certification' => ['icon' => '🎓', 'title' => __( 'Training & Certification', 'gemini2-business-lookup' )],
        'Facilities & Atmosphere' => ['icon' => '🏢', 'title' => __( 'Facilities & Atmosphere', 'gemini2-business-lookup' )],
        'Servicing & Repairs' => ['icon' => '🔧', 'title' => __( 'Servicing & Repairs', 'gemini2-business-lookup' )],
        'Dive Trips & Events' => ['icon' => '🌴', 'title' => __( 'Dive Trips & Events', 'gemini2-business-lookup' )],
        'Accessibility' => ['icon' => '♿', 'title' => __( 'Accessibility', 'gemini2-business-lookup' )],
        'Summary' => ['icon' => '⭐', 'title' => __( 'Summary', 'gemini2-business-lookup' )],
        'Pros & Cons' => ['icon' => '⚖️', 'title' => __( 'Pros & Cons', 'gemini2-business-lookup' )] 
    ];
    
    $first_content_line_processed = false; 

    foreach ( $lines as $line_index => $line ) {
        $trimmed_line = trim( $line );
        if ( empty( $trimmed_line ) ) {
            continue;
        }
        
        if (!$first_content_line_processed && 
            (strpos($trimmed_line, '# ') === 0 || strpos($trimmed_line, '## ') === 0) ) {
            $potential_main_title_text = trim(preg_replace('/^#+\s*/', '', $trimmed_line));
            $cleaned_potential_title = preg_replace('/^(?:\d+\.\s*)?/', '', $potential_main_title_text);
            $cleaned_potential_title = preg_replace('/^\*\*(.*?)\*\*$/', '$1', $cleaned_potential_title);
            $cleaned_potential_title = trim($cleaned_potential_title);

            $is_actually_section_header = false;
            if (isset($section_headers_map[$cleaned_potential_title])) {
                 $is_actually_section_header = true;
            }
            if (!$is_actually_section_header) {
                $first_content_line_processed = true; 
                continue; 
            }
        }
        $first_content_line_processed = true;

        $cleaned_for_header_check = $trimmed_line;
        $cleaned_for_header_check = preg_replace('/^(?:##\s*|#\s*)?/', '', $cleaned_for_header_check);
        $cleaned_for_header_check = preg_replace('/^(?:\d+\.\s*)?/', '', $cleaned_for_header_check);
        $cleaned_for_header_check = preg_replace('/^\*\*(.*?)\*\*$/', '$1', $cleaned_for_header_check);
        $cleaned_for_header_check = trim($cleaned_for_header_check);
        
        $is_header_match = false;
        $header_details_to_display = null;

        if (!empty($cleaned_for_header_check)) {
            if (isset($section_headers_map[$cleaned_for_header_check])) {
                $header_details_to_display = $section_headers_map[$cleaned_for_header_check];
                $is_header_match = true;
                $current_section_key = $cleaned_for_header_check; 
            }
        }

        if ( $is_header_match && $header_details_to_display ) {
            if ( $in_list_type ) {
                $html_output .= "</ul>\n";
                $in_list_type = null;
            }
            $icon_span = $header_details_to_display['icon'] ? esc_html($header_details_to_display['icon']) . ' ' : '';
            $html_output .= '<h3>' . $icon_span . esc_html( $header_details_to_display['title'] ) . "</h3>\n";
        } 
        elseif ($current_section_key === 'Pros & Cons' && strpos($trimmed_line, '✔️ ') === 0) {
            if ($in_list_type !== 'pros') {
                if ($in_list_type) $html_output .= "</ul>\n";
                $html_output .= '<ul class="gemini-pros-list">' . "\n";
                $in_list_type = 'pros';
            }
            $item_content = substr($trimmed_line, strlen('✔️ '));
            $item_content = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $item_content);
            $html_output .= '<li class="gemini-pro-item">' . wp_kses_post($item_content) . "</li>\n";
        }
        elseif ($current_section_key === 'Pros & Cons' && strpos($trimmed_line, '❌ ') === 0) {
            if ($in_list_type !== 'cons') {
                if ($in_list_type) $html_output .= "</ul>\n"; 
                $html_output .= '<ul class="gemini-cons-list">' . "\n";
                $in_list_type = 'cons';
            }
            $item_content = substr($trimmed_line, strlen('❌ '));
            $item_content = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $item_content);
            $html_output .= '<li class="gemini-con-item">' . wp_kses_post($item_content) . "</li>\n";
        }
        elseif ( strpos( $trimmed_line, '-' ) === 0 || strpos( $trimmed_line, '*' ) === 0 ) {
            if ($in_list_type === 'pros' || $in_list_type === 'cons') {
                 $html_output .= "</ul>\n";
                 $in_list_type = null; 
            }
            if ( $in_list_type !== 'generic' ) {
                $html_output .= '<ul class="gemini-generic-list">' . "\n"; 
                $in_list_type = 'generic';
            }
            $list_item_content = ltrim( $trimmed_line, '-* ' );
            $list_item_content = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $list_item_content);
            $html_output .= '<li>' . wp_kses_post($list_item_content) . "</li>\n"; 
        }
        else {
            if ( $in_list_type ) {
                $html_output .= "</ul>\n";
                $in_list_type = null;
            }
            $paragraph_text = preg_replace('/^\d+\.\s+/', '', $trimmed_line);
            $paragraph_text = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $paragraph_text);
            $html_output .= '<p>' . wp_kses_post($paragraph_text) . "</p>\n";
        }
    }

    if ( $in_list_type ) {
        $html_output .= "</ul>\n";
    }
    $html_output .= '<!-- ' . esc_html__( 'Finished gemini_format_review_content_to_html', 'gemini2-business-lookup' ) . ' -->';
    return $html_output;
}


// Add meta box to the configured CPT
function gemini_add_meta_boxes() {
    $cpt_slug = gemini2_get_option('gemini2_cpt_slug');
	add_meta_box(
		'gemini_meta_box',
		__( 'Gemini AI Content', 'gemini2-business-lookup' ), // Slightly shorter title
		'render_gemini_meta_box',
		$cpt_slug, 
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'gemini_add_meta_boxes' );

// In gemini2.php

// ... (other functions like gemini2_get_option, gemini2_check_cpt_configuration_notice, etc. remain the same) ...
// ... (gemini_format_review_content_to_html remains the same) ...
// ... (gemini_add_meta_boxes remains the same) ...

function render_gemini_meta_box( $post ) {
	wp_nonce_field( 'gemini_meta_box', 'gemini_meta_box_nonce' );
	$last_searched_time_meta = get_post_meta( $post->ID, '_gemini_last_searched', true );
	$raw_results_text_meta   = get_post_meta( $post->ID, '_gemini_last_results', true );

    $has_ai_content_on_load = !empty($raw_results_text_meta);
    $button_text = $has_ai_content_on_load ? __( 'Update AI Content', 'gemini2-business-lookup' ) : __( 'Generate AI Content', 'gemini2-business-lookup' );
    
    // Base classes for the button
    $button_classes = ['button', 'button-large']; 

    if ($has_ai_content_on_load) {
        $button_classes[] = 'gemini-button-green'; // Our new green class
        $button_classes[] = 'gemini-update-ai';    // Semantic class
    } else {
        $button_classes[] = 'button-primary';      // Standard blue primary button
        $button_classes[] = 'gemini-generate-ai';  // Semantic class
    }
    $button_class_attr = implode(' ', $button_classes);

    $previews_display_style = $has_ai_content_on_load ? '' : 'style="display:none;"';

	?>
	<div class="gemini-container" style="padding-bottom:10px;">
		<div style="margin-bottom:15px;">
			<button id="gemini-trigger" class="<?php echo esc_attr($button_class_attr); ?>">
				<?php echo esc_html( $button_text ); ?>
			</button>
            <span id="gemini-status-message" style="margin-left: 10px;"></span>
		</div>

        <div id="gemini-output-previews-container" <?php echo $previews_display_style; ?>>
            <div style="margin-top:15px;">
                <h4><?php esc_html_e( 'Last Raw AI Output:', 'gemini2-business-lookup' ); ?></h4>
                <div id="gemini-raw-output-display" style="padding:10px;background:#fff;border:1px solid #eee;max-height:150px;overflow-y:auto;font-family:monospace;font-size:12px;white-space:pre-wrap;margin-bottom:10px;"><?php
                    if ($has_ai_content_on_load) {
                        echo '<strong>--- ' . esc_html__( 'Raw AI Text Start', 'gemini2-business-lookup' ) . ' ---</strong><br>';
                        echo esc_html( $raw_results_text_meta );
                        echo '<br><strong>--- ' . esc_html__( 'Raw AI Text End', 'gemini2-business-lookup' ) . ' ---</strong>';
                    }
                ?></div>
                <h4><?php esc_html_e( 'Formatted HTML (for editor insertion):', 'gemini2-business-lookup' ); ?></h4>
                <div id="gemini-formatted-html-preview" style="padding:10px;background:#f9f9f9;border:1px solid #eee;max-height:200px;overflow-y:auto;font-family:monospace;font-size:12px;white-space:pre-wrap;"><?php
                    if ($has_ai_content_on_load) {
                        $unwrapped_html = gemini_format_review_content_to_html( $raw_results_text_meta );
                        $wrapped_html_for_display = '<div class="gemini-review">' . "\n" . $unwrapped_html . "\n" . '</div>';
                        echo esc_html( $wrapped_html_for_display );
                    }
                ?></div>
            </div>
        </div>

		<p id="gemini-last-generated-time-container" <?php echo $previews_display_style; ?> style="font-size:12px;color:#555;margin-top:8px;">
            <span class="label"><?php esc_html_e( 'Last generated:', 'gemini2-business-lookup' ); ?></span>
            <span class="time-value"><?php echo esc_html( $last_searched_time_meta ); ?></span>
        </p>
	</div>
	<?php
}

// gemini_plugin_enqueue_assets remains the same as in the previous good version (passing hasAiContentMeta, etc.)
// gemini_ajax_search_handler remains the same (ensure it returns 'new_last_searched_time')
// Other functions (shortcode, AI status column) remain the same.

// Enqueue plugin assets (JS, CSS, Dashicons) for the admin page
function gemini_plugin_enqueue_assets() {
	global $post;
    if (is_admin()) {
        $current_screen = get_current_screen();
        $cpt_slug = gemini2_get_option('gemini2_cpt_slug');

        if ( $current_screen && ($current_screen->post_type === $cpt_slug) && ($current_screen->base === 'post' || $current_screen->base === 'edit') ) {
            wp_enqueue_style( 'dashicons' ); 
            // Simple CSS for the green button state if desired
            $custom_css = ".gemini-update-ai.has-content-in-editor { background-color: #4CAF50 !important; border-color: #388E3C !important; color: white !important; }";
            // wp_add_inline_style( 'dashicons', $custom_css ); // Add after a core style like dashicons
        }

        if ( $current_screen && ($current_screen->post_type === $cpt_slug) && ($current_screen->base === 'post') ) {
            wp_enqueue_script(
                'gemini-admin',
                plugins_url( 'js/gemini-admin.js', __FILE__ ),
                array( 'jquery', 'wp-blocks', 'wp-data', 'wp-edit-post', 'wp-i18n' ),
                filemtime( plugin_dir_path( __FILE__ ) . 'js/gemini-admin.js' ), 
                true
            );
            wp_set_script_translations( 'gemini-admin', 'gemini2-business-lookup', plugin_dir_path( __FILE__ ) . 'languages' );
            
            $post_id_for_js = (isset($post) && is_object($post)) ? $post->ID : (isset($_GET['post']) ? intval($_GET['post']) : 0);
            $has_ai_content_meta = !empty(get_post_meta( $post_id_for_js, '_gemini_last_results', true ));

            wp_localize_script( 'gemini-admin', 'geminiAjax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'gemini_nonce' ),
                'post_id'  => $post_id_for_js,
                'hasAiContentMeta' => $has_ai_content_meta, // Flag for JS
                'i18n'     => array( 
                    'generateText' => __( 'Generate AI Content', 'gemini2-business-lookup' ),
                    'updateText'   => __( 'Update AI Content', 'gemini2-business-lookup' ),
                    'generatingText' => __( 'Generating...', 'gemini2-business-lookup' ),
                    'processingText' => __( 'Processing...', 'gemini2-business-lookup' ),
                    'errorPostId' => __( 'Error: Post ID not available. Please save the post and try again.', 'gemini2-business-lookup' ),
                    'appendSuccess' => __( 'Appended to text editor. Review formatting.', 'gemini2-business-lookup' ),
                    'insertFail'  => __( 'Could not insert content. See console for details.', 'gemini2-business-lookup' ),
                    'genericError' => __( 'Unknown error generating content. Check console.', 'gemini2-business-lookup' ),
                    'successMessage' => __( 'AI Content Generated and Inserted!', 'gemini2-business-lookup' ),
                )
            ) );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'gemini_plugin_enqueue_assets' );

// Enqueue styles for the frontend (shortcode) - ( 그대로 )
function gemini_plugin_enqueue_frontend_styles() {
    global $post;
    $load_styles = false;
    if (isset($post) && is_a($post, 'WP_Post')) {
        if (has_shortcode($post->post_content, 'gemini_review') || has_shortcode($post->post_content, 'gemini_description')) {
            $load_styles = true;
        }
    }
    $cpt_slug = gemini2_get_option('gemini2_cpt_slug');
    if (is_singular($cpt_slug)) { 
        $load_styles = true;
    }
    if ($load_styles) {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'gemini-review-styles',
            plugins_url( 'css/gemini-review-styles.css', __FILE__ ),
            array('dashicons'), 
            filemtime( plugin_dir_path( __FILE__ ) . 'css/gemini-review-styles.css' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'gemini_plugin_enqueue_frontend_styles' );


// AJAX handler: call Gemini API - ( 그대로 )
function gemini_ajax_search_handler() {
	check_ajax_referer( 'gemini_nonce', 'nonce' );
	$post_id = intval( $_POST['post_id'] );
    if ( ! $post_id ) { wp_send_json_error( __( 'Post ID not found.', 'gemini2-business-lookup' ) ); return; }

    $meta_field_name = gemini2_get_option('gemini2_meta_field_name');
    $meta_field_city = gemini2_get_option('gemini2_meta_field_city');
	$name    = get_post_meta( $post_id, $meta_field_name, true );
	$city    = get_post_meta( $post_id, $meta_field_city, true );

	if ( ! $name || ! $city ) { 
        wp_send_json_error( 
            sprintf(
                /* translators: 1: Name meta key, 2: City meta key */
                esc_html__( 'Business name (meta key: %1$s) or city (meta key: %2$s) not found or empty for this post.', 'gemini2-business-lookup' ),
                '<code>' . esc_html($meta_field_name) . '</code>',
                '<code>' . esc_html($meta_field_city) . '</code>'
            )
        ); 
        return; 
    }

    $prompt_text_template = <<<PROMPT
You are an AI assistant tasked with writing a friendly, professional, and informative scuba shop review. The review should appeal to both beginner and experienced divers and be approximately 400-500 w[...] 
**IMPORTANT INSTRUCTIONS FOR YOUR OUTPUT:** (Same as v2.3.7)
**INPUT BULLET POINTS FOR AI:** (Same as v2.3.7)
**EXPECTED AI OUTPUT STRUCTURE (Use these exact plain text headers):** (Same as v2.3.7)
Introduction
Product Range
Pricing
Customer Service
Training & Certification
Facilities & Atmosphere
Servicing & Repairs
Dive Trips & Events
Accessibility
Summary
Pros & Cons
---
The business name to be reviewed is '%s' and it is located in %s. Please ensure the review flows naturally, incorporating this information where appropriate, especially in the Introduction section.
PROMPT;

    $prompt_text = sprintf($prompt_text_template, $name, $city);
    $model = 'gemini-2.0-flash'; 
	$api_key = gemini2_get_option( 'gemini2_api_key' );
	if ( ! $api_key ) { wp_send_json_error( __( 'Gemini API key not set.', 'gemini2-business-lookup' ) ); return; }
	
    $url   = sprintf( 'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', $model, $api_key );
	$body_params = [
		'contents' => [ [ 'parts' => [ [ 'text' => $prompt_text, ], ], ], ],
		'generationConfig' => [ 'temperature'   => 0.6, 'maxOutputTokens' => 2048, ],
        'safetySettings' => [
            [ 'category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE', ],
            [ 'category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE', ],
            [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE', ],
            [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE', ], ] ];
    $body = wp_json_encode($body_params);
	$response = wp_remote_post( $url, [ 'headers' => [ 'Content-Type' => 'application/json' ], 'body'    => $body, 'timeout' => 60, ] );

    if ( is_wp_error( $response ) ) { wp_send_json_error( __( 'API connection error:', 'gemini2-business-lookup' ) . ' ' . $response->get_error_message() ); return; }
	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	if ( $response_code !== 200 ) { wp_send_json_error( __( 'API error code:', 'gemini2-business-lookup' ) . ' ' . $response_code . '. ' . __( 'Response:', 'gemini2-business-lookup' ) . ' ' . esc_html($response_body) ); return; }
	$data = json_decode( $response_body, true );
	if ( isset( $data['error'] ) ) { wp_send_json_error( __( 'API Error:', 'gemini2-business-lookup' ) . ' ' . (isset($data['error']['message']) ? esc_html($data['error']['message']) : wp_json_encode($data['error'])) ); return; }
    
    $raw_ai_output = '';
    if ( !empty($data['candidates']) && isset($data['candidates'][0]['content']['parts'][0]['text']) ) {
        $raw_ai_output = $data['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
        wp_send_json_error( __( 'Content generation issue. Reason:', 'gemini2-business-lookup' ) . ' ' . esc_html($data['candidates'][0]['finishReason']) . '. ' . __( 'Response:', 'gemini2-business-lookup' ) . ' ' . esc_html($response_body) ); return;
    } else { wp_send_json_error( __( 'No content from API. Response:', 'gemini2-business-lookup' ) . ' ' . esc_html($response_body)); return; }

	update_post_meta( $post_id, '_gemini_last_results', $raw_ai_output );
	update_post_meta( $post_id, '_gemini_last_searched', current_time( 'mysql' ) );

	$unwrapped_html_content = gemini_format_review_content_to_html( $raw_ai_output );
    $html_content_for_editor = '<div class="gemini-review">' . "\n" . $unwrapped_html_content . "\n" . '</div>';

	wp_send_json_success( [ 
        'message' => __( 'AI Content Generated!', 'gemini2-business-lookup' ), // More direct success message
        'raw_content'  => $raw_ai_output, 
        'html_content' => $html_content_for_editor
    ] );
}
add_action( 'wp_ajax_gemini_search', 'gemini_ajax_search_handler' );


// Shortcode: [gemini_review post_id="123"] - ( 그대로 )
function gemini_review_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'post_id' => '', 'class'    => 'gemini-review', 'header'   => '', ], $atts, 'gemini_review' );
	if ( empty( $atts['post_id'] ) ) { return '<p>' . esc_html__( 'Error: Post ID required for shortcode.', 'gemini2-business-lookup' ) . '</p>'; }
	$post_id = intval( $atts['post_id'] );
	$raw_content = get_post_meta( $post_id, '_gemini_last_results', true );
	if ( empty( $raw_content ) ) { return '<p>' . esc_html__( 'No AI review found for this post ID.', 'gemini2-business-lookup' ) . '</p>'; }
	
    $core_html_content = gemini_format_review_content_to_html( $raw_content );
    
	$output_html = '<div class="' . esc_attr( $atts['class'] ) . '">';
	if ( ! empty( $atts['header'] ) ) { $output_html .= '<h2>' . esc_html( $atts['header'] ) . '</h2>'; }
	$output_html .= $core_html_content;
	$output_html .= '</div>';
	return $output_html;
}
add_shortcode( 'gemini_review', 'gemini_review_shortcode' );
add_shortcode( 'gemini_description', 'gemini_review_shortcode' ); 


// --- AI Status Column Functions (Dynamic for CPT) --- ( 그대로 )
add_action( 'admin_init', 'gemini_register_dynamic_admin_column_hooks' );
function gemini_register_dynamic_admin_column_hooks() {
    $cpt_slug = gemini2_get_option('gemini2_cpt_slug');
    if (empty($cpt_slug) || !post_type_exists($cpt_slug)) {
        return;
    }

    add_filter( "manage_edit-{$cpt_slug}_columns", 'gemini_add_ai_status_column_header' );
    add_action( "manage_{$cpt_slug}_posts_custom_column", 'gemini_render_ai_status_column_content', 10, 2 );
    add_filter( "manage_edit-{$cpt_slug}_sortable_columns", 'gemini_make_ai_status_column_sortable' );
}

function gemini_add_ai_status_column_header( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        $new_columns[$key] = $title;
        if ( $key === 'title' ) { 
            $new_columns['gemini_ai_status'] = __( 'AI Status', 'gemini2-business-lookup' );
        }
    }
    if ( !isset($new_columns['gemini_ai_status']) ) { 
        if (isset($columns['cb'])) {
            $cb = $columns['cb'];
            unset($columns['cb']);
            $new_columns = array_merge(['cb' => $cb, 'gemini_ai_status' => __( 'AI Status', 'gemini2-business-lookup' )], $columns);
        } else {
            $new_columns['gemini_ai_status'] = __( 'AI Status', 'gemini2-business-lookup' );
        }
    }
    return $new_columns;
}

function gemini_render_ai_status_column_content( $column_name, $post_id ) {
    if ( $column_name === 'gemini_ai_status' ) {
        $ai_results_raw = get_post_meta( $post_id, '_gemini_last_results', true );
        $post_content_direct = get_post_field( 'post_content', $post_id ); // Get raw content

        if ( ! empty( $ai_results_raw ) ) {
            $found_wrapper_div = strpos( $post_content_direct, '<div class="gemini-review">' ) !== false;
            $start_comment_text = esc_html__( 'Starting gemini_format_review_content_to_html', 'gemini2-business-lookup' );
            $found_start_comment = strpos( $post_content_direct, '<!-- ' . $start_comment_text . ' -->' ) !== false;

            if ( $found_wrapper_div && $found_start_comment ) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;" title="' . esc_attr__('AI Content Structure Found in Editor', 'gemini2-business-lookup') . '"></span>';
            } else if ($found_wrapper_div) { // Wrapper found, but maybe not the specific comment (e.g. edited)
                echo '<span class="dashicons dashicons-warning" style="color: #E69A17;" title="' . esc_attr__('AI Content Wrapper Found, Structure May Be Altered', 'gemini2-business-lookup') . '"></span>';
            }
            else { // Raw results exist, but key structural elements are not in post_content.
                echo '<span class="dashicons dashicons-no-alt" style="color: #D32F2F;" title="' . esc_attr__('AI Content Generated but Not Found/Matching in Editor', 'gemini2-business-lookup') . '"></span>';
            }
        } else {
            echo '—'; 
        }
    }
}

function gemini_make_ai_status_column_sortable( $columns ) {
    $columns['gemini_ai_status'] = 'gemini_ai_status_sort'; 
    return $columns;
}

add_action( 'pre_get_posts', 'gemini_ai_status_column_orderby' );
function gemini_ai_status_column_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $orderby = $query->get( 'orderby');
    if ( 'gemini_ai_status_sort' === $orderby ) {
        $meta_query = $query->get('meta_query');
        if(!is_array($meta_query)){
            $meta_query = [];
        }
        $meta_query['relation'] = 'OR'; 
        $meta_query[] = [ 
            'key' => '_gemini_last_results',
            'compare' => 'EXISTS',
        ];
        $meta_query[] = [ 
            'key' => '_gemini_last_results',
            'compare' => 'NOT EXISTS',
        ];
        
        $query->set( 'meta_query', $meta_query );
        $query->set( 'orderby', 'meta_key' ); 
    }
}
?>
