jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').hide();
        $(target).show();
    });

    // Load posts for bulk editor
    function loadPosts() {
        var postType = $('#post-type-filter').val();
        
        $.ajax({
            url: wpsmd_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'wpsmd_get_posts',
                post_type: postType,
                nonce: wpsmd_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var html = '';
                    response.data.forEach(function(post) {
                        html += '<div class="seo-item">';
                        html += '<h3>' + post.title + '</h3>';
                        html += '<p><label>SEO Title:</label><br>';
                        html += '<input type="text" name="seo_title[' + post.ID + ']" value="' + (post.seo_title || '') + '" class="large-text" /></p>';
                        html += '<p><label>Meta Description:</label><br>';
                        html += '<textarea name="meta_description[' + post.ID + ']" class="large-text" rows="3">' + (post.meta_description || '') + '</textarea></p>';
                        html += '</div>';
                    });
                    $('#bulk-seo-items').html(html);
                }
            }
        });
    }

    // Load posts on post type change
    $('#post-type-filter').on('change', loadPosts);
    
    // Initial load
    loadPosts();

    // Save bulk meta data
    $('#bulk-seo-form').on('submit', function(e) {
        e.preventDefault();
        
        var items = [];
        $('.seo-item').each(function() {
            var postId = $(this).find('input[name^="seo_title"]').attr('name').match(/\d+/)[0];
            items.push({
                post_id: postId,
                title: $(this).find('input[name^="seo_title"]').val(),
                description: $(this).find('textarea[name^="meta_description"]').val()
            });
        });

        $.ajax({
            url: wpsmd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_save_bulk_meta',
                items: items,
                nonce: wpsmd_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Updated ' + response.data.updated + ' items successfully!');
                }
            }
        });
    });

    // Save CPT templates
    $('#cpt-templates-form').on('submit', function(e) {
        e.preventDefault();
        
        var titleTemplates = {};
        var descTemplates = {};
        
        $('input[name^="title_template"]').each(function() {
            var postType = $(this).attr('name').match(/\[(.*?)\]/)[1];
            titleTemplates[postType] = $(this).val();
        });
        
        $('textarea[name^="desc_template"]').each(function() {
            var postType = $(this).attr('name').match(/\[(.*?)\]/)[1];
            descTemplates[postType] = $(this).val();
        });

        $.ajax({
            url: wpsmd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_save_cpt_templates',
                title_templates: titleTemplates,
                desc_templates: descTemplates,
                nonce: wpsmd_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Templates saved successfully!');
                }
            }
        });
    });

    // Export settings
    $('#export-settings').on('click', function() {
        $.ajax({
            url: wpsmd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_export_settings',
                nonce: wpsmd_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var dataStr = JSON.stringify(response.data);
                    var dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
                    
                    var exportLink = document.createElement('a');
                    exportLink.setAttribute('href', dataUri);
                    exportLink.setAttribute('download', 'wpsmd-settings.json');
                    document.body.appendChild(exportLink);
                    exportLink.click();
                    document.body.removeChild(exportLink);
                }
            }
        });
    });

    // Import settings
    $('#import-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = document.getElementById('import-file');
        if (!fileInput.files.length) {
            alert('Please select a file to import.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'wpsmd_import_settings');
        formData.append('nonce', wpsmd_ajax.nonce);
        formData.append('import_file', fileInput.files[0]);

        $.ajax({
            url: wpsmd_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Settings imported successfully!');
                    location.reload();
                }
            }
        });
    });
}));