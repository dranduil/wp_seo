<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div class="wrap wpsmd-analytics-dashboard">
    <h1><?php _e('SEO Analytics Dashboard', 'wp-seo-meta-descriptions'); ?></h1>

    <div class="wpsmd-analytics-grid">
        <!-- Search Console Connection Status -->
        <div class="wpsmd-analytics-card wpsmd-gsc-status">
            <h2><?php _e('Google Search Console', 'wp-seo-meta-descriptions'); ?></h2>
            <div class="wpsmd-gsc-connection">
                <p class="description"><?php _e('Connect to Google Search Console to view your search performance data.', 'wp-seo-meta-descriptions'); ?></p>
                <button class="button button-primary" id="wpsmd-verify-gsc">
                    <?php _e('Connect to Search Console', 'wp-seo-meta-descriptions'); ?>
                </button>
            </div>
        </div>

        <!-- Search Performance Overview -->
        <div class="wpsmd-analytics-card wpsmd-search-performance">
            <h2><?php _e('Search Performance', 'wp-seo-meta-descriptions'); ?></h2>
            <div class="wpsmd-metrics-grid">
                <div class="wpsmd-metric">
                    <h3><?php _e('Clicks', 'wp-seo-meta-descriptions'); ?></h3>
                    <div class="wpsmd-metric-value" id="wpsmd-clicks">-</div>
                </div>
                <div class="wpsmd-metric">
                    <h3><?php _e('Impressions', 'wp-seo-meta-descriptions'); ?></h3>
                    <div class="wpsmd-metric-value" id="wpsmd-impressions">-</div>
                </div>
                <div class="wpsmd-metric">
                    <h3><?php _e('Avg. Position', 'wp-seo-meta-descriptions'); ?></h3>
                    <div class="wpsmd-metric-value" id="wpsmd-position">-</div>
                </div>
                <div class="wpsmd-metric">
                    <h3><?php _e('CTR', 'wp-seo-meta-descriptions'); ?></h3>
                    <div class="wpsmd-metric-value" id="wpsmd-ctr">-</div>
                </div>
            </div>
            <div id="wpsmd-performance-chart" class="wpsmd-chart"></div>
        </div>

        <!-- Top Keywords -->
        <div class="wpsmd-analytics-card wpsmd-keywords">
            <h2><?php _e('Top Keywords', 'wp-seo-meta-descriptions'); ?></h2>
            <div class="wpsmd-table-container">
                <table class="wpsmd-keywords-table">
                    <thead>
                        <tr>
                            <th><?php _e('Keyword', 'wp-seo-meta-descriptions'); ?></th>
                            <th><?php _e('Clicks', 'wp-seo-meta-descriptions'); ?></th>
                            <th><?php _e('Impressions', 'wp-seo-meta-descriptions'); ?></th>
                            <th><?php _e('Position', 'wp-seo-meta-descriptions'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpsmd-keywords-body">
                        <tr>
                            <td colspan="4" class="wpsmd-no-data"><?php _e('No data available', 'wp-seo-meta-descriptions'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Crawl Errors -->
        <div class="wpsmd-analytics-card wpsmd-crawl-errors">
            <h2><?php _e('Crawl Errors', 'wp-seo-meta-descriptions'); ?></h2>
            <div class="wpsmd-table-container">
                <table class="wpsmd-errors-table">
                    <thead>
                        <tr>
                            <th><?php _e('URL', 'wp-seo-meta-descriptions'); ?></th>
                            <th><?php _e('Error Type', 'wp-seo-meta-descriptions'); ?></th>
                            <th><?php _e('Last Detected', 'wp-seo-meta-descriptions'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpsmd-errors-body">
                        <tr>
                            <td colspan="3" class="wpsmd-no-data"><?php _e('No errors found', 'wp-seo-meta-descriptions'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>