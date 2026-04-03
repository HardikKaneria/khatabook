<?php

use KBS\Admin\AdminPage;

if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'kbs_otp_attempts';
$attempts = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100");

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
			'label'  => 'Entries shown',
			'value'  => number_format_i18n(count($attempts)),
			'helper' => 'Latest 100 records',
		],
		[
			'label'  => 'Verified',
			'value'  => number_format_i18n($status_counts['verified']),
			'helper' => 'Successful OTP validations',
		],
		[
			'label'  => 'Failed',
			'value'  => number_format_i18n($status_counts['failed']),
			'helper' => 'Attempts that did not verify',
		],
		[
			'label'  => 'Latest attempt',
			'value'  => $latest_attempt ? AdminPage::format_date($latest_attempt, false) : 'No activity',
			'helper' => $latest_attempt ? AdminPage::relative_time($latest_attempt) : 'Nothing logged yet',
		],
	],
	[
		'note' => 'Showing the latest 100 records to keep the page responsive while your auth activity grows.',
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Recent Authentication Attempts</h2>
			<p class="kbs-panel__description">Status, context, and delivery metadata stay readable without table columns.</p>
		</div>
		<?php echo AdminPage::badge('Latest 100', 'info'); ?>
	</div>

	<?php if (empty($attempts)) : ?>
		<?php AdminPage::render_empty_state('No OTP attempts', 'OTP send and verify events will appear here once authentication requests start coming in.'); ?>
	<?php else : ?>
		<div class="kbs-record-list">
			<?php foreach ($attempts as $row) : ?>
				<?php
				$email = AdminPage::value($row, ['email'], 'Unknown email');
				$status = strtolower(AdminPage::value($row, ['status'], 'unknown'));
				$context = AdminPage::value($row, ['context'], 'Not set');
				$ip_address = AdminPage::value($row, ['ip', 'ip_address'], 'Not captured');
				$recorded_at = AdminPage::format_datetime(AdminPage::value($row, ['created_at', 'logged_at'], ''));
				$otp_code = AdminPage::value($row, ['otp_code'], 'Not stored');
				$badge_tone = [
					'sent' => 'info',
					'verified' => 'success',
					'failed' => 'danger',
				][$status] ?? 'neutral';

				if ($otp_code !== 'Not stored' && $otp_code !== '******') {
					$visible = substr($otp_code, -2);
					$otp_code = str_repeat('*', max(strlen($otp_code) - 2, 0)) . $visible;
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
						<span class="kbs-record__details-label">Stored OTP</span>
						<p class="kbs-record__details-text"><?php echo esc_html($otp_code); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
