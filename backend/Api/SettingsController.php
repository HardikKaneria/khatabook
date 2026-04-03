<?php
namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SettingsController
{
    const NS = 'kbs/v1';

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes()
    {
        register_rest_route(self::NS, '/settings', [
            [
                'methods'  => WP_REST_Server::READABLE, // GET
                'callback' => [__CLASS__, 'get_settings'],
                'permission_callback' => [__CLASS__, 'can_read'],
                'args' => [
                    'org_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                    'category' => [
                        'required' => false,
                        'type'     => 'string',
                    ],
                ],
            ],
            [
                'methods'  => WP_REST_Server::CREATABLE, // POST (single or batch via "batch")
                'callback' => [__CLASS__, 'upsert_settings'],
                'permission_callback' => [__CLASS__, 'can_write'],
                'args' => [
                    'org_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                    'category' => [
                        'required' => false,
                        'type'     => 'string',
                    ],
                    'settings' => [
                        'required' => false,
                    ],
                    'version' => [
                        'required' => false,
                        'type'     => 'integer',
                    ],
                    'batch' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                    'versions' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                ],
            ],
        ]);
    }

    /** ---------- logging helpers ---------- */

    private static function log($event, $msg, $context = [])
    {
        // Use your app logger if available, else PHP error log
        if (class_exists('\\KBS\\Core\\SystemLogger')) {
            try {
                // \KBS\Core\SystemLogger::log($event, $msg, $context);
                return;
            } catch (\Throwable $e) {
                // fall through to error_log
            }
        }
        $suffix = $context ? ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        error_log("[KBS SettingsController] {$event}: {$msg}{$suffix}");
    }

    private static function etag_matches_client($etag)
    {
        $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        return $ifNone === "\"{$etag}\"";
    }

    private static function respond_with_etag($data, $etag, $status = 200)
    {
        $resp = new WP_REST_Response($data, $status);
        $resp->header('ETag', '"' . $etag . '"');
        return $resp;
    }

    /** ---------- permissions ---------- */

    public static function can_read(WP_REST_Request $req)
    {
        $org_id  = (int) $req->get_param('org_id');
        $user_id = get_current_user_id();
        $allowed = self::user_can_access_org($user_id, $org_id);

        self::log('can_read', 'Checking read access', [
            'user_id' => $user_id,
            'org_id'  => $org_id,
            'allowed' => $allowed,
        ]);

        return $allowed;
    }

    public static function can_write(WP_REST_Request $req)
    {
        $org_id  = (int) $req->get_param('org_id');
        $user_id = get_current_user_id();
        $allowed = current_user_can('manage_options') || self::user_is_company_admin($user_id, $org_id);

        self::log('can_write', 'Checking write access', [
            'user_id' => $user_id,
            'org_id'  => $org_id,
            'allowed' => $allowed,
        ]);

        return $allowed;
    }

    private static function user_can_access_org($user_id, $org_id)
    {
        global $wpdb;
        if (!$user_id || !$org_id) return false;

        if (current_user_can('manage_options')) {
            return true;
        }

        $tbl = $wpdb->prefix . 'kbs_user_org_roles';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE user_id=%d AND org_id=%d",
            $user_id,
            $org_id
        ));

        return $count > 0;
    }

    private static function user_is_company_admin($user_id, $org_id)
    {
        global $wpdb;
        if (!$user_id || !$org_id) return false;

        if (current_user_can('manage_options')) {
            return true;
        }

        $tbl = $wpdb->prefix . 'kbs_user_org_roles';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE user_id=%d AND org_id=%d AND role=%s",
            $user_id,
            $org_id,
            'company_admin'
        ));

        return $count > 0;
    }

    /** ---------- GET ---------- */

    public static function get_settings(WP_REST_Request $req)
    {
        global $wpdb;

        $table    = $wpdb->prefix . 'kbs_settings';
        $org_id   = (int) $req['org_id'];
        $category = isset($req['category']) && $req['category'] !== '' ? (string) $req['category'] : null;

        self::log('get_settings', 'Fetching settings', [
            'org_id'   => $org_id,
            'category' => $category,
        ]);

        if ($category) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT settings_json, version, UNIX_TIMESTAMP(updated_at) AS ts
                 FROM {$table}
                 WHERE org_id=%d AND category=%s",
                $org_id,
                $category
            ));

            $settings = $row ? json_decode($row->settings_json, true) : new \stdClass();
            $version  = $row ? (int) $row->version : 0;
            $etag     = $row ? sha1($version . ':' . $row->settings_json) : sha1('empty');

            if (self::etag_matches_client($etag)) {
                self::log('get_settings', 'ETag matched, returning 304', ['etag' => $etag]);
                return self::respond_with_etag(null, $etag, 304);
            }

            $data = [
                'org_id'     => $org_id,
                'category'   => $category,
                'settings'   => $settings,
                'version'    => $version,
                'updated_at' => isset($row->ts) ? (int) $row->ts : null,
            ];

            return self::respond_with_etag($data, $etag, 200);
        }

        // all categories
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT category, settings_json, version
             FROM {$table}
             WHERE org_id=%d",
            $org_id
        ));

        $payload = [];
        foreach ($rows as $r) {
            $payload[$r->category] = [
                'settings' => json_decode($r->settings_json, true),
                'version'  => (int) $r->version,
            ];
        }

        // weak-ish ETag across payload; acceptable for cache validation
        $etag = sha1(serialize($payload));

        if (self::etag_matches_client($etag)) {
            self::log('get_settings', 'ETag matched (all categories), returning 304', ['etag' => $etag]);
            return self::respond_with_etag(null, $etag, 304);
        }

        $data = [
            'org_id' => $org_id,
            'data'   => $payload,
        ];

        return self::respond_with_etag($data, $etag, 200);
    }

    /** ---------- UPSERT (single or batch) ---------- */

    public static function upsert_settings(WP_REST_Request $req)
    {
        $is_batch = is_array($req->get_param('batch'));
        return $is_batch ? self::upsert_batch($req) : self::upsert_single($req);
    }

    private static function upsert_single(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_settings';

        $org_id   = (int) $req['org_id'];
        $category = (string) $req['category'];
        $settings = (array) ($req->get_param('settings') ?: []);
        $expected = $req->get_param('version'); // optional optimistic concurrency
        $user_id  = get_current_user_id();

        if (!$org_id || !$category) {
            return new WP_Error('bad_request', 'org_id and category are required', ['status' => 400]);
        }

        $validation = self::validate_against_schema($category, $settings);
        if (is_wp_error($validation)) return $validation;

        self::log('upsert_single', 'Upserting single category', [
            'org_id'  => $org_id,
            'category' => $category,
            'expected_version' => $expected,
            'user_id' => $user_id,
        ]);

        // transaction for optimistic concurrency
        $wpdb->query('START TRANSACTION');

        try {
            // lock row
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, version, settings_json
                 FROM {$table}
                 WHERE org_id=%d AND category=%s
                 FOR UPDATE",
                $org_id,
                $category
            ));

            if ($row) {
                if ($expected !== null && (int)$expected !== (int)$row->version) {
                    $wpdb->query('ROLLBACK');
                    self::log('upsert_single', 'Version mismatch', [
                        'org_id'   => $org_id,
                        'category' => $category,
                        'expected' => (int)$expected,
                        'current'  => (int)$row->version,
                    ]);
                    return new WP_Error('conflict', 'Version mismatch', [
                        'status' => 409,
                        'current_version' => (int)$row->version,
                    ]);
                }

                $new_version = (int)$row->version + 1;
                $ok = $wpdb->update(
                    $table,
                    [
                        'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'version'       => $new_version,
                        'updated_by'    => $user_id,
                        'updated_at'    => current_time('mysql'),
                    ],
                    ['id' => (int)$row->id],
                    ['%s', '%d', '%d', '%s'],
                    ['%d']
                );

                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    self::log('upsert_single', 'DB update failed', ['org_id' => $org_id, 'category' => $category]);
                    return new WP_Error('db_error', 'Failed to update settings', ['status' => 500]);
                }
            } else {
                $new_version = 1;
                $ok = $wpdb->insert(
                    $table,
                    [
                        'org_id'        => $org_id,
                        'category'      => $category,
                        'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'version'       => $new_version,
                        'updated_by'    => $user_id,
                        'updated_at'    => current_time('mysql'),
                    ],
                    ['%d', '%s', '%s', '%d', '%d', '%s']
                );

                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    self::log('upsert_single', 'DB insert failed', ['org_id' => $org_id, 'category' => $category]);
                    return new WP_Error('db_error', 'Failed to insert settings', ['status' => 500]);
                }
            }

            $wpdb->query('COMMIT');

            self::log('upsert_single', 'Upsert success', [
                'org_id'   => $org_id,
                'category' => $category,
                'new_version' => $new_version,
            ]);

            $resp = new WP_REST_Response(['ok' => true, 'version' => $new_version], 200);
            $resp->header('ETag', '"' . sha1($new_version . ':' . wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"');
            return $resp;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            self::log('upsert_single', 'Exception in upsert', [
                'org_id' => $org_id,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
            return new WP_Error('server_error', 'Unexpected error', ['status' => 500]);
        }
    }

    private static function upsert_batch(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_settings';

        $org_id   = (int) $req['org_id'];
        $batch    = (array) $req->get_param('batch');
        $versions = (array) ($req->get_param('versions') ?: []);
        $user_id  = get_current_user_id();

        if (!$org_id) {
            return new WP_Error('bad_request', 'org_id is required', ['status' => 400]);
        }

        self::log('upsert_batch', 'Batch update start', [
            'org_id'     => $org_id,
            'user_id'    => $user_id,
            'categories' => array_keys($batch),
        ]);

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($batch as $category => $settings) {
                $settings = (array) $settings;

                $err = self::validate_against_schema($category, $settings);
                if (is_wp_error($err)) {
                    $wpdb->query('ROLLBACK');
                    self::log('upsert_batch', 'Schema validation failed', [
                        'org_id'   => $org_id,
                        'category' => $category,
                    ]);
                    return $err;
                }

                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, version
                     FROM {$table}
                     WHERE org_id=%d AND category=%s
                     FOR UPDATE",
                    $org_id,
                    (string) $category
                ));

                $expected = array_key_exists($category, $versions) ? (int) $versions[$category] : null;

                if ($row) {
                    if ($expected !== null && (int)$expected !== (int)$row->version) {
                        $wpdb->query('ROLLBACK');
                        self::log('upsert_batch', 'Version mismatch', [
                            'org_id'   => $org_id,
                            'category' => $category,
                            'expected' => (int)$expected,
                            'current'  => (int)$row->version,
                        ]);
                        return new WP_Error('conflict', "Version mismatch for {$category}", [
                            'status' => 409,
                            'category' => $category,
                            'current_version' => (int)$row->version,
                        ]);
                    }

                    $ok = $wpdb->update(
                        $table,
                        [
                            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
                        self::log('upsert_batch', 'DB update failed', ['org_id' => $org_id, 'category' => $category]);
                        return new WP_Error('db_error', "Failed updating {$category}", ['status' => 500]);
                    }
                } else {
                    $ok = $wpdb->insert(
                        $table,
                        [
                            'org_id'        => $org_id,
                            'category'      => (string) $category,
                            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'version'       => 1,
                            'updated_by'    => $user_id,
                            'updated_at'    => current_time('mysql'),
                        ],
                        ['%d', '%s', '%s', '%d', '%d', '%s']
                    );

                    if ($ok === false) {
                        $wpdb->query('ROLLBACK');
                        self::log('upsert_batch', 'DB insert failed', ['org_id' => $org_id, 'category' => $category]);
                        return new WP_Error('db_error', "Failed inserting {$category}", ['status' => 500]);
                    }
                }
            }

            $wpdb->query('COMMIT');

            self::log('upsert_batch', 'Batch update committed', [
                'org_id' => $org_id,
                'count'  => count($batch),
            ]);

            return new WP_REST_Response(['ok' => true], 200);
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            self::log('upsert_batch', 'Exception in batch', [
                'org_id' => $org_id,
                'error'  => $e->getMessage(),
            ]);
            return new WP_Error('server_error', 'Unexpected error', ['status' => 500]);
        }
    }

    /** ---------- validation (plug your own schemas here) ---------- */

    private static function validate_against_schema($category, $settings)
    {
        // Example schemas; extend via filter in your app.
        $schemas = [
            'company' => [
                'type' => 'object',
                'properties' => [
                    'business_name'    => ['type' => 'string'],
                    'display_name'     => ['type' => 'string'],
                    'gst_registered'   => ['type' => 'boolean'],
                    'gstin'            => ['type' => 'string'],
                    'state'            => ['type' => 'string'],
                    'fy_start'         => ['type' => 'string'],
                    'base_currency'    => ['type' => 'string'],
                    'multi_currency'   => ['type' => 'boolean'],
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
                    'default_due_days' => ['type' => 'integer'],
                    'round_off'      => ['type' => 'number'],
                ],
                'additionalProperties' => true,
            ],
            'tax' => [
                'type' => 'object',
                'properties' => [
                    'gst_type'        => ['type' => 'string'],
                    'income_tax_rate' => ['type' => 'number'],
                ],
                'additionalProperties' => true,
            ],
        ];

        $schemas = apply_filters('kbs_settings_json_schemas', $schemas);
        $schema  = $schemas[$category] ?? ['type' => 'object', 'additionalProperties' => true];

        if (function_exists('rest_validate_value_from_schema')) {
            $valid = rest_validate_value_from_schema($settings, $schema, 'settings');
            if ($valid !== true) {
                return new WP_Error(
                    'invalid_settings',
                    is_wp_error($valid) ? $valid->get_error_message() : 'Invalid settings',
                    ['status' => 400]
                );
            }
        }
        return true;
    }
}
