<?php
/**
 * Admin Page: Registration Logs
 */

use KBS\Admin\AdminPage;
use KBS\Api\AdminData;

if (!defined('ABSPATH')) exit;

$page = AdminPage::request_int('paged', 1, 1);
$per_page = AdminPage::request_int('per_page', 25, 1, 100);
$email = AdminPage::request_email('email');

$request = new \WP_REST_Request('GET');
$request->set_param('page', $page);
$request->set_param('per_page', $per_page);
if ($email !== '') {
	$request->set_param('email', $email);
}

$response = AdminData::get_registration_logs($request);
$logs = $response->get_data();
$headers = $response->get_headers();
$total = (int) ($headers['X-WP-Total'] ?? count($logs));
$has_filters = $email !== '';

$emails = array_values(array_filter(array_unique(array_map(static function ($log): string {
	return AdminPage::value($log, ['email'], '');
}, $logs))));
$latest_log = !empty($logs) ? AdminPage::value($logs[0], ['logged_at', 'created_at'], '') : '';

AdminPage::render_page_start(
	'Registration Logs',
	'Track signup activity in a readable feed layout that is easier to extend with tags, filters, and notes.',
	[
		[
			'label'  => 'Entries matched',
			'value'  => number_format_i18n($total),
			'helper' => 'Current filtered result size',
		],
		[
			'label'  => 'Emails shown',
			'value'  => number_format_i18n(count($emails)),
			'helper' => 'Current page only',
		],
		[
			'label'  => 'Latest entry',
			'value'  => $latest_log ? AdminPage::format_date($latest_log, false) : 'No activity',
			'helper' => $latest_log ? AdminPage::relative_time($latest_log) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'This page now follows the same paginated admin dataset as the REST support endpoints.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent Registration Activity</h2>
			<p class="kbs-panel__description">Filter by exact email when support needs to trace a specific registration path.</p>
		</div>
		<?php echo AdminPage::badge($has_filters ? 'Filtered results' : 'Operational log', 'info'); ?>
	</div>

	<?php
	AdminPage::render_filter_form([
		[
			'name' => 'email',
			'label' => 'Email',
			'type' => 'email',
			'value' => $email,
			'placeholder' => 'name@company.com',
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
		<?php AdminPage::render_empty_state($has_filters ? 'No matching registration logs' : 'No registration logs', $has_filters ? 'No registration entries match the current filters. Reset the filters to inspect the wider log.' : 'Registration events will appear here once users start signing up.'); ?>
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
		<?php AdminPage::render_pagination($page, $per_page, $total); ?>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
