<?php
/*
Plugin Name: Gemini2 Business Lookup
Description: Generate rich AI business reviews for dive operations using Google Gemini API.
Version: 2.3.9
Author: TheRev
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) Settings page for API key:
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Function to add editor styles
function gemini_add_editor_styles() {
    add_editor_style( plugins_url( 'css/gemini-review-styles.css', __FILE__ ) );
}
add_action( 'admin_init', 'gemini_add_editor_styles' );

// Helper function to format raw AI content to HTML
function gemini_format_review_content_to_html( $raw_content ) {
    if ( empty( $raw_content ) ) {
        return '<!-- Raw content was empty -->';
    }

    $lines = explode( "\n", $raw_content );
    $html_output = '<!-- Starting gemini_format_review_content_to_html -->';
    $in_list_type = null; 
    $current_section_key = null; 

    $section_headers_map = [
        'Introduction' => ['icon' => 'dashicons-info-outline', 'title' => 'Introduction'],
        'Product Range' => ['icon' => 'dashicons-cart', 'title' => 'Product Range'],
        'Pricing' => ['icon' => 'dashicons-tag', 'title' => 'Pricing'],
        'Customer Service' => ['icon' => 'dashicons-admin-users', 'title' => 'Customer Service'],
        'Training & Certification' => ['icon' => 'dashicons-awards', 'title' => 'Training & Certification'],
        'Facilities & Atmosphere' => ['icon' => 'dashicons-store', 'title' => 'Facilities & Atmosphere'],
        'Servicing & Repairs' => ['icon' => 'dashicons-admin-tools', 'title' => 'Servicing & Repairs'],
        'Dive Trips & Events' => ['icon' => 'dashicons-palmtree', 'title' => 'Dive Trips & Events'],
        'Accessibility' => ['icon' => 'dashicons-location-alt', 'title' => 'Accessibility'],
        'Summary' => ['icon' => 'dashicons-star-filled', 'title' => 'Summary'],
        'Pros & Cons' => ['icon' => 'dashicons-yes-alt', 'title' => 'Pros & Cons'] 
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
        $header_details = null;

        if (!empty($cleaned_for_header_check)) {
            if (isset($section_headers_map[$cleaned_for_header_check])) {
                $header_details = $section_headers_map[$cleaned_for_header_check];
                $is_header_match = true;
                $current_section_key = $cleaned_for_header_check; 
            }
        }

        if ( $is_header_match && $header_details ) {
            if ( $in_list_type ) {
                $html_output .= "</ul>\n";
                $in_list_type = null;
            }
            $icon_span = $header_details['icon'] ? '<span class="dashicons ' . esc_attr($header_details['icon']) . '"></span> ' : '';
            $html_output .= '<h3>' . $icon_span . esc_html( $header_details['title'] ) . "</h3>\n";
        } 
        elseif ($current_section_key === 'Pros & Cons' && strpos($trimmed_line, '✔️ ') === 0) {
            if ($in_list_type !== 'pros') {
                if ($in_list_type) $html_output .= "</ul>\n";
                $html_output .= '<ul class="gemini-pros-list">' . "\n";
                $in_list_type = 'pros';
            }
            $item_content = substr($trimmed_line, strlen('✔️ '));
            $item_content = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $item_content);
            $html_output .= '<li class="gemini-pro-item">' . esc_html($item_content) . "</li>\n";
        }
        elseif ($current_section_key === 'Pros & Cons' && strpos($trimmed_line, '❌ ') === 0) {
            if ($in_list_type !== 'cons') {
                if ($in_list_type) $html_output .= "</ul>\n"; 
                $html_output .= '<ul class="gemini-cons-list">' . "\n";
                $in_list_type = 'cons';
            }
            $item_content = substr($trimmed_line, strlen('❌ '));
            $item_content = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $item_content);
            $html_output .= '<li class="gemini-con-item">' . esc_html($item_content) . "</li>\n";
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
            $html_output .= '<li>' . $list_item_content . "</li>\n"; 
        }
        else {
            if ( $in_list_type ) {
                $html_output .= "</ul>\n";
                $in_list_type = null;
            }
            $paragraph_text = preg_replace('/^\d+\.\s+/', '', $trimmed_line);
            $paragraph_text = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $paragraph_text);
            $html_output .= '<p>' . $paragraph_text . "</p>\n";
        }
    }

    if ( $in_list_type ) {
        $html_output .= "</ul>\n";
    }
    $html_output .= '<!-- Finished gemini_format_review_content_to_html -->';
    return $html_output;
}

// 2) Add meta box to the Business CPT
function gemini_add_meta_boxes() {
	add_meta_box(
		'gemini_meta_box',
		'Gemini AI Business Description',
		'render_gemini_meta_box',
		'business', // CPT slug
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'gemini_add_meta_boxes' );

function render_gemini_meta_box( $post ) {
	wp_nonce_field( 'gemini_meta_box', 'gemini_meta_box_nonce' );
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
                    $unwrapped_html = gemini_format_review_content_to_html( $raw_results_text );
                    $wrapped_html_for_display = '<div class="gemini-review">' . "\n" . $unwrapped_html . "\n" . '</div>';
                    echo esc_html( $wrapped_html_for_display );
                ?></div>
			</div>
		<?php endif; ?>

		<?php if ( $last_searched_time ) : ?>
			<p style="font-size:12px;color:#555;margin-top:8px;">Last generated: <?php echo esc_html( $last_searched_time ); ?></p>
		<?php endif; ?>
		<div style="margin-top:15px;padding:8px;background:#f0f0f0;font-size:11px;color:#666;">
			<?php /* Debug info */ ?>
		</div>
	</div>
	<?php
}

