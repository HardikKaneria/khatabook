<?php
/**
 * Admin Page: Pending Users
 */

if (!defined('ABSPATH')) exit;

// Load styles
wp_enqueue_style('kbs-admin-style', plugin_dir_url(__FILE__) . '../../assets/css/admin-style.css');

// Get pending users
global $wpdb;
$users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kbs_pending_users WHERE status = 'pending' ORDER BY applied_at DESC");
?>

<div class="kbs-admin-container">
  <h1>Pending Users</h1>

  <table class="kbs-admin-table">
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Company</th><th>Applied At</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($users): ?>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?php echo esc_html($user->id); ?></td>
            <td><?php echo esc_html($user->name); ?></td>
            <td><?php echo esc_html($user->email); ?></td>
            <td><?php echo esc_html($user->company); ?></td>
            <td><?php echo esc_html($user->applied_at); ?></td>
            <td>
              <!-- APPROVE -->
              <form method="post" style="display:inline-block;">
                <?php wp_nonce_field('kbs_pending_user_action', 'kbs_pending_user_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
                <button type="submit" class="button approve-kbs-user" name="kbs_approve_user" value="1">Approve</button>
              </form>

              <!-- DECLINE -->
              <form method="post" style="display:inline-block;">
                <?php wp_nonce_field('kbs_pending_user_action', 'kbs_pending_user_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
                <button type="submit" class="button decline-kbs-user" name="kbs_decline_user" value="1">Decline</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6">No pending users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
