# Kế hoạch triển khai: wp-plugin-registry

## Tổng quan

Plugin WordPress cho phép người dùng cài đặt và quản lý plugins từ GitHub repository trực tiếp thông qua giao diện admin và WP-CLI commands.

## 1. Cấu trúc thư mục plugin

```
wp-plugin-registry/
├── wp-plugin-registry.php           # Main plugin file
├── uninstall.php                     # Cleanup script
├── bin/
│   └── cli-commands.php              # WP-CLI commands registration
├── src/
│   ├── Main.php                      # Plugin entry point
│   ├── Admin/
│   │   └── Admin.php                 # Admin menu & settings page
│   ├── GitHub/
│   │   ├── GitHubClient.php          # GitHub API client
│   │   └── Downloader.php            # Download & extract plugins
│   ├── Plugin/
│   │   ├── Manager.php               # Plugin lifecycle management
│   │   └── Registry.php              # Track installed GitHub plugins
│   └── CLI/
│       └── Commands.php              # WP-CLI commands implementation
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── languages/
│   └── wp-plugin-registry.pot
└── composer.json
```

## 2. Plugin Header & Constants

**File:** `wp-plugin-registry.php`

```php
<?php
/**
 * Plugin Name: WP Plugin Registry
 * Plugin URI: https://github.com/yourname/wp-plugin-registry
 * Description: Install and manage WordPress plugins from GitHub repositories.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
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
```

## 3. Main Plugin Class

**File:** `src/Main.php`

```php
<?php
namespace WPPluginRegistry;

use WPPluginRegistry\Admin\Admin;
use WPPluginRegistry\Plugin\Registry;
use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\GitHub\GitHubClient;
use WPPluginRegistry\CLI\Commands;
use WPPluginRegistry\Traits\Singleton;

class Main {
    use Singleton;

    private GitHubClient $github_client;
    private Manager $plugin_manager;
    private Registry $registry;

    private function __construct() {
        $this->init_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    private function init_dependencies(): void {
        // Initialize autoloader
        if (file_exists(WPPR_PATH . 'vendor/autoload.php')) {
            require_once WPPR_PATH . 'vendor/autoload.php';
        }
    }

    private function init_components(): void {
        $this->github_client = new GitHubClient();
        $this->plugin_manager = new Manager($this->github_client);
        $this->registry = Registry::get_instance();

        // Initialize admin if in admin area
        if (is_admin()) {
            Admin::get_instance();
        }

        // Initialize WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            Commands::register($this->plugin_manager, $this->registry);
        }
    }

    private function init_hooks(): void {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        register_activation_hook(WPPR_FILE, [$this, 'activate']);
        register_deactivation_hook(WPPR_FILE, [$this, 'deactivate']);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('wp-plugin-registry', false, dirname(WPPR_BASENAME) . '/languages');
    }

    public function activate(): void {
        $this->registry->create_tables();
        update_option('wppr_version', WPPR_VERSION);
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook('wppr_check_updates');
    }

    public function get_manager(): Manager {
        return $this->plugin_manager;
    }

    public function get_registry(): Registry {
        return $this->registry;
    }

    public function get_github_client(): GitHubClient {
        return $this->github_client;
    }
}

// Bootstrap the plugin
Main::get_instance();
```

## 4. GitHub API Client

**File:** `src/GitHub/GitHubClient.php`