// 3) Enqueue plugin assets (JS, CSS, Dashicons) for the admin page
function gemini_plugin_enqueue_assets() {
	global $post;
    if (is_admin()) {
        $current_screen = get_current_screen();
        // Ensure Dashicons are enqueued on the 'business' CPT edit list screen as well for the new column
        if ( $current_screen && ($current_screen->post_type === 'business') && ($current_screen->base === 'post' || $current_screen->base === 'edit') ) {
            wp_enqueue_style( 'dashicons' ); 
        }

        if ( $current_screen && ($current_screen->post_type === 'business') && ($current_screen->base === 'post') ) {
            wp_enqueue_script(
                'gemini-admin',
                plugins_url( 'js/gemini-admin.js', __FILE__ ),
                array( 'jquery', 'wp-blocks', 'wp-data', 'wp-edit-post' ),
                '2.3.9.' . time(), 
                true
            );
            $post_id_for_js = (isset($post) && is_object($post)) ? $post->ID : (isset($_GET['post']) ? intval($_GET['post']) : 0);
            wp_localize_script( 'gemini-admin', 'geminiAjax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'gemini_nonce' ),
                'post_id'  => $post_id_for_js,
            ) );
            // Dashicons already enqueued above if it's a 'business' post type screen
        }
    }
}
add_action( 'admin_enqueue_scripts', 'gemini_plugin_enqueue_assets' );

// Enqueue styles for the frontend (shortcode)
function gemini_plugin_enqueue_frontend_styles() {
    global $post;
    $load_styles = false;
    if (isset($post) && is_a($post, 'WP_Post')) {
        if (has_shortcode($post->post_content, 'gemini_review') || has_shortcode($post->post_content, 'gemini_description')) {
            $load_styles = true;
        }
    }
    if (is_singular('business')) { 
        $load_styles = true;
    }
    if ($load_styles) {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'gemini-review-styles',
            plugins_url( 'css/gemini-review-styles.css', __FILE__ ),
            array('dashicons'), 
            '2.3.9.' . time() 
        );
    }
}
add_action( 'wp_enqueue_scripts', 'gemini_plugin_enqueue_frontend_styles' );

