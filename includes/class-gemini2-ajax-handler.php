<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_AJAX_Handler {

    private $api_client;
    private $content_formatter;

    public function __construct( Gemini2_API_Client $api_client, Gemini2_Content_Formatter $content_formatter ) {
        $this->api_client = $api_client;
        $this->content_formatter = $content_formatter;
    }

    /**
     * Initialize WordPress hooks for AJAX actions.
     */
    public function init_hooks() {
        add_action( 'wp_ajax_gemini2_generate_description', array( $this, 'handle_generate_description' ) );
    }

    /**
     * AJAX handler to generate business description via Gemini API.
     */
    public function handle_generate_description() {
        // Verify nonce
        check_ajax_referer( 'gemini_ajax_nonce', 'nonce' ); // Matches nonce in Gemini2_Admin and JS

        if ( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ) {
            wp_send_json_error( array( 'message' => 'Error: Post ID not provided.' ) );
            return;
        }
        $post_id = intval( $_POST['post_id'] );
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Error: Invalid Post ID.' ) );
            return;
        }

        // Get business name and city from post meta
        // These meta keys are based on the original plugin's usage.
        // Ensure these are the correct meta keys for your 'business' CPT.
        $business_name = get_post_meta( $post_id, '_gpd_display_name', true );
        $business_city = get_post_meta( $post_id, '_gpd_locality', true );

        if ( ! $business_name || ! $business_city ) {
            wp_send_json_error( array( 'message' => 'Business name or city meta data not found for this post.' ) );
            return;
        }

        // Construct the prompt (similar to the original plugin)
        // Consider making this prompt template configurable or more dynamic
        $prompt_text_template = <<<PROMPT
You are an AI assistant tasked with writing a friendly, professional, and informative scuba shop review. Do not use I or any first person pronouns.
The review should appeal to both beginner and experienced divers and be approximately 400-500 words.
Your output should be in Markdown-like format. Use standard Markdown for headers, lists, and emphasis.
Specifically for Pros & Cons, use "✔️ Pro Item" or "❌ Con Item" for each point.

**EXPECTED AI OUTPUT STRUCTURE (Use these exact plain text headers, without # or ##):**
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
        $prompt_text = sprintf( $prompt_text_template, $business_name, $business_city );

        // Call the API client
        $api_response = $this->api_client->generate_content( $prompt_text );

        if ( ! $api_response['success'] ) {
            wp_send_json_error( array( 'message' => $api_response['error_message'], 'details' => $api_response['data'] ) );
            return;
        }

        $raw_ai_output = $api_response['data'];

        // Save raw results and timestamp
        update_post_meta( $post_id, '_gemini_last_results', $raw_ai_output );
        update_post_meta( $post_id, '_gemini_last_searched', current_time( 'mysql' ) );

        // Format content for editor
        $unwrapped_html_content = $this->content_formatter->format( $raw_ai_output );
        if (trim($unwrapped_html_content) !== '') {
            $html_content_for_editor = '<div class="gemini-review">' . "\n" . $unwrapped_html_content . "\n" . '</div>';
        } else {
            $html_content_for_editor = '';
        }

        wp_send_json_success( array(
            'message'      => 'Description generated successfully!',
            'raw_content'  => $raw_ai_output,
            'html_content' => $html_content_for_editor
        ) );
    }
}
?>
