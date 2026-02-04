<?php
namespace WPPluginRegistry;

/**
 * Main plugin class
 */
class Main {
    private static $instance = null;
    private $github_client;
    private $plugin_manager;
    private $registry;
    private $initialized = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register for initialization after plugins_loaded
        if (did_action('plugins_loaded')) {
            $this->init();
        } else {
            add_action('plugins_loaded', [$this, 'init'], 5);
        }
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    public function init() {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $this->init_components();
        $this->init_hooks();
    }

    private function init_components() {
        $this->github_client = new GitHubClient();
        $this->registry = Registry::get_instance();
        $this->plugin_manager = new Plugin\Manager($this->github_client);

        // Initialize admin if in admin area
        if (is_admin()) {
            Admin::get_instance();
        }

        // Initialize WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            CLI\Commands::register($this->plugin_manager, $this->registry);
        }
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_textdomain'], 10);
        register_activation_hook(WPPR_FILE, [$this, 'activate']);
        register_deactivation_hook(WPPR_FILE, [$this, 'deactivate']);
        add_action('wppr_daily_cleanup', [$this, 'daily_cleanup']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('wp-plugin-registry', false, dirname(WPPR_BASENAME) . '/languages');
    }

    public function activate() {
        // Create tables
        Registry::get_instance()->create_tables();
        Plugin\ActivityLogger::get_instance()->create_table();

        // Set version
        update_option('wppr_version', WPPR_VERSION);

        // Schedule daily cleanup for old logs
        if (!wp_next_scheduled('wppr_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wppr_daily_cleanup');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('wppr_check_updates');
        wp_clear_scheduled_hook('wppr_daily_cleanup');
    }

    public function daily_cleanup() {
        // Delete logs older than 30 days
        Plugin\ActivityLogger::get_instance()->delete_old_logs(30);
    }

    public function get_manager() {
        return $this->plugin_manager;
    }

    public function get_registry() {
        return $this->registry;
    }

    public function get_github_client() {
        return $this->github_client;
    }
}
