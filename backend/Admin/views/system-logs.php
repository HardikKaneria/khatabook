<?php

use KBS\Core\SystemLogger;

global $wpdb;
$table = $wpdb->prefix . 'kbs_system_logs';
$logs  = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
?>

<div class="kbs-admin-wrapper">
  <h1>System Logs</h1>

  <table class="kbs-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Event</th>
        <th>Timestamp</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($logs)) : ?>
        <?php foreach ($logs as $log) : ?>
          <tr>
            <td><?php echo esc_html($log->id); ?></td>
            <td><?php echo esc_html($log->email); ?></td>
            <td><?php echo esc_html($log->event); ?></td>
            <td><?php echo esc_html($log->created_at); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="4">No logs found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
