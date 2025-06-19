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

        add_settings_section(
            'wpsmd_auto_generation_settings_section', // ID
            __( 'Auto Generation Settings', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'print_auto_generation_section_info' ), // Callback
            'wpsmd-settings-admin' // Page
        );

        add_settings_field(
            'enable_auto_seo_title', // ID
            __( 'Enable Auto SEO Title', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'enable_auto_seo_title_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
        );

        add_settings_field(
            'enable_auto_seo_description', // ID
            __( 'Enable Auto SEO Description', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'enable_auto_seo_description_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
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
        $new_input['enable_auto_seo_title'] = isset( $input['enable_auto_seo_title'] ) ? 1 : 0;
        $new_input['enable_auto_seo_description'] = isset( $input['enable_auto_seo_description'] ) ? 1 : 0;
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

    /**
     * Print the Section text for Auto Generation.
     */
    public function print_auto_generation_section_info() {
        print __( 'Configure automatic generation of SEO titles and descriptions:', 'wp-seo-meta-descriptions' );
    }

    /**
     * Callback for Enable Auto SEO Title checkbox.
     */
    public function enable_auto_seo_title_callback() {
        printf(
            '<input type="checkbox" id="enable_auto_seo_title" name="wpsmd_options[enable_auto_seo_title]" value="1" %s />',
            checked( 1, isset( $this->options['enable_auto_seo_title'] ) ? $this->options['enable_auto_seo_title'] : 0, false )
        );
        echo '<label for="enable_auto_seo_title"> ' . __( 'Automatically generate SEO titles if not manually set.', 'wp-seo-meta-descriptions' ) . '</label>';
    }

    /**
     * Callback for Enable Auto SEO Description checkbox.
     */
    public function enable_auto_seo_description_callback() {
        printf(
            '<input type="checkbox" id="enable_auto_seo_description" name="wpsmd_options[enable_auto_seo_description]" value="1" %s />',
            checked( 1, isset( $this->options['enable_auto_seo_description'] ) ? $this->options['enable_auto_seo_description'] : 0, false )
        );
        echo '<label for="enable_auto_seo_description"> ' . __( 'Automatically generate meta descriptions if not manually set (uses OpenAI if API key is provided and field is empty, otherwise generates from content).', 'wp-seo-meta-descriptions' ) . '</label>';
    }
}

if ( is_admin() ) {
    new WPSMD_Settings();
}