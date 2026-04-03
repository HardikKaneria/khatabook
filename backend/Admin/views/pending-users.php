<?php
/**
 * Admin Page: Pending Users
 */

use KBS\Admin\AdminPage;

if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'kbs_pending_users';
$users = $wpdb->get_results("SELECT id, name, email, company, status, applied_at FROM {$table} WHERE status = 'pending' ORDER BY applied_at DESC");

$company_names = array_values(array_filter(array_unique(array_map(static function ($user): string {
	return AdminPage::value($user, ['company'], '');
}, $users))));
$latest_request = !empty($users) ? AdminPage::value($users[0], ['applied_at'], '') : '';

AdminPage::render_page_start(
	'Pending Users',
	'Review new company registrations from a simple card layout that can grow with more actions and workflow details later.',
	[
		[
			'label'  => 'Awaiting review',
			'value'  => number_format_i18n(count($users)),
			'helper' => 'Pending applications',
		],
		[
			'label'  => 'Companies',
			'value'  => number_format_i18n(count($company_names)),
			'helper' => 'Distinct businesses in queue',
		],
		[
			'label'  => 'Latest request',
			'value'  => $latest_request ? AdminPage::format_date($latest_request, false) : 'No requests',
			'helper' => $latest_request ? AdminPage::relative_time($latest_request) : 'Nothing waiting right now',
		],
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Applications</h2>
			<p class="kbs-panel__description">Approve or decline each request without relying on rigid WordPress tables.</p>
		</div>
		<?php echo AdminPage::badge(!empty($users) ? 'Action required' : 'Queue empty', !empty($users) ? 'warning' : 'neutral'); ?>
	</div>

	<?php if (empty($users)) : ?>
		<?php AdminPage::render_empty_state('No pending users', 'New registrations awaiting approval will appear here.'); ?>
	<?php else : ?>
		<div class="kbs-record-list">
			<?php foreach ($users as $user) : ?>
				<?php
				$name = AdminPage::value($user, ['name'], 'Unnamed applicant');
				$email = AdminPage::value($user, ['email']);
				$company = AdminPage::value($user, ['company'], 'Not provided');
				$applied_at = AdminPage::format_datetime(AdminPage::value($user, ['applied_at'], ''));
				?>
				<article class="kbs-record">
					<div class="kbs-record__top">
						<div>
							<h3 class="kbs-record__title"><?php echo esc_html($name); ?></h3>
							<p class="kbs-record__subtitle"><?php echo esc_html($email); ?></p>
						</div>
						<?php echo AdminPage::badge('Pending review', 'warning'); ?>
					</div>

					<?php
					AdminPage::render_meta_grid([
						['label' => 'Applicant ID', 'value' => '#' . AdminPage::value($user, ['id'])],
						['label' => 'Company', 'value' => $company],
						['label' => 'Role', 'value' => 'Company admin'],
						['label' => 'Applied', 'value' => $applied_at],
					]);
					?>

					<div class="kbs-record__actions">
						<form method="post" class="kbs-inline-form">
							<?php wp_nonce_field('kbs_pending_user_action', 'kbs_pending_user_nonce'); ?>
							<input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
							<button type="submit" class="button kbs-button kbs-button--approve" name="kbs_approve_user" value="1">Approve User</button>
						</form>

						<form method="post" class="kbs-inline-form">
							<?php wp_nonce_field('kbs_pending_user_action', 'kbs_pending_user_nonce'); ?>
							<input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
							<button type="submit" class="button kbs-button kbs-button--decline" name="kbs_decline_user" value="1">Decline Request</button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
