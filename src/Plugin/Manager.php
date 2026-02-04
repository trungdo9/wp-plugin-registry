<?php
namespace WPPluginRegistry\Plugin;

/**
 * Manage plugin lifecycle (install, update, activate, deactivate, uninstall)
 */
class Manager {
    private $github;
    private $downloader;
    private $activity_logger;
    private $github_actions;

    public function __construct($github) {
        $this->github = $github;
        $this->downloader = new Downloader($this->github);
        $this->activity_logger = new ActivityLogger();
        $this->github_actions = new GitHubActions();
    }

    /**
     * Install plugin from GitHub URL
     */
    public function install($url, $version = 'main') {
        // Parse URL
        $parsed = $this->github->parse_url($url);

        if (!$parsed) {
            return [
                'success' => false,
                'error' => __('Invalid GitHub URL', 'wp-plugin-registry'),
            ];
        }

        $owner = $parsed['owner'];
        $repo = $parsed['repo'];
        $slug = $this->generate_slug($owner, $repo);

        // Check if already installed
        if (Registry::get_instance()->exists($slug)) {
            return [
                'success' => false,
                'error' => __('Plugin is already installed', 'wp-plugin-registry'),
            ];
        }

        // Download and extract
        $result = $this->downloader->download_and_extract($owner, $repo, $version, $slug);

        if (!$result['success']) {
            $this->activity_logger->log_install($slug, $version, $url, false, $result['error']);
            return $result;
        }

        // Get installed version
        $installed_version = $this->get_plugin_version($result['path']);

        // Get latest version from GitHub
        $latest_release = $this->github->get_latest_release($owner, $repo);
        $latest_version = $latest_release['success']
            ? $latest_release['data']['tag_name']
            : $version;

        // Register plugin
        Registry::get_instance()->add([
            'slug' => $slug,
            'owner' => $owner,
            'repo' => $repo,
            'local_path' => $result['path'],
            'version' => $installed_version,
            'latest_version' => $latest_version,
            'source_url' => $url,
            'branch' => $version,
        ]);

        // Log activity
        $this->activity_logger->log_install($slug, $installed_version, $url, true);

        // Trigger GitHub Actions
        $this->github_actions->on_install($owner, $repo, $installed_version);

        return [
            'success' => true,
            'slug' => $slug,
            'path' => $result['path'],
            'version' => $installed_version,
        ];
    }

