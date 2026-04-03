<?php

namespace KBS\Accounts;

/**
 * Legacy pre-organization helper retained for backward compatibility.
 * Current membership and authorization flows use kbs_organizations and kbs_user_org_roles.
 */
class ClientAccount
{
    /**
     * Assign a user to a client account
     *
     * @param int $user_id
     * @param int $client_account_id
     * @return void
     */
    public static function assign_user_to_account(int $user_id, int $client_account_id): void
    {
        update_user_meta($user_id, 'client_account_id', $client_account_id);
    }

    /**
     * Get the client account ID for a user
     *
     * @param int $user_id
     * @return int|null
     */
    public static function get_client_account_id(int $user_id): ?int
    {
        $id = get_user_meta($user_id, 'client_account_id', true);
        return $id ? (int)$id : null;
    }

    /**
     * Get all users assigned to a specific client account
     *
     * @param int $client_account_id
     * @return array
     */
    public static function get_users_by_account(int $client_account_id): array
    {
        $args = [
            'meta_key' => 'client_account_id',
            'meta_value' => $client_account_id,
            'number' => -1,
            'fields' => ['ID', 'display_name', 'user_email']
        ];

        $users = get_users($args);
        return $users;
    }

    /**
     * Create a new client account and assign the creating user as admin
     *
     * @param int $user_id
     * @return int client_account_id
     */
    public static function create_account_for_user(int $user_id): int
    {
        $client_account_id = time(); // Or use UUID/random/int generator

        self::assign_user_to_account($user_id, $client_account_id);

        // Optionally assign role
        $user = get_userdata($user_id);
        if ($user) {
            $user->set_role('company_admin');
        }

        return $client_account_id;
    }
}
