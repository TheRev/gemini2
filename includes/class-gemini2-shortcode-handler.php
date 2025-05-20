<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_Shortcode_Handler {

    private $content_formatter;

    public function __construct( Gemini2_Content_Formatter $content_formatter ) {
        $this->content_formatter = $content_formatter;
    }

    /**
     * Initialize WordPress hooks for shortcodes.
     */
    public function init_hooks() {
        add_shortcode( 'gemini_review', array( $this, 'render_review_shortcode' ) );
        // Keep the alias if you want to maintain backward compatibility or alternative name
        add_shortcode( 'gemini_description', array( $this, 'render_review_shortcode' ) );
    }

    /**
     * Render the [gemini_review] shortcode.
     *
     * Attributes:
     *  post_id (int) - Required. The ID of the post to get the review from.
     *  class   (string) - Optional. Custom CSS class for the wrapper div. Defaults to 'gemini-review'.
     *  header  (string) - Optional. A header text to display above the review content.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shortcode.
     */
    public function render_review_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'post_id' => '',
                'class'   => 'gemini-review', // Default class
                'header'  => '',
            ),
            $atts,
            'gemini_review' // Shortcode tag for context in filters if any
        );

        if ( empty( $atts['post_id'] ) ) {
            return '<p class="gemini-error">Error: Post ID is required for the Gemini review shortcode.</p>';
        }

        $post_id = intval( $atts['post_id'] );
        if ( ! $post_id ) {
            return '<p class="gemini-error">Error: Invalid Post ID provided.</p>';
        }

        // Retrieve the raw AI-generated content from post meta
        $raw_content = get_post_meta( $post_id, '_gemini_last_results', true );

        if ( empty( $raw_content ) ) {
            // Check if the post itself exists to give a more specific message
            if ( get_post_status( $post_id ) ) {
                return '<p class="gemini-notice">No AI review content found for this post.</p>';
            } else {
                return '<p class="gemini-error">Error: Post with ID ' . esc_html( $post_id ) . ' not found.</p>';
            }
        }

        // Format the raw content using the Content Formatter class
        $core_html_content = $this->content_formatter->format( $raw_content );

        // Build the output HTML
        $output_html = '<div class="' . esc_attr( $atts['class'] ) . '">';

        if ( ! empty( $atts['header'] ) ) {
            $output_html .= '<h2 class="gemini-review-shortcode-header">' . esc_html( $atts['header'] ) . '</h2>';
        }

        $output_html .= $core_html_content;
        $output_html .= '</div>';

        return $output_html;
    }
}
?>