```php
<?php
namespace WPPluginRegistry\GitHub;

class GitHubClient {
    private $api_base = 'https://api.github.com';
    private $token;
    private $rate_limit_remaining = 60;
    private $rate_limit_reset;

    public function __construct($token = '') {
        $this->token = $token ?: $this->get_stored_token();
    }

    private function get_stored_token(): string {
        $options = get_option('wppr_settings', []);
        return $options['github_token'] ?? '';
    }

    /**
     * Parse GitHub URL to extract owner and repo
     */
    public function parse_url(string $url): ?array {
        $patterns = [
            '/github\.com\/([^\/]+)\/([^\/]+)/',
            '/^([^\/]+)\/([^\/]+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'owner' => $matches[1],
                    'repo' => preg_replace('/\.git$/', '', $matches[2]),
                ];
            }
        }

        return null;
    }

    /**
     * Get latest release information
     */
    public function get_latest_release(string $owner, string $repo): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases/latest";
        return $this->request($endpoint);
    }

    /**
     * List all releases
     */
    public function list_releases(string $owner, string $repo, int $per_page = 30): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases?per_page={$per_page}";
        return $this->request($endpoint);
    }

    /**
     * Get release by tag
     */
    public function get_release_by_tag(string $owner, string $repo, string $tag): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases/tags/{$tag}";
        return $this->request($endpoint);
    }

    /**
     * Get repository information
     */
    public function get_repo_info(string $owner, string $repo): array {
        $endpoint = "/repos/{$owner}/{$repo}";
        return $this->request($endpoint);
    }

    /**
     * Get download URL for tarball
     */
    public function get_tarball_url(string $owner, string $repo, string $ref): string {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/tarball/{$ref}";

        if (!empty($this->token)) {
            $url = "https://{$this->token}:x-oauth-basic@github.com/{$owner}/{$repo}/tarball/{$ref}";
        }

        return $url;
    }

    /**
     * Download tarball to file
     */
    public function download_tarball(string $owner, string $repo, string $ref, string $destination): array {
        $url = $this->get_tarball_url($owner, $repo, $ref);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => fopen($destination, 'wb'),
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'WP-Plugin-Registry/' . WPPR_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($this->token)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/vnd.github+json',
            ]);
        }

        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$success || $http_code !== 200) {
            @unlink($destination);
            return [
                'success' => false,
                'error' => $error ?: "HTTP {$http_code}",
            ];
        }

        return ['success' => true, 'path' => $destination];
    }

    /**
     * Make API request
     */
    private function request(string $endpoint): array {
        $url = $this->api_base . $endpoint;

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WP-Plugin-Registry/' . WPPR_VERSION,
            ],
        ];

        if (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Update rate limit info
        $this->update_rate_limit($response);

        if ($code >= 400) {
            return [
                'success' => false,
                'error' => json_decode($body, true)['message'] ?? 'API request failed',
                'code' => $code,
            ];
        }

        return [
            'success' => true,
            'data' => json_decode($body, true),
        ];
    }

    private function update_rate_limit($response): void {
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-ratelimit-remaining'])) {
            $this->rate_limit_remaining = (int) $headers['x-ratelimit-remaining'];
        }
        if (isset($headers['x-ratelimit-reset'])) {
            $this->rate_limit_reset = (int) $headers['x-ratelimit-reset'];
        }
    }

    public function get_rate_limit_status(): array {
        return [
            'remaining' => $this->rate_limit_remaining,
            'reset' => $this->rate_limit_reset,
        ];
    }
}
```

## 5. Plugin Registry (Database)

**File:** `src/Plugin/Registry.php`

```php
<?php
namespace WPPluginRegistry\Plugin;

use WPPluginRegistry\Traits\Singleton;

class Registry {
    use Singleton;

    private const OPTION_NAME = 'wppr_installed_plugins';
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
            return [];
        }

        return get_plugin_data($plugin_file, false, false);
    }

    /**
     * Drop tables on uninstall
     */
    public function drop_tables(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
```

## 6. Plugin Manager

**File:** `src/Plugin/Manager.php`