    /**
     * Update installed plugin
     */
    public function update($slug) {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found in registry', 'wp-plugin-registry'),
            ];
        }

        // Get latest release
        $release = $this->github->get_latest_release($plugin['owner'], $plugin['repo']);

        if (!$release['success']) {
            $this->activity_logger->log_update_check(
                $slug,
                $plugin['installed_version'],
                '',
                false
            );
            return [
                'success' => false,
                'error' => $release['error'],
            ];
        }

        $latest_version = $release['data']['tag_name'];

        // Log update check
        $has_update = version_compare($latest_version, $plugin['installed_version'], '>');
        $this->activity_logger->log_update_check(
            $slug,
            $plugin['installed_version'],
            $latest_version,
            $has_update
        );

        // Check if update is needed
        if (!$has_update) {
            return [
                'success' => true,
                'message' => __('Plugin is already up to date', 'wp-plugin-registry'),
                'current_version' => $plugin['installed_version'],
                'latest_version' => $latest_version,
            ];
        }

        // Deactivate before update
        $this->deactivate($slug);

        // Download new version
        $result = $this->downloader->download_and_extract(
            $plugin['owner'],
            $plugin['repo'],
            $latest_version,
            $slug
        );

        if (!$result['success']) {
            $this->activity_logger->log_update(
                $slug,
                $plugin['installed_version'],
                $latest_version,
                false,
                $result['error']
            );
            return $result;
        }

        // Get installed version
        $installed_version = $this->get_plugin_version($result['path']);

        // Update registry
        Registry::get_instance()->update($slug, [
            'local_path' => $result['path'],
            'installed_version' => $installed_version,
            'latest_version' => $latest_version,
            'has_update' => false,
        ]);

        // Log activity
        $this->activity_logger->log_update(
            $slug,
            $plugin['installed_version'],
            $installed_version,
            true
        );

        // Trigger GitHub Actions
        $this->github_actions->on_update(
            $plugin['owner'],
            $plugin['repo'],
            $plugin['installed_version'],
            $installed_version
        );

        return [
            'success' => true,
            'slug' => $slug,
            'old_version' => $plugin['installed_version'],
            'new_version' => $installed_version,
        ];
    }

    /**
     * Activate plugin
     */
    public function activate($slug) {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        $plugin_file = $this->find_main_plugin_file($plugin['local_path']);

        if (!$plugin_file) {
            $this->activity_logger->log_activate($slug, false, __('Main plugin file not found', 'wp-plugin-registry'));
            return [
                'success' => false,
                'error' => __('Main plugin file not found', 'wp-plugin-registry'),
            ];
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            $this->activity_logger->log_activate($slug, false, $result->get_error_message());
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        $this->activity_logger->log_activate($slug, true);

        return [
            'success' => true,
            'message' => __('Plugin activated', 'wp-plugin-registry'),
        ];
    }

    /**
     * Deactivate plugin
     */
    public function deactivate($slug) {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        $plugin_file = $this->find_main_plugin_file($plugin['local_path']);

        if (!$plugin_file) {
            $this->activity_logger->log_deactivate($slug, false, __('Main plugin file not found', 'wp-plugin-registry'));
            return [
                'success' => false,
                'error' => __('Main plugin file not found', 'wp-plugin-registry'),
            ];
        }

        deactivate_plugins($plugin_file);
        $this->activity_logger->log_deactivate($slug, true);

        return [
            'success' => true,
            'message' => __('Plugin deactivated', 'wp-plugin-registry'),
        ];
    }

    /**
     * Uninstall and delete plugin
     */
    public function uninstall($slug) {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        // Deactivate first
        $plugin_file = $this->find_main_plugin_file($plugin['local_path']);
        if ($plugin_file) {
            deactivate_plugins($plugin_file, true);
        }

        // Run uninstall.php if exists
        $uninstall_file = $plugin['local_path'] . '/uninstall.php';
        if (file_exists($uninstall_file)) {
            define('WP_UNINSTALL_PLUGIN', $plugin_file);
            include $uninstall_file;
        }

        // Delete plugin files
        $this->delete_directory($plugin['local_path']);

        // Remove from registry
        Registry::get_instance()->remove($slug);

        // Log activity
        $this->activity_logger->log_uninstall($slug, $plugin['installed_version'], true);

        // Trigger GitHub Actions
        $this->github_actions->on_uninstall(
            $plugin['owner'],
            $plugin['repo'],
            $plugin['installed_version']
        );

        return [
            'success' => true,
            'message' => __('Plugin uninstalled', 'wp-plugin-registry'),
        ];
    }

    /**
     * Get plugin version from main file
     */
    public function get_plugin_version($plugin_path) {
        $main_file = $this->find_main_plugin_file($plugin_path);

        if (!$main_file || !file_exists($main_file)) {
            return '';
        }

        $data = get_plugin_data($main_file, false, false);
        return isset($data['Version']) ? $data['Version'] : '';
    }

    /**
     * Check for updates for all registered plugins
     */
    public function check_for_updates() {
        $plugins = Registry::get_instance()->get_all();
        $updates = [];

        foreach ($plugins as $plugin) {
            $release = $this->github->get_latest_release($plugin['owner'], $plugin['repo']);

            if ($release['success']) {
                $latest_version = $release['data']['tag_name'];
                $has_update = version_compare($latest_version, $plugin['installed_version'], '>');

                Registry::get_instance()->update($plugin['slug'], [
                    'latest_version' => $latest_version,
                    'has_update' => $has_update,
                ]);

                if ($has_update) {
                    $updates[] = [
                        'slug' => $plugin['slug'],
                        'current_version' => $plugin['installed_version'],
                        'latest_version' => $latest_version,
                        'download_url' => $release['data']['html_url'],
                    ];

                    // Trigger GitHub Actions for update available
                    $this->github_actions->on_update_available(
                        $plugin['owner'],
                        $plugin['repo'],
                        $plugin['installed_version'],
                        $latest_version
                    );
                }
            }
        }

        return $updates;
    }

    /**
     * Get activity logs
     */
    public function get_activity_logs($limit = 50, $offset = 0, $action = '', $plugin_slug = '') {
        return $this->activity_logger->get_all($limit, $offset, $action, $plugin_slug);
    }

    /**
     * Get recent activity logs
     */
    public function get_recent_activity($limit = 10) {
        return $this->activity_logger->get_recent($limit);
    }

    /**
     * Clear activity logs
     */
    public function clear_activity_logs($plugin_slug = '') {
        if (!empty($plugin_slug)) {
            return $this->activity_logger->clear_plugin_logs($plugin_slug);
        }
        return $this->activity_logger->clear_all();
    }

    /**
     * Generate plugin slug from owner/repo
     */
    private function generate_slug($owner, $repo) {
        return sanitize_title($owner . '-' . $repo);
    }

    /**
     * Find main plugin file in directory
     */
    private function find_main_plugin_file($path) {
        if (!is_dir($path)) {
            return '';
        }

        $files = glob($path . '/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/Plugin Name:/i', $content)) {
                return $file;
            }
        }

        // Fallback to directory name + .php
        $dir_name = basename($path);
        $fallback = $path . '/' . $dir_name . '.php';

        return file_exists($fallback) ? $fallback : '';
    }

    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }

        return @rmdir($dir);
    }
}
