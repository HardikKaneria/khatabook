<?php
/**
 * Admin Page: Registration Logs
 */

use KBS\Admin\AdminPage;

if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'kbs_registration_logs';
$logs = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100");

$emails = array_values(array_filter(array_unique(array_map(static function ($log): string {
	return AdminPage::value($log, ['email'], '');
}, $logs))));
$latest_log = !empty($logs) ? AdminPage::value($logs[0], ['logged_at', 'created_at'], '') : '';

AdminPage::render_page_start(
	'Registration Logs',
	'Track signup activity in a readable feed layout that is easier to extend with tags, filters, and notes.',
	[
		[
			'label'  => 'Entries shown',
			'value'  => number_format_i18n(count($logs)),
			'helper' => 'Latest 100 records',
		],
		[
			'label'  => 'Unique emails',
			'value'  => number_format_i18n(count($emails)),
			'helper' => 'Contacts represented in this list',
		],
		[
			'label'  => 'Latest entry',
			'value'  => $latest_log ? AdminPage::format_date($latest_log, false) : 'No activity',
			'helper' => $latest_log ? AdminPage::relative_time($latest_log) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'Showing the latest 100 records to keep the page fast as the log grows.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent Registration Activity</h2>
			<p class="kbs-panel__description">Every event is presented as a flexible record card instead of a fixed table row.</p>
		</div>
		<?php echo AdminPage::badge('Latest 100', 'info'); ?>
	</div>

	<?php if (empty($logs)) : ?>
		<?php AdminPage::render_empty_state('No registration logs', 'Registration events will appear here once users start signing up.'); ?>
	<?php else : ?>
		<div class="kbs-record-list">
			<?php foreach ($logs as $log) : ?>
				<?php
				$email = AdminPage::value($log, ['email'], 'No email recorded');
				$event = AdminPage::value($log, ['event', 'message'], 'No event details recorded');
				$logged_at = AdminPage::format_datetime(AdminPage::value($log, ['logged_at', 'created_at'], ''));
				$ip_address = AdminPage::value($log, ['ip_address', 'ip'], 'Not captured');
				?>
				<article class="kbs-record">
					<div class="kbs-record__top">
						<div>
							<h3 class="kbs-record__title"><?php echo esc_html($email); ?></h3>
							<p class="kbs-record__subtitle">Registration workflow event</p>
						</div>
						<?php echo AdminPage::badge('Registration', 'info'); ?>
					</div>

					<?php
					AdminPage::render_meta_grid([
						['label' => 'Log ID', 'value' => '#' . AdminPage::value($log, ['id'])],
						['label' => 'Recorded', 'value' => $logged_at],
						['label' => 'IP address', 'value' => $ip_address],
					]);
					?>

					<div class="kbs-record__details">
						<span class="kbs-record__details-label">Event</span>
						<p class="kbs-record__details-text"><?php echo esc_html($event); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