```php
<?php
namespace WPPluginRegistry\Plugin;

use WPPluginRegistry\GitHub\GitHubClient;
use WPPluginRegistry\GitHub\Downloader;

class Manager {
    private GitHubClient $github;
    private Downloader $downloader;

    public function __construct(GitHubClient $github) {
        $this->github = $github;
        $this->downloader = new Downloader($this->github);
    }

    /**
     * Install plugin from GitHub URL
     */
    public function install(string $url, string $version = 'main'): array {
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

        // Activate if requested
        if (!empty($assoc_args['activate'])) {
            $this->activate($slug);
        }

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
    public function update(string $slug): array {
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
            return [
                'success' => false,
                'error' => $release['error'],
            ];
        }

        $latest_version = $release['data']['tag_name'];

        // Check if update is needed
        if (version_compare($latest_version, $plugin['installed_version'], '<=')) {
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

        // Activate if was active
        if (is_plugin_active($plugin['local_dir'] . '/' . $plugin['local_dir'] . '.php')) {
            $this->activate($slug);
        }

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
    public function activate(string $slug): array {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        $plugin_file = $this->find_main_plugin_file($plugin['local_path']);

        if (!$plugin_file) {
            return [
                'success' => false,
                'error' => __('Main plugin file not found', 'wp-plugin-registry'),
            ];
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        return [
            'success' => true,
            'message' => __('Plugin activated', 'wp-plugin-registry'),
        ];
    }

    /**
     * Deactivate plugin
     */
    public function deactivate(string $slug): array {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        $plugin_file = $this->find_main_plugin_file($plugin['local_path']);

        if (!$plugin_file) {
            return [
                'success' => false,
                'error' => __('Main plugin file not found', 'wp-plugin-registry'),
            ];
        }

        deactivate_plugins($plugin_file);

        return [
            'success' => true,
            'message' => __('Plugin deactivated', 'wp-plugin-registry'),
        ];
    }

    /**
     * Uninstall and delete plugin
     */
    public function uninstall(string $slug): array {
        $plugin = Registry::get_instance()->get($slug);

        if (!$plugin) {
            return [
                'success' => false,
                'error' => __('Plugin not found', 'wp-plugin-registry'),
            ];
        }

        // Deactivate first
        deactivate_plugins($this->find_main_plugin_file($plugin['local_path']), true);

        // Run uninstall.php if exists
        $uninstall_file = $plugin['local_path'] . '/uninstall.php';
        if (file_exists($uninstall_file)) {
            define('WP_UNINSTALL_PLUGIN', $this->find_main_plugin_file($plugin['local_path']));
            include $uninstall_file;
        }

        // Delete plugin files
        $this->delete_directory($plugin['local_path']);

        // Remove from registry
        Registry::get_instance()->remove($slug);

        return [
            'success' => true,
            'message' => __('Plugin uninstalled', 'wp-plugin-registry'),
        ];
    }

    /**
     * Get plugin version from main file
     */
    public function get_plugin_version(string $plugin_path): string {
        $main_file = $this->find_main_plugin_file($plugin_path);

        if (!$main_file || !file_exists($main_file)) {
            return '';
        }

        $data = get_plugin_data($main_file, false, false);
        return $data['Version'] ?? '';
    }

    /**
     * Check for updates for all registered plugins
     */
    public function check_for_updates(): array {
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
                }
            }
        }

        return $updates;
    }

    /**
     * Generate plugin slug from owner/repo
     */
    private function generate_slug(string $owner, string $repo): string {
        return sanitize_title($owner . '-' . $repo);
    }

    /**
     * Find main plugin file in directory
     */
    private function find_main_plugin_file(string $path): string {
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
    private function delete_directory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }

        return rmdir($dir);
    }
}
```

## 7. GitHub Downloader

**File:** `src/GitHub/Downloader.php`

```php
<?php
namespace WPPluginRegistry\GitHub;

class Downloader {
    private GitHubClient $github;

    public function __construct(GitHubClient $github) {
        $this->github = $github;
    }

    /**
     * Download and extract GitHub repository
     */
    public function download_and_extract(string $owner, string $repo, string $ref, string $slug): array {
        // Create temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'wppr_');
        $temp_file = $temp_file . '.tar.gz';
        rename($temp_file, $temp_file);

        // Download
        $result = $this->github->download_tarball($owner, $repo, $ref, $temp_file);

        if (!$result['success']) {
            @unlink($temp_file);
            return $result;
        }

        // Extract
        $extract_result = $this->extract_to_plugins($temp_file, $slug);

        // Cleanup temp file
        @unlink($temp_file);

        if (!$extract_result['success']) {
            return $extract_result;
        }

        return [
            'success' => true,
            'path' => $extract_result['path'],
        ];
    }

    /**
     * Extract tarball to plugins directory
     */
    private function extract_to_plugins(string $tarball, string $slug): array {
        $plugins_dir = WPPR_PLUGINS_DIR;

        if (!is_dir($plugins_dir)) {
            @mkdir($plugins_dir, 0755, true);
        }

        if (!class_exists('PharData')) {
            return [
                'success' => false,
                'error' => __('PharData class not available', 'wp-plugin-registry'),
            ];
        }

        try {
            $phar = new PharData($tarball);

            // Get root folder name
            $root_folder = $this->get_root_folder($phar);

            if (!$root_folder) {
                return [
                    'success' => false,
                    'error' => __('Could not determine root folder', 'wp-plugin-registry'),
                ];
            }

            $destination = $plugins_dir . '/' . $slug;

            // Clean up existing installation
            if (is_dir($destination)) {
                $this->delete_directory($destination);
            }

            // Extract
            $phar->extractTo($destination, null, true);

            // Move files from root folder to destination
            $temp_extract = $plugins_dir . '/temp_' . uniqid();
            $phar->extractTo($temp_extract);

            $extracted_root = $temp_extract . '/' . $root_folder;

            if (is_dir($extracted_root)) {
                $this->copy_directory($extracted_root, $destination);
                $this->delete_directory($temp_extract);
            } else {
                // Try without root folder
                $this->copy_directory($temp_extract, $destination);
                $this->delete_directory($temp_extract);
            }

            return [
                'success' => true,
                'path' => $destination,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get root folder name from archive
     */
    private function get_root_folder(\PharData $phar): ?string {
        foreach ($phar as $entry) {
            $filename = $entry->getFilename();
            $parts = explode('/', $filename);
            if (count($parts) > 1) {
                return $parts[0];
            }
        }
        return null;
    }

    /**
     * Copy directory recursively
     */
    private function copy_directory(string $src, string $dst): void {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }

        $files = scandir($src);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path);
            } else {
                @copy($src_path, $dst_path);
            }
        }
    }

    /**
     * Delete directory recursively
     */
    private function delete_directory(string $dir): bool {
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
```

