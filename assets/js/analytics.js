jQuery(document).ready(function($) {
    'use strict';
    initAnalytics();

    // Initialize the analytics dashboard
    function initAnalytics() {
        bindEvents();
        checkGSCConnection();
        loadAnalyticsData();
    }

    // Bind event handlers
    function bindEvents() {
        $('#wpsmd-verify-gsc').on('click', verifySearchConsole);
    }

    // Verify Google Search Console connection
    function verifySearchConsole() {
        const $button = $('#wpsmd-verify-gsc');
        const originalText = $button.text();

        $button.prop('disabled', true)
               .text(wpsmdAnalytics.i18n.loadingData);

        console.log('WPSMD: Sending verify request with nonce:', wpsmdAnalytics.nonce);

        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_verify_gsc',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                console.log('WPSMD: Verify response:', response);
                if (response.success) {
                    showNotice(wpsmdAnalytics.i18n.verifySuccess, 'success');
                    loadAnalyticsData();
                } else {
                    console.error('WPSMD: Verification failed:', response.data ? response.data.message : 'Unknown error');
                    showNotice(response.data ? response.data.message : wpsmdAnalytics.i18n.verifyError, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WPSMD: AJAX error:', textStatus, errorThrown);
                console.error('WPSMD: Response:', jqXHR.responseText);
                showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + textStatus, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Check GSC connection status
    function checkGSCConnection() {
        // TODO: Implement connection status check
    }

    // Load analytics data
    function loadAnalyticsData() {
        loadSearchAnalytics();
        loadCrawlErrors();
    }

    // Load search analytics data
    function loadSearchAnalytics() {
        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_get_search_analytics',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateMetrics(response.data);
                    updatePerformanceChart(response.data);
                    updateKeywordsTable(response.data.keywords || []);
                } else {
                    showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
                }
            },
            error: function() {
                showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
            }
        });
    }

    // Load crawl errors
    function loadCrawlErrors() {
        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_get_crawl_errors',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateErrorsTable(response.data.errors || []);
                } else {
                    showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
                }
            },
            error: function() {
                showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
            }
        });
    }

    // Update metrics display
    function updateMetrics(data) {
        $('#wpsmd-clicks').text(data.clicks || 0);
        $('#wpsmd-impressions').text(data.impressions || 0);
        $('#wpsmd-position').text(data.position ? data.position.toFixed(1) : 0);
        $('#wpsmd-ctr').text(data.ctr ? (data.ctr * 100).toFixed(1) + '%' : '0%');
    }

    // Update performance chart
    function updatePerformanceChart(data) {
        // TODO: Implement chart visualization using a charting library
    }

    // Update keywords table
    function updateKeywordsTable(keywords) {
        const $tbody = $('#wpsmd-keywords-body');
        if (!keywords.length) {
            return;
        }

        let html = '';
        keywords.forEach(function(keyword) {
            html += `
                <tr>
                    <td>${escapeHtml(keyword.term)}</td>
                    <td>${keyword.clicks}</td>
                    <td>${keyword.impressions}</td>
                    <td>${keyword.position.toFixed(1)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    // Update errors table
    function updateErrorsTable(errors) {
        const $tbody = $('#wpsmd-errors-body');
        if (!errors.length) {
            return;
        }

        let html = '';
        errors.forEach(function(error) {
            html += `
                <tr>
                    <td>${escapeHtml(error.url)}</td>
                    <td>${escapeHtml(error.type)}</td>
                    <td>${escapeHtml(error.detected)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    // Show admin notice
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>')
            .hide()
            .insertAfter('.wrap h1')
            .slideDown();

        setTimeout(function() {
            $notice.slideUp(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(initAnalytics);

})(jQuery);