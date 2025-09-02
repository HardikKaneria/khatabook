<?php

global $wpdb;
$table = $wpdb->prefix . 'kbs_otp_attempts';
$attempts = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
?>

<div class="kbs-admin-wrapper">
  <h1>OTP Attempts</h1>

  <table class="kbs-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>OTP Code</th>
        <th>Status</th>
        <th>Timestamp</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($attempts)) : ?>
        <?php foreach ($attempts as $row) : ?>
          <tr>
            <td><?php echo esc_html($row->id); ?></td>
            <td><?php echo esc_html($row->email); ?></td>
            <td><?php echo esc_html($row->otp_code); ?></td>
            <td><?php echo esc_html(ucfirst($row->status)); ?></td>
            <td><?php echo esc_html($row->created_at); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="5">No OTP attempts found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
