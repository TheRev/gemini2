/**
 * Gemini AI Integration for WordPress
 * Version: 1.0.1
 * Date: 2025-05-14
 */

// 1) Your Google API key - IMPORTANT: Replace with your actual API key
if ( ! defined( 'GEMINI_API_KEY' ) ) {
    define( 'GEMINI_API_KEY', 'AIzaSyAXDbDNOCYn0wuiD0y7p52-T96QE33dG4k' );
}

// 2) Add the meta box to the Business CPT
function gemini_add_meta_boxes() {
    add_meta_box( 
        'gemini_meta_box', 
        'Gemini AI Business Description', 
        'render_gemini_meta_box', 
        'business', 
        'normal', 
        'high'
    );
}
add_action( 'add_meta_boxes', 'gemini_add_meta_boxes' );

function render_gemini_meta_box( $post ) {
    wp_nonce_field( 'gemini_meta_box', 'gemini_meta_box_nonce' );
    $last = get_post_meta( $post->ID, '_gemini_last_searched', true );
    $results = get_post_meta( $post->ID, '_gemini_last_results', true );
    
    ?>
    <div class="gemini-container" style="padding-bottom:10px;">
        <div style="margin-bottom:15px;">
            <button id="gemini-trigger" class="button button-primary button-large">
                Generate Business Description with AI
            </button>
        </div>
        
        <div id="gemini-results" style="margin-top:1em;padding:10px;border:1px solid #ddd;background:#f9f9f9;<?php echo empty($results) ? 'display:none;' : ''; ?>">
            <?php if (!empty($results)) : ?>
                <div class="gemini-content" style="padding: 10px; border: 1px solid #ddd; background: #fff;">
                    <?php echo wpautop($results); ?>
                </div>
                <div style="margin-top:10px;">
                    <button id="gemini-insert" class="button">Insert Into Content</button>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ( $results ) : ?>
            <div style="margin-top:15px;">
                <h4>Last Generated Description:</h4>
                <div style="padding:10px;background:#fff;border:1px solid #eee;"><?php echo wpautop($results); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ( $last ) : ?>
            <p style="font-size:12px;color:#555;margin-top:8px;">Last generated: <?php echo esc_html($last); ?></p>
        <?php endif; ?>
        
        <!-- Debug information -->
        <div style="margin-top:15px;padding:8px;background:#f0f0f0;font-size:11px;color:#666;">
            <?php 
                $name = get_post_meta($post->ID, '_gpd_display_name', true);
                $city = get_post_meta($post->ID, '_gpd_locality', true);
            ?>
            <strong>Debug Info:</strong><br>
            Business Name: <?php echo esc_html($name ?: 'Not set'); ?><br>
            City: <?php echo esc_html($city ?: 'Not set'); ?><br>
            Post ID: <?php echo $post->ID; ?>
        </div>
    </div>
    <?php
}

// 3) Enqueue our JS
function enqueue_gemini_script( $hook ) {
    global $post;
    
    if ( ! isset( $post ) || $post->post_type !== 'business' ) {
        return;
    }
    
    // Only load on post edit screens
    if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
        return;
    }
    
    // Use theme directory
    $js_path = get_template_directory_uri() . '/gemini-admin.js';
    
    wp_enqueue_script(
        'gemini-admin',
        $js_path,
        array('jquery'),
        '1.1.' . time(),
        true
    );
    
    wp_localize_script( 'gemini-admin', 'geminiAjax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'gemini_nonce' ),
        'post_id'  => $post->ID,
    ));
}
add_action( 'admin_enqueue_scripts', 'enqueue_gemini_script' );

// 4) AJAX handler: call Gemini API
function gemini_ajax_search_handler() {
    // Check nonce for security
    check_ajax_referer( 'gemini_nonce', 'nonce' );

    $post_id = intval( $_POST['post_id'] );
    $name = get_post_meta( $post_id, '_gpd_display_name', true );
    $city = get_post_meta( $post_id, '_gpd_locality', true );
    
    if ( ! $name || ! $city ) {
        wp_send_json_error( 'Required information missing. Please make sure business name and city are set in the post metadata.' );
        return;
    }

    // Build prompt text
    $prompt_text = sprintf( 
        "Write a detailed 3-paragraph description of the business '%s' located in %s. " .
        "Include what they might offer, their expertise, and why customers might choose them. " .
        "Format this for use on a business website.", 
        $name, 
        $city 
    );
    
    // Use Gemini Pro 1.5 endpoint
    $model = 'gemini-1.5-pro';
    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
        $model,
        GEMINI_API_KEY
    );
    
    // Correct request format for Gemini API
    $body = wp_json_encode([
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt_text
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 800,
        ]
    ]);
    
    $response = wp_remote_post( $url, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => $body,
        'timeout' => 15,
    ] );
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'API connection error: ' . $response->get_error_message() );
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        wp_send_json_error('API returned error code: ' . $response_code . '. Check your API key and settings.');
        return;
    }

    $data = json_decode($response_body, true);
    
    if (isset($data['error'])) {
        wp_send_json_error($data['error']['message']);
        return;
    }

    // Extract text from Gemini response
    $output = '';
    if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
        $output = $data['candidates'][0]['content']['parts'][0]['text'];
    } else {
        wp_send_json_error('No content returned from the API.');
        return;
    }

    // Save and return
    update_post_meta($post_id, '_gemini_last_results', $output);
    update_post_meta($post_id, '_gemini_last_searched', current_time('mysql'));

    wp_send_json_success([
        'message' => 'Description generated successfully.',
        'content' => $output,
    ]);
}
add_action('wp_ajax_gemini_search', 'gemini_ajax_search_handler');

