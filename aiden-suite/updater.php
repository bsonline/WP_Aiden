<?php
/**
 * AiDen Suite GitHub Updater
 *
 * A class to handle checking for updates from a public GitHub repository.
 *
 * @package AiDen_Suite
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AiDen_GitHub_Updater {

    private $plugin_slug;
    private $plugin_data;
    private $repo_slug;
    private $github_api_url;

    /**
     * Constructor. Sets up the properties and hooks.
     */
    public function __construct() {
        // The slug of our plugin. This is the directory/file.php.
        $this->plugin_slug = 'aiden-suite/aiden-suite.php';

        // We need get_plugin_data() to read the plugin's header for the version number.
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $this->plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_slug );

        // Get the repo slug from the settings.
        $this->repo_slug = get_option( 'aiden_github_repo' );

        // Set the GitHub API URL.
        if ( ! empty( $this->repo_slug ) ) {
            $this->github_api_url = 'https://api.github.com/repos/' . $this->repo_slug . '/releases/latest';
        }

        // Hook into the update check transient.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
    }

    /**
     * The main update check logic.
     *
     * @param object $transient The update transient object.
     * @return object The modified transient object.
     */
    public function check_for_update( $transient ) {
        if ( empty( $this->repo_slug ) ) {
            return $transient;
        }

        // Make the API request to GitHub.
        $response = wp_remote_get( $this->github_api_url );

        // Check for a valid response.
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $transient;
        }

        $release_data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $release_data ) || ! isset( $release_data->tag_name ) ) {
            return $transient;
        }

        // Compare the GitHub release version with the current plugin version.
        if ( version_compare( $release_data->tag_name, $this->plugin_data['Version'], '>' ) ) {
            $update_info = new \stdClass();
            $update_info->slug        = $this->plugin_slug;
            $update_info->plugin      = $this->plugin_slug;
            $update_info->new_version = $release_data->tag_name;
            $update_info->url         = $release_data->html_url;
            $update_info->package     = $release_data->zipball_url;

            // Inject our update info into the transient.
            $transient->response[ $this->plugin_slug ] = $update_info;
        }

        return $transient;
    }
}
