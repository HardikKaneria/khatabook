<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

class VyRestContacts
{
    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/contacts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_contacts'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/contacts', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_contact'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/contacts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_contact'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/contacts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_contact'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/contacts/(?P<id>\d+)/archive', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'archive_contact'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function list_contacts(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vy_contacts';
        $where = ['org_id = %d'];
        $params = [$org];

        $type = strtoupper($request->get_param('type') ?? '');
        if (in_array($type, ['CUSTOMER', 'VENDOR', 'BOTH'], true)) {
            if ($type === 'CUSTOMER') {
                $where[] = "(type IN ('CUSTOMER','BOTH'))";
            } elseif ($type === 'VENDOR') {
                $where[] = "(type IN ('VENDOR','BOTH'))";
            } else {
                $where[] = "(type IN ('BOTH'))";
            }
        }

        $status = strtoupper($request->get_param('status') ?? 'ACTIVE');
        if ($status === 'ARCHIVED') {
            $where[] = "status = 'ARCHIVED'";
        } else {
            $where[] = "status = 'ACTIVE'";
        }

        $q = trim((string) $request->get_param('q'));
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = "(name LIKE %s OR email LIKE %s OR phone LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(50, max(1, (int) ($request->get_param('per_page') ?? 10)));
        $offset = ($page - 1) * $perPage;

        $conditions = implode(' AND ', $where);

        $query = "SELECT id, name, type, email, phone, gstin, status
                  FROM {$table}
                  WHERE {$conditions}
                  ORDER BY name ASC
                  LIMIT %d OFFSET %d";
        $paramsWithLimit = array_merge($params, [$perPage, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($query, ...$paramsWithLimit));

        $countSql = "SELECT COUNT(1) FROM {$table} WHERE {$conditions}";
        $total = (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params));

        return new WP_REST_Response([
            'data' => array_map(fn($r) => [
                'id'     => (int) $r->id,
                'name'   => $r->name,
                'type'   => $r->type,
                'email'  => $r->email,
                'phone'  => $r->phone,
                'gstin'  => $r->gstin,
                'status' => $r->status,
            ], $rows ?: []),
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
            ],
        ], 200);
    }

    public static function create_contact(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $payload = self::sanitize_contact_payload($request->get_json_params());
        if (empty($payload['name'])) {
            return new WP_Error('vy_contact_bad_name', 'Name is required.', ['status' => 400]);
        }

        global $wpdb;
        $payload['org_id'] = $org;
        $payload['created_at'] = current_time('mysql', true);
        $payload['updated_at'] = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_contacts',
            $payload,
            ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_contact_insert_failed', 'Failed to create contact.', ['status' => 500]);
        }

        $contactId = (int) $wpdb->insert_id;
        return self::get_contact_response($org, $contactId);
    }

    public static function get_contact(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;
        return self::get_contact_response((int) $org, (int) $request['id']);
    }

    public static function update_contact(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $contactId = (int) $request['id'];
        $contact = self::fetch_contact((int) $org, $contactId);
        if (!$contact) {
            return new WP_Error('vy_not_found', 'Contact not found.', ['status' => 404]);
        }

        $payload = self::sanitize_contact_payload($request->get_json_params(), false);
        if (!$payload) {
            return new WP_Error('vy_contact_no_fields', 'No updatable fields provided.', ['status' => 400]);
        }

        $payload['updated_at'] = current_time('mysql', true);
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'vy_contacts',
            $payload,
            ['org_id' => $org, 'id' => $contactId],
            null,
            ['%d','%d']
        );

        return self::get_contact_response($org, $contactId);
    }

    public static function archive_contact(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $contactId = (int) $request['id'];
        $contact = self::fetch_contact((int) $org, $contactId);
        if (!$contact) {
            return new WP_Error('vy_not_found', 'Contact not found.', ['status' => 404]);
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'vy_contacts',
            ['status' => 'ARCHIVED', 'updated_at' => current_time('mysql', true)],
            ['org_id' => $org, 'id' => $contactId],
            ['%s','%s'],
            ['%d','%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    private static function get_contact_response(int $org_id, int $contact_id): WP_REST_Response|WP_Error
    {
        $contact = self::fetch_contact($org_id, $contact_id);
        if (!$contact) {
            return new WP_Error('vy_not_found', 'Contact not found.', ['status' => 404]);
        }
        $contact['id'] = (int) $contact['id'];
        return new WP_REST_Response($contact, 200);
    }

    private static function fetch_contact(int $org_id, int $contact_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_contacts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $contact_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function sanitize_contact_payload(array $data, bool $requireAll = true): array
    {
        $out = [];
        if ($requireAll || array_key_exists('type', $data)) {
            $type = strtoupper($data['type'] ?? 'CUSTOMER');
            $out['type'] = in_array($type, ['CUSTOMER', 'VENDOR', 'BOTH'], true) ? $type : 'CUSTOMER';
        }
        if ($requireAll || array_key_exists('name', $data)) {
            $out['name'] = sanitize_text_field($data['name'] ?? '');
        }
        if ($requireAll || array_key_exists('email', $data)) {
            $out['email'] = sanitize_email($data['email'] ?? '');
        }
        if ($requireAll || array_key_exists('phone', $data)) {
            $out['phone'] = sanitize_text_field($data['phone'] ?? '');
        }
        if ($requireAll || array_key_exists('gstin', $data)) {
            $out['gstin'] = sanitize_text_field($data['gstin'] ?? '');
        }
        if ($requireAll || array_key_exists('billing_address', $data)) {
            $out['billing_address'] = wp_kses_post($data['billing_address'] ?? '');
        }
        if ($requireAll || array_key_exists('shipping_address', $data)) {
            $out['shipping_address'] = wp_kses_post($data['shipping_address'] ?? '');
        }
        if ($requireAll || array_key_exists('notes', $data)) {
            $out['notes'] = wp_kses_post($data['notes'] ?? '');
        }
        if ($requireAll || array_key_exists('status', $data)) {
            $status = strtoupper($data['status'] ?? 'ACTIVE');
            $out['status'] = in_array($status, ['ACTIVE', 'ARCHIVED'], true) ? $status : 'ACTIVE';
        }
        return $out;
    }
}
