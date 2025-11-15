<?php

namespace KBS\Admin;

class AdminMenu {
	public static function init() {
		add_action('admin_menu', [self::class, 'register_menu']);
		add_action('admin_enqueue_scripts', [self::class, 'enqueue_custom_styles']);
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
		// Only load for kbs-admin pages
		if (strpos($hook, 'kbs-admin') !== false) {
			wp_enqueue_style(
				'kbs-admin-style',
				KHATABOOK_PLUGIN_FILE . '/assets/css/admin-style.css',
				[],
				'1.0'
			);
		}
	}

	public static function render_pending_users() {
		include __DIR__ . '/views/pending-users.php';
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
