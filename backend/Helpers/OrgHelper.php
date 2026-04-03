<?php

namespace KBS\Helpers {

use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

class OrgHelper
{
    private static ?int $resolved_org_id = null;

    public static function current_org_id(): int|WP_Error
    {
        if (self::$resolved_org_id && self::$resolved_org_id > 0) {
            return self::$resolved_org_id;
        }

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $org_id = self::default_org_for_user($user_id);
            if ($org_id > 0) {
                self::$resolved_org_id = $org_id;
                return $org_id;
            }
        }

        $from_filter = apply_filters('vy_current_org_id', null);
        if ($from_filter !== null) {
            $org_id = (int) $from_filter;
            if ($org_id > 0) {
                self::$resolved_org_id = $org_id;
                return $org_id;
            }
        }

        return new WP_Error('vy_no_org', 'Organization could not be determined for this request.', ['status' => 400]);
    }

    public static function resolve_request_org_id(WP_REST_Request $request, ?int $user_id = null): int|WP_Error
    {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
        }

        $requested_org_id = self::sanitize_org_candidate($request->get_param('org_id'));
        if ($requested_org_id > 0) {
            if (!self::user_can_access_org($user_id, $requested_org_id)) {
                return new WP_Error('vy_forbidden_org', 'You do not have access to that organization.', ['status' => 403]);
            }

            self::$resolved_org_id = $requested_org_id;
            return $requested_org_id;
        }

        $default_org_id = self::default_org_for_user($user_id);
        if ($default_org_id > 0 && self::user_can_access_org($user_id, $default_org_id)) {
            self::$resolved_org_id = $default_org_id;
            return $default_org_id;
        }

        return new WP_Error('vy_no_org_access', 'No accessible organization context could be determined for this request.', ['status' => 403]);
    }

    public static function user_can_access_org(int $user_id, int $org_id): bool
    {
        if ($user_id <= 0 || $org_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE user_id = %d AND org_id = %d",
            $user_id,
            $org_id
        ));

        return $count > 0;
    }

    public static function user_role_for_org(int $user_id, int $org_id): ?string
    {
        if ($user_id <= 0 || $org_id <= 0) {
            return null;
        }

        if (user_can($user_id, 'manage_options')) {
            return 'administrator';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';

        $role = $wpdb->get_var($wpdb->prepare(
            "SELECT role
             FROM {$table}
             WHERE user_id = %d AND org_id = %d
             LIMIT 1",
            $user_id,
            $org_id
        ));

        if (!is_string($role) || $role === '') {
            return null;
        }

        return sanitize_key($role);
    }

    public static function set_active_org_for_user(int $user_id, int $org_id): bool|WP_Error
    {
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
        }

        if ($org_id <= 0 || !self::org_exists($org_id) || !self::user_can_access_org($user_id, $org_id)) {
            return new WP_Error('vy_forbidden_org', 'You do not have access to that organization.', ['status' => 403]);
        }

        update_user_meta($user_id, 'org_id', $org_id);
        update_user_meta($user_id, 'vy_active_org_id', $org_id);
        self::$resolved_org_id = $org_id;

        return true;
    }

    private static function default_org_for_user(int $user_id): int
    {
        foreach (['vy_active_org_id', 'org_id'] as $meta_key) {
            $org_id = self::sanitize_org_candidate(get_user_meta($user_id, $meta_key, true));
            if ($org_id > 0 && self::user_can_access_org($user_id, $org_id)) {
                return $org_id;
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT org_id
             FROM {$table}
             WHERE user_id = %d
             ORDER BY is_primary DESC, id ASC
             LIMIT 1",
            $user_id
        ));
    }

    private static function org_exists(int $org_id): bool
    {
        if ($org_id <= 0) {
            return false;
        }

        global $wpdb;

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d",
            $org_id
        ));

        return $exists > 0;
    }

    private static function sanitize_org_candidate($value): int
    {
        if (is_array($value) || is_object($value)) {
            return 0;
        }

        return absint($value);
    }
}

}

namespace {
    if (!function_exists('vy_get_current_org_id')) {
        function vy_get_current_org_id()
        {
            return \KBS\Helpers\OrgHelper::current_org_id();
        }
    }
}
