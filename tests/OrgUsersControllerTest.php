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

kbs_test('accept invite removes a freshly created user when membership creation fails', function (): void {
    kbs_test_add_user([
        'ID' => 705,
        'user_email' => 'invite-admin@example.com',
        'display_name' => 'Invite Admin',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(0);
    kbs_test_seed_org_membership(705, 73, 'company_admin', true, 'Accept Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites', [[
        'id' => 3,
        'org_id' => 73,
        'email' => 'new-accept@example.com',
        'role' => 'c_employee',
        'token' => 'accept-token',
        'status' => 'invited',
        'invited_by' => 705,
        'created_at' => current_time('mysql'),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
    ]]);
    kbs_test_fail_next_insert($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');

    $result = OrgUsersController::accept_invite(kbs_test_make_request('POST', '/kbs/v1/accept-invite', [
        'invite' => 'accept-token',
        'email' => 'new-accept@example.com',
    ]));
    kbs_assert_wp_error($result, 'invite_accept_failed', 500);

    kbs_assert_same(false, get_user_by('email', 'new-accept@example.com'));
    kbs_assert_count(1, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles'), 'Failed invite acceptance should not create an org membership row for the invited user.');
    kbs_assert_same('invited', kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites')[0]['status'] ?? null);
});

kbs_test('claim invites for user rolls back all invite claims when one membership write fails', function (): void {
    kbs_test_add_user([
        'ID' => 706,
        'user_email' => 'claim-user@example.com',
        'display_name' => 'Claim User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites', [
        [
            'id' => 4,
            'org_id' => 74,
            'email' => 'claim-user@example.com',
            'role' => 'c_employee',
            'token' => 'claim-one',
            'status' => 'invited',
            'invited_by' => 705,
            'created_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
        ],
        [
            'id' => 5,
            'org_id' => 75,
            'email' => 'claim-user@example.com',
            'role' => 'c_manager',
            'token' => 'claim-two',
            'status' => 'invited',
            'invited_by' => 705,
            'created_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
        ],
    ]);
    kbs_test_fail_next_insert($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');

    OrgUsersController::claim_invites_for_user(706, 'claim-user@example.com');

    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles'));
    $invites = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_org_invites');
    kbs_assert_same('invited', $invites[0]['status'] ?? null);
    kbs_assert_same('invited', $invites[1]['status'] ?? null);
});

kbs_test('remove user repairs active org meta when the removed organization was selected', function (): void {
    kbs_test_add_user([
        'ID' => 707,
        'user_email' => 'remove-admin@example.com',
        'display_name' => 'Remove Admin',
        'roles' => ['c_employee'],
    ]);
    kbs_test_add_user([
        'ID' => 708,
        'user_email' => 'remove-member@example.com',
        'display_name' => 'Remove Member',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(707);
    kbs_test_seed_org_membership(707, 76, 'company_admin', true, 'Org 76');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles', [
        [
            'id' => 1,
            'org_id' => 76,
            'user_id' => 707,
            'role' => 'company_admin',
            'is_primary' => 1,
        ],
        [
            'id' => 2,
            'org_id' => 76,
            'user_id' => 708,
            'role' => 'c_employee',
            'is_primary' => 1,
        ],
        [
            'id' => 3,
            'org_id' => 77,
            'user_id' => 708,
            'role' => 'c_employee',
            'is_primary' => 0,
        ],
    ]);
    kbs_test_set_user_meta(708, 'org_id', 76);
    kbs_test_set_user_meta(708, 'vy_active_org_id', 76);

    $result = OrgUsersController::remove_user(kbs_test_make_request('DELETE', '/kbs/v1/user', [
        'org_id' => 76,
        'user_id' => 708,
    ]));
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same(true, $data['deleted'] ?? null);
    kbs_assert_same(77, get_user_meta(708, 'org_id', true));
    kbs_assert_same(77, get_user_meta(708, 'vy_active_org_id', true));

    $roles = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_user_org_roles');
    kbs_assert_count(2, $roles);
});
