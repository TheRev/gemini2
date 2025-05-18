# Gemini AI Business Review Generator for WordPress

**Requires the Google Places Import Plugin

Version: 2.3.9
Author: TheRev

## Description

The **Gemini AI Business Review Generator** is a WordPress plugin designed to seamlessly integrate with your existing Google Places listings. It leverages the Google Gemini API to automatically generate rich, professional, and informative AI-powered reviews for your listed businesses, specifically tailored for dive operations but adaptable for other business types.

This plugin helps you enhance your business listings with unique content, saving you time and effort in crafting individual reviews. The generated reviews are designed to appeal to both beginners and experienced customers.

## Features

*   **Automatic AI Review Generation**: Utilizes the Google Gemini API to create detailed business reviews.
*   **Single Business Review Generation**: Generate or update an AI review directly from the business post editor screen.
*   **Structured Content**: Generated reviews follow a predefined structure including sections like Introduction, Product Range, Pricing, Customer Service, Training & Certification, Facilities & Atmosphere, Servicing & Repairs, Dive Trips & Events, Accessibility, Summary, and Pros & Cons.
*   **HTML Formatting**: Raw AI output is automatically formatted into clean HTML with appropriate headers, lists (including pros/cons with icons), and paragraphs.
*   **Editor Integration**: Generated HTML content can be directly inserted into the Gutenberg or Classic WordPress editor.
*   **Admin Settings Page**: A dedicated settings page to securely store your Google Gemini API key.
*   **AI Status Admin Column**: Adds a sortable "AI" column to your 'Business' Custom Post Type list in the WordPress admin. This column visually indicates:
    *   If an AI review has been generated.
    *   If the generated review content is found within the main post editor content.
    *   Uses Dashicons for quick status recognition (Generated & In Editor, Generated & Not in Editor, Wrapper Found but Altered, Not Generated).
*   **Shortcode Display**: Use the `[gemini_review post_id="123"]` or `[gemini_description post_id="123"]` shortcode to display the formatted AI review on the frontend.
*   **Customizable Styling**: Comes with a CSS file (`css/gemini-review-styles.css`) for styling the AI-generated reviews, which can be easily overridden by your theme.
*   **Dashicon Integration**: Uses WordPress Dashicons for section headers and list item indicators within the generated review content.

## Prerequisites

1.  **WordPress Installation**: A working WordPress website.
2.  **Business Listings Plugin**: A plugin that manages your business listings as a Custom Post Type (e.g., 'business') and stores the business name and city/locality in post meta fields. This plugin specifically looks for:
    *   `_gpd_display_name` for the business name.
    *   `_gpd_locality` for the business city.
    *(If your plugin uses different meta keys, you will need to adjust them in `gemini2.php`)*.
3.  **Google Gemini API Key**: You need a valid API key from Google AI Studio (formerly MakerSuite) or Google Cloud AI Platform to use the generative capabilities.

## Installation

1.  **Download the Plugin**: Download the `gemini2-business-lookup` plugin files.
2.  **Upload to WordPress**:
    *   Log in to your WordPress admin area.
    *   Navigate to `Plugins` > `Add New`.
    *   Click on the `Upload Plugin` button.
    *   Choose the downloaded ZIP file and click `Install Now`.
3.  **Activate the Plugin**: Once uploaded, activate the "Gemini2 Business Lookup" plugin from your Plugins page.
4.  **Configure API Key**:
    *   Go to `Settings` > `Gemini2`.
    *   Enter your Google Gemini API key in the provided field.
    *   Click `Save Changes`.

## How to Use

### Generating a Single AI Review

1.  Navigate to your 'Business' Custom Post Type.
2.  Edit an existing business listing or create a new one.
3.  Ensure the business has a 'Display Name' and 'Locality' (City) saved in its metadata (as per the prerequisite plugin, e.g., via `_gpd_display_name` and `_gpd_locality` meta fields).
4.  In the post editor, you will find a meta box titled "Gemini AI Business Description".
5.  Click the "Generate Business Description with AI" button.
6.  The plugin will call the Gemini API. Upon success:
    *   A status message will appear.
    *   The raw AI output and formatted HTML will be displayed within the meta box.
    *   The formatted HTML content will be automatically inserted into your WordPress editor (Gutenberg, TinyMCE, or Classic Text Editor).
