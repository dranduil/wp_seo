<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div class="wrap wpsmd-bulk-seo">
    <h1><?php _e('Bulk SEO Editor', 'wp-seo-meta-descriptions'); ?></h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#bulk-editor" class="nav-tab nav-tab-active" data-tab="bulk-editor"><?php _e('Bulk Editor', 'wp-seo-meta-descriptions'); ?></a>
        <a href="#cpt-templates" class="nav-tab" data-tab="cpt-templates"><?php _e('CPT Templates', 'wp-seo-meta-descriptions'); ?></a>
        <a href="#import-export" class="nav-tab" data-tab="import-export"><?php _e('Import/Export', 'wp-seo-meta-descriptions'); ?></a>
    </nav>

    <!-- Bulk Editor Tab -->
    <div id="bulk-editor" class="tab-content active">
        <div class="bulk-editor-controls">
            <select id="post-type-filter">
                <option value="post"><?php _e('Posts', 'wp-seo-meta-descriptions'); ?></option>
                <option value="page"><?php _e('Pages', 'wp-seo-meta-descriptions'); ?></option>
                <?php
                $post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
                foreach ($post_types as $post_type) {
                    echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                }
                ?>
            </select>
            <button class="button" id="load-posts"><?php _e('Load Posts', 'wp-seo-meta-descriptions'); ?></button>
        </div>

        <div class="bulk-editor-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-title"><?php _e('Title', 'wp-seo-meta-descriptions'); ?></th>
                        <th class="column-seo-title"><?php _e('SEO Title', 'wp-seo-meta-descriptions'); ?></th>
                        <th class="column-meta-description"><?php _e('Meta Description', 'wp-seo-meta-descriptions'); ?></th>
                        <th class="column-canonical"><?php _e('Canonical URL', 'wp-seo-meta-descriptions'); ?></th>
                    </tr>
                </thead>
                <tbody id="bulk-seo-rows"></tbody>
            </table>
        </div>

        <div class="bulk-editor-actions">
            <button class="button button-primary" id="save-bulk-seo"><?php _e('Save All Changes', 'wp-seo-meta-descriptions'); ?></button>
        </div>
    </div>

    <!-- CPT Templates Tab -->
    <div id="cpt-templates" class="tab-content">
        <form id="cpt-templates-form">
            <?php
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                $options = get_option('wpsmd_' . $post_type->name . '_templates', array());
                ?>
                <div class="cpt-template-section">
                    <h3><?php echo esc_html($post_type->label); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($post_type->name); ?>_title_template">
                                    <?php _e('Title Template', 'wp-seo-meta-descriptions'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" id="<?php echo esc_attr($post_type->name); ?>_title_template"
                                       name="<?php echo esc_attr($post_type->name); ?>_title_template"
                                       value="<?php echo esc_attr($options['title_template'] ?? '%title% | %sitename%'); ?>"
                                       class="large-text">
                                <p class="description"><?php _e('Available variables: %title%, %sitename%, %excerpt%, %category%, %author%', 'wp-seo-meta-descriptions'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($post_type->name); ?>_description_template">
                                    <?php _e('Description Template', 'wp-seo-meta-descriptions'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="<?php echo esc_attr($post_type->name); ?>_description_template"
                                          name="<?php echo esc_attr($post_type->name); ?>_description_template"
                                          class="large-text" rows="3"><?php echo esc_textarea($options['description_template'] ?? '%excerpt%'); ?></textarea>
                                <p class="description"><?php _e('Available variables: %title%, %sitename%, %excerpt%, %category%, %author%', 'wp-seo-meta-descriptions'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php
            }
            ?>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Save Templates', 'wp-seo-meta-descriptions'); ?></button>
            </p>
        </form>
    </div>

    <!-- Import/Export Tab -->
    <div id="import-export" class="tab-content">
        <div class="export-section">
            <h3><?php _e('Export Settings', 'wp-seo-meta-descriptions'); ?></h3>
            <p><?php _e('Export your SEO settings including templates and meta data.', 'wp-seo-meta-descriptions'); ?></p>
            <button class="button" id="export-settings"><?php _e('Export Settings', 'wp-seo-meta-descriptions'); ?></button>
        </div>

        <div class="import-section">
            <h3><?php _e('Import Settings', 'wp-seo-meta-descriptions'); ?></h3>
            <p><?php _e('Import your SEO settings. This will overwrite your current settings.', 'wp-seo-meta-descriptions'); ?></p>
            <input type="file" id="import-file" accept=".json">
            <button class="button" id="import-settings"><?php _e('Import Settings', 'wp-seo-meta-descriptions'); ?></button>
        </div>
    </div>
</div>