// 5) Add the shortcode for use in FSE templates with specific formatting instructions
function gemini_review_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'business' => '',        // Business name
        'location' => '',        // Business location
        'class'    => 'gemini-review',  // CSS class
        'header'   => '',        // Header text (empty for no header)
        'force'    => 'no',      // Force refresh cached content
    ), $atts, 'gemini_review');

    // We need both business name and location
    if (empty($atts['business']) || empty($atts['location'])) {
        return '<p>Error: Business name and location required.</p>';
    }

    // Build prompt text specifically requesting the exact formatting style
    $prompt_text = sprintf(
        "Write a professional business review about %s in %s. Follow EXACTLY this formatting pattern:\n\n" .
        "1. Start with a brief introduction paragraph.\n\n" .
        "2. Then use section headers followed by content in this exact style:\n" .
        "   - Section headers should be on their own line with no punctuation.\n" .
        "   - Content starts on the line immediately after each header.\n" .
        "   - Do not bold or style section headers in the text.\n\n" .
        
        "3. Include these specific sections in this order:\n" .
        "   - Overview\n" .
        "   - Strengths (include specific services and features)\n" .
        "   - Potential Weaknesses (if any)\n" .
        "   - Overall\n\n" .
        
        "4. For bullet points, use a new line with a hyphen and space '- ' for each point.\n\n" .
        
        "IMPORTANT: Format exactly like this example:\n\n" .
        "Brief intro paragraph about the business.\n\n" .
        "Overview\n" .
        "Content about the overview...\n\n" .
        "Strengths\n" .
        "Strength 1: Description.\n\n" .
        "Strength 2: Description.\n" .
        "- Sub-point 1\n" .
        "- Sub-point 2\n\n" .
        "Potential Weaknesses\n" .
        "Content about weaknesses...\n\n" .
        "Overall\n" .
        "Conclusion paragraph...\n\n" .
        "This format is critical for proper display on our website.", 
        $atts['business'], 
        $atts['location']
    );
    
    // Generate a cache key based on business and location
    $cache_key = md5($atts['business'] . $atts['location'] . 'formatted_v2');
    $transient_name = 'gemini_review_' . $cache_key;
    
    // Check if we need to force refresh
    $force_refresh = ($atts['force'] === 'yes');
    
    // Try to get cached result first (unless force refresh is enabled)
    $content = !$force_refresh ? get_transient($transient_name) : '';
    
    if (empty($content)) {
        // Use Gemini API to generate content
        $model = 'gemini-1.5-pro';
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
            $model,
            GEMINI_API_KEY
        );
        
        $body = wp_json_encode([
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt_text
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2, // Lower temperature for more precise adherence to instructions
                'maxOutputTokens' => 1500, // Increased token count for detailed reviews
            ]
        ]);
        
        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 25, // Longer timeout for detailed review generation
        ] );
        
        if ( is_wp_error( $response ) ) {
            return '<p>Error: ' . $response->get_error_message() . '</p>';
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $data['error'] ) ) {
            return '<p>API Error: ' . $data['error']['message'] . '</p>';
        }
        
        // Extract text from Gemini response
        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $data['candidates'][0]['content']['parts'][0]['text'];
            // Cache the result for 1 week
            set_transient($transient_name, $content, 7 * DAY_IN_SECONDS);
        } else {
            return '<p>Error: No output returned from the API.</p>';
        }
    }
    
    // Simple array of section headers to look for
    $section_headers = array('Overview', 'Strengths', 'Services', 'Features', 'Potential Weaknesses', 'Weaknesses', 'Overall', 'Conclusion');
    
    // Process the content to format headers properly
    $lines = explode("\n", $content);
    $processed_content = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            $processed_content .= "\n\n"; // Preserve empty lines
            continue;
        }
        
        // Check if this line is a standalone section header
        if (in_array($line, $section_headers)) {
            $processed_content .= "\n<strong>" . $line . "</strong>\n";
        } else {
            $processed_content .= $line . "\n";
        }
    }
    
    // Format the output with proper HTML
    $output = '<div class="' . esc_attr($atts['class']) . '">';
    
    // Add header if provided
    if (!empty($atts['header'])) {
        $output .= '<h2>' . esc_html($atts['header']) . '</h2>';
    }
    
    // Apply minimal formatting to preserve line breaks but make it HTML-safe
    $output .= wpautop($processed_content);
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('gemini_review', 'gemini_review_shortcode');

// For backward compatibility
add_shortcode('gemini_description', 'gemini_review_shortcode');

// Add minimal CSS for better readability
function add_gemini_review_minimal_styles() {
    ?>
    <style>
        .gemini-review {
            line-height: 1.6;
            font-family: inherit;
            margin: 1.5em 0;
        }
        .gemini-review strong {
            display: block;
            font-size: 1.2em;
            margin-top: 1.2em;
            margin-bottom: 0.5em;
        }
        .gemini-review ul, .gemini-review ol {
            margin-left: 1.5em;
            margin-bottom: 1.2em;
        }
        .gemini-review li {
            margin-bottom: 0.5em;
        }
    </style>
    <?php
}
add_action('wp_head', 'add_gemini_review_minimal_styles');