7.  Review the inserted content and make any desired adjustments.
8.  Save or update your post.

### AI Status Column

In the admin list view for your 'Business' CPT (e.g., `wp-admin/edit.php?post_type=business`):

*   A new column labeled "AI" will be visible.
*   **Green Checkmark (`dashicons-yes-alt`)**: AI content has been generated, and its primary structural elements (wrapper div and identifying HTML comment) are found in the post's main content.
*   **Orange Warning (`dashicons-warning`)**: The AI content wrapper `div` is found, but the internal identifying HTML comment is missing, suggesting the content might have been significantly altered or only partially used.
*   **Red Cross (`dashicons-no-alt`)**: AI content has been generated (raw data exists in post meta), but its primary structural elements are NOT found in the post's main content.
*   **Em Dash (`—`)**: No AI review has been generated for this business yet.
*   The column is sortable, allowing you to quickly group businesses based on their AI review status (primarily by the existence of generated AI data).

### Displaying Reviews on the Frontend

The plugin will automatically place the AI generated review in the post content section...make sure to hit update to save it. You can also use shortcode to place elsewhere.

Use the following shortcode in your posts, pages, or templates where you want to display the AI-generated review:

`[gemini_review post_id="YOUR_BUSINESS_POST_ID"]`

Or its alias:

`[gemini_description post_id="YOUR_BUSINESS_POST_ID"]`

Replace `YOUR_BUSINESS_POST_ID` with the actual ID of the business post.

## Customization

*   **Styling**: Modify `css/gemini-review-styles.css` or add overriding styles to your theme's stylesheet to change the appearance of the reviews.
*   **Prompt Engineering**: The AI generation prompt is located in the `gemini_ajax_search_handler()` function within `gemini2.php`. You can adjust this prompt to better suit different business types or to change the tone and focus of the generated reviews.
*   **Meta Keys**: If your business listing plugin uses different meta keys for business name and city, update the `get_post_meta()` calls in `gemini_ajax_search_handler()` in `gemini2.php`.
*   **HTML Structure**: The HTML formatting logic is within `gemini_format_review_content_to_html()` in `gemini2.php`.

## Important Notes

*   **API Costs**: Use of the Google Gemini API may incur costs depending on your usage and Google's pricing model. Monitor your API usage in your Google Cloud Console or Google AI Studio.
*   **Content Quality**: AI-generated content should always be reviewed and edited for accuracy, tone, and relevance before publishing.
*   **Rate Limiting**: The plugin includes a basic `sleep(1)` for every 3 API calls in the (now removed) bulk processing. If you reinstate or build similar batch features, be mindful of API rate limits.
*   **Plugin Conflicts**: While designed to be compatible, conflicts with other plugins (especially those heavily modifying the editor or admin list tables) are possible. Test thoroughly.

## Troubleshooting

*   **API Key Not Set**: Ensure your API key is correctly entered and saved in `Settings > Gemini2`.
*   **Business Name/City Missing**: The plugin requires business name and city metadata to be present for the target post. Check if these fields are populated by your primary business listing plugin.
*   **API Errors**: Check the status message in the meta box or your browser's developer console for error details from the Gemini API. Common issues include invalid API keys, billing not enabled for the project, or exceeding quotas.
*   **Content Not Inserting**: If content generates but doesn't insert into the editor, check for JavaScript errors in your browser's console. It might indicate an incompatibility with your theme or another plugin.
*   **Dashicons Not Appearing in Column**: Ensure Dashicons are correctly enqueued. The plugin attempts to enqueue them on the necessary admin screens. If they are missing, another plugin or theme might be dequeuing them.

---

This plugin is intended to be a helpful tool for content generation. Always review and refine AI-generated content to ensure it meets your quality standards.
