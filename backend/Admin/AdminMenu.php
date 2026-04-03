<?php

namespace KBS\Admin;

class AdminMenu {
	private const PAGE_SLUGS = [
		'kbs-admin',
		'kbs-smtp-settings',
		'kbs-registration-logs',
		'kbs-system-logs',
		'kbs-otp-attempts',
	];

	public static function init() {
		add_action('admin_menu', [self::class, 'register_menu']);
		add_action('admin_enqueue_scripts', [self::class, 'enqueue_custom_styles']);
		add_action('admin_post_kbs_save_smtp_settings', [SmtpSettingsPage::class, 'handle_save']);
	}

	public static function get_page_slugs(): array {
		return self::PAGE_SLUGS;
	}

	public static function register_menu() {
		add_menu_page(
			'Vyavhar Admin',
			'Vyavhar Admin',
			'manage_options',
			'kbs-admin',
			[self::class, 'render_pending_users'],
			'dashicons-admin-users',
			26
		);

		add_submenu_page(
			'kbs-admin',
			'Pending Users',
			'Pending Users',
			'manage_options',
			'kbs-admin',
			[self::class, 'render_pending_users']
		);

		add_submenu_page(
			'kbs-admin',
			'SMTP Settings',
			'SMTP Settings',
			'manage_options',
			'kbs-smtp-settings',
			[self::class, 'render_smtp_settings']
		);

		add_submenu_page(
			'kbs-admin',
			'Registration Logs',
			'Registration Logs',
			'manage_options',
			'kbs-registration-logs',
			[self::class, 'render_registration_logs']
		);

		add_submenu_page(
			'kbs-admin',
			'System Logs',
			'System Logs',
			'manage_options',
			'kbs-system-logs',
			[self::class, 'render_system_logs']
		);

		add_submenu_page(
			'kbs-admin',
			'OTP Attempts',
			'OTP Attempts',
			'manage_options',
			'kbs-otp-attempts',
			[self::class, 'render_otp_attempts']
		);
	}

	public static function enqueue_custom_styles($hook) {
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if (!in_array($page, self::get_page_slugs(), true)) {
			return;
		}

		$style_path = plugin_dir_path(KHATABOOK_PLUGIN_FILE) . 'assets/css/admin-style.css';

		wp_enqueue_style(
			'kbs-admin-style',
			plugin_dir_url(KHATABOOK_PLUGIN_FILE) . 'assets/css/admin-style.css',
			[],
			file_exists($style_path) ? (string) filemtime($style_path) : '1.0'
		);
	}

	public static function render_pending_users() {
		include __DIR__ . '/views/pending-users.php';
	}

	public static function render_smtp_settings() {
		include __DIR__ . '/views/smtp-settings.php';
	}

	public static function render_registration_logs() {
		include __DIR__ . '/views/registration-logs.php';
	}

	public static function render_system_logs() {
		include __DIR__ . '/views/system-logs.php';
	}

	public static function render_otp_attempts() {
		include __DIR__ . '/views/otp-attempts.php';
	}
}
