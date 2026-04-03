<?php

namespace KBS\Admin;

defined('ABSPATH') || exit;

class SmtpSettingsPage
{
    private const OPTION_KEYS = [
        'host'       => 'kbs_smtp_host',
        'port'       => 'kbs_smtp_port',
        'user'       => 'kbs_smtp_user',
        'pass'       => 'kbs_smtp_pass',
        'from_email' => 'kbs_smtp_from_email',
        'from_name'  => 'kbs_smtp_from_name',
        'secure'     => 'kbs_smtp_secure',
    ];

    public static function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage SMTP settings.', 'khatabook'), 403);
        }

        check_admin_referer('kbs_save_smtp_settings', 'kbs_smtp_nonce');

        $redirect = add_query_arg(
            [
                'page' => 'kbs-smtp-settings',
            ],
            admin_url('admin.php')
        );

        $posted = wp_unslash($_POST);

        self::persist_option('host', isset($posted['kbs_smtp_host']) ? sanitize_text_field((string) $posted['kbs_smtp_host']) : '');
        self::persist_option('port', isset($posted['kbs_smtp_port']) ? max(0, absint($posted['kbs_smtp_port'])) : 0);
        self::persist_option('user', isset($posted['kbs_smtp_user']) ? sanitize_text_field((string) $posted['kbs_smtp_user']) : '');
        self::persist_option('from_email', isset($posted['kbs_smtp_from_email']) ? sanitize_email((string) $posted['kbs_smtp_from_email']) : '');
        self::persist_option('from_name', isset($posted['kbs_smtp_from_name']) ? sanitize_text_field((string) $posted['kbs_smtp_from_name']) : '');
        self::persist_option('secure', self::sanitize_secure_mode($posted['kbs_smtp_secure'] ?? 'tls'));

        $clearPassword = !empty($posted['kbs_smtp_clear_pass']);
        $passwordInput = isset($posted['kbs_smtp_pass']) ? (string) $posted['kbs_smtp_pass'] : '';

        if ($clearPassword) {
            delete_option(self::OPTION_KEYS['pass']);
        } elseif ($passwordInput !== '') {
            update_option(self::OPTION_KEYS['pass'], $passwordInput, false);
        }

        $redirect = add_query_arg(
            [
                'updated'          => '1',
                'password_cleared' => $clearPassword ? '1' : '0',
            ],
            $redirect
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public static function get_form_values(): array
    {
        return [
            'host'         => (string) get_option(self::OPTION_KEYS['host'], ''),
            'port'         => (string) get_option(self::OPTION_KEYS['port'], ''),
            'user'         => (string) get_option(self::OPTION_KEYS['user'], ''),
            'from_email'   => (string) get_option(self::OPTION_KEYS['from_email'], ''),
            'from_name'    => (string) get_option(self::OPTION_KEYS['from_name'], ''),
            'secure'       => self::sanitize_secure_mode((string) get_option(self::OPTION_KEYS['secure'], 'tls')),
            'password_set' => (string) get_option(self::OPTION_KEYS['pass'], '') !== '',
        ];
    }

    public static function has_saved_password(): bool
    {
        return (string) get_option(self::OPTION_KEYS['pass'], '') !== '';
    }

    private static function persist_option(string $field, $value): void
    {
        $key = self::OPTION_KEYS[$field] ?? null;
        if (!$key) {
            return;
        }

        $isEmpty = $value === '' || $value === null || $value === 0;
        if ($field === 'port' && (int) $value > 0) {
            $isEmpty = false;
        }

        if ($isEmpty) {
            delete_option($key);
            return;
        }

        update_option($key, $value, false);
    }

    private static function sanitize_secure_mode($value): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['ssl', 'smtps'], true)) {
            return 'ssl';
        }
        if (in_array($value, ['none', 'off', 'false', '0'], true)) {
            return 'none';
        }
        return 'tls';
    }
}
