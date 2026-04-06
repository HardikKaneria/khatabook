<?php

use KBS\Admin\AdminPage;
use KBS\Api\AdminData;

if (!defined('ABSPATH')) exit;

$page = AdminPage::request_int('paged', 1, 1);
$per_page = AdminPage::request_int('per_page', 25, 1, 100);
$email = AdminPage::request_email('email');
$status_filter = AdminPage::request_key('status');
$context_filter = AdminPage::request_key('context');

$request = new \WP_REST_Request('GET');
$request->set_param('page', $page);
$request->set_param('per_page', $per_page);
if ($email !== '') {
	$request->set_param('email', $email);
}
if ($status_filter !== '') {
	$request->set_param('status', $status_filter);
}
if ($context_filter !== '') {
	$request->set_param('context', $context_filter);
}

$response = AdminData::get_otp_attempts($request);
$attempts = $response->get_data();
$headers = $response->get_headers();
$total = (int) ($headers['X-WP-Total'] ?? count($attempts));
$has_filters = $email !== '' || $status_filter !== '' || $context_filter !== '';

$status_counts = [
	'sent' => 0,
	'verified' => 0,
	'failed' => 0,
];

foreach ($attempts as $attempt) {
	$status = strtolower(AdminPage::value($attempt, ['status'], ''));
	if (isset($status_counts[$status])) {
		$status_counts[$status]++;
	}
}

$latest_attempt = !empty($attempts) ? AdminPage::value($attempts[0], ['created_at', 'logged_at'], '') : '';

AdminPage::render_page_start(
	'OTP Attempts',
	'Keep authentication activity readable with stacked cards that can absorb more metadata as security workflows expand.',
	[
		[
			'label'  => 'Entries matched',
			'value'  => number_format_i18n($total),
			'helper' => 'Current filtered result size',
		],
		[
			'label'  => 'Verified',
			'value'  => number_format_i18n($status_counts['verified']),
			'helper' => 'Current page only',
		],
		[
			'label'  => 'Failed',
			'value'  => number_format_i18n($status_counts['failed']),
			'helper' => 'Current page only',
		],
		[
			'label'  => 'Latest attempt',
			'value'  => $latest_attempt ? AdminPage::format_date($latest_attempt, false) : 'No activity',
			'helper' => $latest_attempt ? AdminPage::relative_time($latest_attempt) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'OTP codes remain redacted in admin, even when support filters down to a single user or flow.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent Authentication Attempts</h2>
			<p class="kbs-panel__description">Filter by exact email, OTP status, or auth context while keeping the sensitive code value redacted.</p>
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
			'name' => 'status',
			'label' => 'Status',
			'type' => 'select',
			'value' => $status_filter,
			'options' => [
				'' => 'All statuses',
				'sent' => 'Sent',
				'verified' => 'Verified',
				'failed' => 'Failed',
			],
		],
		[
			'name' => 'context',
			'label' => 'Context',
			'type' => 'select',
			'value' => $context_filter,
			'options' => [
				'' => 'All contexts',
				'login' => 'Login',
				'register' => 'Registration',
			],
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

	<?php if (empty($attempts)) : ?>
		<?php AdminPage::render_empty_state($has_filters ? 'No matching OTP attempts' : 'No OTP attempts', $has_filters ? 'No OTP attempts match the current filters. Reset the filters to inspect broader auth activity.' : 'OTP send and verify events will appear here once authentication requests start coming in.'); ?>
	<?php else : ?>
		<div class="kbs-record-list">
			<?php foreach ($attempts as $row) : ?>
				<?php
				$email = AdminPage::value($row, ['email'], 'Unknown email');
				$status = strtolower(AdminPage::value($row, ['status'], 'unknown'));
				$context = AdminPage::value($row, ['context'], 'Not set');
				$ip_address = AdminPage::value($row, ['ip', 'ip_address'], 'Not captured');
				$recorded_at = AdminPage::format_datetime(AdminPage::value($row, ['created_at', 'logged_at'], ''));
				$otp_code = AdminPage::value($row, ['otp_code'], 'Redacted');
				$badge_tone = [
					'sent' => 'info',
					'verified' => 'success',
					'failed' => 'danger',
				][$status] ?? 'neutral';

				if ($otp_code === '******') {
					$otp_code = 'Redacted';
				} elseif ($otp_code !== 'Redacted') {
					$otp_code = 'Redacted';
				}
				?>
				<article class="kbs-record">
					<div class="kbs-record__top">
						<div>
							<h3 class="kbs-record__title"><?php echo esc_html($email); ?></h3>
							<p class="kbs-record__subtitle"><?php echo esc_html(ucfirst($context) . ' flow'); ?></p>
						</div>
						<?php echo AdminPage::badge(ucfirst($status), $badge_tone); ?>
					</div>

					<?php
					AdminPage::render_meta_grid([
						['label' => 'Attempt ID', 'value' => '#' . AdminPage::value($row, ['id'])],
						['label' => 'Context', 'value' => ucfirst($context)],
						['label' => 'IP address', 'value' => $ip_address],
						['label' => 'Recorded', 'value' => $recorded_at],
					]);
					?>

					<div class="kbs-record__details">
						<span class="kbs-record__details-label">OTP Storage</span>
						<p class="kbs-record__details-text"><?php echo esc_html($otp_code); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php AdminPage::render_pagination($page, $per_page, $total); ?>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
