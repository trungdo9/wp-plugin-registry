<?php
namespace WPPluginRegistry;

/**
 * Main plugin class
 */
class Main {
    private static $instance = null;
    private $initialized = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Prevent direct instantiation
    }

    public function init() {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $this->registry = Plugin\Registry::get_instance();
        $this->manager = new Plugin\Manager(new GitHub\GitHubClient());

        // Initialize admin if in admin area
        if (is_admin()) {
            Admin\Admin::get_instance();
        }

        // Initialize WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            CLI\Commands::register($this->manager, $this->registry);
        }
    }

    public function get_manager() {
        return isset($this->manager) ? $this->manager : null;
    }

    public function get_registry() {
        return isset($this->registry) ? $this->registry : Plugin\Registry::get_instance();
    }

    public function get_github_client() {
        return new GitHub\GitHubClient();
    }
}