## 8. Admin Interface

**File:** `src/Admin/Admin.php`

```php
<?php
namespace WPPluginRegistry\Admin;

use WPPluginRegistry\Plugin\Registry;
use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\GitHub\GitHubClient;

class Admin {
    use \WPPluginRegistry\Traits\Singleton;

    private Manager $manager;
    private Registry $registry;
    private GitHubClient $github;

    private function __construct() {
        $this->manager = \WPPluginRegistry\Main::get_instance()->get_manager();
        $this->registry = \WPPluginRegistry\Main::get_instance()->get_registry();
        $this->github = \WPPluginRegistry\Main::get_instance()->get_github_client();

        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_wppr_install', [$this, 'handle_install']);
        add_action('admin_post_wppr_update', [$this, 'handle_update']);
        add_action('admin_post_wppr_activate', [$this, 'handle_activate']);
        add_action('admin_post_wppr_deactivate', [$this, 'handle_deactivate']);
        add_action('admin_post_wppr_uninstall', [$this, 'handle_uninstall']);
    }

    public function add_menu_pages(): void {
        add_menu_page(
            __('WP Plugin Registry', 'wp-plugin-registry'),
            __('Plugin Registry', 'wp-plugin-registry'),
            'manage_options',
            'wp-plugin-registry',
            [$this, 'render_main_page'],
            'dashicons-plugins-checked',
            30
        );
    }

    public function enqueue_assets(): void {
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

    public function render_main_page(): void {
        // Handle actions
        $action = $_GET['action'] ?? '';
        $message = $_GET['message'] ?? '';
        $error = $_GET['error'] ?? '';

        // Check for updates
        $this->manager->check_for_updates();

        // Get all registered plugins
        $plugins = $this->registry->get_all();

        include WPPR_PATH . 'templates/admin-page.php';
    }

    public function handle_install(): void {
        check_admin_referer('wppr_install');

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

    public function handle_update(): void {
        check_admin_referer('wppr_update_' . $_GET['slug']);

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $slug = sanitize_text_field($_GET['slug'] ?? '');
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

    public function handle_activate(): void {
        check_admin_referer('wppr_activate_' . $_GET['slug']);

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $slug = sanitize_text_field($_GET['slug'] ?? '');
        $this->manager->activate($slug);

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry'));
        exit;
    }

    public function handle_deactivate(): void {
        check_admin_referer('wppr_deactivate_' . $_GET['slug']);

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $slug = sanitize_text_field($_GET['slug'] ?? '');
        $this->manager->deactivate($slug);

        wp_redirect(admin_url('admin.php?page=wp-plugin-registry'));
        exit;
    }

    public function handle_uninstall(): void {
        check_admin_referer('wppr_uninstall_' . $_GET['slug']);

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-plugin-registry'));
        }

        $slug = sanitize_text_field($_GET['slug'] ?? '');
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
}
```

## 9. WP-CLI Commands

**File:** `src/CLI/Commands.php`

```php
<?php
namespace WPPluginRegistry\CLI;

use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\Plugin\Registry;

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
            $is_active = is_plugin_active($plugin_file);
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
        $is_active = is_plugin_active($plugin_file);

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
```

## 10. Security Considerations

### 1. Capability Checks
```php
// All admin actions check for 'manage_options'
if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions'));
}
```

### 2. Nonces
```php
// Generate nonce
wp_nonce_field('wppr_install', 'wppr_nonce');

// Verify nonce
check_admin_referer('wppr_install');
```

