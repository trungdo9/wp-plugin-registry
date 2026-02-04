<?php
namespace WPPluginRegistry\GitHub;

/**
 * GitHub Actions Integration
 * Triggers GitHub Actions when new releases are detected
 */
class GitHubActions {
    private $token;
    private $enabled = false;
    private $triggers = [];

    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load settings from options
     */
    private function load_settings() {
        $options = get_option('wppr_settings', []);
        $this->enabled = isset($options['github_actions_enabled']) && $options['github_actions_enabled'];
        $this->token = !empty($options['github_token']) ? $options['github_token'] : '';
        $this->triggers = isset($options['github_actions_triggers']) ? $options['github_actions_triggers'] : [];
    }

    /**
     * Save settings to options
     */
    public function save_settings($enabled, $token = '', $triggers = []) {
        $options = get_option('wppr_settings', []);
        $options['github_actions_enabled'] = $enabled;
        $options['github_token'] = $token;
        $options['github_actions_triggers'] = $triggers;

        $this->enabled = $enabled;
        $this->token = $token;
        $this->triggers = $triggers;

        update_option('wppr_settings', $options);
    }

    /**
     * Check if GitHub Actions integration is enabled
     */
    public function is_enabled() {
        return $this->enabled && !empty($this->token);
    }

    /**
     * Get available triggers
     */
    public function get_available_triggers() {
        return [
            'on_release' => [
                'name' => __('On New Release', 'wp-plugin-registry'),
                'description' => __('Trigger when a new release is published', 'wp-plugin-registry'),
                'default' => true,
            ],
            'on_update_available' => [
                'name' => __('On Update Available', 'wp-plugin-registry'),
                'description' => __('Trigger when a new version is available', 'wp-plugin-registry'),
                'default' => false,
            ],
            'on_install' => [
                'name' => __('On Plugin Install', 'wp-plugin-registry'),
                'description' => __('Trigger when a plugin is installed', 'wp-plugin-registry'),
                'default' => false,
            ],
            'on_update' => [
                'name' => __('On Plugin Update', 'wp-plugin-registry'),
                'description' => __('Trigger when a plugin is updated', 'wp-plugin-registry'),
                'default' => true,
            ],
            'on_uninstall' => [
                'name' => __('On Plugin Uninstall', 'wp-plugin-registry'),
                'description' => __('Trigger when a plugin is uninstalled', 'wp-plugin-registry'),
                'default' => false,
            ],
        ];
    }

    /**
     * Check if a specific trigger is enabled
     */
    public function is_trigger_enabled($trigger_name) {
        if (!$this->is_enabled()) {
            return false;
        }

        return isset($this->triggers[$trigger_name]) && $this->triggers[$trigger_name];
    }

    /**
     * Trigger GitHub Actions for a repository
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $event_type Type of event (release, installation, etc.)
     * @param array $payload Additional payload data
     * @return array Result with success status
     */
    public function trigger($owner, $repo, $event_type, $payload = []) {
        if (!$this->is_enabled()) {
            return [
                'success' => false,
                'error' => __('GitHub Actions is not enabled', 'wp-plugin-registry'),
            ];
        }

        // Check if this event type should trigger
        $trigger_key = 'on_' . str_replace('-', '_', sanitize_key($event_type));
        if (!$this->is_trigger_enabled($trigger_key)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => __('Trigger is disabled', 'wp-plugin-registry'),
            ];
        }

