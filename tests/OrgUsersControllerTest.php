<?php

use KBS\Api\OrgUsersController;

kbs_test('invite user grants existing users org membership and clears stale invites in one write path', function (): void {
    kbs_test_add_user([
        'ID' => 701,
        'user_email' => 'admin-org@example.com',
        'display_name' => 'Org Admin',
        'roles' => ['c_employee'],
    ]);
    kbs_test_add_user([
        'ID' => 702,
        'user_email' => 'member-org@example.com',
        'display_name' => 'Existing Member',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(701);
    kbs_test_seed_org_membership(701, 71, 'company_admin', true, 'Managed Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites', [[
        'id' => 1,
        'org_id' => 71,
        'email' => 'member-org@example.com',
        'role' => 'c_employee',
        'token' => 'stale-token',
        'status' => 'invited',
        'invited_by' => 701,
        'created_at' => current_time('mysql'),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
    ]]);

    $result = OrgUsersController::invite_user(kbs_test_make_request('POST', '/kbs/v1/invite-user', [
        'org_id' => 71,
        'email' => 'member-org@example.com',
        'role' => 'c_employee',
    ]));
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same('active', $data['status'] ?? null);

    $roles = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');
    $invites = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites');

    kbs_assert_count(2, $roles, 'Existing-user org invites should add exactly one new membership row.');
    kbs_assert_count(0, $invites, 'Granting access to an existing user should clear stale invite rows for that org/email.');
});

kbs_test('invite user rolls back existing-user access when the org membership insert fails', function (): void {
    kbs_test_add_user([
        'ID' => 703,
        'user_email' => 'rollback-admin@example.com',
        'display_name' => 'Rollback Admin',
        'roles' => ['c_employee'],
    ]);
    kbs_test_add_user([
        'ID' => 704,
        'user_email' => 'rollback-member@example.com',
        'display_name' => 'Rollback Member',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(703);
    kbs_test_seed_org_membership(703, 72, 'company_admin', true, 'Rollback Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites', [[
        'id' => 2,
        'org_id' => 72,
        'email' => 'rollback-member@example.com',
        'role' => 'c_employee',
        'token' => 'rollback-token',
        'status' => 'invited',
        'invited_by' => 703,
        'created_at' => current_time('mysql'),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
    ]]);
    kbs_test_fail_next_insert($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');

    $result = OrgUsersController::invite_user(kbs_test_make_request('POST', '/kbs/v1/invite-user', [
        'org_id' => 72,
        'email' => 'rollback-member@example.com',
        'role' => 'c_employee',
    ]));
    kbs_assert_wp_error($result, 'org_invite_existing_user_failed', 500);

    $roles = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');
    $invites = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites');

    kbs_assert_count(1, $roles, 'Failed existing-user access grants should leave only the actor membership in place.');
    kbs_assert_count(1, $invites, 'Failed existing-user access grants should keep the stale invite row untouched.');
});
