<?php
/**
 * Analytics functionality for the WP SEO Meta Descriptions plugin.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPSMD_Analytics {
    /**
     * Initialize the analytics hooks.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_analytics_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        add_action('wp_ajax_wpsmd_verify_gsc', array($this, 'verify_search_console'));
        add_action('wp_ajax_wpsmd_get_search_analytics', array($this, 'get_search_analytics'));
        add_action('wp_ajax_wpsmd_get_crawl_errors', array($this, 'get_crawl_errors'));
    }

    /**
     * Add analytics menu item.
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'tools.php',
            __('SEO Analytics', 'wp-seo-meta-descriptions'),
            __('SEO Analytics', 'wp-seo-meta-descriptions'),
            'manage_options',
            'wpsmd-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Enqueue analytics scripts and styles.
     */
    public function enqueue_analytics_scripts($hook) {
        if ('tools_page_wpsmd-analytics' !== $hook) {
            return;
        }

        wp_enqueue_script('wpsmd-analytics-js', 
            plugin_dir_url(__FILE__) . '../../assets/js/analytics.js',
            array('jquery', 'wp-api'), 
            WPSMD_VERSION,
            true
        );

        wp_enqueue_style('wpsmd-analytics-css',
            plugin_dir_url(__FILE__) . '../../assets/css/analytics.css',
            array(),
            WPSMD_VERSION
        );

        wp_localize_script('wpsmd-analytics-js', 'wpsmdAnalytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsmd_analytics_nonce'),
            'i18n' => array(
                'verifySuccess' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'verifyError' => __('Error connecting to Google Search Console', 'wp-seo-meta-descriptions'),
                'loadingData' => __('Loading data...', 'wp-seo-meta-descriptions'),
                'errorLoadingData' => __('Error loading data', 'wp-seo-meta-descriptions')
            )
        ));
    }

    /**
     * Render the analytics dashboard page.
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once WPSMD_PLUGIN_PATH . 'includes/admin/views/analytics-dashboard.php';
    }

    /**
     * Verify Google Search Console connection.
     */
    public function verify_search_console() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        $options = get_option('wpsmd_options');
        $client_id = isset($options['gsc_client_id']) ? $options['gsc_client_id'] : '';
        $client_secret = isset($options['gsc_client_secret']) ? $options['gsc_client_secret'] : '';

        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(array('message' => __('Please configure Google Search Console API credentials in settings.', 'wp-seo-meta-descriptions')));
            return;
        }

        // Initialize Google Client
        require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';

        try {
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri(admin_url('admin.php?page=wpsmd-analytics'));
            $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');

            // Handle the OAuth 2.0 flow
            if (!isset($_GET['code'])) {
                $auth_url = $client->createAuthUrl();
                wp_send_json_success(array(
                    'auth_url' => $auth_url,
                    'message' => __('Please authorize access to Google Search Console', 'wp-seo-meta-descriptions')
                ));
            } else {
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                update_option('wpsmd_gsc_token', $token);
                wp_send_json_success(array('message' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get search analytics data from Google Search Console.
     */
    public function get_search_analytics() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        $token = get_option('wpsmd_gsc_token');
        if (empty($token)) {
            wp_send_json_error(array('message' => __('Please connect to Google Search Console first.', 'wp-seo-meta-descriptions')));
            return;
        }

        require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';

        try {
            $client = new Google_Client();
            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    update_option('wpsmd_gsc_token', $client->getAccessToken());
                } else {
                    wp_send_json_error(array('message' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            $searchConsole = new Google_Service_SearchConsole($client);
            $siteUrl = get_site_url();

            // Get search analytics data for the last 30 days
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));

            $request = new Google_Service_SearchConsole_SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['query']);
            $request->setRowLimit(10);

            $response = $searchConsole->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows();

            $total_clicks = 0;
            $total_impressions = 0;
            $total_position = 0;
            $keywords = array();

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $total_clicks += $row->getClicks();
                    $total_impressions += $row->getImpressions();
                    $total_position += $row->getPosition();

                    $keywords[] = array(
                        'term' => $row->getKeys()[0],
                        'clicks' => $row->getClicks(),
                        'impressions' => $row->getImpressions(),
                        'position' => $row->getPosition(),
                        'ctr' => $row->getCtr()
                    );
                }
            }

            $avg_position = count($rows) > 0 ? $total_position / count($rows) : 0;
            $ctr = $total_impressions > 0 ? $total_clicks / $total_impressions : 0;

            wp_send_json_success(array(
                'clicks' => $total_clicks,
                'impressions' => $total_impressions,
                'position' => $avg_position,
                'ctr' => $ctr,
                'keywords' => $keywords
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get crawl errors from Google Search Console.
     */
    public function get_crawl_errors() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        $token = get_option('wpsmd_gsc_token');
        if (empty($token)) {
            wp_send_json_error(array('message' => __('Please connect to Google Search Console first.', 'wp-seo-meta-descriptions')));
            return;
        }

        require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';

        try {
            $client = new Google_Client();
            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    update_option('wpsmd_gsc_token', $client->getAccessToken());
                } else {
                    wp_send_json_error(array('message' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            $searchConsole = new Google_Service_SearchConsole($client);
            $siteUrl = get_site_url();

            // Get indexing issues
            $request = new Google_Service_SearchConsole_InspectUrlIndexRequest();
            $request->setInspectionUrl($siteUrl);
            $response = $searchConsole->urlInspection_index->inspect($request);

            $errors = array();
            $inspectionResult = $response->getInspectionResult();

            if ($inspectionResult) {
                $indexingState = $inspectionResult->getIndexingState();
                if ($indexingState && $indexingState->getPageFetchState() !== 'SUCCESSFUL') {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'FETCH_ERROR',
                        'detected' => current_time('mysql')
                    );
                }

                if ($indexingState && !$indexingState->getIsIndexed()) {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'NOT_INDEXED',
                        'detected' => current_time('mysql')
                    );
                }

                $mobileFriendliness = $inspectionResult->getMobileFriendliness();
                if ($mobileFriendliness && $mobileFriendliness->getVerdict() !== 'MOBILE_FRIENDLY') {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'NOT_MOBILE_FRIENDLY',
                        'detected' => current_time('mysql')
                    );
                }
            }

            wp_send_json_success(array('errors' => $errors));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}