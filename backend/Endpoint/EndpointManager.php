<?php

namespace KBS\Endpoint;

use WP_Error;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;
use KBS\Api\AdminData;
use KBS\Auth\OtpAuth;
use KBS\Auth\RegisterController;
use KBS\Admin\PendingUserController;
use KBS\Api\SettingsController;
use KBS\Api\OrgUsersController;

defined('ABSPATH') || exit;

class EndpointManager
{
    public const NS       = 'kbs/v1';
    public const ADMIN_NS = 'kbs-admin/v1';

    /** Call via: add_action('rest_api_init', [\KBS\Endpoint\EndpointManager::class, 'register_endpoints']); */
    public static function register_endpoints(): void
    {
        /* ---------------- Admin: Pending Users ---------------- */
        register_rest_route(self::ADMIN_NS, '/pending-users', [
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => [PendingUserController::class, 'get_all_pending'],
            'permission_callback' => [__CLASS__, 'require_manage_options'],
        ]);

        register_rest_route(self::ADMIN_NS, '/update-status', [
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => [PendingUserController::class, 'update_status'],
            'permission_callback' => [__CLASS__, 'require_manage_options'],
            'args'                => [
                'id'     => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'status' => ['type' => 'string',  'required' => true],
            ],
        ]);

        /* ---------------- Public: OTP / Registration ---------------- */
        register_rest_route(self::NS, '/send-otp', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [OtpAuth::class, 'send_otp'],
            'permission_callback' => '__return_true', // public
        ]);

