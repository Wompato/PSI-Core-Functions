<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*
Plugin Name: PSI Core Functions
Description: Adds base functionality for the website including, custom post types, staff directory, forms, templates, and more.
Version: 1.0
*/
require_once plugin_dir_path(__FILE__) . 'includes/user-roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/forms/acf-form-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/forms/gform-handler.php';
require_once plugin_dir_path(__FILE__) . 'GW_Settings/settings.php';
require_once plugin_dir_path(__FILE__) . 'psi-api/RestEndpoints.php';

class PSI_Main {
    public function __construct() {
        // Check for plugin dependencies
        add_action('admin_init', array($this, 'check_plugin_dependencies'));

        // Hook to load ACF JSON files from plugin directory
        add_filter('acf/settings/load_json', array($this, 'load_acf_json_from_plugin'));

        // Hook to save ACF JSON files to plugin directory
        add_filter('acf/settings/save_json', array($this, 'save_acf_json_to_plugin'));

        // Register the REST API endpoint
        add_action('rest_api_init', array('RestClass', 'register_endpoints'));
    }

    public function check_plugin_dependencies() {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Define required plugins and their file paths
        $required_plugins = array(
            'Advanced Custom Fields Pro' => 'advanced-custom-fields-pro/acf.php',
            'Gravity Forms' => 'gravityforms/gravityforms.php',
            'Ninja Tables PRO' => 'ninja-tables/ninja-tables.php'
        );

        $missing_plugins = array();

        // Check if required plugins are active
        foreach ($required_plugins as $plugin_name => $plugin_file) {
            if (!is_plugin_active($plugin_file)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        // Display admin notice if any required plugin is missing
        if (!empty($missing_plugins)) {
            $error_message = sprintf(
                __('The following required plugin(s) are not active: %s', 'psi-main-textdomain'),
                implode(', ', $missing_plugins)
            );
            add_action('admin_notices', function () use ($error_message) {
                printf('<div class="error"><p>%s</p></div>', esc_html($error_message));
            });
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    // Function to load ACF JSON files from plugin directory
    public function load_acf_json_from_plugin($paths) {
        // Add custom load point for ACF JSON files from plugin directory
        $paths[] = plugin_dir_path(__FILE__) . 'acf-json';

        return $paths;
    }

    // Function to save ACF JSON files to plugin directory
    public function save_acf_json_to_plugin($path) {
        // Specify the save path to the acf-json folder inside the plugin directory
        $path = plugin_dir_path(__FILE__) . 'acf-json';

        return $path;
    }

    // Define your plugin methods here...
}

// Instantiate the plugin class
$psi_plugin = new PSI_Main();

