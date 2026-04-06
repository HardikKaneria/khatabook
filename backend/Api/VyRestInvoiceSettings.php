<?php

namespace KBS\Api;

use KBS\Media\ManagedImageUpload;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

class VyRestInvoiceSettings
{
    private const MAX_LOGO_BYTES = 2097152;
    private const ALLOWED_LOGO_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/invoice-settings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_settings'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoice-settings', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'save_settings'],
            'permission_callback' => [__CLASS__, 'can_manage_settings'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoice-settings/logo', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'upload_logo'],
            'permission_callback' => [__CLASS__, 'can_manage_settings'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoice-settings/logo', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_logo'],
            'permission_callback' => [__CLASS__, 'can_manage_settings'],
        ]);
    }

    public static function get_settings(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $settings = self::get_or_create_settings((int) $org);

        return new WP_REST_Response([
            'settings'  => self::format_settings($settings),
            'templates' => array_values(vy_get_invoice_templates_registry()),
        ], 200);
    }

    public static function save_settings(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $body      = $request->get_json_params() ?: [];
        $registry  = vy_get_invoice_templates_registry();
        $current   = self::get_or_create_settings((int) $org);
        $templateId = sanitize_text_field($body['default_template_id'] ?? $current['default_template_id']);
        if (!isset($registry[$templateId])) {
            return new WP_Error('vy_invalid_template', 'Invalid template selected.', ['status' => 400]);
        }

        $updates = [
            'default_template_id' => $templateId,
            'logo_url'            => self::sanitize_nullable_url($body, 'logo_url', $current['logo_url']),
            'primary_color'       => self::sanitize_nullable_text($body, 'primary_color', $current['primary_color']),
            'accent_color'        => self::sanitize_nullable_text($body, 'accent_color', $current['accent_color']),
            'font_family'         => self::sanitize_nullable_text($body, 'font_family', $current['font_family']),
            'footer_text'         => self::sanitize_nullable_block($body, 'footer_text', $current['footer_text']),
            'terms_and_conditions'=> self::sanitize_nullable_block($body, 'terms_and_conditions', $current['terms_and_conditions']),
            'bank_details'        => self::sanitize_nullable_block($body, 'bank_details', $current['bank_details']),
            'show_tax_breakup'    => self::sanitize_bool($body, 'show_tax_breakup', (int) $current['show_tax_breakup']),
            'show_qr_code'        => self::sanitize_bool($body, 'show_qr_code', (int) $current['show_qr_code']),
            'auto_email_on_create'=> self::sanitize_bool($body, 'auto_email_on_create', (int) $current['auto_email_on_create']),
            'email_subject_template' => self::sanitize_nullable_text($body, 'email_subject_template', $current['email_subject_template']),
            'email_body_template'    => self::sanitize_nullable_block($body, 'email_body_template', $current['email_body_template']),
            'updated_at'             => current_time('mysql', true),
        ];

        global $wpdb;
        $table = self::get_table();
        $result = $wpdb->update(
            $table,
            $updates,
            ['org_id' => (int) $org],
            ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s','%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('vy_settings_save_failed', 'Failed to save invoice settings.', ['status' => 500]);
        }

        $settings = self::get_or_create_settings((int) $org);

        return new WP_REST_Response([
            'settings'  => self::format_settings($settings),
            'templates' => array_values($registry),
        ], 200);
    }

    public static function upload_logo(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $files = $request->get_file_params();
        $logoFile = $files['logo'] ?? null;
        $upload = ManagedImageUpload::upload((array) $logoFile, [
            'max_bytes'     => self::MAX_LOGO_BYTES,
            'allowed_mimes' => self::ALLOWED_LOGO_MIMES,
            'meta'          => [
                '_vy_invoice_logo_org_id' => (int) $org,
                '_vy_invoice_logo_managed' => 1,
            ],
        ]);

        if (is_wp_error($upload)) {
            return $upload;
        }

        $current = self::get_or_create_settings((int) $org);
        $newLogoUrl = esc_url_raw((string) ($upload['url'] ?? ''));

        global $wpdb;
        $result = $wpdb->update(
            self::get_table(),
            [
                'logo_url'   => $newLogoUrl,
                'updated_at' => current_time('mysql', true),
            ],
            ['org_id' => (int) $org],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            ManagedImageUpload::deleteByUrl($newLogoUrl, [
                'org_id'           => (int) $org,
                'org_meta_key'     => '_vy_invoice_logo_org_id',
                'managed_meta_key' => '_vy_invoice_logo_managed',
            ]);
            return new WP_Error('vy_logo_settings_failed', 'Logo was uploaded but invoice settings could not be updated.', ['status' => 500]);
        }

        $previousLogoUrl = (string) ($current['logo_url'] ?? '');
        if ($previousLogoUrl !== '' && $previousLogoUrl !== $newLogoUrl) {
            self::maybe_delete_managed_logo($previousLogoUrl, (int) $org);
        }

        $settings = self::get_or_create_settings((int) $org);

        return new WP_REST_Response([
            'logo_url'  => $settings['logo_url'],
            'settings'  => self::format_settings($settings),
            'templates' => array_values(vy_get_invoice_templates_registry()),
        ], 200);
    }

    public static function delete_logo(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $current = self::get_or_create_settings((int) $org);
        $currentLogoUrl = (string) ($current['logo_url'] ?? '');

        global $wpdb;
        $result = $wpdb->update(
            self::get_table(),
            [
                'logo_url'   => null,
                'updated_at' => current_time('mysql', true),
            ],
            ['org_id' => (int) $org],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('vy_logo_remove_failed', 'Unable to remove the current invoice logo.', ['status' => 500]);
        }

        self::maybe_delete_managed_logo($currentLogoUrl, (int) $org);
        $settings = self::get_or_create_settings((int) $org);

        return new WP_REST_Response([
            'logo_url'  => null,
            'settings'  => self::format_settings($settings),
            'templates' => array_values(vy_get_invoice_templates_registry()),
        ], 200);
    }

    private static function get_or_create_settings(int $org_id): array
    {
        $existing = self::get_settings_row($org_id);
        if ($existing) {
            return $existing;
        }

        global $wpdb;
        $table    = self::get_table();
        $defaults = vy_get_invoice_template_default_settings();
        $defaults['org_id']    = $org_id;
        $defaults['created_at']= current_time('mysql', true);
        $defaults['updated_at']= current_time('mysql', true);

        $wpdb->insert($table, $defaults);
        return self::get_settings_row($org_id);
    }

    private static function get_settings_row(int $org_id): ?array
    {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d LIMIT 1",
            $org_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function get_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vy_invoice_template_settings';
    }

    private static function format_settings(array $row): array
    {
        return [
            'default_template_id'  => $row['default_template_id'],
            'logo_url'             => $row['logo_url'],
            'primary_color'        => $row['primary_color'],
            'accent_color'         => $row['accent_color'],
            'font_family'          => $row['font_family'],
            'footer_text'          => $row['footer_text'],
            'terms_and_conditions' => $row['terms_and_conditions'],
            'bank_details'         => $row['bank_details'],
            'show_tax_breakup'     => (int) $row['show_tax_breakup'],
            'show_qr_code'         => (int) $row['show_qr_code'],
            'auto_email_on_create' => (int) $row['auto_email_on_create'],
            'email_subject_template' => $row['email_subject_template'],
            'email_body_template'    => $row['email_body_template'],
        ];
    }

    public static function can_manage_settings(WP_REST_Request $request): bool|WP_Error
    {
        $auth = VyRestAccounts::require_auth($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $userId = get_current_user_id();
        $orgId = \vy_get_current_org_id();
        if (is_wp_error($orgId)) {
            return $orgId;
        }

        $role = \KBS\Helpers\OrgHelper::user_role_for_org((int) $userId, (int) $orgId);
        if (in_array($role, ['administrator', 'company_admin', 'c_manager'], true)) {
            return true;
        }

        return new WP_Error('vy_settings_forbidden', 'You do not have permission to manage invoice settings.', ['status' => 403]);
    }

    private static function sanitize_nullable_url(array $body, string $key, ?string $fallback): ?string
    {
        if (!array_key_exists($key, $body)) {
            return $fallback;
        }
        $value = trim((string) $body[$key]);
        if ($value === '') {
            return null;
        }

        $sanitized = esc_url_raw($value);
        if ($sanitized === '') {
            return $fallback;
        }

        if ($fallback && $sanitized === $fallback) {
            return $sanitized;
        }

        $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $valueHost = wp_parse_url($sanitized, PHP_URL_HOST);
        if (is_string($siteHost) && is_string($valueHost) && strtolower($siteHost) === strtolower($valueHost)) {
            return $sanitized;
        }

        return $fallback;
    }

    private static function sanitize_nullable_text(array $body, string $key, ?string $fallback): ?string
    {
        if (!array_key_exists($key, $body)) {
            return $fallback;
        }
        $value = trim((string) $body[$key]);
        return $value === '' ? null : sanitize_text_field($value);
    }

    private static function sanitize_nullable_block(array $body, string $key, ?string $fallback): ?string
    {
        if (!array_key_exists($key, $body)) {
            return $fallback;
        }
        $value = trim((string) $body[$key]);
        return $value === '' ? null : wp_kses_post($value);
    }

    private static function sanitize_bool(array $body, string $key, int $fallback): int
    {
        if (!array_key_exists($key, $body)) {
            return $fallback;
        }
        return (int) ((!empty($body[$key]) && $body[$key] !== '0'));
    }

    private static function maybe_delete_managed_logo(?string $logoUrl, int $orgId): void
    {
        ManagedImageUpload::deleteByUrl((string) $logoUrl, [
            'org_id'           => $orgId,
            'org_meta_key'     => '_vy_invoice_logo_org_id',
            'managed_meta_key' => '_vy_invoice_logo_managed',
        ]);
    }
}
