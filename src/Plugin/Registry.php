<?php
namespace WPPluginRegistry\Plugin;

use WPPluginRegistry\Traits\Singleton;

/**
 * Registry for tracking installed GitHub plugins
 */
class Registry {
    use Singleton;

    private const TABLE_NAME = 'wppr_plugin_registry';

    /**
     * Get all registered GitHub plugins
     */
    public function get_all(): array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $results = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        if (!$results) {
            return [];
        }

        return array_map(function($row) {
            return $this->hydrate_plugin($row);
        }, $results);
    }

    /**
     * Get single plugin by slug
     */
    public function get(string $slug): ?array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE plugin_slug = %s", $slug),
            ARRAY_A
        );

        return $row ? $this->hydrate_plugin($row) : null;
    }

    /**
     * Add new GitHub plugin to registry
     */
    public function add(array $plugin): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->replace($table, [
            'plugin_slug' => $plugin['slug'],
            'github_owner' => $plugin['owner'],
            'github_repo' => $plugin['repo'],
            'local_path' => $plugin['local_path'],
            'installed_version' => $plugin['version'] ?? '',
            'latest_version' => $plugin['latest_version'] ?? '',
            'source_url' => $plugin['source_url'] ?? '',
            'branch' => $plugin['branch'] ?? 'main',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return $result !== false;
    }

    /**
     * Update plugin info
     */
    public function update(string $slug, array $data): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update($table, $data, ['plugin_slug' => $slug]) !== false;
    }

    /**
     * Remove plugin from registry
     */
    public function remove(string $slug): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->delete($table, ['plugin_slug' => $slug]) !== false;
    }

    /**
     * Check if plugin is registered
     */
    public function exists(string $slug): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT 1 FROM {$table} WHERE plugin_slug = %s LIMIT 1", $slug)
        );
    }

    /**
     * Get plugin by local path
     */
    public function find_by_path(string $path): ?array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE local_path = %s", $path),
            ARRAY_A
        );

        return $row ? $this->hydrate_plugin($row) : null;
    }

    /**
     * Check for available updates
     */
    public function get_plugins_with_updates(): array {
        $plugins = $this->get_all();
        return array_filter($plugins, function($plugin) {
            return !empty($plugin['has_update']);
        });
    }

    /**
     * Create database table
     */
    public function create_tables(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            plugin_slug varchar(191) NOT NULL UNIQUE,
            github_owner varchar(100) NOT NULL,
            github_repo varchar(100) NOT NULL,
            local_path varchar(500) NOT NULL,
            installed_version varchar(50) DEFAULT '',
            latest_version varchar(50) DEFAULT '',
            source_url varchar(1000) DEFAULT '',
            branch varchar(100) DEFAULT 'main',
            has_update tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT '0000-00-00 00:00:00',
            updated_at datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY plugin_slug (plugin_slug),
            KEY local_path (local_path(255))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop tables on uninstall
     */
    public function drop_tables(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Hydrate plugin array from database row
     */
    private function hydrate_plugin(array $row): array {
        $plugin_dir = basename($row['local_path']);

        return [
            'id' => (int) $row['id'],
            'slug' => $row['plugin_slug'],
            'owner' => $row['github_owner'],
            'repo' => $row['github_repo'],
            'local_path' => $row['local_path'],
            'local_dir' => $plugin_dir,
            'installed_version' => $row['installed_version'],
            'latest_version' => $row['latest_version'],
            'source_url' => $row['source_url'],
            'branch' => $row['branch'],
            'has_update' => (bool) $row['has_update'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'wp_plugin_data' => $this->get_wp_plugin_data($row['local_path']),
        ];
    }

    /**
     * Get native WordPress plugin data
     */
    private function get_wp_plugin_data(string $local_path): array {
        $plugin_file = $local_path . '/' . basename($local_path) . '.php';

        if (!file_exists($plugin_file)) {
            // Try to find main plugin file
            $files = glob($local_path . '/*.php');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/Plugin Name:/i', $content)) {
                    return get_plugin_data($file, false, false);
                }
            }
            return [];
        }

        return get_plugin_data($plugin_file, false, false);
    }
}
