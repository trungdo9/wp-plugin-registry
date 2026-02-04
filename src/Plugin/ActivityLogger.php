<?php
namespace WPPluginRegistry\Plugin;

use WPPluginRegistry\Traits\Singleton;

/**
 * Activity Logger for tracking plugin operations
 */
class ActivityLogger {
    use Singleton;

    private const TABLE_NAME = 'wppr_activity_logs';

    /**
     * Log an activity
     *
     * @param string $action Action type (install, update, activate, deactivate, uninstall)
     * @param string $plugin_slug Plugin slug
     * @param string $message Log message
     * @param array $extra Additional data
     * @return int|false Log ID or false on failure
     */
    public function log($action, $plugin_slug, $message = '', $extra = []) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->insert($table, [
            'action' => $action,
            'plugin_slug' => $plugin_slug,
            'message' => $message,
            'extra_data' => !empty($extra) ? json_encode($extra) : '',
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => current_time('mysql'),
        ]);

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Log plugin installation
     */
    public function log_install($plugin_slug, $version, $source_url, $success = true, $error = '') {
        $message = sprintf(
            __('Plugin installed from GitHub: %s v%s', 'wp-plugin-registry'),
            $plugin_slug,
            $version
        );

        return $this->log('install', $plugin_slug, $message, [
            'version' => $version,
            'source_url' => $source_url,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Log plugin update
     */
    public function log_update($plugin_slug, $old_version, $new_version, $success = true, $error = '') {
        $message = sprintf(
            __('Plugin updated: %s from v%s to v%s', 'wp-plugin-registry'),
            $plugin_slug,
            $old_version,
            $new_version
        );

        return $this->log('update', $plugin_slug, $message, [
            'old_version' => $old_version,
            'new_version' => $new_version,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Log plugin activation
     */
    public function log_activate($plugin_slug, $success = true, $error = '') {
        $message = sprintf(
            __('Plugin activated: %s', 'wp-plugin-registry'),
            $plugin_slug
        );

        return $this->log('activate', $plugin_slug, $message, [
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Log plugin deactivation
     */
    public function log_deactivate($plugin_slug, $success = true, $error = '') {
        $message = sprintf(
            __('Plugin deactivated: %s', 'wp-plugin-registry'),
            $plugin_slug
        );

        return $this->log('deactivate', $plugin_slug, $message, [
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Log plugin uninstall
     */
    public function log_uninstall($plugin_slug, $version, $success = true, $error = '') {
        $message = sprintf(
            __('Plugin uninstalled: %s (v%s)', 'wp-plugin-registry'),
            $plugin_slug,
            $version
        );

        return $this->log('uninstall', $plugin_slug, $message, [
            'version' => $version,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Log update check
     */
    public function log_update_check($plugin_slug, $current_version, $latest_version, $has_update) {
        return $this->log('update_check', $plugin_slug, __('Update check performed', 'wp-plugin-registry'), [
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'has_update' => $has_update,
        ]);
    }

    /**
     * Log GitHub Actions trigger
     */
    public function log_github_action($plugin_slug, $action_type, $success = true, $error = '') {
        $message = sprintf(
            __('GitHub Action triggered: %s for %s', 'wp-plugin-registry'),
            $action_type,
            $plugin_slug
        );

        return $this->log('github_action', $plugin_slug, $message, [
            'action_type' => $action_type,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Get all logs
     */
    public function get_all($limit = 50, $offset = 0, $action = '', $plugin_slug = '') {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $sql = "SELECT * FROM {$table}";

        $conditions = [];
        $values = [];

        if (!empty($action)) {
            $conditions[] = 'action = %s';
            $values[] = $action;
        }

        if (!empty($plugin_slug)) {
            $conditions[] = 'plugin_slug = %s';
            $values[] = $plugin_slug;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';

        if (!empty($values)) {
            $values[] = $limit;
            $values[] = $offset;
            $sql = $wpdb->prepare($sql, $values);
        } else {
            $sql = $wpdb->prepare($sql, [$limit, $offset]);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get logs for a specific plugin
     */
    public function get_by_plugin($plugin_slug, $limit = 20) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE plugin_slug = %s ORDER BY created_at DESC LIMIT %d",
                $plugin_slug,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get recent logs
     */
    public function get_recent($limit = 10) {
        return $this->get_all($limit, 0);
    }

    /**
     * Clear logs for a specific plugin
     */
    public function clear_plugin_logs($plugin_slug) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->delete($table, ['plugin_slug' => $plugin_slug]);
    }

    /**
     * Clear all logs
     */
    public function clear_all() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->query("TRUNCATE TABLE {$table}");
    }

    /**
     * Delete old logs (retention)
     */
    public function delete_old_logs($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get log count
     */
    public function get_count($action = '') {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $sql = "SELECT COUNT(*) FROM {$table}";

        if (!empty($action)) {
            $sql = $wpdb->prepare($sql . ' WHERE action = %s', $action);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get unique action types
     */
    public function get_action_types() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_col("SELECT DISTINCT action FROM {$table} ORDER BY action");
    }

    /**
     * Create database table
     */
    public function create_table() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            plugin_slug varchar(191) NOT NULL,
            message text NOT NULL,
            extra_data text,
            user_id mediumint(9) DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY plugin_slug (plugin_slug),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop table
     */
    public function drop_table() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
