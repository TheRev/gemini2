# Gemini AI Business Review Generator for WordPress

**Requires the Google Places Import Plugin (or a similar plugin providing business name and locality meta fields)**

Version: 3.0.0
Author: TheRev

## Description

The **Gemini AI Business Review Generator** is a WordPress plugin designed to seamlessly integrate with your existing business listings (e.g., from Google Places). It leverages the Google Gemini API to automatically generate rich, structured reviews for businesses, particularly tailored for operations like dive shops.

This plugin helps you enhance your business listings with unique content, saving you time and effort in crafting individual reviews. The generated reviews are designed to appeal to both beginners and experienced customers, with a focus on providing informative and engaging content.

## Features

*   **Automatic AI Review Generation**: Utilizes the Google Gemini API (specifically the `gemini-2.0-flash` model) to create detailed business reviews.
*   **Single Business Review Generation**: Generate or update an AI review directly from the business post editor screen.
*   **Structured Content**: Generated reviews follow a predefined structure including sections like Introduction, Product Range, Customer Service, etc., each potentially headed by a relevant Unicode icon.
*   **HTML Formatting**: Raw AI output is automatically formatted into clean HTML with appropriate headers (using Unicode icons), lists (including pros/cons with "✔️" and "❌" Unicode icons), and paragraphs.
*   **Editor Integration**: Generated HTML content can be directly inserted into the Gutenberg or Classic WordPress editor.
*   **Admin Settings Page**: A dedicated settings page (`Settings > Gemini2`) to securely store your Google Gemini API key.
*   **AI Status Admin Column (Conceptual - Implementation details may vary)**: Adds a sortable "AI" column to your 'Business' Custom Post Type list in the WordPress admin. This column visually indicates:
    *   If an AI review has been generated.
    *   If the generated review content is found within the main post editor content.
    *   Uses Dashicons for quick status recognition (e.g., Generated & In Editor, Generated & Not in Editor, Not Generated).
*   **Shortcode Display**: Use the `[gemini_review post_id="123"]` or `[gemini_description post_id="123"]` shortcode to display the formatted AI review on the frontend.
*   **Customizable Styling**: Comes with a CSS file (`css/gemini-review-styles.css`) for styling the AI-generated reviews, which can be easily overridden by your theme. Styles are also applied in the editor for a more WYSIWYG experience.
*   **Unicode Icon Integration**: Uses Unicode characters for section headers and list item indicators within the generated review content for a modern look.

## Prerequisites

1.  **WordPress Installation**: A working WordPress website.
2.  **Business Listings Plugin**: A plugin that manages your business listings as a Custom Post Type (e.g., 'business') and stores the business name and city/locality in post meta fields. This plugin is designed to work with meta keys:
    *   `_gpd_display_name` for the business name.
    *   `_gpd_locality` for the business city.
    *(If your plugin uses different meta keys, you will need to adjust them in `includes/class-gemini2-ajax-handler.php`)*.
3.  **Google Gemini API Key**: You need a valid API key from Google AI Studio or Google Cloud AI Platform with the Gemini API enabled.

## Installation

1.  **Download the Plugin**: Download the plugin files (e.g., as a ZIP archive).
2.  **Upload to WordPress**:
    *   Log in to your WordPress admin area.
    *   Navigate to `Plugins` > `Add New`.
    *   Click on the `Upload Plugin` button.
    *   Choose the downloaded ZIP file and click `Install Now`.
3.  **Activate the Plugin**: Once uploaded, activate the "Gemini2 AI Business Lookup (OOP)" plugin from your Plugins page.
4.  **Configure API Key**:
    *   Go to `Settings` > `Gemini2`.
    *   Enter your Google Gemini API key in the provided field.
    *   Click `Save Changes`.

## How to Use

### Generating a Single AI Review

