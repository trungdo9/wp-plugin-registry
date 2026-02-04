<?php
/**
 * Activity Logs page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$logs = $logs ?? [];
$action_types = $action_types ?? [];
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';

$action_labels = [
    'install' => __('Install', 'wp-plugin-registry'),
    'update' => __('Update', 'wp-plugin-registry'),
    'activate' => __('Activate', 'wp-plugin-registry'),
    'deactivate' => __('Deactivate', 'wp-plugin-registry'),
    'uninstall' => __('Uninstall', 'wp-plugin-registry'),
    'update_check' => __('Update Check', 'wp-plugin-registry'),
    'github_action' => __('GitHub Action', 'wp-plugin-registry'),
];
?>

<div class="wrap">
    <h1><?php _e('Activity Logs', 'wp-plugin-registry'); ?></h1>

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

    <div class="wppr-logs-header">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('wppr_clear_logs', 'wppr_nonce'); ?>
            <input type="hidden" name="action" value="wppr_clear_logs">
            <?php submit_button(__('Clear All Logs', 'wp-plugin-registry'), 'delete', 'clear_logs', false); ?>
        </form>
    </div>

    <?php if (empty($logs)): ?>
        <p><?php _e('No activity logs yet.', 'wp-plugin-registry'); ?></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Action', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Plugin', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Message', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('User', 'wp-plugin-registry'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $user = get_userdata($log['user_id']);
                    $action_label = isset($action_labels[$log['action']]) ? $action_labels[$log['action']] : $log['action'];
                    $action_class = 'action-' . $log['action'];
                    ?>
                    <tr>
                        <td>
                            <span class="log-time"><?php echo esc_html($log['created_at']); ?></span>
                        </td>
                        <td>
                            <span class="wppr-badge <?php echo esc_attr($action_class); ?>">
                                <?php echo esc_html($action_label); ?>
                            </span>
                        </td>
                        <td>
                            <code><?php echo esc_html($log['plugin_slug']); ?></code>
                        </td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td>
                            <?php if ($user): ?>
                                <?php echo esc_html($user->display_name); ?>
                            <?php else: ?>
                                <span class="description"><?php _e('System', 'wp-plugin-registry'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.wppr-logs-header {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #c3c4c7;
}

.wppr-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.wppr-badge.action-install { background: #d4edda; color: #155724; }
.wppr-badge.action-update { background: #cce5ff; color: #004085; }
.wppr-badge.action-activate { background: #d1ecf1; color: #0c5460; }
.wppr-badge.action-deactivate { background: #fff3cd; color: #856404; }
.wppr-badge.action-uninstall { background: #f8d7da; color: #721c24; }
.wppr-badge.action-update_check { background: #e2e3e5; color: #383d41; }
.wppr-badge.action-github_action { background: #d4edda; color: #155724; }

.log-time {
    font-size: 12px;
    color: #666;
}
</style>
