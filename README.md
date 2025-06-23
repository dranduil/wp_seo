# WP SEO Meta Descriptions & Schema

A WordPress plugin to enhance your website's SEO by adding comprehensive meta tags and JSON-LD structured data.

## Features

### 1. SEO Meta Box for Posts & Pages
Easily manage crucial SEO elements directly from the post/page editor:
- **SEO Title:** Customize the title tag for search engine results.
- **Meta Description:** Craft compelling meta descriptions to improve click-through rates.
- **Meta Keywords:** Specify relevant keywords (though their impact on modern SEO is minimal, the option is available).

### 2. Social Media Optimization
Control how your content appears when shared on social platforms:
- **Open Graph Tags:**
  - `og:title`
  - `og:description`
  - `og:image`
  - `og:type`
  - `og:url`
- **Twitter Card Tags:**
  - `twitter:card`
  - `twitter:title`
  - `twitter:description`
  - `twitter:image`

### 3. Comprehensive JSON-LD Structured Data
Implement various schema types to provide search engines with detailed information about your content:
- **Supported Schema Types:**
  - **Article/NewsArticle:** Default for posts. Includes properties like headline, author, datePublished, dateModified, image, publisher (Organization), and mainEntityOfPage.
  - **WebPage:** Default for pages. Includes basic webpage information.
  - **Product:** For e-commerce. Includes name, image, description, SKU, brand, offers (price, currency, availability, priceValidUntil), aggregateRating, and review.
  - **Recipe:** For food blogs. Includes name, image, description, recipeIngredient, recipeInstructions, prepTime, cookTime, recipeYield, recipeCategory, recipeCuisine, keywords, aggregateRating, and video.
  - **DiscussionForumPosting:** For forum topics. Includes headline, author, datePublished, image, and mainEntityOfPage.
  - **FAQPage:** For pages with frequently asked questions. Structures questions and their accepted answers.
  - **Organization:** Define your organization's details, including name, URL, and logo.
  - **BreadcrumbList:** Output structured breadcrumbs for improved site navigation in search results.
- **Speakable Schema:**
  - Automatically adds `speakable` schema markup for 'Article' or 'NewsArticle' types, identifying sections suitable for text-to-speech playback. Configurable via CSS selectors.

### 4. SEO Analytics Dashboard
- **Google Search Console Integration:** View your site's search performance data directly in WordPress.
- **Access Analytics:** Navigate to `https://your-site-domain/wp-admin/tools.php?page=wpsmd-analytics` or use the "SEO Analytics" menu item under Tools.
- **Performance Metrics:** Track impressions, clicks, CTR, and average position for your content.

### 5. OpenAI Integration (Optional)
- **Meta Description Suggestions:** Leverage the OpenAI API to automatically generate meta description suggestions based on your post content (requires a valid OpenAI API key).

### 6. Global Settings Page
- **Centralized API Key Management:** A dedicated settings page under "Settings > WP SEO Meta" to securely store and manage your OpenAI API Key globally, rather than per post.

### 7. Efficient Data Storage
- **Post Meta:** All post-specific SEO data (titles, descriptions, schema details, etc.) is stored efficiently as post meta, associated with each individual post or page. Meta keys are prefixed with `_wpsmd_`.
- **WordPress Options:** The global OpenAI API key is stored securely in the `wp_options` table.

### 8. Dynamic & Conditional Logic
- **Contextual Fields:** The SEO meta box intelligently displays only the relevant input fields based on the schema type selected for a particular post or page.
- **Smart Saving:** Data is saved or deleted from post meta based on the chosen schema type and whether specific fields are filled, ensuring a clean database.

### 9. Fallback Mechanisms
- **Sensible Defaults:** The plugin provides intelligent fallbacks if specific SEO fields are not manually filled:
  - Post title is used for SEO title.
  - Post excerpt (or an auto-generated one) is used for the meta description.
  - The post's featured image is used for Open Graph and Twitter Card images.
  - The site icon (favicon) is used for the Organization logo if a specific logo URL isn't provided.

## Installation

1.  Download the plugin ZIP file.
2.  In your WordPress admin panel, go to "Plugins > Add New".
3.  Click "Upload Plugin" and choose the downloaded ZIP file.
4.  Activate the plugin through the 'Plugins' menu in WordPress.
5.  (Optional) Navigate to "Settings > WP SEO Meta" to add your OpenAI API Key for meta description suggestions.

## Usage

-   When editing a post or page, you will find the "WP SEO Meta" box below the main content editor.
-   Fill in the desired SEO fields (Title, Description, Keywords).
-   Select a Schema Type from the dropdown to enable specific structured data fields.
-   Fill in the schema-specific details.
-   Save/Update the post or page.

## Uninstallation

Deactivating and deleting the plugin via the WordPress admin panel will remove its functionality. By default, the SEO meta data stored for each post/page will remain in your database (`wp_postmeta` table) unless a specific cleanup routine is added in the future.

---