// 4) AJAX handler: call Gemini API
function gemini_ajax_search_handler() {
	check_ajax_referer( 'gemini_nonce', 'nonce' );
	$post_id = intval( $_POST['post_id'] );
    if ( ! $post_id ) { wp_send_json_error( 'Post ID not found.' ); return; }
	$name    = get_post_meta( $post_id, '_gpd_display_name', true );
	$city    = get_post_meta( $post_id, '_gpd_locality', true );
	if ( ! $name || ! $city ) { wp_send_json_error( 'Business name/city missing.' ); return; }

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
    $model = 'gemini-2.0-flash'; // or your desired model
	$api_key = get_option( 'gemini2_api_key', '' );
	if ( ! $api_key ) { wp_send_json_error( 'Gemini API key not set.' ); return; }
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

    if ( is_wp_error( $response ) ) { wp_send_json_error( 'API connection error: ' . $response->get_error_message() ); return; }
	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	if ( $response_code !== 200 ) { wp_send_json_error( 'API error code: ' . $response_code . '. Resp: ' . $response_body ); return; }
	$data = json_decode( $response_body, true );
	if ( isset( $data['error'] ) ) { wp_send_json_error( 'API Error: ' . (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error'])) ); return; }
    $raw_ai_output = '';
    if ( !empty($data['candidates']) && isset($data['candidates'][0]['content']['parts'][0]['text']) ) {
        $raw_ai_output = $data['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
        wp_send_json_error( 'Content generation issue. Reason: ' . $data['candidates'][0]['finishReason'] . '. Resp: ' . $response_body ); return;
    } else { wp_send_json_error( 'No content from API. Resp: ' . $response_body); return; }

	update_post_meta( $post_id, '_gemini_last_results', $raw_ai_output );
	update_post_meta( $post_id, '_gemini_last_searched', current_time( 'mysql' ) );

	$unwrapped_html_content = gemini_format_review_content_to_html( $raw_ai_output );
    $html_content_for_editor = '<div class="gemini-review">' . "\n" . $unwrapped_html_content . "\n" . '</div>';

	wp_send_json_success( [ 
        'message' => 'Description generated!', 
        'raw_content'  => $raw_ai_output, 
        'html_content' => $html_content_for_editor
    ] );
}
add_action( 'wp_ajax_gemini_search', 'gemini_ajax_search_handler' );

// 5) Shortcode: [gemini_review post_id="123"]
function gemini_review_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'post_id' => '', 'class'    => 'gemini-review', 'header'   => '', ], $atts, 'gemini_review' );
	if ( empty( $atts['post_id'] ) ) { return '<p>Error: Post ID required.</p>'; }
	$post_id = intval( $atts['post_id'] );
	$raw_content = get_post_meta( $post_id, '_gemini_last_results', true );
	if ( empty( $raw_content ) ) { return '<p>No AI review found.</p>'; }
	
    $core_html_content = gemini_format_review_content_to_html( $raw_content );
    
	$output_html = '<div class="' . esc_attr( $atts['class'] ) . '">';
	if ( ! empty( $atts['header'] ) ) { $output_html .= '<h2>' . esc_html( $atts['header'] ) . '</h2>'; }
	$output_html .= $core_html_content;
	$output_html .= '</div>';
	return $output_html;
}
add_shortcode( 'gemini_review', 'gemini_review_shortcode' );
add_shortcode( 'gemini_description', 'gemini_review_shortcode' );

// Bulk processing functions
add_action('restrict_manage_posts', function($post_type) {
    if ($post_type !== 'business') return; // CPT slug
    echo '<button type="button" class="button" id="bulk-ai-business">Bulk Add AI Reviews</button>';
    echo '<span id="bulk-ai-business-status" style="margin-left:5px;"></span>';
});

add_action('admin_enqueue_scripts', function($hook) {
    $current_screen = get_current_screen();
    if (!$current_screen || $current_screen->base !== 'edit' || $current_screen->id !== 'edit-business' || $current_screen->post_type !== 'business') { // CPT slug
        return; 
    }
    wp_enqueue_script(
        'bulk-ai-business',
        plugins_url('js/bulk-ai-business.js', __FILE__),
        array('jquery'),
        '1.0.4.' . time(), 
        true
    );
    wp_localize_script('bulk-ai-business', 'BulkAIBusiness', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bulk_ai_business_nonce'),
    ]);
});

add_action('wp_ajax_bulk_ai_missing_business', function() {
    check_ajax_referer('bulk_ai_business_nonce', 'nonce');
    $args = [
        'post_type' => 'business', // CPT slug
        'post_status' => 'publish', 
        'posts_per_page' => -1, 
        'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_gemini_last_results', 'compare' => 'NOT EXISTS' ] ] 
    ];
    $businesses = get_posts($args);
    if(empty($businesses)){  wp_send_json_error('All businesses already have AI reviews.'); return; }
    
    $count = 0;
    $api_key = get_option( 'gemini2_api_key', '' );
    if ( ! $api_key ) { wp_send_json_error( 'Gemini API key not set for bulk processing.' ); return; }
    $model = 'gemini-2.0-flash'; // or your desired model

    $prompt_text_template_bulk = <<<PROMPT
