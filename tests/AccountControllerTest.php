<?php

use KBS\Api\VyRestAccounts;

kbs_test('unused accounts can be updated structurally and marked inactive', function (): void {
    kbs_test_add_user([
        'ID' => 401,
        'user_email' => 'accounts-update@example.com',
        'display_name' => 'Accounts Update User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(401);
    kbs_test_set_user_meta(401, 'vy_active_org_id', 41);
    kbs_test_seed_org_membership(401, 41, 'company_admin', true, 'Accounts Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_accounts', [[
        'id' => 1,
        'org_id' => 41,
        'code' => 'CASH-OLD',
        'name' => 'Cash Drawer',
        'type' => 'ASSET',
        'sub_type' => 'CASH',
        'currency' => 'INR',
        'is_system' => 0,
        'status' => 'ACTIVE',
        'opening_balance' => 100.0,
        'opening_balance_type' => 'DEBIT',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);

    $result = VyRestAccounts::update_account(kbs_test_make_request('PUT', '/vy/v1/accounts/1', ['id' => 1], [
        'name' => 'Operating Cash',
        'code' => 'CASH-OPS',
        'type' => 'ASSET',
        'sub_type' => 'WALLET',
        'currency' => 'USD',
        'opening_balance' => 250.0,
        'opening_balance_type' => 'DEBIT',
        'status' => 'ARCHIVED',
    ]));
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    $account = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_accounts')[0];
    kbs_assert_same(true, $data['success'] ?? null);
    kbs_assert_same('Operating Cash', $account['name'] ?? null);
    kbs_assert_same('CASH-OPS', $account['code'] ?? null);
    kbs_assert_same('WALLET', $account['sub_type'] ?? null);
    kbs_assert_same('USD', $account['currency'] ?? null);
    kbs_assert_same('ARCHIVED', $account['status'] ?? null);
    kbs_assert_same(250.0, (float) ($account['opening_balance'] ?? 0));
});

kbs_test('accounts with journal history block structural edits but still allow display and status updates', function (): void {
    kbs_test_add_user([
        'ID' => 402,
        'user_email' => 'accounts-lock@example.com',
        'display_name' => 'Accounts Lock User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(402);
    kbs_test_set_user_meta(402, 'vy_active_org_id', 42);
    kbs_test_seed_org_membership(402, 42, 'company_admin', true, 'Locked Accounts Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_accounts', [[
        'id' => 2,
        'org_id' => 42,
        'code' => 'BANK-LOCK',
        'name' => 'Locked Bank',
        'type' => 'ASSET',
        'sub_type' => 'BANK',
        'currency' => 'INR',
        'is_system' => 0,
        'status' => 'ACTIVE',
        'opening_balance' => 0.0,
        'opening_balance_type' => 'DEBIT',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_journal_lines', [[
        'id' => 1,
        'org_id' => 42,
        'journal_id' => 91,
        'account_id' => 2,
        'debit' => 200.0,
        'credit' => 0.0,
        'line_memo' => 'Historical receipt',
        'created_at' => current_time('mysql'),
    ]]);

    $blocked = VyRestAccounts::update_account(kbs_test_make_request('PUT', '/vy/v1/accounts/2', ['id' => 2], [
        'name' => 'Locked Bank',
        'type' => 'LIABILITY',
    ]));
    kbs_assert_wp_error($blocked, 'vy_account_locked_fields', 400);

    $allowed = VyRestAccounts::update_account(kbs_test_make_request('PUT', '/vy/v1/accounts/2', ['id' => 2], [
        'name' => 'Collections Bank',
        'code' => 'BANK-COLLECT',
        'status' => 'ARCHIVED',
    ]));
    $response = kbs_assert_response($allowed, 200);
    $data = $response->get_data();
    $account = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_accounts')[0];

    kbs_assert_same(true, $data['success'] ?? null);
    kbs_assert_same('Collections Bank', $account['name'] ?? null);
    kbs_assert_same('BANK-COLLECT', $account['code'] ?? null);
    kbs_assert_same('ASSET', $account['type'] ?? null);
    kbs_assert_same('ARCHIVED', $account['status'] ?? null);
});

kbs_test('unused accounts delete permanently, used accounts archive, and archived accounts cannot post new money flow', function (): void {
    kbs_test_add_user([
        'ID' => 403,
        'user_email' => 'accounts-delete@example.com',
        'display_name' => 'Accounts Delete User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(403);
    kbs_test_set_user_meta(403, 'vy_active_org_id', 43);
    kbs_test_seed_org_membership(403, 43, 'company_admin', true, 'Delete Accounts Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_accounts', [
        [
            'id' => 3,
            'org_id' => 43,
            'code' => 'TEMP-DEL',
            'name' => 'Disposable Account',
            'type' => 'EXPENSE',
            'sub_type' => '',
            'currency' => 'INR',
            'is_system' => 0,
            'status' => 'ACTIVE',
            'opening_balance' => 0.0,
            'opening_balance_type' => 'DEBIT',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 4,
            'org_id' => 43,
            'code' => 'BANK-HIST',
            'name' => 'Historical Bank',
            'type' => 'ASSET',
            'sub_type' => 'BANK',
            'currency' => 'INR',
            'is_system' => 0,
            'status' => 'ACTIVE',
            'opening_balance' => 0.0,
            'opening_balance_type' => 'DEBIT',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 5,
            'org_id' => 43,
            'code' => 'REV-ACC',
            'name' => 'Revenue',
            'type' => 'INCOME',
            'sub_type' => 'OPERATING',
            'currency' => 'INR',
            'is_system' => 0,
            'status' => 'ACTIVE',
            'opening_balance' => 0.0,
            'opening_balance_type' => 'CREDIT',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_journal_lines', [[
        'id' => 2,
        'org_id' => 43,
        'journal_id' => 92,
        'account_id' => 4,
        'debit' => 100.0,
        'credit' => 0.0,
        'line_memo' => 'Existing activity',
        'created_at' => current_time('mysql'),
    ]]);

    $deleted = VyRestAccounts::delete_account(kbs_test_make_request('DELETE', '/vy/v1/accounts/3', ['id' => 3]));
    $deletedResponse = kbs_assert_response($deleted, 200);
    kbs_assert_same('deleted', $deletedResponse->get_data()['action'] ?? null);

    $archived = VyRestAccounts::delete_account(kbs_test_make_request('DELETE', '/vy/v1/accounts/4', ['id' => 4]));
    $archivedResponse = kbs_assert_response($archived, 200);
    kbs_assert_same('archived', $archivedResponse->get_data()['action'] ?? null);

    $accounts = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_accounts');
    kbs_assert_count(2, $accounts, 'Deleting an unused account should remove it while keeping the used account archived.');
    kbs_assert_same('ARCHIVED', $accounts[0]['status'] ?? null);

    $payment = VyRestAccounts::create_receipt(kbs_test_make_request('POST', '/vy/v1/transactions/receipt', [], [
        'amount' => 50,
        'date' => '2026-04-10',
        'to_account_id' => 4,
        'from_account_id' => 5,
        'description' => 'Blocked archived receipt',
    ]));
    kbs_assert_wp_error($payment, 'vy_account_archived', 400);
});
