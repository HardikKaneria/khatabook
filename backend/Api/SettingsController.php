<?php

// plugins/khatabook/backend/Api/SettingsController.php
namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController
{
    const NS = 'kbs/v1';

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes()
    {
        
    }

    /** ---------- permissions ---------- */

    public static function can_read(WP_REST_Request $req)
    {
        $org_id = (int) $req->get_param('org_id');
        return self::user_can_access_org(get_current_user_id(), $org_id);
    }

    public static function can_write(WP_REST_Request $req)
    {
        $org_id = (int) $req->get_param('org_id');
        // allow org admins or site admins
        return current_user_can('manage_options') || self::user_is_company_admin(get_current_user_id(), $org_id);
    }

    private static function user_can_access_org($user_id, $org_id)
    {
        global $wpdb;
        if (!$user_id || !$org_id) return false;
        $tbl = $wpdb->prefix . 'kbs_user_org_roles';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE user_id=%d AND org_id=%d",
            $user_id,
            $org_id
        ));
        return $count > 0 || current_user_can('manage_options');
    }

    private static function user_is_company_admin($user_id, $org_id)
    {
        global $wpdb;
        if (!$user_id || !$org_id) return false;
        $tbl = $wpdb->prefix . 'kbs_user_org_roles';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE user_id=%d AND org_id=%d AND role=%s",
            $user_id,
            $org_id,
            'company_admin'
        ));
        return $count > 0 || current_user_can('manage_options');
    }

    /** ---------- GET ---------- */

    public static function get_settings(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_settings';
        $org_id = (int) $req['org_id'];
        $category = $req['category'] ?: null;

        // conditional GET with ETag
        $send_etag = function ($etag) {
            if (!headers_sent()) {
                header('ETag: "' . $etag . '"');
            }
        };

        if ($category) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT settings_json, version, UNIX_TIMESTAMP(updated_at) AS ts FROM {$table} WHERE org_id=%d AND category=%s",
                $org_id,
                $category
            ));
            $settings = $row ? json_decode($row->settings_json, true) : new \stdClass();
            $etag = $row ? sha1($row->version . ':' . $row->settings_json) : sha1('empty');
            $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNone === "\"{$etag}\"") {
                return new WP_REST_Response(null, 304);
            }
            $send_etag($etag);

            return new WP_REST_Response([
                'org_id'   => $org_id,
                'category' => $category,
                'settings' => $settings,
                'version'  => (int)($row->version ?? 0),
                'updated_at' => isset($row->ts) ? (int) $row->ts : null,
            ], 200);
        }

        // all categories
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT category, settings_json, version FROM {$table} WHERE org_id=%d",
            $org_id
        ));
        $payload = [];
        foreach ($rows as $r) {
            $payload[$r->category] = [
                'settings' => json_decode($r->settings_json, true),
                'version'  => (int)$r->version,
            ];
        }
        // simple weak ETag across categories
        $etag = sha1(serialize($payload));
        $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNone === "\"{$etag}\"") {
            return new WP_REST_Response(null, 304);
        }
        $send_etag($etag);

        return new WP_REST_Response([
            'org_id' => $org_id,
            'data'   => $payload,
        ], 200);
    }

    /** ---------- UPSERT (single or batch) ---------- */

    public static function upsert_settings(WP_REST_Request $req)
    {
        $batch = $req->get_param('batch');
        if (is_array($batch)) {
            return self::upsert_batch($req);
        }
        return self::upsert_single($req);
    }

    private static function upsert_single(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_settings';

        $org_id   = (int) $req['org_id'];
        $category = (string) $req['category'];
        $settings = (array) $req->get_param('settings') ?: [];
        $expected = $req->get_param('version'); // optional optimistic concurrency
        $user_id  = get_current_user_id();

        if (!$org_id || !$category) {
            return new WP_Error('bad_request', 'org_id and category are required', ['status' => 400]);
        }

        // (optional) validate settings against a schema per category
        $err = self::validate_against_schema($category, $settings);
        if (is_wp_error($err)) return $err;

        // transaction for optimistic concurrency
        $wpdb->query('START TRANSACTION');

        // lock row
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, version FROM {$table} WHERE org_id=%d AND category=%s FOR UPDATE",
            $org_id,
            $category
        ));

        if ($row) {
            if ($expected !== null && (int)$expected !== (int)$row->version) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('conflict', 'Version mismatch', ['status' => 409, 'current_version' => (int)$row->version]);
            }
            $ok = $wpdb->update(
                $table,
                [
                    'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'version'       => (int)$row->version + 1,
                    'updated_by'    => $user_id,
                    'updated_at'    => current_time('mysql'),
                ],
                ['id' => (int)$row->id],
                ['%s', '%d', '%d', '%s'],
                ['%d']
            );
            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Failed to update settings', ['status' => 500]);
            }
            $new_version = (int)$row->version + 1;
        } else {
            $ok = $wpdb->insert(
                $table,
                [
                    'org_id'        => $org_id,
                    'category'      => $category,
                    'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'version'       => 1,
                    'updated_by'    => $user_id,
                    'updated_at'    => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%d', '%d', '%s']
            );
            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Failed to insert settings', ['status' => 500]);
            }
            $new_version = 1;
        }

        $wpdb->query('COMMIT');

        // (optional) audit log
        if (class_exists('\\KBS\\Core\\SystemLogger')) {
            \KBS\Core\SystemLogger::log('settings_update', sprintf('Updated %s for org %d', $category, $org_id));
        }

        $resp = new WP_REST_Response(['ok' => true, 'version' => $new_version], 200);
        $resp->header('ETag', '"' . sha1($new_version . ':' . wp_json_encode($settings)) . '"');
        return $resp;
    }

    private static function upsert_batch(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_settings';

        $org_id   = (int) $req['org_id'];
        $batch    = (array) $req->get_param('batch');
        $versions = (array) $req->get_param('versions');
        $user_id  = get_current_user_id();

        if (!$org_id) {
            return new WP_Error('bad_request', 'org_id is required', ['status' => 400]);
        }

        $wpdb->query('START TRANSACTION');

        foreach ($batch as $category => $settings) {
            $settings = (array) $settings;

            $err = self::validate_against_schema($category, $settings);
            if (is_wp_error($err)) {
                $wpdb->query('ROLLBACK');
                return $err;
            }

            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, version FROM {$table} WHERE org_id=%d AND category=%s FOR UPDATE",
                $org_id,
                $category
            ));

            $expected = isset($versions[$category]) ? (int)$versions[$category] : null;

            if ($row) {
                if ($expected !== null && (int)$expected !== (int)$row->version) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('conflict', "Version mismatch for {$category}", ['status' => 409, 'category' => $category, 'current_version' => (int)$row->version]);
                }
                $ok = $wpdb->update(
                    $table,
                    [
                        'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
                        'version'       => (int)$row->version + 1,
                        'updated_by'    => $user_id,
                        'updated_at'    => current_time('mysql'),
                    ],
                    ['id' => (int)$row->id],
                    ['%s', '%d', '%d', '%s'],
                    ['%d']
                );
                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('db_error', "Failed updating {$category}", ['status' => 500]);
                }
            } else {
                $ok = $wpdb->insert(
                    $table,
                    [
                        'org_id'        => $org_id,
                        'category'      => (string)$category,
                        'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
                        'version'       => 1,
                        'updated_by'    => $user_id,
                        'updated_at'    => current_time('mysql'),
                    ],
                    ['%d', '%s', '%s', '%d', '%d', '%s']
                );
                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('db_error', "Failed inserting {$category}", ['status' => 500]);
                }
            }
        }

        $wpdb->query('COMMIT');

        return new WP_REST_Response(['ok' => true], 200);
    }

    /** ---------- validation (plug your own schemas here) ---------- */

    private static function validate_against_schema($category, $settings)
    {
        // Example schemas: keep small here; extend via filter for real app
        $schemas = [
            'company' => [
                'type' => 'object',
                'properties' => [
                    'business_name'   => ['type' => 'string'],
                    'display_name'    => ['type' => 'string'],
                    'gst_registered'  => ['type' => 'boolean'],
                    'gstin'           => ['type' => 'string'],
                    'state'           => ['type' => 'string'],
                    'fy_start'        => ['type' => 'string'],
                    'base_currency'   => ['type' => 'string'],
                    'multi_currency'  => ['type' => 'boolean'],
                    'numbering_prefix' => ['type' => 'string'],
                    'numbering_suffix' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ],
            'sales' => [
                'type' => 'object',
                'properties' => [
                    'invoice_prefix' => ['type' => 'string'],
                    'invoice_suffix' => ['type' => 'string'],
                    'padding'        => ['type' => 'integer'],
                    'reset_cycle'    => ['type' => 'string'],
                    'default_terms'  => ['type' => 'string'],
                    'round_off'      => ['type' => 'number'],
                ],
                'additionalProperties' => true,
            ],
        ];

        $schemas = apply_filters('kbs_settings_json_schemas', $schemas);
        $schema = $schemas[$category] ?? ['type' => 'object', 'additionalProperties' => true];

        if (function_exists('rest_validate_value_from_schema')) {
            $valid = rest_validate_value_from_schema($settings, $schema, 'settings');
            if ($valid !== true) {
                return new WP_Error('invalid_settings', is_wp_error($valid) ? $valid->get_error_message() : 'Invalid settings', ['status' => 400]);
            }
        }
        return true;
    }
}
