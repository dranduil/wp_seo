<?php
/**
 * Analytics functionality for the WP SEO Meta Descriptions plugin.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Check if composer autoload exists and load it
if (file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';
}

class WPSMD_Analytics {
    /**
     * Initialize the analytics hooks.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_analytics_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        add_action('wp_ajax_wpsmd_verify_gsc', array($this, 'verify_search_console'));
        add_action('wp_ajax_wpsmd_disconnect_gsc', array($this, 'disconnect_search_console'));
        add_action('wp_ajax_wpsmd_get_search_analytics', array($this, 'get_search_analytics'));
        add_action('wp_ajax_wpsmd_get_crawl_errors', array($this, 'get_crawl_errors'));
        add_action('admin_notices', array($this, 'check_dependencies'));
    }

    /**
     * Disconnect from Google Search Console.
     */
    public function disconnect_search_console() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        delete_option('wpsmd_gsc_token');
        wp_send_json_success(array('message' => __('Successfully disconnected from Google Search Console', 'wp-seo-meta-descriptions')));
    }

    /**
     * Check if required dependencies are installed.
     */
    public function check_dependencies() {
        if (!file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
            echo '<div class="notice notice-error"><p>';
            echo __('WP SEO Meta Descriptions plugin requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory.', 'wp-seo-meta-descriptions');
            echo '</p></div>';
        }
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

        $nonce = wp_create_nonce('wpsmd_analytics_nonce');
        error_log('WPSMD: Generated nonce: ' . $nonce);

        wp_localize_script('wpsmd-analytics-js', 'wpsmdAnalytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'i18n' => array(
                'verifySuccess' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'verifyError' => __('Error connecting to Google Search Console', 'wp-seo-meta-descriptions'),
                'loadingData' => __('Loading data...', 'wp-seo-meta-descriptions'),
                'errorLoadingData' => __('Error loading data', 'wp-seo-meta-descriptions'),
                'authRequired' => __('Please authorize access to Google Search Console', 'wp-seo-meta-descriptions'),
                'tokenExpired' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions'),
                'alreadyConnected' => __('Already connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'connectionRefreshed' => __('Successfully refreshed Google Search Console connection', 'wp-seo-meta-descriptions'),
                'disconnected' => __('Successfully disconnected from Google Search Console', 'wp-seo-meta-descriptions')
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

        // Show success message if just connected and state is valid
        if (isset($_GET['connection']) && $_GET['connection'] === 'success' &&
            isset($_GET['state']) && wp_verify_nonce($_GET['state'], 'wpsmd_gsc_success')) {
            add_settings_error(
                'wpsmd_messages',
                'wpsmd_connection_success',
                __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'updated'
            );
        }

        // Display any settings messages
        settings_errors('wpsmd_messages');

        require_once WPSMD_PLUGIN_PATH . 'includes/admin/views/analytics-dashboard.php';
    }

    /**
     * Verify Google Search Console connection.
     */
    public function verify_search_console() {
        error_log('WPSMD: Starting verify_search_console');
        
        if (!check_ajax_referer('wpsmd_analytics_nonce', 'nonce', false)) {
            error_log('WPSMD: Nonce verification failed');
            wp_send_json_error(array('message' => __('Invalid nonce', 'wp-seo-meta-descriptions')));
            return;
        }

        if (!current_user_can('manage_options')) {
            error_log('WPSMD: Permission check failed');
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        error_log('WPSMD: Permission check passed');
        $options = get_option('wpsmd_options');
        error_log('WPSMD: Retrieved options: ' . print_r($options, true));

        $client_id = isset($options['gsc_client_id']) ? $options['gsc_client_id'] : '';
        $client_secret = isset($options['gsc_client_secret']) ? $options['gsc_client_secret'] : '';

        if (empty($client_id) || empty($client_secret)) {
            error_log('WPSMD: Missing GSC credentials - Client ID: ' . (empty($client_id) ? 'empty' : 'set') . ', Client Secret: ' . (empty($client_secret) ? 'empty' : 'set'));
            wp_send_json_error(array('message' => __('Please configure Google Search Console API credentials in settings.', 'wp-seo-meta-descriptions')));
            return;
        }

        error_log('WPSMD: GSC credentials validation passed');

        error_log('WPSMD: Starting Google Client initialization');

        // Check if Google API client is available
        if (!class_exists('Google_Client')) {
            error_log('WPSMD: Google_Client class not found. Checking autoloader...');
            if (!file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
                error_log('WPSMD: Composer autoload.php not found. Please run composer install');
                wp_send_json_error(array('message' => __('Google API Client not installed. Please contact the administrator.', 'wp-seo-meta-descriptions')));
                return;
            }
            require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';
            if (!class_exists('Google_Client')) {
                error_log('WPSMD: Google_Client class still not found after loading autoloader');
                wp_send_json_error(array('message' => __('Google API Client not properly installed. Please contact the administrator.', 'wp-seo-meta-descriptions')));
                return;
            }
        }

        error_log('WPSMD: Google_Client class found, proceeding with initialization');

        try {
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setScopes(array(
                'https://www.googleapis.com/auth/webmasters',
                'https://www.googleapis.com/auth/webmasters.readonly',
                'https://www.googleapis.com/auth/siteverification',
                'https://www.googleapis.com/auth/siteverification.verify_only'
            ));
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            
            // Generate state parameter for CSRF protection
            $state = wp_create_nonce('wpsmd_gsc_auth');
            $client->setState($state);
            
            // Set redirect URI to admin-ajax.php endpoint with action parameter
            // IMPORTANT: This exact URL must be added to authorized redirect URIs in Google Cloud Console
            $redirect_uri = site_url('wp-admin/admin-ajax.php');
            $client->setRedirectUri($redirect_uri);
            error_log('WPSMD: Full redirect URI with protocol: ' . $redirect_uri);
            error_log('WPSMD: Setting redirect URI to: ' . $redirect_uri);
            
            // Add state parameter to track the original request
            $state = array(
                'nonce' => wp_create_nonce('wpsmd_gsc_auth'),
                'action' => 'wpsmd_verify_gsc',
                'page' => 'wpsmd-analytics'
            );
            $client->setState(base64_encode(json_encode($state)));
            
            // OAuth parameters already set above

            // Check if we already have a token
            $existing_token = get_option('wpsmd_gsc_token');
            if (!empty($existing_token)) {
                $client->setAccessToken($existing_token);
                
                if ($client->isAccessTokenExpired()) {
                    if ($client->getRefreshToken()) {
                        try {
                            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                            update_option('wpsmd_gsc_token', $client->getAccessToken());
                            wp_send_json_success(array('message' => __('Successfully refreshed Google Search Console connection', 'wp-seo-meta-descriptions')));
                            return;
                        } catch (Exception $e) {
                            error_log('WPSMD: Token refresh failed: ' . $e->getMessage());
                            delete_option('wpsmd_gsc_token');
                        }
                    } else {
                        error_log('WPSMD: Token expired and no refresh token available');
                        delete_option('wpsmd_gsc_token');
                    }
                } else {
                    wp_send_json_success(array('message' => __('Already connected to Google Search Console', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            // Handle the OAuth 2.0 flow
            if (isset($_GET['code'])) {
                // Verify state parameter to prevent CSRF
                if (!isset($_GET['state'])) {
                    error_log('WPSMD: Missing state parameter');
                    wp_send_json_error(array('message' => __('Missing state parameter. Please try again.', 'wp-seo-meta-descriptions')));
                    return;
                }

                try {
                    $state = json_decode(base64_decode($_GET['state']), true);
                    if (!$state || !isset($state['nonce']) || !wp_verify_nonce($state['nonce'], 'wpsmd_gsc_auth')) {
                        error_log('WPSMD: Invalid OAuth state');
                        wp_send_json_error(array('message' => __('Invalid OAuth state. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }

                    // Set the action and page parameters from the state
                    $_GET['action'] = $state['action'];
                    $_GET['page'] = $state['page'];
                } catch (Exception $e) {
                    error_log('WPSMD: Error decoding state: ' . $e->getMessage());
                    wp_send_json_error(array('message' => __('Invalid state format. Please try again.', 'wp-seo-meta-descriptions')));
                    return;
                }

                try {
                    error_log('WPSMD: Received authorization code, attempting to fetch token');
                    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                    
                    if (isset($token['error'])) {
                        error_log('WPSMD: Token fetch error: ' . $token['error']);
                        wp_send_json_error(array('message' => __('Error fetching access token. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }
                    
                    error_log('WPSMD: Token fetched successfully: ' . print_r($token, true));
                    update_option('wpsmd_gsc_token', $token);
                    
                    // Verify the token works by making a test API call
                    $searchConsole = new Google_Service_SearchConsole($client);
                    $siteUrl = get_site_url();
                    
                    try {
                        // First check if the site is already verified
                        $site = $searchConsole->sites->get($siteUrl);
                        error_log('WPSMD: Site verification status: ' . print_r($site->permissionLevel, true));
                        
                        if ($site->permissionLevel === 'siteUnverifiedUser') {
                            error_log('WPSMD: Site is not verified');
                            delete_option('wpsmd_gsc_token');
                            wp_send_json_error(array('message' => __('Please verify your site in Google Search Console first.', 'wp-seo-meta-descriptions')));
                            return;
                        }
                    } catch (Google_Service_Exception $e) {
                        error_log('WPSMD: Site verification check failed: ' . $e->getMessage());
                        if ($e->getCode() === 404) {
                            delete_option('wpsmd_gsc_token');
                            wp_send_json_error(array('message' => __('Your site is not found in Google Search Console. Please add and verify it first.', 'wp-seo-meta-descriptions')));
                            return;
                        }
                        throw $e;
                    }
                    
                    error_log('WPSMD: Test API call successful');
                    
                    // If this is the OAuth callback (has code parameter), redirect to the analytics page
                    if (isset($_GET['code'])) {
                        $return_url = add_query_arg(
                            array(
                                'page' => 'wpsmd-analytics',
                                'connection' => 'success',
                                'state' => wp_create_nonce('wpsmd_gsc_success')
                            ),
                            admin_url('tools.php')
                        );
                        wp_redirect($return_url);
                        exit;
                    }
                    
                    wp_send_json_success(array(
                        'message' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                        'reload' => true
                    ));
                } catch (Exception $e) {
                    error_log('WPSMD: Token fetch/verification exception: ' . $e->getMessage());
                    delete_option('wpsmd_gsc_token'); // Clean up failed token
                    wp_send_json_error(array('message' => __('Error connecting to Google Search Console. Please try again.', 'wp-seo-meta-descriptions')));
                }
            } else {
                $auth_url = $client->createAuthUrl();
                error_log('WPSMD: Generated auth URL: ' . $auth_url);
                wp_send_json_success(array(
                    'auth_url' => $auth_url,
                    'message' => __('Please authorize access to Google Search Console', 'wp-seo-meta-descriptions')
                ));
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