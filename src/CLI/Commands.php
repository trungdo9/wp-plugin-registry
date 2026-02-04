<?php
namespace WPPluginRegistry\CLI;

use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\Plugin\Registry;

/**
 * WP-CLI Commands for wp-plugin-registry
 */
class Commands {
    private static Manager $manager;
    private static Registry $registry;

    public static function register(Manager $manager, Registry $registry): void {
        self::$manager = $manager;
        self::$registry = $registry;

        \WP_CLI::add_command('wppr', __CLASS__);
    }

    /**
     * Install a plugin from GitHub.
     *
     * ## OPTIONS
     *
     * <url>
     * : GitHub repository URL
     * ---
     * example: https://github.com/trungdo9/wp-nexus.git
     * ---
     *
     * [--version=<version>]
     * : Git branch or tag to download
     * ---
     * default: main
     * ---
     *
     * [--activate]
     * : Activate the plugin after installation
     *
     * ## EXAMPLES
     *
     *     wp wppr install https://github.com/trungdo9/wp-nexus.git
     *     wp wppr install https://github.com/trungdo9/wp-nexus.git --version=main --activate
     *     wp wppr install https://github.com/trungdo9/wp-nexus.git --version=v1.2.3
     *
     */
    public function install($args, $assoc_args): void {
        $url = $args[0] ?? '';

        if (empty($url)) {
            \WP_CLI::error(__('GitHub URL is required', 'wp-plugin-registry'));
        }

        $version = $assoc_args['version'] ?? 'main';
        $activate = isset($assoc_args['activate']);

        \WP_CLI::line(__('Installing plugin from GitHub...', 'wp-plugin-registry'));
        \WP_CLI::line(__('URL:', 'wp-plugin-registry') . ' ' . $url);
        \WP_CLI::line(__('Version:', 'wp-plugin-registry') . ' ' . $version);

        $result = self::$manager->install($url, $version);

        if ($result['success']) {
            \WP_CLI::success(
                sprintf(
                    __('Plugin installed: %s (v%s)', 'wp-plugin-registry'),
                    $result['slug'],
                    $result['version']
                )
            );

            if ($activate) {
                $activate_result = self::$manager->activate($result['slug']);
                if ($activate_result['success']) {
                    \WP_CLI::success(__('Plugin activated', 'wp-plugin-registry'));
                }
            }
        } else {
            \WP_CLI::error($result['error']);
        }
    }

    /**
     * Update an installed GitHub plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug (owner-repo)
     * ---
     * example: trungdo9-wp-nexus
     * ---
     *
     * ## EXAMPLES
     *
     *     wp wppr update trungdo9-wp-nexus
     *     wp wppr update trungdo9-wp-nexus --dry-run
     *
     */
    public function update($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        \WP_CLI::line(__('Checking for updates...', 'wp-plugin-registry'));

        $result = self::$manager->update($slug);

        if ($result['success']) {
            if (isset($result['message']) && strpos($result['message'], 'up to date') !== false) {
                \WP_CLI::success($result['message']);
            } else {
                \WP_CLI::success(
                    sprintf(
                        __('Plugin updated: %s (%s → %s)', 'wp-plugin-registry'),
                        $slug,
                        $result['old_version'],
                        $result['new_version']
                    )
                );
            }
        } else {
            \WP_CLI::error($result['error']);
        }
    }

    /**
     * Activate a registered GitHub plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug (owner-repo)
     *
     * ## EXAMPLES
     *
     *     wp wppr activate trungdo9-wp-nexus
     *
     */
    public function activate($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        $result = self::$manager->activate($slug);

        if ($result['success']) {
            \WP_CLI::success(__('Plugin activated', 'wp-plugin-registry'));
        } else {
            \WP_CLI::error($result['error']);
        }
    }

    /**
     * Deactivate a registered GitHub plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug (owner-repo)
     *
     * ## EXAMPLES
     *
     *     wp wppr deactivate trungdo9-wp-nexus
     *
     */
    public function deactivate($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        $result = self::$manager->deactivate($slug);

        if ($result['success']) {
            \WP_CLI::success(__('Plugin deactivated', 'wp-plugin-registry'));
        } else {
            \WP_CLI::error($result['error']);
        }
    }

    /**
     * Uninstall and delete a GitHub plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug (owner-repo)
     *
     * [--yes]
     * : Skip confirmation
     *
     * ## EXAMPLES
     *
     *     wp wppr uninstall trungdo9-wp-nexus
     *     wp wppr uninstall trungdo9-wp-nexus --yes
     *
     */
    public function uninstall($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        // Check if plugin exists
        $plugin = self::$registry->get($slug);

        if (!$plugin) {
            \WP_CLI::error(__('Plugin not found in registry', 'wp-plugin-registry'));
        }

        // Confirmation
        if (!isset($assoc_args['yes'])) {
            \WP_CLI::confirm(
                sprintf(
                    __('Are you sure you want to uninstall and delete "%s"? This cannot be undone.', 'wp-plugin-registry'),
                    $slug
                )
            );
        }

        $result = self::$manager->uninstall($slug);

        if ($result['success']) {
            \WP_CLI::success(__('Plugin uninstalled and deleted', 'wp-plugin-registry'));
        } else {
            \WP_CLI::error($result['error']);
        }
    }

