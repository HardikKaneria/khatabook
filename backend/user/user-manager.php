<?php

namespace KBS\Core;

class UserManager
{
    /**
     * Register REST API routes for user management
     */
    public static function register_endpoints(): void
    {
        register_rest_route('kbs/v1', '/pending-users', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_pending_users'],
            'permission_callback' => [self::class, 'admin_only'],
        ]);

        register_rest_route('kbs/v1', '/approve-user', [
            'methods'  => 'POST',
            'callback' => [self::class, 'approve_user'],
            'permission_callback' => [self::class, 'admin_only'],
        ]);
    }

    /**
     * Only allow admins to access
     */
    public static function admin_only(): bool
    {
        return current_user_can('administrator');
    }

    /**
     * Get all pending users
     */
    public static function get_pending_users(): array
    {
        $users = get_users([
            'meta_key'   => 'kbs_account_status',
            'meta_value' => 'pending',
            'role'       => 'c_employee',
            'fields'     => ['ID', 'user_email', 'display_name']
        ]);

        return array_map(function ($user) {
            return [
                'user_id' => $user->ID,
                'email' => $user->user_email,
                'name' => $user->display_name
            ];
        }, $users);
    }

    /**
     * Approve user by ID
     */
    public static function approve_user(\WP_REST_Request $request)
    {
        $user_id = intval($request->get_param('user_id'));

        if (!$user_id || !get_userdata($user_id)) {
            return new \WP_Error('invalid_user', 'User not found', ['status' => 404]);
        }

        update_user_meta($user_id, 'kbs_account_status', 'approved');

        return ['status' => 'approved', 'user_id' => $user_id];
    }

    /**
     * Mark new user as pending
     */
    public static function mark_user_pending(int $user_id): void
    {
        update_user_meta($user_id, 'kbs_account_status', 'pending');
    }

    /**
     * Check if user is approved
     */
    public static function is_user_approved(int $user_id): bool
    {
        return get_user_meta($user_id, 'kbs_account_status', true) === 'approved';
    }
}
