# Google Places Directory

**Contributors:** TheRev
**Requires at least:** 5.0 (Suggest a version you've tested with)
**Tested up to:** (The latest WordPress version you've tested with, e.g., 6.5)
**Stable tag:** 1.0.0
**License:** GPLv2 or later (Suggest choosing a license)
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to search, import, and manage business listings from the Google Places API, organizing them into a custom post type with relevant taxonomies.

## Description

Google Places Directory allows you to easily populate your WordPress site with business listings. Search for businesses using the Google Places API, filter by radius, and import them directly into a "Business" custom post type. Imported businesses can be categorized by "Destination" and "Region" taxonomies, making them easy to manage and display.

This plugin is ideal for creating local directories, travel guides, or any site that needs to showcase a curated list of businesses.

## Features

*   **Custom Post Type:** Adds a "Business" CPT to store imported listings.
*   **Custom Taxonomies:** Organizes businesses using "Destinations" and "Regions".
*   **Admin Import Interface:**
    *   Search for businesses via Google Places API using keywords and radius.
    *   Paginated search results.
    *   Flags already imported businesses to prevent duplicates.
    *   Select specific businesses to import from search results.
    *   Bulk import selected businesses.
    *   Inline AJAX import for individual businesses directly from the search results.
*   **API Key Configuration:** Securely store your Google Places API key via a dedicated settings page.
*   **Admin Filtering:** Filter the "Business" CPT list table by "Destinations" and "Regions" for easier management.
*   **Data Storage:** Saves key business details as post meta, including:
    *   Google Place ID
    *   Formatted Address
    *   Locality (City)
    *   Latitude & Longitude
    *   Business Types
    *   Rating
    *   Business Status
    *   Google Maps URL

## Installation

1.  **Download:**
    *   Download the plugin `.zip` file from [Source, e.g., GitHub Releases, WordPress.org - if applicable].
    *   Or, clone the repository if you are installing from source.
2.  **Upload to WordPress:**
    *   In your WordPress admin panel, go to "Plugins" > "Add New".
    *   Click "Upload Plugin" and choose the downloaded `.zip` file.
    *   Alternatively, extract the `.zip` file and upload the `google-places-directory` folder to your `wp-content/plugins/` directory.
3.  **Activate:**
    *   Go to "Plugins" in your WordPress admin panel.
    *   Find "Google Places Directory" and click "Activate".

## Configuration

1.  **Obtain a Google Places API Key:**
    *   You'll need a Google Cloud Platform project with the "Places API" enabled.
    *   Create an API key. Make sure to restrict it appropriately for security (e.g., to your website's domain or IP address, and only allow it to access the Places API).
    *   [Link to Google Cloud Console or instructions for getting an API key]
2.  **Enter API Key in WordPress:**
    *   In your WordPress admin panel, navigate to "Businesses" > "Settings".
    *   Enter your Google Places API Key in the provided field.
    *   Click "Save Settings".

## Usage

1.  **Navigate to the Importer:**
    *   In your WordPress admin, go to "Businesses" > "Business Import".
2.  **Search for Businesses:**
    *   **Search Query:** Enter your keywords (e.g., "restaurants in New York", "cafes near Eiffel Tower").
    *   **Radius (km):** Select the search radius around the identified location.
    *   **Results:** Choose how many results per page you'd like to see.
    *   Click the "Search" button.
3.  **Review and Import:**
    *   The search results will be displayed in a table.
    *   Businesses that have already been imported will be marked and have their checkbox disabled.
    *   By default, all new businesses in the results are checked for import. Uncheck any you do not wish to import.
    *   **Bulk Import:** Click the "Import Selected" button at the bottom of the table to import all checked businesses.
    *   **Inline Import:** Click the "Import" button (or refresh icon if it's an update) next to an individual business listing to import or update it immediately via AJAX.
    *   Use the "Prev Page" and "Next Page" buttons to navigate through search results if there are multiple pages.
4.  **View Imported Businesses:**
    *   Imported businesses will appear under the "Businesses" menu item in your WordPress admin.
    *   You can view, edit, or delete them like any other WordPress post.
    *   On the "Businesses" list table, you can use the "All Destinations" and "All Regions" dropdowns to filter the listings.

## Screenshots

*(It's highly recommended to add screenshots here)*

1.  *Screenshot of the Business Import page with search fields.*
    `[alt text](link_to_screenshot_1.png)`
2.  *Screenshot of the search results table showing businesses and import options.*
    `[alt text](link_to_screenshot_2.png)`
3.  *Screenshot of the Settings page for the API key.*
    `[alt text](link_to_screenshot_3.png)`
4.  *Screenshot of the Business CPT list table with Destination/Region filters.*
    `[alt text](link_to_screenshot_4.png)`

## Frequently Asked Questions

*   **Q: Where do I get a Google Places API Key?**
    A: You need to create a project in the Google Cloud Platform console, enable the "Places API", and generate an API key. [Link to Google's documentation]

*   **Q: What data is imported for each business?**
    A: The plugin imports the business name, formatted address, city (for the Destination taxonomy), latitude, longitude, business types, Google rating, business status, and a link to its Google Maps page.

*   **Q: How are "Destinations" and "Regions" determined?**
    A: "Destinations" are automatically populated based on the 'locality' (city) found in the Google Places API data for the business. "Regions" can be managed manually like any other WordPress taxonomy.

## Changelog

### 1.0.0 - YYYY-MM-DD
* Initial release.

## For Developers

### Custom Post Type Details

*   **Slug:** `business`
*   **Supports:** `title`, `editor`, `custom-fields`
*   **Taxonomies:** `destination`, `region`

### Key Meta Fields for `business` CPT:

*   `_gpd_place_id`: (string) The unique Google Place ID.
*   `_gpd_display_name`: (string) Business name.
*   `_gpd_address`: (string) Full formatted address.
*   `_gpd_locality`: (string) City/locality (used for Destination taxonomy).
*   `_gpd_latitude`: (float) Latitude.
*   `_gpd_longitude`: (float) Longitude.
*   `_gpd_types`: (JSON string) Array of business types from Google.
*   `_gpd_rating`: (float) Google rating.
*   `_gpd_business_status`: (string) e.g., "OPERATIONAL".
*   `_gpd_maps_uri`: (URL string) Link to the business on Google Maps.

*(Add any actions or filters developers can hook into if you plan to include them.)*

---

This README provides a solid foundation. Remember to:
*   Replace placeholder dates, links, and screenshot paths.
*   Decide on and specify a license.
*   Review and update the "Tested up to" WordPress version as you test.
*   Add more to the FAQ or Developer sections as the plugin evolves.