1.  Navigate to your 'Business' Custom Post Type (or the CPT used by your business listings plugin).
2.  Edit an existing business listing or create a new one.
3.  Ensure the business has a 'Display Name' and 'Locality' (City) saved in its metadata (e.g., `_gpd_display_name` and `_gpd_locality` meta fields).
4.  In the post editor, you will find a meta box titled "Gemini AI Business Description".
5.  Click the "Generate Business Description with AI" button.
6.  The plugin will call the Gemini API. Upon success:
    *   A status message will appear.
    *   The raw AI output and formatted HTML will be displayed within the meta box.
    *   The formatted HTML content will be automatically inserted into your WordPress editor.
7.  Review the inserted content and make any desired adjustments.
8.  Save or update your post.

### AI Status Column (If Implemented)

In the admin list view for your 'Business' CPT (e.g., `wp-admin/edit.php?post_type=business`):

*   A new column labeled "AI" may be visible.
*   Icons (likely Dashicons) would indicate the status of AI content generation and integration for each post.
*   The column might be sortable.

### Displaying Reviews on the Frontend

The plugin is designed to insert the AI-generated review directly into the post's main content area. After generation, ensure you **Update** or **Publish** the post to save the content.

Alternatively, you can use the following shortcode in your posts, pages, or templates where you want to display the AI-generated review:

`[gemini_review post_id="YOUR_BUSINESS_POST_ID"]`

Or its alias:

`[gemini_description post_id="YOUR_BUSINESS_POST_ID"]`

Replace `YOUR_BUSINESS_POST_ID` with the actual ID of the business post. The necessary styles will be enqueued automatically if the shortcode is detected or if viewing a single 'business' post type.

## Customization

*   **Styling**: Modify `css/gemini-review-styles.css` or add overriding styles to your theme's stylesheet to change the appearance of the reviews.
*   **Prompt Engineering**: The AI generation prompt is located in the `handle_generate_description()` method within `includes/class-gemini2-ajax-handler.php`. You can adjust this prompt to better suit different business types or desired output styles.
*   **Meta Keys**: If your business listing plugin uses different meta keys for business name and city, update the `get_post_meta()` calls in `handle_generate_description()` in `includes/class-gemini2-ajax-handler.php`.
*   **HTML Structure & Icons**: The HTML formatting logic and the mapping of section headers to Unicode icons are primarily managed within `includes/class-gemini2-content-formatter.php` (this file's content was not fully provided but is referenced).

## Important Notes

*   **API Costs**: Use of the Google Gemini API may incur costs depending on your usage and Google's pricing model. Monitor your API usage in your Google Cloud Console or Google AI Studio.
*   **Content Quality**: AI-generated content should **always** be reviewed and edited for accuracy, tone, and relevance before publishing. It's a tool to assist, not replace, human oversight.
*   **Rate Limiting**: Be mindful of API rate limits if you plan to generate reviews in bulk. The current plugin focuses on single generation.
*   **Plugin Conflicts**: While designed to be compatible, conflicts with other plugins (especially those heavily modifying the editor or AJAX handling) are possible. Test thoroughly.

## Troubleshooting

*   **API Key Not Set**: Ensure your API key is correctly entered and saved in `Settings > Gemini2`.
*   **Business Name/City Missing**: The plugin requires business name and city metadata to be present for the target post. Check if these fields are populated.
*   **API Errors**: Check the status message in the meta box or your browser's developer console for error details from the Gemini API. Common issues include invalid API keys, billing not enabled for the associated Google Cloud project, or API rate limits being hit. The `includes/class-gemini2-api-client.php` has detailed error handling.
*   **Content Not Inserting**: If content generates but doesn't insert into the editor, check for JavaScript errors in your browser's console. It might indicate an incompatibility with your theme or another plugin.
*   **Styling Issues**: Ensure `css/gemini-review-styles.css` is loading on the frontend. Clear caches (browser, plugin, server) if styles don't appear or update.

---

This plugin is intended to be a helpful tool for content generation. Always review and refine AI-generated content to ensure it meets your quality standards and accurately represents the businesses.
