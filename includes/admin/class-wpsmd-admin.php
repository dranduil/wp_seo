<?php
/**
 * Admin-specific functionality for the WP SEO Meta Descriptions plugin.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPSMD_Admin {

    /**
     * Initialize the admin hooks.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_seo_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_wpsmd_generate_seo_content', array( $this, 'ajax_generate_seo_content' ) );
        add_action( 'admin_menu', array( $this, 'add_bulk_seo_menu' ) );
        add_action( 'wp_ajax_wpsmd_get_posts', array( $this, 'get_posts_for_bulk_editor' ) );
        add_action( 'wp_ajax_wpsmd_save_bulk_meta', array( $this, 'save_bulk_meta' ) );
        add_action( 'wp_ajax_wpsmd_save_cpt_templates', array( $this, 'save_cpt_templates' ) );
        add_action( 'wp_ajax_wpsmd_export_settings', array( $this, 'export_settings' ) );
        add_action( 'wp_ajax_wpsmd_import_settings', array( $this, 'import_settings' ) );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Enqueue on post edit screens
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            wp_enqueue_script( 'wpsmd-admin-js', plugin_dir_url( __FILE__ ) . '../../assets/js/admin.js', array( 'jquery' ), WPSMD_VERSION, true );
            wp_localize_script( 'wpsmd-admin-js', 'wpsmd_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce('wpsmd_ajax_nonce')
            ));
            wp_enqueue_style( 'wpsmd-admin-css', plugin_dir_url( __FILE__ ) . '../../assets/css/admin.css', array(), WPSMD_VERSION );
        }

        // Enqueue on our custom bulk SEO page
        if ( 'tools_page_wpsmd-bulk-seo' === $hook ) {
            wp_enqueue_script( 'wpsmd-bulk-editor-js', plugin_dir_url( __FILE__ ) . '../../assets/js/bulk-editor.js', array( 'jquery' ), WPSMD_VERSION, true );
            wp_localize_script( 'wpsmd-bulk-editor-js', 'wpsmdBulkEditor', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce('wpsmd_ajax_nonce'),
                'editText' => __('Edit', 'wp-seo-meta-descriptions'),
                'viewText' => __('View', 'wp-seo-meta-descriptions'),
                'seoTitlePlaceholder' => __('Enter SEO title', 'wp-seo-meta-descriptions'),
                'metaDescPlaceholder' => __('Enter meta description', 'wp-seo-meta-descriptions'),
                'canonicalPlaceholder' => __('Enter canonical URL', 'wp-seo-meta-descriptions'),
                'errorLoading' => __('Error loading posts', 'wp-seo-meta-descriptions'),
                'saveSuccess' => __('Changes saved successfully', 'wp-seo-meta-descriptions'),
                'saveError' => __('Error saving changes', 'wp-seo-meta-descriptions'),
                'templateSaveSuccess' => __('Templates saved successfully', 'wp-seo-meta-descriptions'),
                'templateSaveError' => __('Error saving templates', 'wp-seo-meta-descriptions'),
                'exportError' => __('Error exporting settings', 'wp-seo-meta-descriptions'),
                'importSuccess' => __('Settings imported successfully', 'wp-seo-meta-descriptions'),
                'importError' => __('Error importing settings', 'wp-seo-meta-descriptions'),
                'selectFileError' => __('Please select a file to import', 'wp-seo-meta-descriptions'),
                'invalidFileError' => __('Invalid settings file', 'wp-seo-meta-descriptions')
            ));
            wp_enqueue_style( 'wpsmd-bulk-editor-css', plugin_dir_url( __FILE__ ) . '../../assets/css/bulk-editor.css', array(), WPSMD_VERSION );
        }
    }

    /**
     * AJAX handler for generating SEO content.
     */
    public function ajax_generate_seo_content() {
        check_ajax_referer( 'wpsmd_ajax_nonce', 'nonce' );

        $options = get_option( 'wpsmd_options' );
        $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
        $selected_model = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-3.5-turbo'; // Default model

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set in settings.', 'wp-seo-meta-descriptions' ) ) );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( $_POST['content_type'] ) : ''; // 'title', 'description', 'keywords'

        if ( ! $post_id || ! $content_type ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-seo-meta-descriptions' ) ) );
            return;
        }

        $post_content = get_post_field( 'post_content', $post_id );
        $post_title = get_the_title( $post_id );

        if ( empty( $post_content ) && empty( $post_title ) ) {
            wp_send_json_error( array( 'message' => __( 'Post content or title is empty.', 'wp-seo-meta-descriptions' ) ) );
            return;
        }

        $generated_text = '';

        if ( $content_type === 'description' ) {
            $generated_text = $this->generate_openai_description( $post_content, $api_key, $selected_model );
        } elseif ( $content_type === 'title' ) {
            // Placeholder for title generation - similar to description but different prompt
            $prompt = "Generate a concise and compelling SEO title (max 60 characters) for the following content (current title is '{$post_title}'):\n\n" . strip_tags( $post_content );
            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
                'method'    => 'POST',
                'headers'   => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json'),
                'body'      => json_encode(array('model' => $selected_model, 'messages' => array(array('role' => 'user', 'content' => $prompt)), 'max_tokens' => 20, 'temperature' => 0.7)),
                'timeout'   => 15
            ));
            if (is_wp_error($response)) {
                $generated_text = $response;
            } else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    $generated_text = trim($data['choices'][0]['message']['content']);
                } else {
                    $generated_text = new WP_Error('openai_title_error', 'Failed to generate title. Unexpected API response.');
                }
            }
        } elseif ( $content_type === 'keywords' ) {
            // Placeholder for keyword generation
            $prompt = "Extract the 5 most relevant SEO keywords (comma separated) for the following content:\n\n" . strip_tags( $post_content );
             $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
                'method'    => 'POST',
                'headers'   => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json'),
                'body'      => json_encode(array('model' => $selected_model, 'messages' => array(array('role' => 'user', 'content' => $prompt)), 'max_tokens' => 30, 'temperature' => 0.5)),
                'timeout'   => 15
            ));
            if (is_wp_error($response)) {
                $generated_text = $response;
            } else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    $generated_text = trim($data['choices'][0]['message']['content']);
                } else {
                    $generated_text = new WP_Error('openai_keywords_error', 'Failed to generate keywords. Unexpected API response.');
                }
            }
        }

        if ( is_wp_error( $generated_text ) ) {
            wp_send_json_error( array( 'message' => $generated_text->get_error_message() ) );
        } elseif ( ! empty( $generated_text ) ) {
            wp_send_json_success( array( 'text' => $generated_text ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to generate content or content was empty.', 'wp-seo-meta-descriptions' ) ) );
        }
    }

    /**
     * Adds the meta box to the post editing screen.
     */
    public function add_meta_box() {
        add_meta_box(
            'wpsmd_seo_settings_box',
            __( 'SEO Settings & AI Tools', 'wp-seo-meta-descriptions' ),
            array( $this, 'meta_box_callback' ),
            array( 'post', 'page' ),
            'normal',
            'high'
        );
    }

    /**
     * Add bulk SEO management menu item
     */
    public function add_bulk_seo_menu() {
        add_submenu_page(
            'tools.php',
            __('Bulk SEO Manager', 'wp-seo-meta-descriptions'),
            __('Bulk SEO Manager', 'wp-seo-meta-descriptions'),
            'manage_options',
            'wpsmd-bulk-seo',
            array($this, 'render_bulk_seo_page')
        );
    }

    /**
     * Render the bulk SEO management page
     */
    public function render_bulk_seo_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-seo-meta-descriptions'));
        }

        require_once plugin_dir_path(__FILE__) . 'views/bulk-seo-editor.php';
    }

    /**
     * AJAX handler for getting posts for bulk editor
     */
    public function get_posts_for_bulk_editor() {
        check_ajax_referer('wpsmd_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $posts = get_posts($args);
        $formatted_posts = array();

        foreach ($posts as $post) {
            $formatted_posts[] = array(
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'seo_title' => get_post_meta($post->ID, '_wpsmd_seo_title', true),
                'meta_description' => get_post_meta($post->ID, '_wpsmd_meta_description', true),
                'canonical_url' => get_post_meta($post->ID, '_wpsmd_canonical_url', true),
                'edit_link' => get_edit_post_link($post->ID),
                'permalink' => get_permalink($post->ID)
            );
        }

        wp_send_json_success($formatted_posts);
    }

    /**
     * AJAX handler for saving bulk meta data
     */
    public function save_bulk_meta() {
        check_ajax_referer('wpsmd_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $items = isset($_POST['items']) ? $_POST['items'] : array();
        $updated = 0;

        foreach ($items as $item) {
            $post_id = absint($item['post_id']);
            if ($post_id && current_user_can('edit_post', $post_id)) {
                update_post_meta($post_id, '_wpsmd_seo_title', sanitize_text_field($item['seo_title']));
                update_post_meta($post_id, '_wpsmd_meta_description', sanitize_textarea_field($item['meta_description']));
                if (isset($item['canonical_url'])) {
                    if (!empty($item['canonical_url'])) {
                        update_post_meta($post_id, '_wpsmd_canonical_url', esc_url_raw($item['canonical_url']));
                    } else {
                        delete_post_meta($post_id, '_wpsmd_canonical_url');
                    }
                }
                $updated++;
            }
        }

        wp_send_json_success(array('updated' => $updated));
    }

    /**
     * AJAX handler for saving CPT templates
     */
    public function save_cpt_templates() {
        check_ajax_referer('wpsmd_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $templates = array(
            'title' => isset($_POST['title_templates']) ? $_POST['title_templates'] : array(),
            'description' => isset($_POST['desc_templates']) ? $_POST['desc_templates'] : array()
        );

        update_option('wpsmd_cpt_templates', $templates);
        wp_send_json_success();
    }

    /**
     * AJAX handler for exporting settings
     */
    public function export_settings() {
        check_ajax_referer('wpsmd_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $settings = array(
            'options' => get_option('wpsmd_options'),
            'cpt_templates' => get_option('wpsmd_cpt_templates')
        );

        wp_send_json_success($settings);
    }

    /**
     * AJAX handler for importing settings
     */
    public function import_settings() {
        check_ajax_referer('wpsmd_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $file = $_FILES['import_file'];
        if (!$file || $file['error']) {
            wp_send_json_error('Invalid file');
        }

        $content = file_get_contents($file['tmp_name']);
        $settings = json_decode($content, true);

        if (!$settings || !is_array($settings)) {
            wp_send_json_error('Invalid settings format');
        }

        if (isset($settings['options'])) {
            update_option('wpsmd_options', $settings['options']);
        }
        if (isset($settings['cpt_templates'])) {
            update_option('wpsmd_cpt_templates', $settings['cpt_templates']);
        }

        wp_send_json_success();
    }

    /**
     * Callback function to display the meta box content.
     *
     * @param WP_Post $post The post object.
     */
    public function meta_box_callback( $post ) {
        wp_nonce_field( 'wpsmd_save_seo_data', 'wpsmd_seo_nonce' );

        $seo_title = get_post_meta( $post->ID, '_wpsmd_seo_title', true );
        $meta_description = get_post_meta( $post->ID, '_wpsmd_meta_description', true );
        $canonical_url = get_post_meta( $post->ID, '_wpsmd_canonical_url', true );
        $openai_api_key = get_post_meta( $post->ID, '_wpsmd_openai_api_key', true );
        $schema_type = get_post_meta( $post->ID, '_wpsmd_schema_type', true );
        if ( empty( $schema_type ) ) {
            $schema_type = 'Article'; // Default schema type
        }

        // SEO Title Field
        echo '<p>';
        echo '<label for="wpsmd_seo_title_field"><strong>' . __( 'SEO Title:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_seo_title_field" name="wpsmd_seo_title_field" value="' . esc_attr( $seo_title ) . '" style="width:100%;" />';
        echo '<button type="button" id="wpsmd_generate_title_btn" class="button wpsmd-ai-generate-btn" data-type="title" data-target="wpsmd_seo_title_field">' . __( 'Generate with AI', 'wp-seo-meta-descriptions' ) . '</button>';
        echo '</p>';

        // Canonical URL Field
        echo '<p>';
        echo '<label for="wpsmd_canonical_url_field"><strong>' . __( 'Canonical URL:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="url" id="wpsmd_canonical_url_field" name="wpsmd_canonical_url_field" value="' . esc_url( $canonical_url ) . '" style="width:100%;" placeholder="' . __( 'Enter the canonical URL if this page is a duplicate of another page', 'wp-seo-meta-descriptions' ) . '" />';
        echo '<span class="description">' . __( 'Use this to specify the preferred version of this page to help avoid duplicate content issues', 'wp-seo-meta-descriptions' ) . '</span>';
        echo '</p>';

        // Meta Description Field
        echo '<p>';
        echo '<label for="wpsmd_meta_description_field"><strong>' . __( 'Meta Description:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<textarea id="wpsmd_meta_description_field" name="wpsmd_meta_description_field" rows="4" style="width:100%;">' . esc_textarea( $meta_description ) . '</textarea>';
        echo '<button type="button" id="wpsmd_generate_description_btn" class="button wpsmd-ai-generate-btn" data-type="description" data-target="wpsmd_meta_description_field">' . __( 'Generate with AI', 'wp-seo-meta-descriptions' ) . '</button>';
        echo '</p>';

        // Keywords (placeholder for now, AI will populate this)
        echo '<p>';
        echo '<label for="wpsmd_keywords_field"><strong>' . __( 'Keywords:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_keywords_field" name="wpsmd_keywords_field" readonly style="width:100%;" placeholder="'.__( 'Keywords will be generated by AI', 'wp-seo-meta-descriptions' ).'" />';
        echo '<button type="button" id="wpsmd_find_keywords_btn" class="button wpsmd-ai-generate-btn" data-type="keywords" data-target="wpsmd_keywords_field">' . __( 'Find Keywords with AI', 'wp-seo-meta-descriptions' ) . '</button>';
        echo '</p>';

        // Schema Type Selector
        echo '<hr><h3>' . __( 'Schema Markup / Structured Data', 'wp-seo-meta-descriptions' ) . '</h3>';
        echo '<p>';
        echo '<label for="wpsmd_schema_type_field"><strong>' . __( 'Schema Type:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<select id="wpsmd_schema_type_field" name="wpsmd_schema_type_field" style="width:100%;">';
        $schema_types = array(
            'Article' => __( 'Article (Default for Posts)', 'wp-seo-meta-descriptions' ),
            'WebPage' => __( 'WebPage (Default for Pages)', 'wp-seo-meta-descriptions' ),
            'Product' => __( 'Product', 'wp-seo-meta-descriptions' ),
            'Recipe'  => __( 'Recipe', 'wp-seo-meta-descriptions' ),
            'DiscussionForumPosting' => __( 'Discussion Forum Posting', 'wp-seo-meta-descriptions' ),
            'FAQPage' => __( 'FAQ Page', 'wp-seo-meta-descriptions' ),
            'Organization' => __( 'Organization', 'wp-seo-meta-descriptions' ),
            // Add more schema types here as needed
        );
        foreach ( $schema_types as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $schema_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        // Conditional Fields for Product Schema
        $product_fields_style = ( $schema_type === 'Product' ) ? 'display:block;' : 'display:none;';
        echo '<div id="wpsmd_product_schema_fields" style="' . $product_fields_style . '">';
        echo '<h4>' . __( 'Product Schema Details', 'wp-seo-meta-descriptions' ) . '</h4>';
        $product_name = get_post_meta( $post->ID, '_wpsmd_product_name', true );
        $product_image = get_post_meta( $post->ID, '_wpsmd_product_image', true );
        $product_description = get_post_meta( $post->ID, '_wpsmd_product_description', true );
        $product_sku = get_post_meta( $post->ID, '_wpsmd_product_sku', true );
        $product_brand = get_post_meta( $post->ID, '_wpsmd_product_brand', true );
        $product_price = get_post_meta( $post->ID, '_wpsmd_product_price', true );
        $product_currency = get_post_meta( $post->ID, '_wpsmd_product_currency', true );
        $product_availability = get_post_meta( $post->ID, '_wpsmd_product_availability', true );

        echo '<p><label>' . __( 'Product Name:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_name" value="' . esc_attr( $product_name ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Post Title', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<p><label>' . __( 'Product Image URL:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_image" value="' . esc_url( $product_image ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Featured Image', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<p><label>' . __( 'Product Description:', 'wp-seo-meta-descriptions' ) . ' <textarea name="wpsmd_product_description" rows="3" style="width:100%;" placeholder="' . __( 'Defaults to Meta Description or Excerpt', 'wp-seo-meta-descriptions' ) . '">' . esc_textarea( $product_description ) . '</textarea></label></p>';
        echo '<p><label>' . __( 'SKU:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_sku" value="' . esc_attr( $product_sku ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Brand Name:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_brand" value="' . esc_attr( $product_brand ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Price:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_price" value="' . esc_attr( $product_price ) . '" style="width:50%;" placeholder="e.g., 19.99" /></label>';
        echo ' <label>' . __( 'Currency:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_product_currency" value="' . esc_attr( $product_currency ) . '" style="width:40%;" placeholder="e.g., USD" /></label></p>';
        echo '<p><label>' . __( 'Availability:', 'wp-seo-meta-descriptions' ) . ' <select name="wpsmd_product_availability" style="width:100%;">';
        $availabilities = array( 'https://schema.org/InStock' => 'In Stock', 'https://schema.org/OutOfStock' => 'Out of Stock', 'https://schema.org/PreOrder' => 'Pre-Order' );
        foreach($availabilities as $val => $label) { echo '<option value="'.esc_attr($val).'" '.selected($product_availability, $val, false).'>'.esc_html($label).'</option>'; }
        echo '</select></label></p>';
        echo '</div>';

        // Conditional Fields for Recipe Schema
        $recipe_fields_style = ( $schema_type === 'Recipe' ) ? 'display:block;' : 'display:none;';
        echo '<div id="wpsmd_recipe_schema_fields" style="' . $recipe_fields_style . '">';
        echo '<h4>' . __( 'Recipe Schema Details', 'wp-seo-meta-descriptions' ) . '</h4>';
        $recipe_name = get_post_meta( $post->ID, '_wpsmd_recipe_name', true );
        $recipe_description = get_post_meta( $post->ID, '_wpsmd_recipe_description', true );
        $recipe_image = get_post_meta( $post->ID, '_wpsmd_recipe_image', true );
        $recipe_ingredients = get_post_meta( $post->ID, '_wpsmd_recipe_ingredients', true ); // Store as comma-separated or one per line
        $recipe_instructions = get_post_meta( $post->ID, '_wpsmd_recipe_instructions', true ); // Store as text, steps separated by newlines
        $recipe_prep_time = get_post_meta( $post->ID, '_wpsmd_recipe_prep_time', true ); // ISO 8601 duration format e.g., PT30M
        $recipe_cook_time = get_post_meta( $post->ID, '_wpsmd_recipe_cook_time', true ); // ISO 8601 duration format e.g., PT1H
        $recipe_yield = get_post_meta( $post->ID, '_wpsmd_recipe_yield', true );
        $recipe_calories = get_post_meta( $post->ID, '_wpsmd_recipe_calories', true );

        echo '<p><label>' . __( 'Recipe Name:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_name" value="' . esc_attr( $recipe_name ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Post Title', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<p><label>' . __( 'Recipe Description:', 'wp-seo-meta-descriptions' ) . ' <textarea name="wpsmd_recipe_description" rows="3" style="width:100%;" placeholder="' . __( 'Defaults to Meta Description or Excerpt', 'wp-seo-meta-descriptions' ) . '">' . esc_textarea( $recipe_description ) . '</textarea></label></p>';
        echo '<p><label>' . __( 'Recipe Image URL:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_image" value="' . esc_url( $recipe_image ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Featured Image', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<p><label>' . __( 'Ingredients (one per line):', 'wp-seo-meta-descriptions' ) . ' <textarea name="wpsmd_recipe_ingredients" rows="5" style="width:100%;">' . esc_textarea( $recipe_ingredients ) . '</textarea></label></p>';
        echo '<p><label>' . __( 'Instructions (one step per line):', 'wp-seo-meta-descriptions' ) . ' <textarea name="wpsmd_recipe_instructions" rows="8" style="width:100%;">' . esc_textarea( $recipe_instructions ) . '</textarea></label></p>';
        echo '<p><label>' . __( 'Prep Time (e.g., PT30M for 30 mins):', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_prep_time" value="' . esc_attr( $recipe_prep_time ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Cook Time (e.g., PT1H for 1 hour):', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_cook_time" value="' . esc_attr( $recipe_cook_time ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Recipe Yield (e.g., 4 servings):', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_yield" value="' . esc_attr( $recipe_yield ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Calories (e.g., 250):', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_recipe_calories" value="' . esc_attr( $recipe_calories ) . '" style="width:100%;" /></label></p>';
        echo '</div>';

        // Conditional Fields for FAQPage Schema
        $faq_fields_style = ( $schema_type === 'FAQPage' ) ? 'display:block;' : 'display:none;';
        echo '<div id="wpsmd_faq_schema_fields" style="' . $faq_fields_style . '">';
        echo '<h4>' . __( 'FAQ Page Schema Details', 'wp-seo-meta-descriptions' ) . '</h4>';
        $faq_main_entity = get_post_meta( $post->ID, '_wpsmd_faq_main_entity', true ); // Store as JSON or structured text

        echo '<p><label>' . __( 'FAQ Questions & Answers (one Q&A pair per line, separate Q and A with a pipe | ):', 'wp-seo-meta-descriptions' ) . '<br><small>' . __('Example: What is your return policy? | Our return policy lasts 30 days.', 'wp-seo-meta-descriptions') . '</small><textarea name="wpsmd_faq_main_entity" rows="8" style="width:100%;" placeholder="' . __( 'Enter each question and answer pair on a new line, separated by a pipe character (|).', 'wp-seo-meta-descriptions' ) . '">' . esc_textarea( $faq_main_entity ) . '</textarea></label></p>';
        echo '</div>';

        // Conditional Fields for Organization Schema
        $org_fields_style = ( $schema_type === 'Organization' ) ? 'display:block;' : 'display:none;';
        echo '<div id="wpsmd_organization_schema_fields" style="' . $org_fields_style . '">';
        echo '<h4>' . __( 'Organization Schema Details', 'wp-seo-meta-descriptions' ) . '</h4>';
        $org_name = get_post_meta( $post->ID, '_wpsmd_org_name', true );
        $org_legal_name = get_post_meta( $post->ID, '_wpsmd_org_legal_name', true );
        $org_logo_url = get_post_meta( $post->ID, '_wpsmd_org_logo_url', true );
        $org_street_address = get_post_meta( $post->ID, '_wpsmd_org_street_address', true );
        $org_locality = get_post_meta( $post->ID, '_wpsmd_org_locality', true );
        $org_region = get_post_meta( $post->ID, '_wpsmd_org_region', true );
        $org_postal_code = get_post_meta( $post->ID, '_wpsmd_org_postal_code', true );
        $org_country = get_post_meta( $post->ID, '_wpsmd_org_country', true );
        $org_telephone = get_post_meta( $post->ID, '_wpsmd_org_telephone', true );
        $org_email = get_post_meta( $post->ID, '_wpsmd_org_email', true );
        $org_same_as = get_post_meta( $post->ID, '_wpsmd_org_same_as', true ); // Store as comma-separated URLs

        echo '<p><label>' . __( 'Organization Name (if different from Site Title):', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_name" value="' . esc_attr( $org_name ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Site Title', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<p><label>' . __( 'Legal Name:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_legal_name" value="' . esc_attr( $org_legal_name ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Logo URL:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_logo_url" value="' . esc_url( $org_logo_url ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Site Icon', 'wp-seo-meta-descriptions' ) . '" /></label></p>';
        echo '<h5>' . __( 'Address:', 'wp-seo-meta-descriptions' ) . '</h5>';
        echo '<p><label>' . __( 'Street Address:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_street_address" value="' . esc_attr( $org_street_address ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'City/Locality:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_locality" value="' . esc_attr( $org_locality ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'State/Region:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_region" value="' . esc_attr( $org_region ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Postal Code:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_postal_code" value="' . esc_attr( $org_postal_code ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Country:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_country" value="' . esc_attr( $org_country ) . '" style="width:100%;" /></label></p>';
        echo '<h5>' . __( 'Contact Information:', 'wp-seo-meta-descriptions' ) . '</h5>';
        echo '<p><label>' . __( 'Telephone:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_telephone" value="' . esc_attr( $org_telephone ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Email:', 'wp-seo-meta-descriptions' ) . ' <input type="text" name="wpsmd_org_email" value="' . esc_attr( $org_email ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . __( 'Social Profiles & Other URLs (SameAs - comma separated):', 'wp-seo-meta-descriptions' ) . ' <textarea name="wpsmd_org_same_as" rows="3" style="width:100%;" placeholder="https://www.facebook.com/yourpage, https://www.twitter.com/yourprofile">' . esc_textarea( $org_same_as ) . '</textarea></label></p>';
        echo '</div>';

        // JavaScript for AJAX and UI updates
        echo "<script type='text/javascript'>
            jQuery(document).ready(function($) {
                // Schema fields toggle logic (existing)
                function toggleSchemaFields() {
                    var selectedType = $('#wpsmd_schema_type_field').val();
                    $('#wpsmd_product_schema_fields').hide();
                    $('#wpsmd_recipe_schema_fields').hide();
                    $('#wpsmd_faq_schema_fields').hide();
                    $('#wpsmd_organization_schema_fields').hide();
                    if (selectedType === 'Product') {
                        $('#wpsmd_product_schema_fields').show();
                    } else if (selectedType === 'Recipe') {
                        $('#wpsmd_recipe_schema_fields').show();
                    } else if (selectedType === 'FAQPage') {
                        $('#wpsmd_faq_schema_fields').show();
                    } else if (selectedType === 'Organization') {
                        $('#wpsmd_organization_schema_fields').show();
                    }
                }
                toggleSchemaFields();
                $('#wpsmd_schema_type_field').on('change', toggleSchemaFields);

                // AI Generation Button Click Handler
                $('.wpsmd-ai-generate-btn').on('click', function() {
                    var \$button = $(this);
                    var contentType = \$button.data('type'); // 'title', 'description', 'keywords'
                    var \$targetField = $('#' + \$button.data('target'));
                    var originalButtonText = \$button.html();

                    \$button.html('" . __( 'Generating...', 'wp-seo-meta-descriptions' ) . "').prop('disabled', true);
                    \$targetField.prop('disabled', true);

                    // Remove any existing notices
                    \$('.wpsmd-notice').remove();

                    $.ajax({
                        url: ajaxurl, // WordPress AJAX URL
                        type: 'POST',
                        data: {
                            action: 'wpsmd_generate_seo_content',
                            nonce: '" . wp_create_nonce('wpsmd_ajax_nonce') . "',
                            post_id: '" . $post->ID . "',
                            content_type: contentType
                        },
                        success: function(response) {
                            if (response.success) {
                                \$targetField.val(response.data.text);
                                \$button.after('<div class=\"notice notice-success is-dismissible wpsmd-notice\"><p>' + contentType.charAt(0).toUpperCase() + contentType.slice(1) + ' " . __( 'generated successfully!', 'wp-seo-meta-descriptions' ) . "</p></div>');
                            } else {
                                var errorMessage = response.data && response.data.message ? response.data.message : '" . __( 'An unknown error occurred.', 'wp-seo-meta-descriptions' ) . "';
                                \$button.after('<div class=\"notice notice-error is-dismissible wpsmd-notice\"><p><strong>Error:</strong> ' + errorMessage + '<br><small>" . __( 'Please check your OpenAI API key in settings, ensure the API has credits, and try again. If the issue persists, the content might be too long or the API service might be temporarily unavailable.', 'wp-seo-meta-descriptions' ) . "</small></p></div>');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                             var errorMessage = errorThrown || textStatus;
                             \$button.after('<div class=\"notice notice-error is-dismissible wpsmd-notice\"><p><strong>AJAX Error:</strong> ' + errorMessage + '<br><small>" . __( 'Could not connect to the server. Please check your internet connection and try again.', 'wp-seo-meta-descriptions' ) . "</small></p></div>');
                        },
                        complete: function() {
                            \$button.html(originalButtonText).prop('disabled', false);
                            \$targetField.prop('disabled', false);
                        }
                    });
                });

                // Make notices dismissible
                $('body').on('click', '.wpsmd-notice .notice-dismiss', function(){
                    $(this).closest('.wpsmd-notice').remove();
                });
            });
        </script>";
        echo '<style>.wpsmd-ai-generate-btn { margin-left: 5px; }</style>'; // Basic styling for buttons



        // Open Graph Fields
        echo '<hr><h3>' . __( 'Open Graph Settings (Facebook, LinkedIn, etc.)', 'wp-seo-meta-descriptions' ) . '</h3>';
        $og_title = get_post_meta( $post->ID, '_wpsmd_og_title', true );
        $og_description = get_post_meta( $post->ID, '_wpsmd_og_description', true );
        $og_image = get_post_meta( $post->ID, '_wpsmd_og_image', true );

        echo '<p>';
        echo '<label for="wpsmd_og_title_field"><strong>' . __( 'Open Graph Title:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_og_title_field" name="wpsmd_og_title_field" value="' . esc_attr( $og_title ) . '" style="width:100%;" placeholder="' . __( 'Defaults to SEO Title or Post Title', 'wp-seo-meta-descriptions' ) . '" />';
        echo '</p>';

        echo '<p>';
        echo '<label for="wpsmd_og_description_field"><strong>' . __( 'Open Graph Description:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<textarea id="wpsmd_og_description_field" name="wpsmd_og_description_field" rows="3" style="width:100%;" placeholder="' . __( 'Defaults to Meta Description or Post Excerpt', 'wp-seo-meta-descriptions' ) . '">' . esc_textarea( $og_description ) . '</textarea>';
        echo '</p>';

        echo '<p>';
        echo '<label for="wpsmd_og_image_field"><strong>' . __( 'Open Graph Image URL:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_og_image_field" name="wpsmd_og_image_field" value="' . esc_url( $og_image ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Featured Image. Enter full URL.', 'wp-seo-meta-descriptions' ) . '" />';
        echo '</p>';

        // Twitter Card Fields
        echo '<hr><h3>' . __( 'Twitter Card Settings', 'wp-seo-meta-descriptions' ) . '</h3>';
        $twitter_title = get_post_meta( $post->ID, '_wpsmd_twitter_title', true );
        $twitter_description = get_post_meta( $post->ID, '_wpsmd_twitter_description', true );
        $twitter_image = get_post_meta( $post->ID, '_wpsmd_twitter_image', true );

        // OpenAI API Key is now managed globally, field removed from here.

        echo '<p>';
        echo '<label for="wpsmd_twitter_title_field"><strong>' . __( 'Twitter Title:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_twitter_title_field" name="wpsmd_twitter_title_field" value="' . esc_attr( $twitter_title ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Open Graph Title or SEO Title', 'wp-seo-meta-descriptions' ) . '" />';
        echo '</p>';

        echo '<p>';
        echo '<label for="wpsmd_twitter_description_field"><strong>' . __( 'Twitter Description:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<textarea id="wpsmd_twitter_description_field" name="wpsmd_twitter_description_field" rows="3" style="width:100%;" placeholder="' . __( 'Defaults to Open Graph Description or Meta Description', 'wp-seo-meta-descriptions' ) . '">' . esc_textarea( $twitter_description ) . '</textarea>';
        echo '</p>';

        echo '<p>';
        echo '<label for="wpsmd_twitter_image_field"><strong>' . __( 'Twitter Image URL:', 'wp-seo-meta-descriptions' ) . '</strong></label><br />';
        echo '<input type="text" id="wpsmd_twitter_image_field" name="wpsmd_twitter_image_field" value="' . esc_url( $twitter_image ) . '" style="width:100%;" placeholder="' . __( 'Defaults to Open Graph Image or Featured Image. Enter full URL.', 'wp-seo-meta-descriptions' ) . '" />';
        echo '</p>';

        // OpenAI API Key Field - Moved to global settings page
    }

    /**
     * Saves the SEO data when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    /**
     * Placeholder for OpenAI API call to generate description.
     *
     * @param string $content The post content.
     * @param string $api_key The OpenAI API key.
     * @param string $model The OpenAI model to use.
     * @return string|WP_Error The generated description or WP_Error on failure.
     */
    private function generate_openai_description( $content, $api_key, $model = 'gpt-3.5-turbo' ) {
        $prompt = "Generate a concise and compelling meta description (max 160 characters) for the following content:\n\n" . strip_tags( $content );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'method'    => 'POST',
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'      => json_encode( array(
                'model'       => $model,
                'messages'    => array( array('role' => 'user', 'content' => $prompt )),
                'max_tokens'  => 70, // Adjusted for meta description length
                'temperature' => 0.7,
            ) ),
            'timeout'   => 20, // Increased timeout
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'WPSMD OpenAI API Error: ' . $response->get_error_message() );
            return new WP_Error('openai_api_error', 'OpenAI API Error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }
        
        $error_message = 'OpenAI API Unexpected Response: '; 
        if(isset($data['error']['message'])){
            $error_message .= $data['error']['message'];
        } else {
            $error_message .= $body;
        }
        error_log( 'WPSMD ' . $error_message );
        return new WP_Error('openai_response_error', $error_message);
    }

    public function save_seo_data( $post_id ) {
        if ( ! isset( $_POST['wpsmd_seo_nonce'] ) || ! wp_verify_nonce( $_POST['wpsmd_seo_nonce'], 'wpsmd_save_seo_data' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        // Save SEO Title
        if ( isset( $_POST['wpsmd_seo_title_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_seo_title', sanitize_text_field( $_POST['wpsmd_seo_title_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_seo_title' );
        }

        // Save Canonical URL
        if ( isset( $_POST['wpsmd_canonical_url_field'] ) && !empty( $_POST['wpsmd_canonical_url_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_canonical_url', esc_url_raw( $_POST['wpsmd_canonical_url_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_canonical_url' );
        }

        // Save Meta Description
        $meta_description_input = isset( $_POST['wpsmd_meta_description_field'] ) ? sanitize_textarea_field( $_POST['wpsmd_meta_description_field'] ) : '';

        if ( empty( $meta_description_input ) ) {
            $options = get_option( 'wpsmd_options' );
            $selected_model_for_save = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-3.5-turbo';
            if ( ! empty( $options['enable_auto_seo_description'] ) && ! empty( $options['openai_api_key'] ) ) {
                $post_content = get_post_field( 'post_content', $post_id );
                if ( ! empty( $post_content ) ) {
                    $generated_description = $this->generate_openai_description( $post_content, $options['openai_api_key'], $selected_model_for_save );
                    if ( ! is_wp_error( $generated_description ) && ! empty( $generated_description ) ) {
                        $meta_description_input = $generated_description;
                        // error_log('WPSMD: Successfully auto-generated description (OpenAI): ' . $meta_description_input);
                    } else {
                        // Fallback to excerpt if OpenAI fails or returns error
                        $meta_description_input = wp_trim_words( $post_content, 25, '...' );
                        if(is_wp_error($generated_description)){
                            // error_log('WPSMD: OpenAI generation failed: ' . $generated_description->get_error_message() . '. Fell back to excerpt.');
                        } else {
                            // error_log('WPSMD: OpenAI generation returned empty. Fell back to excerpt.');
                        }
                    }
                }
            }
        }

        if ( ! empty( $meta_description_input ) ) {
            update_post_meta( $post_id, '_wpsmd_meta_description', $meta_description_input );
        } else {
            delete_post_meta( $post_id, '_wpsmd_meta_description' );
        }

        // Save OpenAI API Key - Moved to global settings, no longer saved per post
        // delete_post_meta( $post_id, '_wpsmd_openai_api_key' ); // Optionally, remove old per-post keys if desired during a migration step, not here.

        // Save Open Graph Title
        if ( isset( $_POST['wpsmd_og_title_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_og_title', sanitize_text_field( $_POST['wpsmd_og_title_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_og_title' );
        }

        // Save Open Graph Description
        if ( isset( $_POST['wpsmd_og_description_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_og_description', sanitize_textarea_field( $_POST['wpsmd_og_description_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_og_description' );
        }

        // Save Open Graph Image
        if ( isset( $_POST['wpsmd_og_image_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_og_image', esc_url_raw( $_POST['wpsmd_og_image_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_og_image' );
        }

        // Save Twitter Title
        if ( isset( $_POST['wpsmd_twitter_title_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_twitter_title', sanitize_text_field( $_POST['wpsmd_twitter_title_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_twitter_title' );
        }

        // Save Twitter Description
        if ( isset( $_POST['wpsmd_twitter_description_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_twitter_description', sanitize_textarea_field( $_POST['wpsmd_twitter_description_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_twitter_description' );
        }

        // Save Twitter Image
        if ( isset( $_POST['wpsmd_twitter_image_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_twitter_image', esc_url_raw( $_POST['wpsmd_twitter_image_field'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_twitter_image' );
        }

        // Save FAQPage Schema Data
        if ( isset( $_POST['wpsmd_schema_type_field'] ) && $_POST['wpsmd_schema_type_field'] === 'FAQPage' ) {
            if ( isset( $_POST['wpsmd_faq_main_entity'] ) ) {
                update_post_meta( $post_id, '_wpsmd_faq_main_entity', sanitize_textarea_field( $_POST['wpsmd_faq_main_entity'] ) );
            } else {
                delete_post_meta( $post_id, '_wpsmd_faq_main_entity' );
            }
        } else {
            // If schema type is not FAQPage, ensure FAQ data is removed to prevent orphaned data
            delete_post_meta( $post_id, '_wpsmd_faq_main_entity' );
        }

        // Save Organization Schema Data
        if ( isset( $_POST['wpsmd_schema_type_field'] ) && $_POST['wpsmd_schema_type_field'] === 'Organization' ) {
            $org_fields_to_save = array(
                '_wpsmd_org_name',
                '_wpsmd_org_legal_name',
                '_wpsmd_org_logo_url',
                '_wpsmd_org_street_address',
                '_wpsmd_org_locality',
                '_wpsmd_org_region',
                '_wpsmd_org_postal_code',
                '_wpsmd_org_country',
                '_wpsmd_org_telephone',
                '_wpsmd_org_email',
                '_wpsmd_org_same_as'
            );
            foreach($org_fields_to_save as $field_key_suffix) {
                $post_key = str_replace('_wpsmd_', 'wpsmd_', $field_key_suffix); // form field name
                if ( isset( $_POST[$post_key] ) ) {
                    if ($field_key_suffix === '_wpsmd_org_logo_url' || $field_key_suffix === '_wpsmd_org_same_as') {
                         if ($field_key_suffix === '_wpsmd_org_same_as') {
                            update_post_meta( $post_id, $field_key_suffix, sanitize_textarea_field( $_POST[$post_key] ) );
                         } else {
                            update_post_meta( $post_id, $field_key_suffix, esc_url_raw( $_POST[$post_key] ) );
                         }
                    } else {
                        update_post_meta( $post_id, $field_key_suffix, sanitize_text_field( $_POST[$post_key] ) );
                    }
                } else {
                    delete_post_meta( $post_id, $field_key_suffix );
                }
            }
        } else {
            // If schema type is not Organization, ensure Organization data is removed
            $org_fields_to_delete = array(
                '_wpsmd_org_name',
                '_wpsmd_org_legal_name',
                '_wpsmd_org_logo_url',
                '_wpsmd_org_street_address',
                '_wpsmd_org_locality',
                '_wpsmd_org_region',
                '_wpsmd_org_postal_code',
                '_wpsmd_org_country',
                '_wpsmd_org_telephone',
                '_wpsmd_org_email',
                '_wpsmd_org_same_as'
            );
            foreach($org_fields_to_delete as $field_key_suffix) {
                delete_post_meta( $post_id, $field_key_suffix );
            }
        }

        // Save BreadcrumbList Schema Data
        if ( isset( $_POST['wpsmd_schema_type_field'] ) && $_POST['wpsmd_schema_type_field'] === 'BreadcrumbList' ) {
            if ( isset( $_POST['wpsmd_breadcrumb_items'] ) ) {
                update_post_meta( $post_id, '_wpsmd_breadcrumb_items', sanitize_textarea_field( $_POST['wpsmd_breadcrumb_items'] ) );
            } else {
                delete_post_meta( $post_id, '_wpsmd_breadcrumb_items' );
            }
        } else {
            // If schema type is not BreadcrumbList, ensure Breadcrumb data is removed
            delete_post_meta( $post_id, '_wpsmd_breadcrumb_items' );
        }

        // Save Speakable CSS Selectors
        if ( !empty( $_POST['wpsmd_speakable_css_selectors'] ) ) {
            update_post_meta( $post_id, '_wpsmd_speakable_css_selectors', sanitize_textarea_field( $_POST['wpsmd_speakable_css_selectors'] ) );
        } else {
            delete_post_meta( $post_id, '_wpsmd_speakable_css_selectors' );
        }

        // Save Schema Type
        if ( isset( $_POST['wpsmd_schema_type_field'] ) ) {
            update_post_meta( $post_id, '_wpsmd_schema_type', sanitize_text_field( $_POST['wpsmd_schema_type_field'] ) );
        } else {
            // Default to Article or WebPage based on post type if not set
            $post_type = get_post_type($post_id);
            $default_schema = ($post_type === 'page') ? 'WebPage' : 'Article';
            update_post_meta( $post_id, '_wpsmd_schema_type', $default_schema );
        }

        // Save Product Schema Data
        $product_fields = array(
            '_wpsmd_product_name' => 'sanitize_text_field',
            '_wpsmd_product_image' => 'esc_url_raw',
            '_wpsmd_product_description' => 'sanitize_textarea_field',
            '_wpsmd_product_sku' => 'sanitize_text_field',
            '_wpsmd_product_brand' => 'sanitize_text_field',
            '_wpsmd_product_price' => 'sanitize_text_field', // Consider floatval or similar for actual use
            '_wpsmd_product_currency' => 'sanitize_text_field',
            '_wpsmd_product_availability' => 'sanitize_text_field', // Should be a valid schema.org URL
        );
        foreach ($product_fields as $key => $sanitizer) {
            $field_name = str_replace('_wpsmd_', 'wpsmd_', $key); // maps _wpsmd_product_name to wpsmd_product_name
            if ( isset( $_POST[$field_name] ) ) {
                update_post_meta( $post_id, $key, call_user_func( $sanitizer, $_POST[$field_name] ) );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        // No specific fields for DiscussionForumPosting to save in this iteration beyond the type itself.

        // Save Recipe Schema Data
        $recipe_fields = array(
            '_wpsmd_recipe_name' => 'sanitize_text_field',
            '_wpsmd_recipe_description' => 'sanitize_textarea_field',
            '_wpsmd_recipe_image' => 'esc_url_raw',
            '_wpsmd_recipe_ingredients' => 'sanitize_textarea_field', // Stored as string, parsed on output
            '_wpsmd_recipe_instructions' => 'sanitize_textarea_field', // Stored as string, parsed on output
            '_wpsmd_recipe_prep_time' => 'sanitize_text_field', // ISO 8601
            '_wpsmd_recipe_cook_time' => 'sanitize_text_field', // ISO 8601
            '_wpsmd_recipe_yield' => 'sanitize_text_field',
            '_wpsmd_recipe_calories' => 'sanitize_text_field',
        );
        foreach ($recipe_fields as $key => $sanitizer) {
            $field_name = str_replace('_wpsmd_', 'wpsmd_', $key);
            if ( isset( $_POST[$field_name] ) ) {
                update_post_meta( $post_id, $key, call_user_func( $sanitizer, $_POST[$field_name] ) );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        // Save Twitter Image (This was duplicated, ensure it's correctly placed or removed if redundant)
        // The original block for Twitter image was above the new schema saving logic.
        // If it's meant to be here, it's fine. If it was a copy-paste error, it should be removed.
        // For now, assuming it's intentional or a reordering.
        // if ( isset( $_POST['wpsmd_twitter_image_field'] ) ) { // This is a duplicate of lines 196-200
        // update_post_meta( $post_id, '_wpsmd_twitter_image', esc_url_raw( $_POST['wpsmd_twitter_image_field'] ) );
        // } else {
        // delete_post_meta( $post_id, '_wpsmd_twitter_image' );
        // }
    }

}

?>