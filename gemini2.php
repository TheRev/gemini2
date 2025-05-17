<?php
/*
Plugin Name: Gemini2 Business Lookup
Description: Generate rich AI business reviews for dive operations using Google Gemini API.
Version: 2.0
Author: TheRev
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) Settings page for API key:
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// 2) Add meta box to the Business CPT
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
	$last    = get_post_meta( $post->ID, '_gemini_last_searched', true );
	$results = get_post_meta( $post->ID, '_gemini_last_results', true );
	?>
	<div class="gemini-container" style="padding-bottom:10px;">
		<div style="margin-bottom:15px;">
			<button id="gemini-trigger" class="button button-primary button-large">
				Generate Business Description with AI
			</button>
		</div>
		<div id="gemini-results" style="margin-top:1em;padding:10px;border:1px solid #ddd;background:#f9f9f9;<?php echo empty( $results ) ? 'display:none;' : ''; ?>">
			<?php if ( ! empty( $results ) ) : ?>
				<div class="gemini-content" style="padding: 10px; border: 1px solid #ddd; background: #fff;">
					<?php echo wpautop( $results ); ?>
				</div>
				<div style="margin-top:10px;">
					<button id="gemini-insert" class="button">Insert Into Content</button>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $results ) : ?>
			<div style="margin-top:15px;">
				<h4>Last Generated Description:</h4>
				<div style="padding:10px;background:#fff;border:1px solid #eee;"><?php echo wpautop( $results ); ?></div>
			</div>
		<?php endif; ?>
		<?php if ( $last ) : ?>
			<p style="font-size:12px;color:#555;margin-top:8px;">Last generated: <?php echo esc_html( $last ); ?></p>
		<?php endif; ?>
		<div style="margin-top:15px;padding:8px;background:#f0f0f0;font-size:11px;color:#666;">
			<?php
			$name = get_post_meta( $post->ID, '_gpd_display_name', true );
			$city = get_post_meta( $post->ID, '_gpd_locality', true );
			?>
			<strong>Debug Info:</strong><br>
			Business Name: <?php echo esc_html( $name ?: 'Not set' ); ?><br>
			City: <?php echo esc_html( $city ?: 'Not set' ); ?><br>
			Post ID: <?php echo $post->ID; ?>
		</div>
	</div>
	<?php
}

// 3) Enqueue our JS
function enqueue_gemini_script( $hook ) {
	global $post;
	if ( ! isset( $post ) || $post->post_type !== 'business' ) return;
	// Only load on post edit screens
	if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
	wp_enqueue_script(
		'gemini-admin',
		plugins_url( 'js/gemini-admin.js', __FILE__ ),
		array( 'jquery' ),
		'2.0.4.' . time(),
		true
	);
	wp_localize_script( 'gemini-admin', 'geminiAjax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'gemini_nonce' ),
		'post_id'  => $post->ID,
	) );
}
add_action( 'admin_enqueue_scripts', 'enqueue_gemini_script' );

// 4) AJAX handler: call Gemini API
function gemini_ajax_search_handler() {
	check_ajax_referer( 'gemini_nonce', 'nonce' );
	$post_id = intval( $_POST['post_id'] );
	$name    = get_post_meta( $post_id, '_gpd_display_name', true );
	$city    = get_post_meta( $post_id, '_gpd_locality', true );
	if ( ! $name || ! $city ) {
		wp_send_json_error( 'Required information missing. Please make sure business name and city are set in the post metadata.' );
		return;
	}
	// Build prompt text for detailed output
	$prompt_text = sprintf(
		"Write a professional and detailed business review about '%s' located in %s, a prime destination for scuba diving. Structure the review with the following exact section headers, each on a new line and followed immediately by the content for that section:\n\n" .
		"Overview\n" .
		"Provide a general overview of the dive operation, mentioning any certifications, services offered (introductory dives, certifications, excursions), and any unique features like on-site accommodation.\n\n" .
		"Strengths\n" .
		"Detail the key strengths of the business using bullet points where appropriate. Include specific aspects like certification benefits, the range of services offered (list examples).\n\n" .
		"Potential Weaknesses\n" .
		"Based on common considerations for dive operations, briefly mention potential weaknesses.\n\n" .
		"Overall\n" .
		"Conclude with an overall summary of the business's reputation and suitability for divers of all levels, based on the strengths and potential weaknesses discussed.\n\n" .
		"Start the review with a brief introductory sentence stating the business name and its location." ,
		$name,
		$city
	);
	$model = 'gemini-1.5-pro';
	$api_key = get_option( 'gemini2_api_key', '' );
	if ( ! $api_key ) {
		wp_send_json_error( 'Gemini API key not set in plugin settings.' );
	}
	$url   = sprintf(
		'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
		$model,
		$api_key
	);
	$body = wp_json_encode( [
		'contents' => [
			[
				'parts' => [
					[
						'text' => $prompt_text,
					],
				],
			],
		],
		'generationConfig' => [
			'temperature'   => 0.3,
			'maxOutputTokens' => 2000,
		],
	] );
	$response = wp_remote_post(
		$url,
		[
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 30,
		]
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'API connection error: ' . $response->get_error_message() );
		return;
	}
	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	if ( $response_code !== 200 ) {
		wp_send_json_error( 'API returned error code: ' . $response_code . '. Check your API key and settings.' );
		return;
	}
	$data = json_decode( $response_body, true );
	if ( isset( $data['error'] ) ) {
		wp_send_json_error( $data['error']['message'] );
		return;
	}
	$output = '';
	if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
		$output = $data['candidates'][0]['content']['parts'][0]['text'];
	} else {
		wp_send_json_error( 'No content returned from the API.' );
		return;
	}
	update_post_meta( $post_id, '_gemini_last_results', $output );
	update_post_meta( $post_id, '_gemini_last_searched', current_time( 'mysql' ) );
	wp_send_json_success(
		[
			'message' => 'Description generated successfully.',
			'content' => $output,
		]
	);
}
add_action( 'wp_ajax_gemini_search', 'gemini_ajax_search_handler' );

// 5) Shortcode: [gemini_review post_id="123"]
function gemini_review_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'post_id' => '',
			'class'    => 'gemini-review',
			'header'   => '',
		),
		$atts,
		'gemini_review'
	);
	if ( empty( $atts['post_id'] ) ) {
		return '<p>Error: Post ID required to display the review.</p>';
	}
	$post_id = intval( $atts['post_id'] );
	$content = get_post_meta( $post_id, '_gemini_last_results', true );
	if ( empty( $content ) ) {
		return '<p>No AI-generated review found for this business.</p>';
	}
	$lines           = explode( "\n", $content );
	$output          = '<div class="' . esc_attr( $atts['class'] ) . '">';
	$current_section = '';
	if ( ! empty( $atts['header'] ) ) {
		$output .= '<h2>' . esc_html( $atts['header'] ) . '</h2>';
	}
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( empty( $line ) ) {
			continue;
		}
		if ( in_array( $line, array( 'Overview', 'Strengths', 'Potential Weaknesses', 'Overall' ), true ) ) {
			if ( $current_section !== '' ) {
				$output .= '</div>';
			}
			$output .= '<div class="gemini-section gemini-' . sanitize_title( $line ) . '">';
			$output .= '<h3><strong>' . esc_html( $line ) . '</strong></h3>';
			$current_section = $line;
		} else {
			if ( $current_section === 'Strengths' ) {
				if ( strpos( $line, '-' ) === 0 ) {
					$output .= '<li style="margin-left: 1.5em;">' . esc_html( ltrim( $line, '- ' ) ) . '</li>';
				} else {
					$output .= '<p>' . esc_html( $line ) . '</p>';
				}
			} else {
				$output .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
	}
	if ( $current_section !== '' ) {
		$output .= '</div>';
	}
	$output .= '</div>';
	return $output;
}
add_shortcode( 'gemini_review', 'gemini_review_shortcode' );
add_shortcode( 'gemini_description', 'gemini_review_shortcode' );

// 6) Minimal CSS for review display
function add_gemini_review_minimal_styles() {
	?>
	<style>
		.gemini-review {
			line-height: 1.6;
			font-family: inherit;
			margin: 1.5em 0;
		}
		.gemini-review h3 {
			font-size: 1.3em;
			margin-top: 1.5em;
			margin-bottom: 0.5em;
			font-weight: bold;
		}
		.gemini-review p {
			margin-bottom: 1em;
		}
		.gemini-review ul {
			margin-left: 1em;
			margin-bottom: 1em;
			list-style-type: disc;
		}
		.gemini-review li {
			margin-bottom: 0.5em;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'add_gemini_review_minimal_styles' );

// Add Bulk Button
add_action('restrict_manage_posts', function($post_type) {
    if ($post_type !== 'business') return;
    ?>
    <button type="button" class="button" id="bulk-ai-business">Bulk Add AI Reviews</button>
    <span id="bulk-ai-business-status"></span>
    <?php
});

// enque button
add_action('admin_enqueue_scripts', function($hook) {
    $screen = get_current_screen();
    if ($hook !== 'edit.php' || $screen->post_type !== 'business') return;
    wp_enqueue_script(
        'bulk-ai-business',
        plugins_url('js/bulk-ai-business.js', __FILE__),
        array('jquery'),
        '1.0',
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
        'post_type' => 'business',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_gemini_last_results',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ];
    $businesses = get_posts($args);
    if(empty($businesses)){
        wp_send_json_error('All businesses already have AI reviews.');
    }
    $count = 0;
    $api_key = get_option( 'gemini2_api_key', '' );
    $model = 'gemini-1.5-pro';
    foreach($businesses as $post_id){
        $name = get_post_meta($post_id, '_gpd_display_name', true);
        $city = get_post_meta($post_id, '_gpd_locality', true);
        if(!$name || !$city) continue;
        $prompt_text = sprintf(
            "Write a professional and detailed business review about '%s' located in %s, a prime destination for scuba diving. Structure the review with the following exact section headers, each on a new line and followed immediately by the content for that section:\n\n" .
            "Overview\n" .
            "Provide a general overview of the dive operation, mentioning any certifications, services offered (introductory dives, certifications, excursions), and any unique features like on-site accommodation.\n\n" .
            "Strengths\n" .
            "Detail the key strengths of the business using bullet points where appropriate. Include specific aspects like certification benefits, the range of services offered (list examples), the advantage of on-site accommodation (if applicable), the use of small boats and personalized experiences, positive aspects of their customer service (mentioning staff friendliness and valet service if noted in general reviews), their focus on safety, and the convenience of their location.\n\n" .
            "Potential Weaknesses\n" .
            "Based on common considerations for dive operations, briefly mention potential weaknesses.\n\n" .
            "Overall\n" .
            "Conclude with an overall summary of the business's reputation and suitability for divers of all levels, based on the strengths and potential weaknesses discussed.\n\n" .
            "Start the review with a brief introductory sentence stating the business name and its location." ,
            $name,
            $city
        );
        $url   = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
            $model,
            $api_key
        );
        $body = wp_json_encode( [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt_text,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'   => 0.3,
                'maxOutputTokens' => 2000,
            ],
        ] );
        $response = wp_remote_post(
            $url,
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $body,
                'timeout' => 30,
            ]
        );
        if ( is_wp_error( $response ) ) continue;
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        if ( $response_code !== 200 ) continue;
        $data = json_decode( $response_body, true );
        if ( isset( $data['error'] ) ) continue;
        $output = '';
        if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $output = $data['candidates'][0]['content']['parts'][0]['text'];
        } else {
            continue;
        }
        update_post_meta($post_id, '_gemini_last_results', $output);
        update_post_meta($post_id, '_gemini_last_searched', current_time('mysql'));
        $count++;
    }
    wp_send_json_success("$count business(es) updated.");
});