    /**
     * Check installed GitHub plugins for updates.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     * ---
     * default: table
     *
     * ## EXAMPLES
     *
     *     wp wppr check-updates
     *     wp wppr check-updates --format=json
     *
     */
    public function check_updates($args, $assoc_args): void {
        $format = $assoc_args['format'] ?? 'table';

        \WP_CLI::line(__('Checking for updates...', 'wp-plugin-registry'));

        $updates = self::$manager->check_for_updates();

        if (empty($updates)) {
            \WP_CLI::success(__('All plugins are up to date', 'wp-plugin-registry'));
            return;
        }

        $data = [];
        foreach ($updates as $update) {
            $data[] = [
                'slug' => $update['slug'],
                'current_version' => $update['current_version'],
                'latest_version' => $update['latest_version'],
                'download_url' => $update['download_url'],
            ];
        }

        \WP_CLI::success(
            sprintf(
                __('%d plugin(s) have updates available', 'wp-plugin-registry'),
                count($updates)
            )
        );

        $headers = ['slug', 'current_version', 'latest_version', 'download_url'];
        \WP_CLI::format_items($format, $data, $headers);
    }

    /**
     * List all registered GitHub plugins.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status (active, inactive, update-available)
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     * ---
     * default: table
     *
     * ## EXAMPLES
     *
     *     wp wppr list
     *     wp wppr list --status=active
     *     wp wppr list --format=json
     *
     */
    public function list($args, $assoc_args): void {
        $format = $assoc_args['format'] ?? 'table';
        $status_filter = $assoc_args['status'] ?? '';

        $plugins = self::$registry->get_all();

        if (empty($plugins)) {
            \WP_CLI::line(__('No GitHub plugins installed', 'wp-plugin-registry'));
            return;
        }

        $data = [];
        foreach ($plugins as $plugin) {
            $plugin_file = $plugin['local_path'] . '/' . $plugin['local_dir'] . '.php';
            $is_active = file_exists($plugin_file) && is_plugin_active($plugin_file);
            $has_update = $plugin['has_update'];

            if ($status_filter) {
                if ($status_filter === 'active' && !$is_active) {
                    continue;
                }
                if ($status_filter === 'inactive' && $is_active) {
                    continue;
                }
                if ($status_filter === 'update-available' && !$has_update) {
                    continue;
                }
            }

            $data[] = [
                'slug' => $plugin['slug'],
                'name' => $plugin['wp_plugin_data']['Name'] ?? $plugin['repo'],
                'version' => $plugin['installed_version'],
                'latest' => $plugin['latest_version'],
                'status' => $is_active ? 'active' : 'inactive',
                'update' => $has_update ? 'yes' : 'no',
            ];
        }

        if (empty($data)) {
            \WP_CLI::line(__('No plugins match the criteria', 'wp-plugin-registry'));
            return;
        }

        $headers = ['slug', 'name', 'version', 'latest', 'status', 'update'];
        \WP_CLI::format_items($format, $data, $headers);
    }

    /**
     * Get detailed information about a registered plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug
     *
     * ## EXAMPLES
     *
     *     wp wppr info trungdo9-wp-nexus
     *
     */
    public function info($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        $plugin = self::$registry->get($slug);

        if (!$plugin) {
            \WP_CLI::error(__('Plugin not found', 'wp-plugin-registry'));
        }

        $plugin_file = $plugin['local_path'] . '/' . $plugin['local_dir'] . '.php';
        $wp_data = $plugin['wp_plugin_data'];
        $is_active = file_exists($plugin_file) && is_plugin_active($plugin_file);

        $info = [
            __('Slug', 'wp-plugin-registry') => $plugin['slug'],
            __('Name', 'wp-plugin-registry') => $wp_data['Name'] ?? $plugin['repo'],
            __('Description', 'wp-plugin-registry') => $wp_data['Description'] ?? '-',
            __('Author', 'wp-plugin-registry') => $wp_data['Author'] ?? '-',
            __('Version', 'wp-plugin-registry') => $plugin['installed_version'],
            __('Latest Version', 'wp-plugin-registry') => $plugin['latest_version'],
            __('GitHub Owner', 'wp-plugin-registry') => $plugin['owner'],
            __('GitHub Repo', 'wp-plugin-registry') => $plugin['repo'],
            __('Branch', 'wp-plugin-registry') => $plugin['branch'],
            __('Status', 'wp-plugin-registry') => $is_active ? 'Active' : 'Inactive',
            __('Has Update', 'wp-plugin-registry') => $plugin['has_update'] ? 'Yes' : 'No',
            __('Local Path', 'wp-plugin-registry') => $plugin['local_path'],
            __('Source URL', 'wp-plugin-registry') => $plugin['source_url'],
        ];

        foreach ($info as $key => $value) {
            \WP_CLI::line(sprintf('%s: %s', $key, $value));
        }
    }

    /**
     * Get plugin version.
     *
     * ## OPTIONS
     *
     * <slug>
     * : Plugin slug
     *
     * ## EXAMPLES
     *
     *     wp wppr version trungdo9-wp-nexus
     *
     */
    public function version($args, $assoc_args): void {
        $slug = $args[0] ?? '';

        if (empty($slug)) {
            \WP_CLI::error(__('Plugin slug is required', 'wp-plugin-registry'));
        }

        $plugin = self::$registry->get($slug);

        if (!$plugin) {
            \WP_CLI::error(__('Plugin not found', 'wp-plugin-registry'));
        }

        $version = self::$manager->get_plugin_version($plugin['local_path']);

        \WP_CLI::line($version);

        // Also show latest version
        \WP_CLI::line(sprintf(
            __('Latest on GitHub: %s', 'wp-plugin-registry'),
            $plugin['latest_version']
        ));
    }
}
