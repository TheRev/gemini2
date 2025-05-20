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
        $output = '';
        $in_list_type = null; // null, 'pros', 'cons', 'generic'
        $current_section_key = null;
        $first_content_line_processed = false;
        $list_buffer = [];

        foreach ( $lines as $line ) {
            $trimmed_line = trim( $line );
            if ( empty( $trimmed_line ) ) {
                continue;
            }
            // Skip Markdown horizontal rule remnant
            if ($trimmed_line === '---') {
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
                // Output any buffered list before new section
                if ($in_list_type && count($list_buffer)) {
                    $output .= $this->render_gutenberg_list_block($list_buffer);
                    $list_buffer = [];
                    $in_list_type = null;
                }
                $header_details = $this->section_headers_map[$cleaned_for_header_check];
                $is_header_match = true;
                $current_section_key = $cleaned_for_header_check;
            }

            if ( $is_header_match && $header_details ) {
                $level = 3; // Default to h3, but you can adjust if you want to support other levels
                $icon_prefix = $header_details['icon'] ? esc_html($header_details['icon']) . ' ' : '';
                $output .= '<!-- wp:heading {"level":' . $level . '} --><h' . $level . '>' . $icon_prefix . esc_html( $header_details['title'] ) . '</h' . $level . '><!-- /wp:heading -->' . "\n";
                // If this is the Pros & Cons section, set a flag to output plain text only
                if ($current_section_key === 'Pros & Cons') {
                    $in_list_type = 'proscons_plain';
                    continue;
                }
            }
            elseif ($in_list_type === 'proscons_plain') {
                // For Pros & Cons, output check mark for pros and X for cons, using unicode
                if (preg_match('/^(✔️ |✅ |- |\* )/', $trimmed_line)) {
                    $plain_text = trim(preg_replace('/^(✔️ |✅ |- |\* )/', '', $trimmed_line));
                    $plain_text = strip_tags($plain_text);
                    if ($plain_text !== '') {
                        $output .= '<!-- wp:paragraph -->✔️ ' . esc_html($plain_text) . '<!-- /wp:paragraph -->' . "\n";
                    }
                } elseif (preg_match('/^(❌ |✖️ )/', $trimmed_line)) {
                    $plain_text = trim(preg_replace('/^(❌ |✖️ )/', '', $trimmed_line));
                    $plain_text = strip_tags($plain_text);
                    if ($plain_text !== '') {
                        $output .= '<!-- wp:paragraph -->❌ ' . esc_html($plain_text) . '<!-- /wp:paragraph -->' . "\n";
                    }
                } else {
                    $plain_text = strip_tags($trimmed_line);
                    if ($plain_text !== '') {
                        $output .= '<!-- wp:paragraph -->' . esc_html($plain_text) . '<!-- /wp:paragraph -->' . "\n";
                    }
                }
                continue;
            }
            elseif ( strpos( $trimmed_line, '-' ) === 0 || strpos( $trimmed_line, '*' ) === 0 ) {
                if ($in_list_type !== 'generic' && count($list_buffer)) {
                    $output .= $this->render_gutenberg_list_block($list_buffer);
                    $list_buffer = [];
                }
                $in_list_type = 'generic';
                $list_item_content = ltrim( $trimmed_line, '-* ' );
                $list_item_content = $this->format_text_emphasis($list_item_content);
                $list_buffer[] = $list_item_content;
            }
            else {
                if ($in_list_type && count($list_buffer)) {
                    $output .= $this->render_gutenberg_list_block($list_buffer);
                    $list_buffer = [];
                    $in_list_type = null;
                }
                $paragraph_text = preg_replace('/^\d+\.\s+/', '', $trimmed_line);
                $paragraph_text = $this->format_text_emphasis($paragraph_text);
                $output .= '<!-- wp:paragraph -->' . $paragraph_text . "<!-- /wp:paragraph -->\n";
            }
        }

        if ($in_list_type && count($list_buffer)) {
            $output .= $this->render_gutenberg_list_block($list_buffer);
        }
        if ($output === '') {
            $output = '';
        }
        $output .= '<!-- Finished Gemini2 Content Formatting -->';
        return $output;
    }

    private function render_gutenberg_list_block($items) {
        // Output a Gutenberg list block as valid HTML for maximum compatibility
        if (empty($items)) return '';
        $block = "<!-- wp:list -->\n<ul>\n";
        foreach ($items as $item) {
            $item = strip_tags($item);
            $item = trim($item);
            if ($item !== '') {
                $block .= '<li>' . esc_html($item) . '</li>' . "\n";
            }
        }
        $block .= "</ul>\n<!-- /wp:list -->\n";
        return $block;
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