        // Check for repository-specific override
        $repo_key = $owner . '/' . $repo;
        if (isset($this->triggers['repo_overrides']) && isset($this->triggers['repo_overrides'][$repo_key])) {
            $repo_settings = $this->triggers['repo_overrides'][$repo_key];
            if (isset($repo_settings['enabled']) && !$repo_settings['enabled']) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'message' => __('GitHub Actions disabled for this repository', 'wp-plugin-registry'),
                ];
            }
        }

        return $this->dispatch_workflow($owner, $repo, $event_type, $payload);
    }

    /**
     * Dispatch a workflow to GitHub
     */
    private function dispatch_workflow($owner, $repo, $event_type, $payload) {
        $api_url = "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows";

        // Check if workflows exist and get the first one
        $workflows = $this->get_workflows($owner, $repo);

        if (empty($workflows)) {
            return [
                'success' => false,
                'error' => __('No workflows found in repository', 'wp-plugin-registry'),
            ];
        }

        // Use the first workflow or a specific one
        $workflow_id = $workflows[0]['id'];
        $ref = $payload['ref'] ?? 'main';

        // Dispatch the workflow
        $dispatch_url = "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows/{$workflow_id}/dispatches";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $dispatch_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'ref' => $ref,
                'inputs' => [
                    'event_type' => $event_type,
                    'plugin_slug' => $payload['plugin_slug'] ?? '',
                    'version' => $payload['version'] ?? '',
                    'source' => 'wp-plugin-registry',
                ],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
            ];
        }

        if ($http_code === 204) {
            return [
                'success' => true,
                'message' => __('Workflow triggered successfully', 'wp-plugin-registry'),
            ];
        }

        if ($http_code === 404) {
            return [
                'success' => false,
                'error' => __('Workflow not found. Make sure the repository has GitHub Actions workflows.', 'wp-plugin-registry'),
            ];
        }

        if ($http_code === 422) {
            return [
                'success' => false,
                'error' => __('Workflow cannot be triggered. Check workflow permissions and inputs.', 'wp-plugin-registry'),
            ];
        }

        return [
            'success' => false,
            'error' => __('Unknown error occurred', 'wp-plugin-registry'),
            'code' => $http_code,
        ];
    }

    /**
     * Get available workflows for a repository
     */
    public function get_workflows($owner, $repo) {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            return [];
        }

        $data = json_decode($response, true);
        return isset($data['workflows']) ? $data['workflows'] : [];
    }

    /**
     * Trigger on new release
     */
    public function on_new_release($owner, $repo, $release_tag, $version) {
        return $this->trigger($owner, $repo, 'release', [
            'version' => $version,
            'release_tag' => $release_tag,
            'plugin_slug' => sanitize_title($owner . '-' . $repo),
        ]);
    }

    /**
     * Trigger on update available
     */
    public function on_update_available($owner, $repo, $current_version, $latest_version) {
        return $this->trigger($owner, $repo, 'update_available', [
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'plugin_slug' => sanitize_title($owner . '-' . $repo),
        ]);
    }

    /**
     * Trigger on plugin install
     */
    public function on_install($owner, $repo, $version) {
        return $this->trigger($owner, $repo, 'install', [
            'version' => $version,
            'plugin_slug' => sanitize_title($owner . '-' . $repo),
        ]);
    }

    /**
     * Trigger on plugin update
     */
    public function on_update($owner, $repo, $old_version, $new_version) {
        return $this->trigger($owner, $repo, 'update', [
            'old_version' => $old_version,
            'new_version' => $new_version,
            'plugin_slug' => sanitize_title($owner . '-' . $repo),
        ]);
    }

    /**
     * Trigger on plugin uninstall
     */
    public function on_uninstall($owner, $repo, $version) {
        return $this->trigger($owner, $repo, 'uninstall', [
            'version' => $version,
            'plugin_slug' => sanitize_title($owner . '-' . $repo),
        ]);
    }

    /**
     * Test connection to GitHub
     */
    public function test_connection() {
        $url = 'https://api.github.com/user';

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $user = json_decode($response, true);
            return [
                'success' => true,
                'message' => __('Connected successfully as', 'wp-plugin-registry') . ' ' . $user['login'],
            ];
        }

        return [
            'success' => false,
            'error' => __('Authentication failed. Check your token.', 'wp-plugin-registry'),
            'code' => $http_code,
        ];
    }
}
