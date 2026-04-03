<?php
/**
 * Admin Page: SMTP Settings
 */

use KBS\Admin\AdminPage;
use KBS\Admin\SmtpSettingsPage;
use KBS\Email\EmailManager;

if (!defined('ABSPATH')) {
	exit;
}

$values = SmtpSettingsPage::get_form_values();
$diagnostics = EmailManager::get_config_diagnostics();
$isConfigured = !empty($diagnostics['configured']);
$source = (string) ($diagnostics['source'] ?? 'none');
$sourceLabel = (string) ($diagnostics['source_label'] ?? 'Not configured');
$effectiveConfig = is_array($diagnostics['config'] ?? null) ? $diagnostics['config'] : [];
$statusLabel = $isConfigured ? 'SMTP active' : 'Default wp_mail';
$statusTone = $isConfigured ? 'success' : 'warning';
$notice = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
	$notice = !empty($_GET['password_cleared'])
		? 'SMTP settings updated. The saved SMTP password was cleared.'
		: 'SMTP settings updated.';
}

AdminPage::render_page_start(
	'SMTP Settings',
	'Store the email transport settings in WordPress so invoice and invite emails can use SMTP without editing environment variables.',
	[
		[
			'label'  => 'Effective transport',
			'value'  => $statusLabel,
			'helper' => $isConfigured ? 'SMTP is configured for outgoing mail' : 'The site will use default wp_mail transport',
		],
		[
			'label'  => 'Config source',
			'value'  => $sourceLabel,
			'helper' => $source === 'options' ? 'Using the values saved below' : 'Saved values can be overridden by another source',
		],
		[
			'label'  => 'Password',
			'value'  => $values['password_set'] ? 'Stored' : 'Not saved',
			'helper' => $values['password_set'] ? 'Leave password blank to keep it unchanged' : 'Save a password before SMTP can authenticate',
		],
	],
	[
		'note' => 'Environment variables or constants still take priority when they exist. If you want this screen to control mail delivery, do not define SMTP values elsewhere.',
	]
);
?>

<?php if ($notice !== '') : ?>
	<div class="kbs-notice kbs-notice--success">
		<?php echo esc_html($notice); ?>
	</div>
<?php endif; ?>

<?php if ($source !== 'options' && $source !== 'none') : ?>
	<div class="kbs-notice kbs-notice--warning">
		<?php echo esc_html(sprintf('SMTP is currently being resolved from %s. The admin values below will be saved, but they will not take effect until that external source is removed.', $sourceLabel)); ?>
	</div>
<?php endif; ?>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Connection</h2>
			<p class="kbs-panel__description">These values are saved in WordPress options and loaded by the existing mailer automatically.</p>
		</div>
		<?php echo AdminPage::badge($statusLabel, $statusTone); ?>
	</div>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kbs-settings-form">
		<?php wp_nonce_field('kbs_save_smtp_settings', 'kbs_smtp_nonce'); ?>
		<input type="hidden" name="action" value="kbs_save_smtp_settings">

		<div class="kbs-form-grid">
			<div class="kbs-field">
				<label for="kbs-smtp-host">SMTP Host</label>
				<input id="kbs-smtp-host" class="kbs-input" type="text" name="kbs_smtp_host" value="<?php echo esc_attr($values['host']); ?>" placeholder="smtp-relay.example.com">
				<p class="kbs-field__hint">Required for SMTP delivery.</p>
			</div>

			<div class="kbs-field">
				<label for="kbs-smtp-port">Port</label>
				<input id="kbs-smtp-port" class="kbs-input" type="number" min="1" step="1" name="kbs_smtp_port" value="<?php echo esc_attr($values['port']); ?>" placeholder="587">
				<p class="kbs-field__hint">Common values are `587` for TLS and `465` for SSL.</p>
			</div>

			<div class="kbs-field">
				<label for="kbs-smtp-user">Username</label>
				<input id="kbs-smtp-user" class="kbs-input" type="text" name="kbs_smtp_user" value="<?php echo esc_attr($values['user']); ?>" placeholder="smtp-user@example.com">
				<p class="kbs-field__hint">Usually the SMTP login username or email.</p>
			</div>

			<div class="kbs-field">
				<label for="kbs-smtp-secure">Encryption</label>
				<select id="kbs-smtp-secure" class="kbs-select" name="kbs_smtp_secure">
					<option value="tls" <?php selected($values['secure'], 'tls'); ?>>TLS</option>
					<option value="ssl" <?php selected($values['secure'], 'ssl'); ?>>SSL</option>
					<option value="none" <?php selected($values['secure'], 'none'); ?>>None</option>
				</select>
				<p class="kbs-field__hint">Matches the `SMTPSecure` mode used by PHPMailer.</p>
			</div>

			<div class="kbs-field">
				<label for="kbs-smtp-from-email">From Email</label>
				<input id="kbs-smtp-from-email" class="kbs-input" type="email" name="kbs_smtp_from_email" value="<?php echo esc_attr($values['from_email']); ?>" placeholder="no-reply@example.com">
				<p class="kbs-field__hint">Optional. If blank, WordPress keeps the default sender address.</p>
			</div>

			<div class="kbs-field">
				<label for="kbs-smtp-from-name">From Name</label>
				<input id="kbs-smtp-from-name" class="kbs-input" type="text" name="kbs_smtp_from_name" value="<?php echo esc_attr($values['from_name']); ?>" placeholder="Vyavhar">
				<p class="kbs-field__hint">Optional sender name shown in inboxes.</p>
			</div>
		</div>

		<div class="kbs-field">
			<label for="kbs-smtp-pass">Password</label>
			<input id="kbs-smtp-pass" class="kbs-input" type="password" name="kbs_smtp_pass" value="" autocomplete="new-password" placeholder="<?php echo $values['password_set'] ? esc_attr__('Saved password is hidden', 'khatabook') : esc_attr__('Enter SMTP password', 'khatabook'); ?>">
			<p class="kbs-field__hint">Leave this blank to keep the current saved password. Enter a new value only when you want to replace it.</p>

			<label class="kbs-checkbox" for="kbs-smtp-clear-pass">
				<input id="kbs-smtp-clear-pass" type="checkbox" name="kbs_smtp_clear_pass" value="1">
				<span>Clear the saved SMTP password</span>
			</label>
		</div>

		<div class="kbs-form-actions">
			<button type="submit" class="button kbs-button kbs-button--approve">Save SMTP Settings</button>
		</div>
	</form>
</section>

<section class="kbs-panel">
	<div class="kbs-panel__header">
		<div>
			<h2 class="kbs-panel__title">Effective Mailer Snapshot</h2>
			<p class="kbs-panel__description">This reflects the transport the mailer will use right now, after applying source precedence.</p>
		</div>
	</div>

	<?php
	AdminPage::render_meta_grid([
		['label' => 'Source', 'value' => $sourceLabel],
		['label' => 'Host', 'value' => $effectiveConfig['host'] ?? '—'],
		['label' => 'Port', 'value' => isset($effectiveConfig['port']) ? (string) $effectiveConfig['port'] : '—'],
		['label' => 'Username', 'value' => $effectiveConfig['user'] ?? '—'],
		['label' => 'Encryption', 'value' => strtoupper($effectiveConfig['secure'] ?? '—')],
		['label' => 'From Email', 'value' => $effectiveConfig['from_email'] ?? '—'],
		['label' => 'From Name', 'value' => $effectiveConfig['from_name'] ?? '—'],
	]);
	?>
</section>

<?php AdminPage::render_page_end(); ?>