        register_rest_route(self::NS, '/verify-otp', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [OtpAuth::class, 'verify_otp'],
            'permission_callback' => '__return_true', // public
        ]);

        register_rest_route(self::NS, '/submit-registration', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [RegisterController::class, 'handle_register'],
            'permission_callback' => '__return_true', // public
        ]);

        /* ---------------- Authenticated utility ---------------- */
        register_rest_route(self::NS, '/logout', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [OtpAuth::class, 'handle_logout'],
            'permission_callback' => [__CLASS__, 'require_logged_in'],
        ]);

        register_rest_route(self::NS, '/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'me'],
            'permission_callback' => [__CLASS__, 'require_logged_in'],
        ]);

        /* ---------------- Settings (delegate to SettingsController) ---------------- */
        register_rest_route(self::NS, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => [SettingsController::class, 'get_settings'],
                'permission_callback' => [__CLASS__, 'can_read'],
                'args'                => [
                    'org_id'   => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                    'category' => ['type' => 'string',  'required' => false],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE, // POST/PUT/PATCH
                'callback'            => [SettingsController::class, 'upsert_settings'],
                'permission_callback' => [__CLASS__, 'can_write'],
                'args'                => [
                    'org_id'   => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                    'category' => ['type' => 'string',  'required' => false],
                    'settings' => ['type' => 'object',  'required' => false],
                    'version'  => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
                    'batch'    => ['type' => 'object',  'required' => false],
                    'versions' => ['type' => 'object',  'required' => false],
                ],
            ],
        ]);

        /* ---------------- Read-only logs (admin) ---------------- */
        register_rest_route(self::NS, '/registration-logs', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [AdminData::class, 'get_registration_logs'],
            'permission_callback' => [__CLASS__, 'require_manage_options'],
        ]);

        register_rest_route(self::NS, '/otp-attempts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [AdminData::class, 'get_otp_attempts'],
            'permission_callback' => [__CLASS__, 'require_manage_options'],
        ]);

        register_rest_route(self::NS, '/system-logs', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [AdminData::class, 'get_system_logs'],
            'permission_callback' => [__CLASS__, 'require_manage_options'],
        ]);

        /** ------------- Org Users (per-organization management) ------------- */
        register_rest_route(self::NS, '/org-users', [
            'methods'             => 'GET',
            'callback'            => [OrgUsersController::class, 'get_org_users'],
            'permission_callback' => [OrgUsersController::class, 'can_manage_org'],
            'args'                => [
                'org_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NS, '/invite-user', [
            'methods'             => 'POST',
            'callback'            => [OrgUsersController::class, 'invite_user'],
            'permission_callback' => [OrgUsersController::class, 'can_manage_org'],
            'args'                => [
                'org_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'email'  => ['type' => 'string',  'required' => true],
                'role'   => ['type' => 'string',  'required' => false],
            ],
        ]);

        register_rest_route(self::NS, '/user-role', [
            'methods'             => 'PUT',
            'callback'            => [OrgUsersController::class, 'update_user_role'],
            'permission_callback' => [OrgUsersController::class, 'can_manage_org'],
            'args'                => [
                'org_id'  => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'user_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'role'    => ['type' => 'string',  'required' => true],
            ],
        ]);

        register_rest_route(self::NS, '/resend-invite', [
            'methods'             => 'POST',
            'callback'            => [OrgUsersController::class, 'resend_invite'],
            'permission_callback' => [OrgUsersController::class, 'can_manage_org'],
            'args'                => [
                'org_id'  => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                // One of:
                'user_id' => ['type' => 'string',  'required' => false], // supports "invite:123"
                'email'   => ['type' => 'string',  'required' => false],
            ],
        ]);

        register_rest_route(self::NS, '/user', [
            'methods'             => 'DELETE',
            'callback'            => [OrgUsersController::class, 'remove_user'],
            'permission_callback' => [OrgUsersController::class, 'can_manage_org'],
            'args'                => [
                'org_id'  => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'user_id' => ['type' => 'string',  'required' => true], // supports numeric or "invite:123"
            ],
        ]);
    }

    /* ===== Helpers / permission callbacks ===== */

    public static function me(): WP_REST_Response|WP_Error
    {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }
        return new WP_REST_Response([
            'id'    => (int) $user->ID,
            'email' => $user->user_email,
            'name'  => $user->display_name,
            'role'  => $user->roles[0] ?? null,
        ], 200);
    }

    /** Accept either logged-in cookie+nonce, or our custom X-KBS-Token */
    private static function user_from_token(WP_REST_Request $request): ?int
    {
        $token = $request->get_header('X-KBS-Token') ?: $request->get_header('x-kbs-token');
        if (!$token) {
            return null;
        }

        global $wpdb;
        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'auth_token' AND meta_value = %s LIMIT 1",
            $token
        ));
        if (!$user_id) {
            return null;
        }

        $exp = (int) get_user_meta($user_id, 'auth_token_expires', true);
        if (!$exp || time() >= $exp) {
            return null;
        }

        return $user_id;
    }

    /** Ensure WP has a current user (from cookie OR from token) */
    private static function assert_auth(WP_REST_Request $request): int|false
    {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }

        $uid = self::user_from_token($request);
        if ($uid) {
            wp_set_current_user($uid); // establish user for caps checks
            return $uid;
        }
        return false;
    }

    public static function require_logged_in(WP_REST_Request $request): bool|WP_Error
    {
        if (is_user_logged_in() || self::assert_auth($request)) {
            return true;
        }
        return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
    }

    public static function require_manage_options(WP_REST_Request $request): bool|WP_Error
    {
        $uid = is_user_logged_in() ? get_current_user_id() : self::assert_auth($request);
        if (!$uid) {
            return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
        }
        if (user_can($uid, 'manage_options')) {
            return true;
        }
        return new WP_Error('forbidden', 'Insufficient permissions.', ['status' => 403]);
    }

    /** Read access: any logged-in user with 'read' or role in {administrator, company_admin} */
    public static function can_read(WP_REST_Request $request): bool
    {
        $uid = self::assert_auth($request);
        if (!$uid) {
            return false;
        }
        $user = get_userdata($uid);
        $role = $user->roles[0] ?? '';
        return user_can($uid, 'read') || in_array($role, ['administrator', 'company_admin'], true);
    }

    /** Write access: restrict to {administrator, company_admin} */
    public static function can_write(WP_REST_Request $request): bool
    {
        $uid = self::assert_auth($request);
        if (!$uid) {
            return false;
        }
        $user = get_userdata($uid);
        $role = $user->roles[0] ?? '';
        return in_array($role, ['administrator', 'company_admin'], true);
    }
}