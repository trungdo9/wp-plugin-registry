<?php
namespace WPPluginRegistry\Admin;

/**
 * Admin interface for plugin management
 */
class Admin {
    private $manager;
    private $registry;

    private function __construct() {
        // Initialize Main first
        $main = \WPPluginRegistry\Main::get_instance();
        $main->init();

        $this->manager = $main->get_manager();
        $this->registry = $main->get_registry();

        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_wppr_install', [$this, 'handle_install']);
        add_action('admin_post_wppr_update', [$this, 'handle_update']);
        add_action('admin_post_wppr_activate', [$this, 'handle_activate']);
        add_action('admin_post_wppr_deactivate', [$this, 'handle_deactivate']);
        add_action('admin_post_wppr_uninstall', [$this, 'handle_uninstall']);
        add_action('admin_post_wppr_clear_logs', [$this, 'handle_clear_logs']);
        add_action('admin_post_wppr_test_github', [$this, 'handle_test_github']);
        add_action('admin_post_wppr_save_github_actions', [$this, 'handle_save_github_actions']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_pages() {
        add_menu_page(
            __('WP Plugin Registry', 'wp-plugin-registry'),
            __('Plugin Registry', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry',
            [$this, 'render_main_page'],
            'dashicons-plugins-checked',
            30
        );

        add_submenu_page(
            'wp-plugin-registry',
            __('Installed Plugins', 'wp-plugin-registry'),
            __('Plugins', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'wp-plugin-registry',
            __('Activity Logs', 'wp-plugin-registry'),
            __('Activity Logs', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'wp-plugin-registry',
            __('GitHub Actions Settings', 'wp-plugin-registry'),
            __('GitHub Actions', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry-github',
            [$this, 'render_github_actions_page']
        );

        add_submenu_page(
            'wp-plugin-registry',
            __('Settings', 'wp-plugin-registry'),
            __('Settings', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('wppr_settings', 'wppr_settings');
        register_setting('wppr_logs_settings', 'wppr_logs_settings');
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'wppr-admin',
            WPPR_URL . 'assets/css/admin.css',
            [],
            WPPR_VERSION
        );

        wp_enqueue_script(
            'wppr-admin',
            WPPR_URL . 'assets/js/admin.js',
            ['jquery'],
            WPPR_VERSION,
            true
        );

        wp_localize_script('wppr-admin', 'wppr', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wppr_admin'),
        ]);
    }

    public function render_main_page() {
        // Handle actions
        $message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
        $error = isset($_GET['error']) ? urldecode($_GET['error']) : '';

        // Check for updates
        $this->manager->check_for_updates();

        // Get all registered plugins
        $plugins = $this->registry->get_all();

        // Get settings
        $options = get_option('wppr_settings', []);

        include WPPR_PATH . 'templates/admin-page.php';
    }

    public function render_logs_page() {
        // Handle clear logs action
        if (isset($_GET['action']) && $_GET['action'] === 'clear') {
            check_admin_referer('wppr_clear_logs');
            $this->manager->clear_activity_logs();
            wp_redirect(add_query_arg('message', urlencode(__('Logs cleared', 'wp-plugin-registry')), admin_url('admin.php?page=wp-plugin-registry-logs')));
            exit;
        }

        // Get logs
        $logs = $this->manager->get_activity_logs(50, 0);

        // Get action types for filter
        $action_types = \WPPluginRegistry\Plugin\ActivityLogger::get_instance()->get_action_types();

        include WPPR_PATH . 'templates/logs-page.php';
    }

    public function render_github_actions_page() {
        $github_actions = new \WPPluginRegistry\GitHub\GitHubActions();
        $triggers = $github_actions->get_available_triggers();
        $options = get_option('wppr_settings', []);

        // Handle test connection
        $test_result = [];
        if (isset($_GET['tested']) && $_GET['tested'] === 'true') {
            $test_result = $github_actions->test_connection();
        }

        include WPPR_PATH . 'templates/github-actions-page.php';
    }

    public function render_settings_page() {
        $options = get_option('wppr_settings', []);
        $logs_options = get_option('wppr_logs_settings', []);

        include WPPR_PATH . 'templates/settings-page.php';
    }

    public function handle_install() {
        check_admin_referer('wppr_install', 'wppr_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $url = sanitize_text_field($_POST['github_url'] ?? '');
        $version = sanitize_text_field($_POST['version'] ?? 'main');
        $activate = isset($_POST['activate']);

        if (empty($url)) {
            wp_redirect(add_query_arg('error', urlencode('URL is required'), admin_url('admin.php?page=wp-plugin-registry')));
            exit;
        }

        $result = $this->manager->install($url, $version);

        if ($result['success']) {
            if ($activate) {
                $this->manager->activate($result['slug']);
            }
            wp_redirect(add_query_arg('message', urlencode('Plugin installed successfully'), admin_url('admin.php?page=wp-plugin-registry')));
        } else {
            wp_redirect(add_query_arg('error', urlencode($result['error']), admin_url('admin.php?page=wp-plugin-registry')));
        }
        exit;
    }

    public function handle_update() {
        $slug = sanitize_text_field($_GET['slug'] ?? '');
        check_admin_referer('wppr_update_' . $slug);

        if (!current_user_can('manage_options') || empty($slug)) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $result = $this->manager->update($slug);

        $redirect_url = admin_url('admin.php?page=wp-plugin-registry');

        if ($result['success']) {
            $redirect_url = add_query_arg('message', urlencode($result['message'] ?? 'Plugin updated'), $redirect_url);
        } else {
            $redirect_url = add_query_arg('error', urlencode($result['error']), $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_activate() {
        $slug = sanitize_text_field($_GET['slug'] ?? '');
        check_admin_referer('wppr_activate_' . $slug);

        if (!current_user_can('manage_options') || empty($slug)) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $this->manager->activate($slug);

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry'));
        exit;
    }

    public function handle_deactivate() {
        $slug = sanitize_text_field($_GET['slug'] ?? '');
        check_admin_referer('wppr_deactivate_' . $slug);

        if (!current_user_can('manage_options') || empty($slug)) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $this->manager->deactivate($slug);

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry'));
        exit;
    }

    public function handle_uninstall() {
        $slug = sanitize_text_field($_GET['slug'] ?? '');
        check_admin_referer('wppr_uninstall_' . $slug);

        if (!current_user_can('manage_options') || empty($slug)) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $result = $this->manager->uninstall($slug);

        $redirect_url = admin_url('admin.php?page=wp-plugin-registry');

        if ($result['success']) {
            $redirect_url = add_query_arg('message', urlencode('Plugin uninstalled'), $redirect_url);
        } else {
            $redirect_url = add_query_arg('error', urlencode($result['error']), $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_clear_logs() {
        check_admin_referer('wppr_clear_logs', 'wppr_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $this->manager->clear_activity_logs();

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry-logs&message=' . urlencode(__('Logs cleared', 'wp-plugin-registry'))));
        exit;
    }

    public function handle_test_github() {
        check_admin_referer('wppr_test_github', 'wppr_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $github_actions = new \WPPluginRegistry\GitHub\GitHubActions();
        $result = $github_actions->test_connection();

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=wp-plugin-registry-github&tested=true&message=' . urlencode($result['message'])));
        } else {
            wp_redirect(admin_url('admin.php?page=wp-plugin-registry-github&tested=true&error=' . urlencode($result['error'])));
        }
        exit;
    }

    public function handle_save_github_actions() {
        check_admin_referer('wppr_save_github_actions', 'wppr_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $enabled = isset($_POST['github_actions_enabled']);
        $token = sanitize_text_field($_POST['github_token'] ?? '');
        $triggers = [];

        foreach (['on_release', 'on_update_available', 'on_install', 'on_update', 'on_uninstall'] as $trigger) {
            $triggers[$trigger] = isset($_POST['trigger_' . $trigger]);
        }

        $github_actions = new \WPPluginRegistry\GitHub\GitHubActions();
        $github_actions->save_settings($enabled, $token, $triggers);

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry-github&message=' . urlencode(__('Settings saved', 'wp-plugin-registry'))));
        exit;
    }
}
