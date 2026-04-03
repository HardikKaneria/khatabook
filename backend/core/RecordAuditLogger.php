<?php

namespace KBS\Core;

defined('ABSPATH') || exit;

class RecordAuditLogger
{
    private const TABLE = 'vy_record_history';

    public static function log(
        int $org_id,
        string $record_type,
        int $record_id,
        string $action,
        string $summary,
        array $details = [],
        ?string $related_record_type = null,
        ?int $related_record_id = null
    ): void {
        if ($org_id <= 0 || $record_id <= 0 || $record_type === '' || $action === '' || $summary === '') {
            return;
        }

        global $wpdb;

        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $actor_label = self::resolve_actor_label($user_id);

        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'org_id'              => $org_id,
                'record_type'         => sanitize_key($record_type),
                'record_id'           => $record_id,
                'related_record_type' => $related_record_type ? sanitize_key($related_record_type) : null,
                'related_record_id'   => $related_record_id ?: null,
                'action'              => sanitize_key($action),
                'summary'             => sanitize_text_field($summary),
                'details_json'        => wp_json_encode(self::normalize_details($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'actor_user_id'       => $user_id > 0 ? $user_id : null,
                'actor_label'         => $actor_label,
                'created_at'          => current_time('mysql', true),
            ],
            ['%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    public static function list_for_record(int $org_id, string $record_type, int $record_id, int $limit = 25): array
    {
        if ($org_id <= 0 || $record_id <= 0 || $record_type === '') {
            return [];
        }

        global $wpdb;
        $limit = min(100, max(1, $limit));
        $table = $wpdb->prefix . self::TABLE;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, record_type, record_id, related_record_type, related_record_id, action, summary, details_json, actor_user_id, actor_label, created_at
             FROM {$table}
             WHERE org_id = %d
               AND (
                    (record_type = %s AND record_id = %d)
                 OR (related_record_type = %s AND related_record_id = %d)
               )
             ORDER BY created_at DESC, id DESC
             LIMIT %d",
            $org_id,
            sanitize_key($record_type),
            $record_id,
            sanitize_key($record_type),
            $record_id,
            $limit
        ), ARRAY_A);

        if (!$rows) {
            return [];
        }

        return array_map(static function (array $row): array {
            $details = json_decode((string) ($row['details_json'] ?? ''), true);
            if (!is_array($details)) {
                $details = [];
            }

            return [
                'id'                  => (int) ($row['id'] ?? 0),
                'record_type'         => (string) ($row['record_type'] ?? ''),
                'record_id'           => (int) ($row['record_id'] ?? 0),
                'related_record_type' => (string) ($row['related_record_type'] ?? ''),
                'related_record_id'   => !empty($row['related_record_id']) ? (int) $row['related_record_id'] : null,
                'action'              => (string) ($row['action'] ?? ''),
                'summary'             => (string) ($row['summary'] ?? ''),
                'details'             => self::details_lines($details),
                'actor_user_id'       => !empty($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
                'actor_label'         => (string) ($row['actor_label'] ?? 'System'),
                'created_at'          => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    public static function money(float $amount, string $currency = 'INR'): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'INR';
        }

        return sprintf('%s %s', $currency, number_format($amount, 2, '.', ','));
    }

    private static function normalize_details(array $details): array
    {
        $lines = [];
        foreach (($details['lines'] ?? []) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return ['lines' => $lines];
    }

    private static function details_lines(array $details): array
    {
        $lines = $details['lines'] ?? [];
        if (!is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($line): string {
            return trim((string) $line);
        }, $lines), static function (string $line): bool {
            return $line !== '';
        }));
    }

    private static function resolve_actor_label(int $user_id): string
    {
        if ($user_id > 0 && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && (int) ($user->ID ?? 0) === $user_id) {
                $label = trim((string) ($user->display_name ?? ''));
                if ($label === '') {
                    $label = trim((string) ($user->user_email ?? ''));
                }
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return $user_id > 0 ? 'User #' . $user_id : 'System';
    }
}
