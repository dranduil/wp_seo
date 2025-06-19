<?php
/**
 * Frontend-specific functionality for the WP SEO Meta Descriptions plugin.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPSMD_Frontend {

    /**
     * Initialize the frontend hooks.
     */
    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_json_ld' ) );
        add_action( 'wp_head', array( $this, 'output_social_meta_tags' ) );
    }

    /**
     * Outputs the JSON-LD structured data in the HTML head.
     */
    public function output_json_ld() {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( ! $post_id ) {
                return;
            }

            $post_obj = get_post( $post_id );
            if ( ! $post_obj ) {
                return;
            }

            $wpsmd_options = get_option( 'wpsmd_options' );
            $enable_auto_seo_title = isset( $wpsmd_options['enable_auto_seo_title'] ) ? (bool) $wpsmd_options['enable_auto_seo_title'] : false;
            $enable_auto_seo_description = isset( $wpsmd_options['enable_auto_seo_description'] ) ? (bool) $wpsmd_options['enable_auto_seo_description'] : false;

            $custom_seo_title = get_post_meta( $post_id, '_wpsmd_seo_title', true );
            $headline = $custom_seo_title;
            if ( empty( $headline ) && $enable_auto_seo_title ) {
                $headline = get_the_title( $post_id );
            } elseif (empty($headline)) {
                $headline = get_the_title( $post_id ); // Default fallback if not auto and not set
            }
            $headline = esc_attr($headline);

            $custom_meta_description = get_post_meta( $post_id, '_wpsmd_meta_description', true );
            $description = $custom_meta_description;
            if ( empty( $description ) && $enable_auto_seo_description ) {
                $description = wp_strip_all_tags( $post_obj->post_excerpt ? $post_obj->post_excerpt : mb_substr( wp_strip_all_tags( $post_obj->post_content ), 0, 160 ) );
            } elseif (empty($description)) {
                 $description = wp_strip_all_tags( $post_obj->post_excerpt ? $post_obj->post_excerpt : mb_substr( wp_strip_all_tags( $post_obj->post_content ), 0, 160 ) ); // Default fallback
            }

            $selected_schema_type = get_post_meta( $post_id, '_wpsmd_schema_type', true );
            if ( empty( $selected_schema_type ) ) {
                $selected_schema_type = is_page() ? 'WebPage' : 'Article';
            }

            $schema = array(
                '@context' => 'https://schema.org',
                '@type'    => $selected_schema_type,
                'mainEntityOfPage' => array(
                    '@type' => 'WebPage',
                    '@id'   => get_permalink( $post_id ),
                ),
                'headline' => $headline,
                'description' => esc_attr( $description ),
                'datePublished' => get_the_date( 'c', $post_id ),
                'dateModified'  => get_the_modified_date( 'c', $post_id ),
                'author' => array(
                    '@type' => 'Person',
                    'name'  => get_the_author_meta( 'display_name', $post_obj->post_author ),
                ),
                'publisher' => array(
                    '@type' => 'Organization',
                    'name'  => get_bloginfo( 'name' ),
                    'logo'  => array(
                        '@type' => 'ImageObject',
                        'url'   => get_site_icon_url() ? get_site_icon_url() : '',
                    ),
                ),
            );

            if ( has_post_thumbnail( $post_id ) ) {
                $image_id = get_post_thumbnail_id( $post_id );
                $image_url = wp_get_attachment_image_url( $image_id, 'full' );

                // Ensure wp_get_attachment_image_meta() is available or provide a fallback.
                if ( ! function_exists( 'wp_get_attachment_image_meta' ) ) {
                    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
                        // Only attempt to include if in admin, AJAX, or other safe contexts.
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                    }
                }

                // After attempting to include, check again or use a fallback.
                if ( function_exists( 'wp_get_attachment_image_meta' ) ) {
                    $image_meta = wp_get_attachment_image_meta( $image_id );
                } else {
                    // Fallback if the function is still not available (e.g., frontend context where include failed or wasn't attempted).
                    $image_meta = array( 'width' => 0, 'height' => 0 ); // Provide a minimal fallback.
                    // Optionally log this situation:
                    // error_log('WPSMD: wp_get_attachment_image_meta not available on frontend for image ID ' . $image_id);
                }

                if ( $image_url && !empty($image_meta) && !empty($image_meta['width']) ) { // Check width to ensure meta is somewhat valid
                    $schema['image'] = array(
                        '@type'  => 'ImageObject',
                        'url'    => $image_url,
                        'width'  => $image_meta['width'],
                        'height' => $image_meta['height'],
                    );
                }
            }

            // Add Product specific schema
            if ($schema['@type'] === 'Product') {
                $schema['name'] = get_post_meta( $post_id, '_wpsmd_product_name', true ) ?: $headline;
                $product_image_url = get_post_meta( $post_id, '_wpsmd_product_image', true );
                if (!empty($product_image_url)) {
                    $schema['image'] = esc_url($product_image_url);
                } elseif (has_post_thumbnail($post_id)) {
                    $schema['image'] = get_the_post_thumbnail_url($post_id, 'full');
                }
                $schema['description'] = get_post_meta( $post_id, '_wpsmd_product_description', true ) ?: esc_attr($description);
                $sku = get_post_meta( $post_id, '_wpsmd_product_sku', true );
                if (!empty($sku)) $schema['sku'] = esc_attr($sku);
                $brand_name = get_post_meta( $post_id, '_wpsmd_product_brand', true );
                if (!empty($brand_name)) $schema['brand'] = array('@type' => 'Brand', 'name' => esc_attr($brand_name));
                
                $price = get_post_meta( $post_id, '_wpsmd_product_price', true );
                $currency = get_post_meta( $post_id, '_wpsmd_product_currency', true );
                if (!empty($price) && !empty($currency)) {
                    $schema['offers'] = array(
                        '@type' => 'Offer',
                        'priceCurrency' => esc_attr($currency),
                        'price' => esc_attr($price),
                        'availability' => get_post_meta( $post_id, '_wpsmd_product_availability', true ) ?: 'https://schema.org/InStock',
                        'url' => get_permalink($post_id)
                    );
                }
            }

            // Add Recipe specific schema
            if ($schema['@type'] === 'Recipe') {
                $schema['name'] = get_post_meta( $post_id, '_wpsmd_recipe_name', true ) ?: $headline;
                $recipe_image_url = get_post_meta( $post_id, '_wpsmd_recipe_image', true );
                if (!empty($recipe_image_url)) {
                    $schema['image'] = esc_url($recipe_image_url);
                } elseif (has_post_thumbnail($post_id)) {
                     $image_id = get_post_thumbnail_id( $post_id );
                     $image_url_arr = wp_get_attachment_image_src( $image_id, 'full' );
                     $schema['image'] = $image_url_arr ? $image_url_arr[0] : ''; // Schema.org expects a single URL or an array of URLs
                }
                $schema['description'] = get_post_meta( $post_id, '_wpsmd_recipe_description', true ) ?: esc_attr($description);
                
                $ingredients_str = get_post_meta( $post_id, '_wpsmd_recipe_ingredients', true );
                if (!empty($ingredients_str)) $schema['recipeIngredient'] = array_map('trim', explode("\n", esc_textarea($ingredients_str)));
                
                $instructions_str = get_post_meta( $post_id, '_wpsmd_recipe_instructions', true );
                if (!empty($instructions_str)) {
                    $instruction_steps = array_map('trim', explode("\n", esc_textarea($instructions_str)));
                    $schema['recipeInstructions'] = array();
                    foreach($instruction_steps as $step) {
                        if(!empty($step)) $schema['recipeInstructions'][] = array('@type' => 'HowToStep', 'text' => $step);
                    }
                }

                $prep_time = get_post_meta( $post_id, '_wpsmd_recipe_prep_time', true );
                if (!empty($prep_time)) $schema['prepTime'] = esc_attr($prep_time);
                $cook_time = get_post_meta( $post_id, '_wpsmd_recipe_cook_time', true );
                if (!empty($cook_time)) $schema['cookTime'] = esc_attr($cook_time);
                $recipe_yield = get_post_meta( $post_id, '_wpsmd_recipe_yield', true );
                if (!empty($recipe_yield)) $schema['recipeYield'] = esc_attr($recipe_yield);
                $calories = get_post_meta( $post_id, '_wpsmd_recipe_calories', true );
                if (!empty($calories)) $schema['nutrition'] = array('@type' => 'NutritionInformation', 'calories' => esc_attr($calories) . ' calories');
            }
            
            // Add DiscussionForumPosting specific schema (shares many properties with Article)
            if ($schema['@type'] === 'DiscussionForumPosting') {
                // It will inherit headline, author, datePublished, dateModified, publisher, mainEntityOfPage from the base structure.
                // WordPress comments, if displayed on the page, might be separately marked up by the theme or other plugins.
                // For a more integrated approach, one could query comments and add them as 'comment' properties to this schema.
                // For now, we ensure the main posting is correctly typed.
                 if ( has_post_thumbnail( $post_id ) && !isset($schema['image'])) {
                    $image_id = get_post_thumbnail_id( $post_id );
                    $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                    $attachment_meta = null; // Initialize

                    // Ensure wp_get_attachment_metadata is available
                    if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
                        // This function is defined in wp-admin/includes/image.php, which might not be loaded on frontend.
                        // Conditionally load it if we are in an admin context or AJAX request, or if explicitly needed.
                        if ( ( defined( 'WP_ADMIN' ) && WP_ADMIN ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
                            if ( defined( 'ABSPATH' ) ) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                            }
                        }
                    }

                    if ( function_exists( 'wp_get_attachment_metadata' ) ) {
                        $attachment_meta = wp_get_attachment_metadata( $image_id );
                    } else {
                        // Fallback if function is still not available (e.g. image.php failed to load or function removed by other means)
                        $attachment_meta = array( 'width' => 0, 'height' => 0 ); // Mimic structure for width/height
                    }

                    // wp_get_attachment_metadata returns an array with 'width' and 'height' at the top level.
                    if ( $image_url && $attachment_meta && isset($attachment_meta['width']) && $attachment_meta['width'] > 0 && isset($attachment_meta['height']) && $attachment_meta['height'] > 0 ) {
                        $schema['image'] = array(
                            '@type'  => 'ImageObject',
                            'url'    => $image_url,
                            'width'  => $attachment_meta['width'],
                            'height' => $attachment_meta['height'],
                        );
                    } elseif ( $image_url ) { // If meta fails but URL exists, provide URL only as a simpler fallback
                        $schema['image'] = $image_url;
                    }
                }
            }

            // Add FAQPage specific schema
            if ($schema['@type'] === 'FAQPage') {
                $faq_main_entity_str = get_post_meta( $post_id, '_wpsmd_faq_main_entity', true );
                if ( ! empty( $faq_main_entity_str ) ) {
                    $schema['mainEntity'] = array();
                    $qa_pairs = explode( "\n", trim( $faq_main_entity_str ) );
                    foreach ( $qa_pairs as $pair_str ) {
                        if ( strpos( $pair_str, '|' ) !== false ) {
                            list( $question, $answer ) = array_map( 'trim', explode( '|', $pair_str, 2 ) );
                            if ( ! empty( $question ) && ! empty( $answer ) ) {
                                $schema['mainEntity'][] = array(
                                    '@type' => 'Question',
                                    'name' => esc_html( $question ),
                                    'acceptedAnswer' => array(
                                        '@type' => 'Answer',
                                        'text' => wp_kses_post( $answer ) // Allow basic HTML in answers
                                    )
                                );
                            }
                        }
                    }
                    if (empty($schema['mainEntity'])) unset($schema['mainEntity']); // Remove if no valid Q&A pairs found
                }
            }

            // Add BreadcrumbList specific schema
            if ($schema['@type'] === 'BreadcrumbList') {
                $breadcrumb_items_str = get_post_meta( $post_id, '_wpsmd_breadcrumb_items', true );
                if ( ! empty( $breadcrumb_items_str ) ) {
                    $schema['itemListElement'] = array();
                    $items = explode( "\n", trim( $breadcrumb_items_str ) );
                    $position = 1;
                    foreach ( $items as $item_str ) {
                        if ( strpos( $item_str, '|' ) !== false ) {
                            list( $name, $url ) = array_map( 'trim', explode( '|', $item_str, 2 ) );
                            if ( ! empty( $name ) && ! empty( $url ) ) {
                                $schema['itemListElement'][] = array(
                                    '@type' => 'ListItem',
                                    'position' => $position++,
                                    'name' => esc_html( $name ),
                                    'item' => esc_url( $url )
                                );
                            }
                        }
                    }
                    if (empty($schema['itemListElement'])) unset($schema['itemListElement']);
                    // For BreadcrumbList, properties like headline, description, author, publisher, datePublished, dateModified are not relevant.
                    unset($schema['headline']);
                    unset($schema['description']);
                    unset($schema['author']);
                    unset($schema['publisher']);
                    unset($schema['datePublished']);
                    unset($schema['dateModified']);
                    unset($schema['image']); // Image is also not typical for BreadcrumbList
                }
            }

            // Add Organization specific schema
            if ($schema['@type'] === 'Organization') {
                $schema['name'] = get_post_meta( $post_id, '_wpsmd_org_name', true ) ?: get_bloginfo( 'name' );
                $logo_url = get_post_meta( $post_id, '_wpsmd_org_logo_url', true );
                if (!empty($logo_url)) {
                    $schema['logo'] = esc_url($logo_url);
                } elseif (get_site_icon_url()) {
                    $schema['logo'] = get_site_icon_url();
                }
                $schema['legalName'] = get_post_meta( $post_id, '_wpsmd_organization_legal_name', true );

                $address = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => get_post_meta( $post_id, '_wpsmd_organization_street_address', true ),
                    'addressLocality' => get_post_meta( $post_id, '_wpsmd_organization_address_locality', true ),
                    'addressRegion' => get_post_meta( $post_id, '_wpsmd_organization_address_region', true ),
                    'postalCode' => get_post_meta( $post_id, '_wpsmd_organization_postal_code', true ),
                    'addressCountry' => get_post_meta( $post_id, '_wpsmd_organization_address_country', true ),
                );
                // Only add address if at least one field is filled
                if (array_filter($address)) {
                    $schema['address'] = $address;
                }

                $telephone = get_post_meta( $post_id, '_wpsmd_organization_telephone', true );
                if (!empty($telephone)) $schema['telephone'] = esc_attr($telephone);
                $email = get_post_meta( $post_id, '_wpsmd_organization_email', true );
                if (!empty($email)) $schema['email'] = sanitize_email($email);

                $same_as_str = get_post_meta( $post_id, '_wpsmd_organization_same_as', true );
                if (!empty($same_as_str)) {
                    $same_as_urls = array_map('trim', explode("\n", esc_textarea($same_as_str)));
                    $schema['sameAs'] = array_filter(array_map('esc_url', $same_as_urls));
                    if (empty($schema['sameAs'])) unset($schema['sameAs']);
                }
                // Remove default publisher if we are on an Organization schema page, as it's redundant
                unset($schema['publisher']);
                // Organization schema doesn't typically have headline, author, datePublished, dateModified.
                unset($schema['headline']);
                unset($schema['author']);
                unset($schema['datePublished']);
                unset($schema['dateModified']);
                unset($schema['description']); // Also remove description as it's usually not part of Organization schema directly
                // mainEntityOfPage might still be relevant if this Organization page is part of a larger site.
            }

            if ($schema['@type'] === 'Article' || $schema['@type'] === 'WebPage') { // Keep existing Article/WebPage specific fields
                 if ( has_post_thumbnail( $post_id ) && !isset($schema['image'])) { // Ensure image is not overwritten if already set by Product/Recipe
                    $image_id = get_post_thumbnail_id( $post_id );
                    $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                    $attachment_meta = null; // Initialize

                    // Ensure wp_get_attachment_metadata is available
                    if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
                        // This function is defined in wp-admin/includes/image.php, which might not be loaded on frontend.
                        // Conditionally load it if we are in an admin context or AJAX request, or if explicitly needed.
                        if ( ( defined( 'WP_ADMIN' ) && WP_ADMIN ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
                            if ( defined( 'ABSPATH' ) ) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                            }
                        }
                    }

                    if ( function_exists( 'wp_get_attachment_metadata' ) ) {
                        $attachment_meta = wp_get_attachment_metadata( $image_id );
                    } else {
                        // Fallback if function is still not available (e.g. image.php failed to load or function removed by other means)
                        $attachment_meta = array( 'width' => 0, 'height' => 0 ); // Mimic structure for width/height
                    }

                    // wp_get_attachment_metadata returns an array with 'width' and 'height' at the top level.
                    if ( $image_url && $attachment_meta && isset($attachment_meta['width']) && $attachment_meta['width'] > 0 && isset($attachment_meta['height']) && $attachment_meta['height'] > 0 ) {
                        $schema['image'] = array(
                            '@type'  => 'ImageObject',
                            'url'    => $image_url,
                            'width'  => $attachment_meta['width'],
                            'height' => $attachment_meta['height'],
                        );
                    } elseif ( $image_url ) { // If meta fails but URL exists, provide URL only as a simpler fallback
                        $schema['image'] = $image_url;
                    }
                }
            }

            // Add speakable for Article and NewsArticle types
            // Note: 'NewsArticle' is not a selectable type in admin, but if a theme/plugin changes type to NewsArticle, this will apply.
            if ($schema['@type'] === 'Article' || $schema['@type'] === 'NewsArticle') {
                $speakable_css_selectors_str = get_post_meta( $post_id, '_wpsmd_speakable_css_selectors', true );
                if ( ! empty( $speakable_css_selectors_str ) ) {
                    $selectors = array_map( 'trim', explode( "\n", $speakable_css_selectors_str ) );
                    $selectors = array_filter( $selectors ); // Remove empty lines
                    if ( ! empty( $selectors ) ) {
                        $schema['speakable'] = array(
                            '@type' => 'SpeakableSpecification',
                            'cssSelector' => $selectors
                        );
                    }
                }
            }

            if ($schema['@type'] === 'Article') {
                $categories = get_the_category( $post_id );
                if ( ! empty( $categories ) ) {
                    $schema['articleSection'] = esc_html( $categories[0]->name );
                }
                $tags = get_the_tags( $post_id );
                if ( $tags ) {
                    $keywords = array();
                    foreach ( $tags as $tag ) {
                        $keywords[] = esc_html( $tag->name );
                    }
                    $schema['keywords'] = implode( ', ', $keywords );
                }
            }

            if ( ! empty( $schema['description'] ) ) {
                echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
            }
        }
    }

    /**
     * Outputs the Open Graph and Twitter Card meta tags in the HTML head.
     */
    public function output_social_meta_tags() {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( ! $post_id ) {
                return;
            }

            // SEO Title and Description (for fallbacks)
            $wpsmd_options = get_option( 'wpsmd_options' );
            $enable_auto_seo_title = isset( $wpsmd_options['enable_auto_seo_title'] ) ? (bool) $wpsmd_options['enable_auto_seo_title'] : false;
            $enable_auto_seo_description = isset( $wpsmd_options['enable_auto_seo_description'] ) ? (bool) $wpsmd_options['enable_auto_seo_description'] : false;

            $custom_seo_title = get_post_meta( $post_id, '_wpsmd_seo_title', true );
            $seo_title = $custom_seo_title;
            if ( empty( $seo_title ) && $enable_auto_seo_title ) {
                $seo_title = get_the_title( $post_id );
            } elseif (empty($seo_title)) {
                $seo_title = get_the_title( $post_id ); // Default fallback
            }
            $seo_title = esc_attr($seo_title);

            $custom_meta_description = get_post_meta( $post_id, '_wpsmd_meta_description', true );
            $post_obj = get_post($post_id);
            $meta_description = $custom_meta_description;
            if ( empty( $meta_description ) && $enable_auto_seo_description ) {
                $meta_description = wp_strip_all_tags( $post_obj->post_excerpt ? $post_obj->post_excerpt : mb_substr( wp_strip_all_tags( $post_obj->post_content ), 0, 160 ) );
            } elseif (empty($meta_description)) {
                $meta_description = wp_strip_all_tags( $post_obj->post_excerpt ? $post_obj->post_excerpt : mb_substr( wp_strip_all_tags( $post_obj->post_content ), 0, 160 ) ); // Default fallback
            }
            $meta_description = esc_attr( $meta_description );

            // Featured image (for fallbacks)
            $featured_image_url = '';
            if ( has_post_thumbnail( $post_id ) ) {
                $featured_image_url = get_the_post_thumbnail_url( $post_id, 'full' );
            }

            // Open Graph Tags
            $og_title = get_post_meta( $post_id, '_wpsmd_og_title', true );
            $og_description = get_post_meta( $post_id, '_wpsmd_og_description', true );
            $og_image = get_post_meta( $post_id, '_wpsmd_og_image', true );

            $final_og_title = !empty($og_title) ? esc_attr($og_title) : $seo_title;
            $final_og_description = !empty($og_description) ? esc_attr($og_description) : $meta_description;
            $final_og_image = !empty($og_image) ? esc_url($og_image) : $featured_image_url;

            echo '<meta property="og:title" content="' . $final_og_title . '" />' . "\n";
            echo '<meta property="og:description" content="' . $final_og_description . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '" />' . "\n";
            echo '<meta property="og:type" content="' . (is_front_page() || is_home() ? 'website' : 'article') . '" />' . "\n";
            if ( !empty($final_og_image) ) {
                echo '<meta property="og:image" content="' . $final_og_image . '" />' . "\n";
            }
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            if (is_singular('post')) {
                 $author_id = $post_obj->post_author;
                 echo '<meta property="article:author" content="' . esc_url(get_author_posts_url($author_id)) . '" />' . "\n";
                 echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post_id)) . '" />' . "\n";
                 echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post_id)) . '" />' . "\n";
            }

            // Twitter Card Tags
            $twitter_title = get_post_meta( $post_id, '_wpsmd_twitter_title', true );
            $twitter_description = get_post_meta( $post_id, '_wpsmd_twitter_description', true );
            $twitter_image = get_post_meta( $post_id, '_wpsmd_twitter_image', true );

            $final_twitter_title = !empty($twitter_title) ? esc_attr($twitter_title) : $final_og_title;
            $final_twitter_description = !empty($twitter_description) ? esc_attr($twitter_description) : $final_og_description;
            $final_twitter_image = !empty($twitter_image) ? esc_url($twitter_image) : $final_og_image;

            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n"; // Default to summary_large_image
            echo '<meta name="twitter:title" content="' . $final_twitter_title . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . $final_twitter_description . '" />' . "\n";
            if ( !empty($final_twitter_image) ) {
                echo '<meta name="twitter:image" content="' . $final_twitter_image . '" />' . "\n";
            }
            // Optional: Add Twitter site and creator tags if you have global settings for them
            // echo '<meta name="twitter:site" content="@YourTwitterSiteHandle" />' . "\n";
            // echo '<meta name="twitter:creator" content="@YourTwitterCreatorHandle" />' . "\n";
        }
    }
}

?>