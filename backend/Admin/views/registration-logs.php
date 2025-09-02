<?php
/**
 * Admin Page: Registration Logs
 */

if (!defined('ABSPATH')) exit;

// Load styles
wp_enqueue_style('kbs-admin-style', plugin_dir_url(__FILE__) . '../../assets/css/admin-style.css');

// Get logs
global $wpdb;
$logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kbs_registration_logs ORDER BY created_at DESC");
?>

<div class="kbs-admin-container">
  <h1>Registration Logs</h1>

  <table class="kbs-admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Message</th>
        <th>IP Address</th>
        <th>Timestamp</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($logs): ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?php echo esc_html($log->id); ?></td>
            <td><?php echo esc_html($log->email); ?></td>
            <td><?php echo esc_html($log->message); ?></td>
            <td><?php echo esc_html($log->ip_address); ?></td>
            <td><?php echo esc_html($log->created_at); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5">No registration logs found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
