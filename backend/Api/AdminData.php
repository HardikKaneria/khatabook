<?php

namespace KBS\Api;

use WP_REST_Request;
use WP_REST_Response;

class AdminData {
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 100;

    public static function get_registration_logs(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_registration_logs';
        [$page, $per_page, $offset] = self::pagination($request);

        $email = sanitize_email((string) $request->get_param('email'));
        $where = '';
        $params = [];
        if ($email !== '') {
            $where = ' WHERE email = %s';
            $params[] = $email;
        }

        $total = self::count_rows($table, $where, $params);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, event, logged_at
                 FROM {$table}{$where}
                 ORDER BY logged_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                ...array_merge($params, [$per_page, $offset])
            ),
            ARRAY_A
        );

        return self::response($rows ?: [], $total, $page, $per_page);
    }

    public static function get_otp_attempts(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_otp_attempts';
        [$page, $per_page, $offset] = self::pagination($request);

        $where_parts = [];
        $params = [];

        $email = sanitize_email((string) $request->get_param('email'));
        if ($email !== '') {
            $where_parts[] = 'email = %s';
            $params[] = $email;
        }

        $status = sanitize_key((string) $request->get_param('status'));
        if (in_array($status, ['sent', 'verified', 'failed'], true)) {
            $where_parts[] = 'status = %s';
            $params[] = $status;
        }

        $context = sanitize_key((string) $request->get_param('context'));
        if ($context !== '') {
            $where_parts[] = 'context = %s';
            $params[] = $context;
        }

        $where = $where_parts ? ' WHERE ' . implode(' AND ', $where_parts) : '';
        $total = self::count_rows($table, $where, $params);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, otp_code, status, ip, context, created_at
                 FROM {$table}{$where}
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                ...array_merge($params, [$per_page, $offset])
            ),
            ARRAY_A
        );

        $rows = array_map(static function (array $row): array {
            $row['otp_code'] = '******';
            return $row;
        }, $rows ?: []);

        return self::response($rows, $total, $page, $per_page);
    }

    public static function get_system_logs(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_system_logs';
        [$page, $per_page, $offset] = self::pagination($request);

        $where_parts = [];
        $params = [];

        $action = sanitize_text_field((string) $request->get_param('action'));
        if ($action !== '') {
            $where_parts[] = 'action = %s';
            $params[] = $action;
        }

        $user_id = absint($request->get_param('user_id'));
        if ($user_id > 0) {
            $where_parts[] = 'user_id = %d';
            $params[] = $user_id;
        }

        $where = $where_parts ? ' WHERE ' . implode(' AND ', $where_parts) : '';
        $total = self::count_rows($table, $where, $params);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, action, details, file_name, created_at
                 FROM {$table}{$where}
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                ...array_merge($params, [$per_page, $offset])
            ),
            ARRAY_A
        );

        return self::response($rows ?: [], $total, $page, $per_page);
    }

    private static function pagination(WP_REST_Request $request): array
    {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = absint($request->get_param('per_page') ?: self::DEFAULT_PER_PAGE);
        $per_page = max(1, min(self::MAX_PER_PAGE, $per_page));

        return [$page, $per_page, ($page - 1) * $per_page];
    }

    private static function count_rows(string $table, string $where, array $params): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$table}{$where}";
        if ($params) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }

        return (int) $wpdb->get_var($sql);
    }

    private static function response(array $rows, int $total, int $page, int $per_page): WP_REST_Response
    {
        $response = new WP_REST_Response($rows, 200);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) max(1, (int) ceil($total / max(1, $per_page))));
        $response->header('X-KBS-Page', (string) $page);
        $response->header('X-KBS-Per-Page', (string) $per_page);

        return $response;
    }
}
