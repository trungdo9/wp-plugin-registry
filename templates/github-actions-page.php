<?php
/**
 * GitHub Actions settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$triggers = $triggers ?? [];
$options = $options ?? [];
$test_result = $test_result ?? [];
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';

$github_actions_enabled = isset($options['github_actions_enabled']) && $options['github_actions_enabled'];
$github_token = $options['github_token'] ?? '';
$enabled_triggers = isset($options['github_actions_triggers']) ? $options['github_actions_triggers'] : [];
?>

<div class="wrap">
    <h1><?php _e('GitHub Actions Settings', 'wp-plugin-registry'); ?></h1>

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

    <?php if (isset($_GET['tested']) && $_GET['tested'] === 'true' && isset($test_result['success'])): ?>
        <?php if ($test_result['success']): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($test_result['message']); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($test_result['error']); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="wppr-card">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('wppr_save_github_actions', 'wppr_nonce'); ?>
            <input type="hidden" name="action" value="wppr_save_github_actions">

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable GitHub Actions', 'wp-plugin-registry'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="github_actions_enabled" value="1" <?php checked($github_actions_enabled, true); ?>>
                            <?php _e('Enable automatic GitHub Actions triggers', 'wp-plugin-registry'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, GitHub Actions will be triggered based on the events below.', 'wp-plugin-registry'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github_token"><?php _e('Personal Access Token', 'wp-plugin-registry'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="github_token" name="github_token"
                               class="regular-text"
                               value="<?php echo esc_attr($github_token); ?>" autocomplete="off">
                        <p class="description">
                            <?php _e('Required to trigger GitHub Actions. Create a token with "repo" scope at GitHub Settings > Developer settings > Personal access tokens.', 'wp-plugin-registry'); ?>
                        </p>
                        <p>
                            <button type="submit" name="test_connection" class="button button-secondary" value="1">
                                <?php _e('Test Connection', 'wp-plugin-registry'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php _e('Trigger Events', 'wp-plugin-registry'); ?></h2>
            <p class="description">
                <?php _e('Select which events should trigger GitHub Actions in your repositories.', 'wp-plugin-registry'); ?>
            </p>

            <table class="form-table">
                <?php foreach ($triggers as $key => $trigger): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($trigger['name']); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="trigger_<?php echo esc_attr($key); ?>"
                                       value="1" <?php checked(isset($enabled_triggers[$key]) && $enabled_triggers[$key], true); ?>>
                                <?php _e('Enable', 'wp-plugin-registry'); ?>
                            </label>
                            <p class="description"><?php echo esc_html($trigger['description']); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button(__('Save Settings', 'wp-plugin-registry')); ?>
        </form>
    </div>

    <div class="wppr-card">
        <h2><?php _e('How It Works', 'wp-plugin-registry'); ?></h2>
        <ol>
            <li><?php _e('When enabled, the plugin will trigger GitHub Actions workflows in your repositories based on the selected events.', 'wp-plugin-registry'); ?></li>
            <li><?php _e('The plugin will dispatch to the first available workflow in your repository.', 'wp-plugin-registry'); ?></li>
            <li><?php _e('Workflow inputs will include event type, plugin slug, version, and source information.', 'wp-plugin-registry'); ?></li>
            <li><?php _e('Logs of triggered actions are recorded in the Activity Logs page.', 'wp-plugin-registry'); ?></li>
        </ol>

        <h3><?php _e('Example Workflow', 'wp-plugin-registry'); ?></h3>
        <p><?php _e('Your repository should have a workflow file like this:', 'wp-plugin-registry'); ?></p>
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
<code>name: WordPress Plugin
on:
  workflow_dispatch:
    inputs:
      event_type:
        description: 'Event type'
        required: true
      plugin_slug:
        description: 'Plugin slug'
      version:
        description: 'Version'
      source:
        description: 'Source'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Handle <?php _e('event type', 'wp-plugin-registry'); ?>
        run: |
          echo "Event: ${{ github.event.inputs.event_type }}"
          echo "Plugin: ${{ github.event.inputs.plugin_slug }}"
          echo "Version: ${{ github.event.inputs.version }}"</code>
        </pre>
    </div>
</div>

<style>
.wppr-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wppr-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 15px;
}
</style>
