<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_API_Client {

    private $api_key;
    private $model = 'gemini-2.0-flash'; // Corrected: Using your specified model

    public function __construct() {
        $this->api_key = get_option( 'gemini2_api_key', '' );
    }

    /**
     * Set the API key.
     * Useful if the key is retrieved after instantiation or for testing.
     * @param string $api_key The Google Gemini API key.
     */
    public function set_api_key( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Get the current API key.
     * @return string The API key.
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Generate content using the Gemini API.
     *
     * @param string $prompt_text The prompt to send to the API.
     * @return array ['success' => bool, 'data' => string|array, 'error_message' => string|null]
     */
    public function generate_content( $prompt_text ) {
        if ( ! $this->api_key ) {
            return ['success' => false, 'data' => null, 'error_message' => 'Gemini API key not set.'];
        }

        // Corrected: Using v1 endpoint as per your original plugin
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
            $this->model,
            $this->api_key
        );

        $body_params = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt_text ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.6,
                'maxOutputTokens' => 2048,
            ],
            'safetySettings' => [
                [ 'category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE', ],
                [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE', ],
            ]
        ];

        $body = wp_json_encode( $body_params );

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            return ['success' => false, 'data' => null, 'error_message' => 'API connection error: ' . $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( $response_code !== 200 ) {
            $api_error_message = 'API error code: ' . $response_code;
            if (isset($data['error']['message'])) {
                $api_error_message .= '. Message: ' . $data['error']['message'];
            } else {
                $api_error_message .= '. Response: ' . $response_body;
            }
            return ['success' => false, 'data' => $data, 'error_message' => $api_error_message];
        }

        if ( isset( $data['error'] ) ) {
             return ['success' => false, 'data' => $data, 'error_message' => 'API Error: ' . (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']))];
        }

        // The v1 API response structure for candidates might be slightly different (e.g. no 'promptFeedback')
        // but the path to the text content should be similar.
        // Adjust if necessary based on actual v1 response structure for gemini-2.0-flash
        $raw_ai_output = '';
        if ( !empty($data['candidates'][0]['content']['parts'][0]['text']) ) {
            $raw_ai_output = $data['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
            // Check if 'finishReason' exists and is not 'STOP'. Some errors might also appear here.
            $error_detail = 'Content generation issue. Reason: ' . $data['candidates'][0]['finishReason'];
            // Potentially add more details from response if available, e.g., safetyRatings
            if (isset($data['candidates'][0]['safetyRatings'])) {
                $error_detail .= ' Safety Ratings: ' . json_encode($data['candidates'][0]['safetyRatings']);
            }
            return ['success' => false, 'data' => $data, 'error_message' => $error_detail];
        } elseif (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
             // This case handles if 'parts' is empty or 'text' is missing, but no explicit error.
            return ['success' => false, 'data' => $data, 'error_message' => 'No text content found in API response candidate.'];
        } else {
            // Fallback for other unexpected structures or if 'candidates' is empty.
            return ['success' => false, 'data' => $data, 'error_message' => 'No content from API or unexpected response structure. Full response: ' . $response_body];
        }


        return ['success' => true, 'data' => $raw_ai_output, 'error_message' => null];
    }
}
?>