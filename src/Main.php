<?php
namespace WPPluginRegistry;

use WPPluginRegistry\Plugin\Registry;
use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\GitHub\GitHubClient;
use WPPluginRegistry\Admin\Admin;
use WPPluginRegistry\CLI\Commands;

/**
 * Main plugin class
 */
class Main {
    private static $instance = null;
    private $initialized = false;
    private $registry = null;
    private $manager = null;

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

        $this->registry = Registry::get_instance();
        $this->manager = new Manager(new GitHubClient());

        // Initialize admin if in admin area
        if (is_admin()) {
            Admin::get_instance();
        }

        // Initialize WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            Commands::register($this->manager, $this->registry);
        }
    }

    public function get_manager() {
        return isset($this->manager) ? $this->manager : null;
    }

    public function get_registry() {
        return isset($this->registry) ? $this->registry : Registry::get_instance();
    }

    public function get_github_client() {
        return new GitHubClient();
    }
}
