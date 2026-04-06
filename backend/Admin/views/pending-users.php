<?php
/**
 * Admin Page: Pending Users
 */

use KBS\Admin\AdminPage;
use KBS\Admin\PendingUserController;

if (!defined('ABSPATH')) exit;

$page = AdminPage::request_int('paged', 1, 1);
$per_page = AdminPage::request_int('per_page', 20, 1, 50);
$email = AdminPage::request_email('email');
$company = AdminPage::request_text('company');

$request = new \WP_REST_Request('GET');
$request->set_param('page', $page);
$request->set_param('per_page', $per_page);
if ($email !== '') {
	$request->set_param('email', $email);
}
if ($company !== '') {
	$request->set_param('company', $company);
}

$response = PendingUserController::get_all_pending($request);
$users = is_array($response->get_data()['users'] ?? null) ? $response->get_data()['users'] : [];
$summary = is_array($response->get_data()['summary'] ?? null) ? $response->get_data()['summary'] : [];
$headers = $response->get_headers();
$total = (int) ($headers['X-WP-Total'] ?? count($users));
$latest_request = (string) ($summary['latest_request'] ?? '');
$has_filters = $email !== '' || $company !== '';

$company_count = (int) ($summary['company_count'] ?? count(array_values(array_filter(array_unique(array_map(static function ($user): string {
	return AdminPage::value($user, ['company'], '');
}, $users))))));

AdminPage::render_page_start(
	'Pending Users',
	'Review pending company registrations from the same filtered queue used by the admin approval API.',
	[
		[
			'label'  => 'Awaiting review',
			'value'  => number_format_i18n($total),
			'helper' => 'Pending applications',
		],
		[
			'label'  => 'Companies',
			'value'  => number_format_i18n($company_count),
			'helper' => 'Distinct businesses in matching queue',
		],
		[
			'label'  => 'Latest request',
			'value'  => $latest_request ? AdminPage::format_date($latest_request, false) : 'No requests',
			'helper' => $latest_request ? AdminPage::relative_time($latest_request) : 'Nothing waiting right now',
		],
		[
			'label'  => 'Showing',
			'value'  => number_format_i18n(count($users)),
			'helper' => sprintf('%d per page', $per_page),
		],
	]
);
?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Applications</h2>
			<p class="kbs-panel__description">Approve requests to create the account, create or reuse the organization, and assign the new user as company admin. Decline keeps the request out of the active queue.</p>
		</div>
		<?php echo AdminPage::badge($has_filters ? 'Filtered queue' : (!empty($users) ? 'Action required' : 'Queue empty'), $has_filters ? 'info' : (!empty($users) ? 'warning' : 'neutral')); ?>
	</div>

	<?php
	AdminPage::render_filter_form([
		[
			'name' => 'email',
			'label' => 'Applicant email',
			'type' => 'email',
			'value' => $email,
			'placeholder' => 'name@company.com',
		],
		[
			'name' => 'company',
			'label' => 'Company',
			'type' => 'search',
			'value' => $company,
			'placeholder' => 'Filter by company name',
		],
		[
			'name' => 'per_page',
			'label' => 'Rows per page',
			'type' => 'select',
			'value' => (string) $per_page,
			'options' => [
				'10' => '10',
				'20' => '20',
				'50' => '50',
			],
		],
	]);
	?>

	<?php if (empty($users)) : ?>
		<?php AdminPage::render_empty_state($has_filters ? 'No matching pending users' : 'No pending users', $has_filters ? 'No pending applications match the current filters. Reset the filters to review the full queue.' : 'New registrations awaiting approval will appear here.'); ?>
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
		<?php AdminPage::render_pagination($page, $per_page, $total); ?>
	<?php endif; ?>
</section>

<?php AdminPage::render_page_end(); ?>
