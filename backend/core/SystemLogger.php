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

        $wpdb->insert(
            $wpdb->prefix . 'kbs_system_logs',
            [
                'user_id'    => $user_id,
                'action'     => $action,
                'details'    => $details,
                'file_name'  => $file,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
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
}
