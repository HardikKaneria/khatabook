<?php

use KBS\Api\VyRestAccounts;

kbs_test('token auth allows requests only when the user can access the requested organization', function (): void {
    kbs_test_add_user([
        'ID' => 201,
        'user_email' => 'member@example.com',
        'display_name' => 'Member User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_user_meta(201, 'auth_token', 'token-201');
    kbs_test_set_user_meta(201, 'auth_token_expires', time() + 600);
    kbs_test_seed_org_membership(201, 21, 'company_admin', true, 'Org Twenty One');

    $allowed = kbs_test_make_request('GET', '/vy/v1/accounts', ['org_id' => 21], [], [
        'X-KBS-Token' => 'token-201',
    ]);
    $allowedResult = VyRestAccounts::require_auth($allowed);

    kbs_assert_true($allowedResult === true, 'Accessible organizations should pass auth and org checks.');
    kbs_assert_same(201, get_current_user_id(), 'Token auth should hydrate the current user for downstream checks.');

    wp_set_current_user(0);
    $denied = kbs_test_make_request('GET', '/vy/v1/accounts', ['org_id' => 22], [], [
        'X-KBS-Token' => 'token-201',
    ]);
    $deniedResult = VyRestAccounts::require_auth($denied);

    kbs_assert_wp_error($deniedResult, 'vy_forbidden_org', 403);
});
