<?php
/**
 * Plugin uninstaller
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define constants
define('WPPR_PATH', plugin_dir_path(__FILE__));

// Load Registry directly (since autoloader may not be available during uninstall)
require_once WPPR_PATH . 'src/Plugin/Registry.php';
require_once WPPR_PATH . 'src/Traits/Singleton.php';
require_once WPPR_PATH . 'src/Plugin/ActivityLogger.php';

use WPPluginRegistry\Plugin\Registry;
use WPPluginRegistry\Plugin\ActivityLogger;

// Drop custom tables
Registry::get_instance()->drop_tables();
ActivityLogger::get_instance()->drop_table();

// Delete options
delete_option('wppr_settings');
delete_option('wppr_version');
delete_option('wppr_logs_settings');

// Delete transients
delete_transient('wppr_rate_limit');
