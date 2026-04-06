<?php

use KBS\Admin\AdminPage;
use KBS\Api\AdminData;

if (!defined('ABSPATH')) exit;

$page = AdminPage::request_int('paged', 1, 1);
$per_page = AdminPage::request_int('per_page', 25, 1, 100);
$action_filter = AdminPage::request_text('action');
$user_id = AdminPage::request_int('user_id', 0, 0);

$request = new \WP_REST_Request('GET');
$request->set_param('page', $page);
$request->set_param('per_page', $per_page);
if ($action_filter !== '') {
	$request->set_param('action', $action_filter);
}
if ($user_id > 0) {
	$request->set_param('user_id', $user_id);
}

$response = AdminData::get_system_logs($request);
$logs = $response->get_data();
$headers = $response->get_headers();
$total = (int) ($headers['X-WP-Total'] ?? count($logs));
$has_filters = $action_filter !== '' || $user_id > 0;

$actions = array_values(array_filter(array_unique(array_map(static function ($log): string {
	return AdminPage::value($log, ['action', 'event'], '');
}, $logs))));
$latest_log = !empty($logs) ? AdminPage::value($logs[0], ['created_at', 'logged_at'], '') : '';

AdminPage::render_page_start(
	'System Logs',
	'Monitor backend activity in a clean feed that leaves room for richer debugging data later.',
	[
		[
			'label'  => 'Entries matched',
			'value'  => number_format_i18n($total),
			'helper' => 'Current filtered result size',
		],
		[
			'label'  => 'Actions shown',
			'value'  => number_format_i18n(count($actions)),
			'helper' => 'Current page only',
		],
		[
			'label'  => 'Latest event',
			'value'  => $latest_log ? AdminPage::format_date($latest_log, false) : 'No activity',
			'helper' => $latest_log ? AdminPage::relative_time($latest_log) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'Use exact action keys and user IDs when narrowing operational incidents.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent System Events</h2>
			<p class="kbs-panel__description">Operational events stay paginated and filterable without exposing raw full-table dumps in wp-admin.</p>
		</div>
		<?php echo AdminPage::badge($has_filters ? 'Filtered results' : 'Operational log', 'info'); ?>
	</div>

	<?php
	AdminPage::render_filter_form([
		[
			'name' => 'action',
			'label' => 'Action key',
			'type' => 'search',
			'value' => $action_filter,
			'placeholder' => 'Exact action name',
		],
		[
			'name' => 'user_id',
			'label' => 'User ID',
			'type' => 'number',
			'value' => $user_id > 0 ? (string) $user_id : '',
			'placeholder' => 'Optional user ID',
		],
		[
			'name' => 'per_page',
			'label' => 'Rows per page',
			'type' => 'select',
			'value' => (string) $per_page,
			'options' => [
				'25' => '25',
				'50' => '50',
				'100' => '100',
			],
		],
	]);
	?>

	<?php if (empty($logs)) : ?>
		<?php AdminPage::render_empty_state($has_filters ? 'No matching system logs' : 'No system logs', $has_filters ? 'No system events match the current filters. Reset the filters to inspect the wider operational log.' : 'System-level activity will appear here once events are recorded.'); ?>
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
		<?php AdminPage::render_pagination($page, $per_page, $total); ?>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
