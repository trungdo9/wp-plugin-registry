<?php
/**
 * Plugin Name: WP Plugin Registry
 * Plugin URI: https://github.com/trungdo9/wp-plugin-registry
 * Description: Install and manage WordPress plugins from GitHub repositories.
 * Version: 1.0.0
 * Author: trungdo9
 * Author URI: https://github.com/trungdo9
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-plugin-registry
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('WPPR_VERSION', '1.0.0');
define('WPPR_FILE', __FILE__);
define('WPPR_PATH', plugin_dir_path(WPPR_FILE));
define('WPPR_URL', plugin_dir_url(WPPR_FILE));
define('WPPR_BASENAME', plugin_basename(WPPR_FILE));
define('WPPR_PLUGINS_DIR', defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/plugins' : ABSPATH . 'wp-content/plugins');

// Load classes on plugins_loaded
add_action('plugins_loaded', 'wppr_init', 5);

function wppr_init() {
    // Load all classes
    require_once WPPR_PATH . 'src/Traits/Singleton.php';
    require_once WPPR_PATH . 'src/GitHub/GitHubClient.php';
    require_once WPPR_PATH . 'src/GitHub/Downloader.php';
    require_once WPPR_PATH . 'src/GitHub/GitHubActions.php';
    require_once WPPR_PATH . 'src/Plugin/Registry.php';
    require_once WPPR_PATH . 'src/Plugin/ActivityLogger.php';
    require_once WPPR_PATH . 'src/Plugin/Manager.php';
    require_once WPPR_PATH . 'src/Admin/Admin.php';
    require_once WPPR_PATH . 'src/CLI/Commands.php';
    require_once WPPR_PATH . 'src/Main.php';

    // Initialize plugin
    \WPPluginRegistry\Main::get_instance();
}

// Activation hook - must be registered at top level
register_activation_hook(WPPR_FILE, 'wppr_activation_handler');

function wppr_activation_handler() {
    // Create tables
    require_once WPPR_PATH . 'src/Plugin/Registry.php';
    require_once WPPR_PATH . 'src/Plugin/ActivityLogger.php';
    require_once WPPR_PATH . 'src/Traits/Singleton.php';

    \WPPluginRegistry\Plugin\Registry::get_instance()->create_tables();
    \WPPluginRegistry\Plugin\ActivityLogger::get_instance()->create_table();

    update_option('wppr_version', WPPR_VERSION);

    // Schedule cleanup
    if (!wp_next_scheduled('wppr_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'wppr_daily_cleanup');
    }
}

// Deactivation hook
register_deactivation_hook(WPPR_FILE, 'wppr_deactivation_handler');

function wppr_deactivation_handler() {
    wp_clear_scheduled_hook('wppr_check_updates');
    wp_clear_scheduled_hook('wppr_daily_cleanup');
}

// Cleanup hook
add_action('wppr_daily_cleanup', 'wppr_daily_cleanup_handler');

function wppr_daily_cleanup_handler() {
    require_once WPPR_PATH . 'src/Plugin/ActivityLogger.php';
    require_once WPPR_PATH . 'src/Traits/Singleton.php';

    \WPPluginRegistry\Plugin\ActivityLogger::get_instance()->delete_old_logs(30);
}