You are an AI assistant tasked with writing a friendly, professional, and informative scuba shop review. The review should appeal to both beginner and experienced divers and be approximately 400-500 w[...]
**IMPORTANT INSTRUCTIONS FOR YOUR OUTPUT:** (Same as single generation)
**INPUT BULLET POINTS FOR AI:** (Same as single generation)
**EXPECTED AI OUTPUT STRUCTURE (Use these exact plain text headers):**
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

    foreach($businesses as $post_id){
        $name = get_post_meta($post_id, '_gpd_display_name', true);
        $city = get_post_meta($post_id, '_gpd_locality', true);
        if(!$name || !$city) { 
            error_log("Skipping Post ID $post_id in bulk: missing name or city."); 
            continue; 
        }
        $prompt_text = sprintf($prompt_text_template_bulk, $name, $city);
        $url   = sprintf( 'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', $model, $api_key );
        $body_params = [
            'contents' => [ [ 'parts' => [ [ 'text' => $prompt_text, ], ], ], ],
            'generationConfig' => [ 'temperature'   => 0.6, 'maxOutputTokens' => 2048, ],
            'safetySettings' => [
                [ 'category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE', ], ]
        ];
        $body = wp_json_encode($body_params);
        $response = wp_remote_post( $url, [ 'headers' => [ 'Content-Type' => 'application/json' ], 'body'    => $body, 'timeout' => 60, ] );
        
        if ( is_wp_error( $response ) ) { 
            error_log("Gemini Bulk AI WP_Error for Post ID $post_id: " . $response->get_error_message()); 
            continue; 
        }
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        if ( $response_code !== 200 ) { 
            error_log("Gemini Bulk AI HTTP Error for Post ID $post_id: Code $response_code, Response: $response_body"); 
            continue; 
        }
        $data = json_decode( $response_body, true );
        if ( isset( $data['error'] ) ) { 
            error_log("Gemini Bulk AI API Error for Post ID $post_id: " . (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']))); 
            continue;
        }
        $output_text = '';
        if ( !empty($data['candidates']) && isset($data['candidates'][0]['content']['parts'][0]['text']) ) {
            $output_text = $data['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
            error_log("Gemini Bulk AI content generation incomplete/blocked for Post ID $post_id. Reason: " . $data['candidates'][0]['finishReason'] . ". Response: " . $response_body); 
            continue;
        } else { 
            error_log("Gemini Bulk AI No Content for Post ID $post_id. Response: " . $response_body); 
            continue; 
        }
        
        update_post_meta($post_id, '_gemini_last_results', $output_text);
        update_post_meta($post_id, '_gemini_last_searched', current_time('mysql'));
        $count++;
        if ($count > 0 && $count % 3 == 0) { sleep(1); } // Basic rate limiting
    }

    if ($count > 0) { wp_send_json_success("$count business(es) updated with AI reviews."); }
    else { wp_send_json_error('No businesses were updated. Check logs or if they already have reviews.'); }
});

// START: Add AI Status Column to Business CPT List
// -------------------------------------------------

// 1. Add the column header
add_filter( 'manage_edit-business_columns', 'gemini_add_ai_status_column_header' );
function gemini_add_ai_status_column_header( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        $new_columns[$key] = $title;
        if ( $key === 'title' ) { // Placing it after the 'title' column
            $new_columns['gemini_ai_status'] = __( 'AI', 'gemini2-business-lookup' );
        }
    }
    // Fallback if 'title' column wasn't found (e.g. removed by another plugin)
    if ( !isset($new_columns['gemini_ai_status']) && isset($columns['cb']) ) { // Add after checkbox if title is missing
         $cb = $columns['cb'];
         unset($columns['cb']);
         $new_columns = array_merge(['cb' => $cb, 'gemini_ai_status' => __( 'AI', 'gemini2-business-lookup' )], $columns);
    } elseif (!isset($new_columns['gemini_ai_status'])) { // Add at the end if no other suitable place
        $new_columns['gemini_ai_status'] = __( 'AI', 'gemini2-business-lookup' );
    }
    return $new_columns;
}