### 3. Sanitization
```php
// URL validation
$url = esc_url_raw($_POST['github_url']);

// Text sanitization
$version = sanitize_text_field($_POST['version']);

// Path sanitization
$slug = sanitize_title($owner . '-' . $repo);
```

### 4. Output Escaping
```php
// Admin template
echo esc_html($plugin['name']);
echo esc_url($plugin['source_url']);
```

### 5. GitHub Token Storage
```php
// Store encrypted in options table
update_option('wppr_settings', [
    'github_token' => encrypt($token),
]);

// Retrieve and decrypt when needed
$token = decrypt(get_option('wppr_settings')['github_token']);
```

## 11. Uninstall Cleanup

**File:** `uninstall.php`

```php
<?php
/**
 * Plugin uninstaller
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define constants
define('WPPR_PATH', plugin_dir_path(__FILE__));

// Include registry for cleanup
require_once WPPR_PATH . 'src/Plugin/Registry.php';

use WPPluginRegistry\Plugin\Registry;

// Drop custom table
Registry::get_instance()->drop_tables();

// Delete options
delete_option('wppr_settings');
delete_option('wppr_version');

// Delete transients
delete_transient('wppr_rate_limit');
```

## 12. Template (Admin Page)

**File:** `templates/admin-page.php`

```php
<?php
/**
 * Admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugins = $this->registry->get_all();
?>

<div class="wrap">
    <h1><?php _e('WP Plugin Registry', 'wp-plugin-registry'); ?></h1>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="wppr-grid">
        <!-- Install Form -->
        <div class="wppr-card">
            <h2><?php _e('Install from GitHub', 'wp-plugin-registry'); ?></h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('wppr_install', 'wppr_nonce'); ?>
                <input type="hidden" name="action" value="wppr_install">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_url"><?php _e('GitHub URL', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="github_url" name="github_url"
                                   class="regular-text"
                                   placeholder="https://github.com/owner/repo.git" required>
                            <p class="description">
                                <?php _e('Enter the GitHub repository URL', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="version"><?php _e('Version/Branch', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="version" name="version"
                                   class="regular-text" value="main">
                            <p class="description">
                                <?php _e('Branch or tag name (e.g., main, master, v1.0.0)', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="activate"><?php _e('Activation', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="activate" name="activate" value="1">
                                <?php _e('Activate after installation', 'wp-plugin-registry'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Install Plugin', 'wp-plugin-registry')); ?>
            </form>
        </div>

        <!-- Token Settings -->
        <div class="wppr-card">
            <h2><?php _e('GitHub Settings', 'wp-plugin-registry'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('wppr_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_token"><?php _e('Personal Access Token', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="github_token" name="wppr_settings[github_token]"
                                   class="regular-text"
                                   value="<?php echo esc_attr($options['github_token'] ?? ''); ?>">
                            <p class="description">
                                <?php _e('Required for private repositories. Create at GitHub Settings > Developer settings > Personal access tokens', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'wp-plugin-registry')); ?>
            </form>
        </div>
    </div>

    <!-- Plugin List -->
    <h2><?php _e('Installed Plugins', 'wp-plugin-registry'); ?></h2>

    <?php if (empty($plugins)): ?>
        <p><?php _e('No GitHub plugins installed yet.', 'wp-plugin-registry'); ?></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Version', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Latest', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Status', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Actions', 'wp-plugin-registry'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin): ?>
                    <?php
                    $plugin_file = $plugin['local_path'] . '/' . $plugin['local_dir'] . '.php';
                    $is_active = file_exists($plugin_file) && is_plugin_active($plugin_file);
                    $has_update = $plugin['has_update'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($plugin['wp_plugin_data']['Name'] ?? $plugin['slug']); ?></strong>
                            <br>
                            <small><?php echo esc_html($plugin['source_url']); ?></small>
                        </td>
                        <td><?php echo esc_html($plugin['installed_version']); ?></td>
                        <td>
                            <?php echo esc_html($plugin['latest_version']); ?>
                            <?php if ($has_update): ?>
                                <span class="update-badge"><?php _e('Update available', 'wp-plugin-registry'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="status-active"><?php _e('Active', 'wp-plugin-registry'); ?></span>
                            <?php else: ?>
                                <span class="status-inactive"><?php _e('Inactive', 'wp-plugin-registry'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($has_update): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_update&slug=' . $plugin['slug']), 'wppr_update_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Update', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($is_active): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_deactivate&slug=' . $plugin['slug']), 'wppr_deactivate_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Deactivate', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_activate&slug=' . $plugin['slug']), 'wppr_activate_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Activate', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_uninstall&slug=' . $plugin['slug']), 'wppr_uninstall_' . $plugin['slug']); ?>"
                                   class="button button-link-delete"
                                   onclick="return confirm('<?php _e('Are you sure you want to uninstall this plugin?', 'wp-plugin-registry'); ?>')">
                                    <?php _e('Uninstall', 'wp-plugin-registry'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
```

