<?php

use KBS\Admin\AdminPage;

if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'kbs_system_logs';
$logs  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100");

$actions = array_values(array_filter(array_unique(array_map(static function ($log): string {
	return AdminPage::value($log, ['action', 'event'], '');
}, $logs))));
$latest_log = !empty($logs) ? AdminPage::value($logs[0], ['created_at', 'logged_at'], '') : '';

AdminPage::render_page_start(
	'System Logs',
	'Monitor backend activity in a clean feed that leaves room for richer debugging data later.',
	[
		[
			'label'  => 'Entries shown',
			'value'  => number_format_i18n(count($logs)),
			'helper' => 'Latest 100 records',
		],
		[
			'label'  => 'Action types',
			'value'  => number_format_i18n(count($actions)),
			'helper' => 'Unique actions in this view',
		],
		[
			'label'  => 'Latest event',
			'value'  => $latest_log ? AdminPage::format_date($latest_log, false) : 'No activity',
			'helper' => $latest_log ? AdminPage::relative_time($latest_log) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'Showing the latest 100 records to keep the page usable as log volume increases.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent System Events</h2>
			<p class="kbs-panel__description">Operational events are grouped into expandable cards instead of a narrow table grid.</p>
		</div>
		<?php echo AdminPage::badge('Latest 100', 'info'); ?>
	</div>

	<?php if (empty($logs)) : ?>
		<?php AdminPage::render_empty_state('No system logs', 'System-level activity will appear here once events are recorded.'); ?>
	<?php else : ?>
		<div class="kbs-record-list">
			<?php foreach ($logs as $log) : ?>
				<?php
				$action = AdminPage::value($log, ['action', 'event'], 'System event');
				$details = AdminPage::value($log, ['details'], 'No extra details recorded');
				$user_id = AdminPage::value($log, ['user_id'], 'Guest / System');
				$file_name = AdminPage::value($log, ['file_name'], 'Not attached');
				$recorded_at = AdminPage::format_datetime(AdminPage::value($log, ['created_at', 'logged_at'], ''));
				$action_key = strtolower($action);
				$badge_tone = 'info';

				if (strpos($action_key, 'error') !== false || strpos($action_key, 'fail') !== false) {
					$badge_tone = 'danger';
				} elseif (strpos($action_key, 'decline') !== false || strpos($action_key, 'delete') !== false) {
					$badge_tone = 'warning';
				} elseif (strpos($action_key, 'approve') !== false || strpos($action_key, 'create') !== false || strpos($action_key, 'success') !== false) {
					$badge_tone = 'success';
				}
				?>
				<article class="kbs-record">
					<div class="kbs-record__top">
						<div>
							<h3 class="kbs-record__title"><?php echo esc_html($action); ?></h3>
							<p class="kbs-record__subtitle"><?php echo esc_html(is_numeric($user_id) ? 'User ID ' . $user_id : $user_id); ?></p>
						</div>
						<?php echo AdminPage::badge($badge_tone === 'info' ? 'System event' : ucfirst($badge_tone), $badge_tone); ?>
					</div>

					<?php
					AdminPage::render_meta_grid([
						['label' => 'Log ID', 'value' => '#' . AdminPage::value($log, ['id'])],
						['label' => 'User', 'value' => is_numeric($user_id) ? 'User ID ' . $user_id : $user_id],
						['label' => 'File', 'value' => $file_name],
						['label' => 'Recorded', 'value' => $recorded_at],
					]);
					?>

					<div class="kbs-record__details">
						<span class="kbs-record__details-label">Details</span>
						<p class="kbs-record__details-text"><?php echo esc_html($details); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
