<?php
/**
 * Plugin Name: Orion The WP Agent Connector
 * Description: Connecte votre site WordPress à Orion The WP Agent.
 * Version: 1.1.0
 * Author: Orion The WP Agent
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// ORION_WPAGENT_VERSION est lu depuis version.json (voir orion_wpagent_read_version). Garder le header Version: aligné à chaque release.

define('ORION_WPAGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));

if (!function_exists('orion_wpagent_read_version')) {
    /**
     * @return string
     */
    function orion_wpagent_read_version()
    {
        $path = ORION_WPAGENT_PLUGIN_DIR . 'version.json';
        if (is_readable($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['version']) && is_string($data['version'])) {
                return $data['version'];
            }
        }
        return '1.1.0';
    }
}

if (!defined('ORION_WPAGENT_VERSION')) {
    define('ORION_WPAGENT_VERSION', orion_wpagent_read_version());
}

require_once ORION_WPAGENT_PLUGIN_DIR . 'includes/class-auth.php';
require_once ORION_WPAGENT_PLUGIN_DIR . 'includes/class-actions.php';
require_once ORION_WPAGENT_PLUGIN_DIR . 'includes/class-api.php';
require_once ORION_WPAGENT_PLUGIN_DIR . 'includes/class-updater.php';
require_once ORION_WPAGENT_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook(__FILE__, function () {
    if (!get_option('orion_wpagent_token')) {
        update_option('orion_wpagent_token', bin2hex(random_bytes(32)));
    }
});

add_action('rest_api_init', function () {
    (new OrionWPAgent_API())->register_routes();
});

add_action('admin_menu', array('OrionWPAgent_Admin', 'register_menu'));
add_action('admin_init', array('OrionWPAgent_Admin', 'handle_admin_post'));
