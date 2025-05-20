<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Gemini2_Content_Formatter {

    private $section_headers_map;

    public function __construct() {
        // Unicode characters for section headers
        $this->section_headers_map = [
            'Introduction'             => ['icon' => 'ℹ️', 'title' => 'Introduction'],
            'Product Range'            => ['icon' => '🛒', 'title' => 'Product Range'],
            'Pricing'                  => ['icon' => '💲', 'title' => 'Pricing'],
            'Customer Service'         => ['icon' => '🤝', 'title' => 'Customer Service'],
            'Training & Certification' => ['icon' => '🎓', 'title' => 'Training & Certification'],
            'Facilities & Atmosphere'  => ['icon' => '✨', 'title' => 'Facilities & Atmosphere'],
            'Servicing & Repairs'      => ['icon' => '🛠️', 'title' => 'Servicing & Repairs'],
            'Dive Trips & Events'      => ['icon' => '🤿️', 'title' => 'Dive Trips & Events'],
            'Accessibility'            => ['icon' => '♿', 'title' => 'Accessibility'],
            'Summary'                  => ['icon' => '⭐', 'title' => 'Summary'],
            'Pros & Cons'              => ['icon' => '⚖️', 'title' => 'Pros & Cons']
        ];
    }

    /**
     * Formats raw AI content (Markdown-like) to HTML.
     *
     * @param string $raw_content The raw text content from the AI.
     * @return string The formatted HTML.
     */
    public function format( $raw_content ) {
        if ( empty( $raw_content ) ) {
            return '<!-- Raw content was empty -->';
        }

        $lines = explode( "\n", $raw_content );
        $html_output = '<!-- Starting Gemini2 Content Formatting -->';
        $in_list_type = null; // null, 'pros', 'cons', 'generic'
        $current_section_key = null;
        $first_content_line_processed = false;

        foreach ( $lines as $line ) {
            $trimmed_line = trim( $line );
            if ( empty( $trimmed_line ) ) {
                continue;
            }

            if (!$first_content_line_processed && (strpos($trimmed_line, '# ') === 0 || strpos($trimmed_line, '## ') === 0) ) {
                $potential_main_title_text = trim(preg_replace('/^#+\s*/', '', $trimmed_line));
                $cleaned_potential_title = $this->clean_header_text($potential_main_title_text);
                if (!isset($this->section_headers_map[$cleaned_potential_title])) {
                    $first_content_line_processed = true;
                    continue; 
                }
            }
            $first_content_line_processed = true;

            $cleaned_for_header_check = $this->clean_header_text($trimmed_line);
            $is_header_match = false;
            $header_details = null;

            if (isset($this->section_headers_map[$cleaned_for_header_check])) {
                $header_details = $this->section_headers_map[$cleaned_for_header_check];
                $is_header_match = true;
                $current_section_key = $cleaned_for_header_check;
            }

            if ( $is_header_match && $header_details ) {
                if ( $in_list_type ) {
                    $html_output .= "</ul>\n";
                    $in_list_type = null;
                }
                // Prepend Unicode icon directly, ensure space after icon
                $icon_prefix = $header_details['icon'] ? esc_html($header_details['icon']) . ' ' : '';
                $html_output .= '<h3>' . $icon_prefix . esc_html( $header_details['title'] ) . "</h3>\n";
            }
            // Pros & Cons list items - keep the Unicode symbol as part of the text
            elseif ($current_section_key === 'Pros & Cons' && (strpos($trimmed_line, '✔️ ') === 0 || strpos($trimmed_line, '✅ ') === 0 )) {
                if ($in_list_type !== 'pros') {
                    if ($in_list_type) $html_output .= "</ul>\n";
                    $html_output .= '<ul class="gemini-pros-list">' . "\n";
                    $in_list_type = 'pros';
                }
                $item_content = $this->format_text_emphasis($trimmed_line);
                $html_output .= '<li class="gemini-pro-item">' . $item_content . "</li>\n";
            }
            elseif ($current_section_key === 'Pros & Cons' && (strpos($trimmed_line, '❌ ') === 0 || strpos($trimmed_line, '✖️ ') === 0)) {
                if ($in_list_type !== 'cons') {
                    if ($in_list_type) $html_output .= "</ul>\n";
                    $html_output .= '<ul class="gemini-cons-list">' . "\n";
                    $in_list_type = 'cons';
                }
                $item_content = $this->format_text_emphasis($trimmed_line);
                $html_output .= '<li class="gemini-con-item">' . $item_content . "</li>\n";
            }
            elseif ( strpos( $trimmed_line, '-' ) === 0 || strpos( $trimmed_line, '*' ) === 0 ) {
                if ($in_list_type === 'pros' || $in_list_type === 'cons') {
                     $html_output .= "</ul>\n";
                     $in_list_type = null; 
                }
                if ( $in_list_type !== 'generic' ) {
                    if ($in_list_type) $html_output .= "</ul>\n"; 
                    $html_output .= '<ul class="gemini-generic-list">' . "\n";
                    $in_list_type = 'generic';
                }
                $list_item_content = ltrim( $trimmed_line, '-* ' );
                $list_item_content = $this->format_text_emphasis($list_item_content);
                $html_output .= '<li>' . $list_item_content . "</li>\n"; 
            }
            else {
                if ( $in_list_type ) {
                    $html_output .= "</ul>\n";
                    $in_list_type = null;
                }
                $paragraph_text = preg_replace('/^\d+\.\s+/', '', $trimmed_line);
                $paragraph_text = $this->format_text_emphasis($paragraph_text);
                $html_output .= '<p>' . $paragraph_text . "</p>\n";
            }
        }

        if ( $in_list_type ) {
            $html_output .= "</ul>\n";
        }
        $html_output .= '<!-- Finished Gemini2 Content Formatting -->';
        return $html_output;
    }

    private function clean_header_text( $text ) {
        $cleaned = $text;
        $cleaned = preg_replace('/^(?:##\s*|#\s*)?/', '', $cleaned);
        $cleaned = preg_replace('/^(?:\d+\.\s*)?/', '', $cleaned);    
        $cleaned = preg_replace('/^\*\*(.*?)\*\*$/', '$1', $cleaned); 
        $cleaned = preg_replace('/^__(.*?)__$/s', '$1', $cleaned);   
        return trim($cleaned);
    }

    private function format_text_emphasis( $text ) {
        // Replace **text** or __text__ with <strong>text</strong>
        $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/s', '<strong>$1$2</strong>', $text);
        return $text;
    }
}
?>
