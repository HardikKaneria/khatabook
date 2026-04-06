<?php

namespace KBS\Core;

defined('ABSPATH') || exit;

class SystemLogger
{
    /**
     * Log a system-wide event (non-registration).
     */
    public static function log(string $action, string $details = '', int $user_id = 0, string $file = ''): void
    {
        global $wpdb;

        $user_id = $user_id > 0 ? $user_id : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        $payload = [
            'user_id'    => $user_id > 0 ? $user_id : null,
            'action'     => sanitize_key($action),
            'details'    => self::normalize_details($details),
            'file_name'  => $file !== '' ? sanitize_text_field($file) : null,
            'created_at' => current_time('mysql'),
        ];

        try {
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'kbs_system_logs',
                $payload,
                ['%d', '%s', '%s', '%s', '%s']
            );

            if ($inserted === false) {
                self::fallback_log((string) $payload['action'], (string) ($payload['details'] ?? ''), (string) ($payload['file_name'] ?? ''));
            }
        } catch (\Throwable $throwable) {
            self::fallback_log((string) $payload['action'], (string) ($payload['details'] ?? ''), (string) ($payload['file_name'] ?? ''), $throwable->getMessage());
        }
    }

    public static function log_event(string $action, string $message, array $context = [], int $user_id = 0, string $file = ''): void
    {
        $details = sanitize_text_field($message);
        $normalized_context = self::normalize_context($context);
        if ($normalized_context) {
            $details .= ' | Context: ' . wp_json_encode($normalized_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        self::log($action, $details, $user_id, $file);
    }

    /**
     * Log a registration-specific event.
     */
    public static function log_registration(string $email, string $event): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'kbs_registration_logs',
            [
                'email'     => $email,
                'event'     => $event,
                'logged_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );
    }

    private static function normalize_details(string $details): string
    {
        $details = trim(strip_tags($details));
        if ($details === '') {
            return '';
        }

        return substr($details, 0, 4000);
    }

    private static function normalize_context(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$clean_key] = is_string($value)
                    ? substr(trim(strip_tags($value)), 0, 200)
                    : $value;
                continue;
            }

            if (is_array($value)) {
                $normalized[$clean_key] = self::normalize_context($value);
            }
        }

        return $normalized;
    }

    private static function fallback_log(string $action, string $details, string $file = '', string $extra = ''): void
    {
        $parts = ['[Vyavhar System]', $action];
        if ($file !== '') {
            $parts[] = $file;
        }
        if ($details !== '') {
            $parts[] = $details;
        }
        if ($extra !== '') {
            $parts[] = 'Fallback: ' . $extra;
        }

        error_log(implode(' ', $parts));
    }
}