// 2. Populate the column content
// Replace the _debug function and its hook with this one
add_action( 'manage_business_posts_custom_column', 'gemini_render_ai_status_column_content', 10, 2 );
function gemini_render_ai_status_column_content( $column_name, $post_id ) {
    if ( $column_name === 'gemini_ai_status' ) {
        $ai_results_raw = get_post_meta( $post_id, '_gemini_last_results', true );
        $post_content = get_post_field( 'post_content', $post_id );

        if ( ! empty( $ai_results_raw ) ) {
            // Key indicators that the AI content is likely present, even if slightly modified
            $found_wrapper_div = strpos( $post_content, '<div class="gemini-review">' ) !== false;
            $found_start_comment = strpos( $post_content, '<!-- Starting gemini_format_review_content_to_html -->' ) !== false;
            // You could add another check for a common header if needed, e.g., '<h3>Introduction</h3>'
            // but be mindful if users might legitimately type that.
            // $found_intro_header = strpos( $post_content, '<h3>Introduction</h3>') !== false;

            if ( $found_wrapper_div && $found_start_comment ) {
                // If Dashicons are consistently stripped, we might not be able to reliably check for them in post_content.
                // The green check now means the main structure is there.
                echo '<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;" title="' . esc_attr__('AI Content Structure Found in Editor', 'gemini2-business-lookup') . '"></span>';
            } else if ($found_wrapper_div) {
                 // Wrapper is there, but maybe not the comment (e.g. if only part of it was pasted or heavily edited)
                echo '<span class="dashicons dashicons-warning" style="color: #E69A17;" title="' . esc_attr__('AI Content Wrapper Found, but Structure May Be Altered', 'gemini2-business-lookup') . '"></span>';
            }
            else {
                // Raw results exist, but the key structural elements are not in post_content.
                echo '<span class="dashicons dashicons-no-alt" style="color: #D32F2F;" title="' . esc_attr__('AI Content Generated but Not Found in Editor', 'gemini2-business-lookup') . '"></span>';
            }
        } else {
            echo '—'; // Em dash for "not generated" status
        }
    }
}
/** add_action( 'manage_business_posts_custom_column', 'gemini_render_ai_status_column_content', 10, 2 );
function gemini_render_ai_status_column_content( $column_name, $post_id ) {
    if ( $column_name === 'gemini_ai_status' ) {
        $ai_results_raw = get_post_meta( $post_id, '_gemini_last_results', true );
        $post_content = get_post_field( 'post_content', $post_id );

        if ( ! empty( $ai_results_raw ) ) {
            // Format the raw AI results just like it's done before editor insertion
            $unwrapped_html_content = gemini_format_review_content_to_html( $ai_results_raw );
            // Ensure the wrapping div and newlines match exactly how it's inserted
            $expected_html_in_editor = '<div class="gemini-review">' . "\n" . $unwrapped_html_content . "\n" . '</div>';

            // Check if the formatted & wrapped HTML is found in the post_content
            if ( strpos( $post_content, $expected_html_in_editor ) !== false ) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;" title="' . esc_attr__('AI Content Generated and Found in Editor', 'gemini2-business-lookup') . '"></span>';
            } else {
                echo '<span class="dashicons dashicons-warning" style="color: #FFC107;" title="' . esc_attr__('AI Content Generated but Not Found/Modified in Editor', 'gemini2-business-lookup') . '"></span>';
            }
        } else {
            echo '—'; // Em dash for "not generated" status
        }
    }
} **/

// 3. Make the column sortable
add_filter( 'manage_edit-business_sortable_columns', 'gemini_make_ai_status_column_sortable' );
function gemini_make_ai_status_column_sortable( $columns ) {
    $columns['gemini_ai_status'] = 'gemini_ai_status_sort'; // The value is a key for query_vars
    return $columns;
}

// 4. Handle the sorting logic
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
        // This will sort primarily by whether the _gemini_last_results meta key exists.
        // Posts with the key will be grouped.
        // A more nuanced sort (e.g. distinguishing between generated+in_editor vs generated+not_in_editor)
        // would require saving another meta field specifically for sorting or a more complex query.
        $meta_query[] = [
            'key' => '_gemini_last_results',
            'compare' => 'EXISTS', // Prioritize posts that have the meta key
        ];
        $query->set( 'meta_query', $meta_query );
        $query->set( 'orderby', 'meta_value' ); // Order by the content of _gemini_last_results
                                                // (or 'meta_key' if you just want to sort by existence)
    }
}
// END: Add AI Status Column to Business CPT List
// -----------------------------------------------

?>
