<?php
/**
 * Admin settings page for WP SEO Meta Descriptions.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPSMD_Settings {

    private $options;

    /**
     * Initialize the settings page hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add options page.
     */
    public function add_plugin_settings_page() {
        add_options_page(
            __( 'WP SEO Meta Descriptions Settings', 'wp-seo-meta-descriptions' ),
            __( 'WP SEO Meta', 'wp-seo-meta-descriptions' ),
            'manage_options',
            'wpsmd-settings',
            array( $this, 'create_admin_settings_page' )
        );
    }

    /**
     * Create the settings page.
     */
    public function create_admin_settings_page() {
        $this->options = get_option( 'wpsmd_options' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpsmd_option_group' );
                do_settings_sections( 'wpsmd-settings-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings.
     */
    public function register_settings() {
        register_setting(
            'wpsmd_option_group', // Option group
            'wpsmd_options', // Option name
            array( $this, 'sanitize_settings' ) // Sanitize callback
        );

        add_settings_section(
            'wpsmd_openai_settings_section', // ID
            __( 'OpenAI API Settings', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'print_openai_section_info' ), // Callback
            'wpsmd-settings-admin' // Page
        );

        add_settings_field(
            'openai_api_key', // ID
            __( 'OpenAI API Key', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'openai_api_key_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_openai_settings_section' // Section
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param array $input Contains all settings fields as array keys.
     */
    public function sanitize_settings( $input ) {
        $new_input = array();
        if ( isset( $input['openai_api_key'] ) ) {
            $new_input['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
        }
        return $new_input;
    }

    /**
     * Print the Section text.
     */
    public function print_openai_section_info() {
        print __( 'Enter your OpenAI API settings below:', 'wp-seo-meta-descriptions' );
    }

    /**
     * Get the settings option array and print one of its values.
     */
    public function openai_api_key_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="wpsmd_options[openai_api_key]" value="%s" style="width: 50%%;" />',
            isset( $this->options['openai_api_key'] ) ? esc_attr( $this->options['openai_api_key'] ) : ''
        );
        echo '<p class="description">' . __( 'Enter your OpenAI API key. This key will be used for AI-powered features.', 'wp-seo-meta-descriptions' ) . '</p>';
    }
}

if ( is_admin() ) {
    new WPSMD_Settings();
}