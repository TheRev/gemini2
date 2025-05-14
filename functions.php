<?php
// 1) Your Google API key
if ( ! defined( 'GEMINI_API_KEY' ) ) {
    define( 'GEMINI_API_KEY', 'YOUR_GOOGLE_API_KEY_HERE' );
}

// 2) Add the meta box to the Business CPT
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'gemini_meta_box', 'Gemini Lookup', 'render_gemini_meta_box', 'business', 'side', 'default' );
});
function render_gemini_meta_box( $post ) {
    $last = get_post_meta( $post->ID, '_gemini_last_searched', true );
    echo '<button id="gemini-trigger" class="button">Search Gemini</button>';
    echo '<div id="gemini-results" style="margin-top:1em;"></div>';
    if ( $last ) {
        echo '<p style="font-size:12px;color:#555;">Last search: ' . esc_html( $last ) . '</p>';
    }
}

// 3) Enqueue our JS
add_action( 'admin_enqueue_scripts',  'enqueue_gemini_script' );
add_action( 'enqueue_block_editor_assets','enqueue_gemini_script' );
function enqueue_gemini_script() {
    global $post;
    if ( isset( $post ) && $post->post_type === 'business' ) {
        wp_enqueue_script(
            'gemini-admin',
            get_stylesheet_directory_uri() . '/js/gemini-admin.js',
            [ 'jquery' ],
            '1.0',
            true
        );
        wp_localize_script( 'gemini-admin', 'geminiAjax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gemini_nonce' ),
            'post_id'  => $post->ID,
        ] );
    }
}

// 4) AJAX handler: call v1beta2 generateText
add_action( 'wp_ajax_gemini_search', function() {
    check_ajax_referer( 'gemini_nonce', 'nonce' );

    $post_id = intval( $_POST['post_id'] );
    $name    = get_post_meta( $post_id, '_gpd_display_name', true );
    $city    = get_post_meta( $post_id, '_gpd_locality',     true );
    if ( ! $name || ! $city ) {
        wp_send_json_error( 'Missing name or city.' );
    }

    // Build prompt text
    $prompt_text = sprintf( "Briefly describe the business '%s' located in %s.", $name, $city );

    // Use v1beta2 endpoint
    $model = 'text-bison-001';
    $url   = sprintf(
        'https://generativelanguage.googleapis.com/v1beta2/models/%s:generateText?key=%s',
        $model,
        GEMINI_API_KEY
    );
    $body  = wp_json_encode([
        'prompt' => [ 'text' => $prompt_text ],
    ]);

    $response = wp_remote_post( $url, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => $body,
        'timeout' => 15,
    ] );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $data['error'] ) ) {
        wp_send_json_error( $data['error']['message'] );
    }

    $output = $data['candidates'][0]['output'] ?? '';
    if ( ! $output ) {
        wp_send_json_error( 'No output returned.' );
    }

    // Save and return
    update_post_meta( $post_id, '_gemini_last_results', $output );
    update_post_meta( $post_id, '_gemini_last_searched', current_time( 'mysql' ) );

    wp_send_json_success([
        'message' => 'Search completed.',
        'content' => $output,
    ]);
});

// 5) Shortcode: [gemini_search name="..." city="..."]
add_shortcode( 'gemini_search', function( $atts ) {
    $atts = shortcode_atts([
        'post_id' => get_the_ID(),
        'name'    => '',
        'city'    => '',
    ], $atts, 'gemini_search' );

    $name = $atts['name'] ?: get_post_meta( $atts['post_id'], '_gpd_display_name', true );
    $city = $atts['city'] ?: get_post_meta( $atts['post_id'], '_gpd_locality',     true );
    if ( ! $name || ! $city ) {
        return '<p><em>Gemini search: missing name or city.</em></p>';
    }

    $_POST['post_id'] = $atts['post_id'];
    ob_start();
    do_action( 'wp_ajax_gemini_search' );
    return ob_get_clean();
});