## 13. Composer.json

```json
{
    "name": "vendor/wp-plugin-registry",
    "description": "Install and manage WordPress plugins from GitHub repositories",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "WPPluginRegistry\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

## 14. Checklist triển khai

### Phase 1: Core Structure
- [ ] Tạo file `wp-plugin-registry.php` với plugin header và constants
- [ ] Tạo autoloader với Composer
- [ ] Implement singleton trait
- [ ] Tạo class `Main.php` với initialization logic

### Phase 2: GitHub Integration
- [ ] Implement `GitHubClient.php` với API methods
- [ ] Implement `Downloader.php` cho file operations
- [ ] Xử lý tarball extraction
- [ ] Hỗ trợ authentication với PAT

### Phase 3: Plugin Registry
- [ ] Tạo database table cho tracking
- [ ] Implement `Registry.php` CRUD operations
- [ ] Xử lý plugin metadata extraction

### Phase 4: Plugin Manager
- [ ] Implement `Manager.php` với install/update/activate/deactivate/uninstall
- [ ] Tích hợp với WordPress plugin API
- [ ] Xử lý file cleanup

### Phase 5: Admin Interface
- [ ] Tạo admin menu pages
- [ ] Implement install form
- [ ] Create plugin list table
- [ ] Add action buttons
- [ ] Enqueue assets

### Phase 6: WP-CLI Commands
- [ ] Register `wp wppr install` command
- [ ] Register `wp wppr update` command
- [ ] Register `wp wppr activate/deactivate` commands
- [ ] Register `wp wppr uninstall` command
- [ ] Register `wp wppr list` command
- [ ] Register `wp wppr info` command
- [ ] Register `wp wppr version` command
- [ ] Register `wp wppr check-updates` command

### Phase 7: Security & Cleanup
- [ ] Thêm capability checks
- [ ] Implement nonce verification
- [ ] Add input sanitization
- [ ] Add output escaping
- [ ] Implement uninstall cleanup
- [ ] Tạo `uninstall.php`

### Phase 8: Testing
- [ ] Test với public repository
- [ ] Test với private repository
- [ ] Test với various branch/tag formats
- [ ] Test update workflow
- [ ] Test activation/deactivation
- [ ] Test uninstall cleanup
- [ ] Test WP-CLI commands

## 15. Ví dụ sử dụng

### WP-CLI Commands
```bash
# Cài đặt plugin từ GitHub
wp wppr install https://github.com/trungdo9/wp-nexus.git

# Cài đặt với branch cụ thể và tự động activate
wp wppr install https://github.com/trungdo9/wp-nexus.git --version=main --activate

# Cài đặt từ release tag
wp wppr install https://github.com/trungdo9/wp-nexus.git --version=v1.2.3

# Kiểm tra version
wp wppr version trungdo9-wp-nexus

# Update plugin
wp wppr update trungdo9-wp-nexus

# Activate/Deactivate
wp wppr activate trungdo9-wp-nexus
wp wppr deactivate trungdo9-wp-nexus

# Uninstall
wp wppr uninstall trungdo9-wp-nexus --yes

# List tất cả plugins
wp wppr list
wp wppr list --status=active

# Kiểm tra updates
wp wppr check-updates

# Xem chi tiết plugin
wp wppr info trungdo9-wp-nexus
```

## 16. Limitations & Future Improvements

### Current Limitations
- Chỉ hỗ trợ tarball (`.tar.gz`) từ GitHub
- Không hỗ trợ GitHub releases với custom assets
- Rate limit từ GitHub API (60 requests/hour không auth)

### Future Improvements
- [ ] Hỗ trợ GitHub Releases assets (`.zip`)
- [ ] Tích hợp với WordPress update checker
- [ ] Async download operations
- [ ] Progress bar cho download
- [ ] Bulk operations trong admin
- [ ] Translation support
- [ ] Network/multisite support
