<?php

use KBS\Api\VyRestExpenses;

kbs_test('expense create rejects requests without a category and positive amount', function (): void {
    kbs_test_add_user([
        'ID' => 401,
        'user_email' => 'expenses@example.com',
        'display_name' => 'Expense User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(401);
    kbs_test_set_user_meta(401, 'vy_active_org_id', 41);
    kbs_test_seed_org_membership(401, 41, 'company_admin', true, 'Expense Org');

    $request = kbs_test_make_request('POST', '/vy/v1/expenses', [], [
        'category' => '',
        'amount' => 0,
        'payee' => 'Vendor',
    ]);

    $result = VyRestExpenses::create_expense($request);
    kbs_assert_wp_error($result, 'vy_bad_expense', 400);
});

kbs_test('expense create persists the expense, vendor contact, and default expense account when needed', function (): void {
    kbs_test_add_user([
        'ID' => 402,
        'user_email' => 'expense-owner@example.com',
        'display_name' => 'Expense Owner',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(402);
    kbs_test_set_user_meta(402, 'vy_active_org_id', 42);
    kbs_test_seed_org_membership(402, 42, 'company_admin', true, 'Expense Org');

    $request = kbs_test_make_request('POST', '/vy/v1/expenses', [], [
        'expense_date' => '2026-04-03',
        'category' => 'Office Supplies',
        'payee' => 'Stationery Vendor',
        'amount' => 1180,
        'gst_rate' => 18,
        'currency' => 'INR',
        'description' => 'Printer paper and pens',
    ]);

    $result = VyRestExpenses::create_expense($request);
    $response = kbs_assert_response($result, 201);
    $data = $response->get_data();

    kbs_assert_true((int) ($data['id'] ?? 0) > 0, 'Expense creation should return a persisted expense ID.');
    kbs_assert_true(array_key_exists('payment_journal_id', $data), 'Expense creation responses should always include the journal field.');
    kbs_assert_same(null, $data['payment_journal_id']);

    $contacts = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_contacts');
    $expenses = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_expenses');
    $accounts = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_accounts');
    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');

    kbs_assert_count(1, $contacts, 'Expense creation should create a vendor contact when no contact_id is supplied.');
    kbs_assert_count(1, $expenses, 'One expense row should be created.');
    kbs_assert_count(1, $accounts, 'A default expense account should be created when none is supplied.');
    kbs_assert_count(1, $history, 'Expense creation should write an audit history row.');

    $expense = $expenses[0];
    kbs_assert_same('Office Supplies', $expense['category'] ?? null);
    kbs_assert_same('Stationery Vendor', $expense['payee'] ?? null);
    kbs_assert_same(1180.0, (float) ($expense['amount'] ?? 0));
    kbs_assert_same(212.4, (float) ($expense['gst_amount'] ?? 0));
    kbs_assert_same('POSTED', $expense['status'] ?? null);
    kbs_assert_same('expense', $history[0]['record_type'] ?? null);
    kbs_assert_same('created', $history[0]['action'] ?? null);

    $account = $accounts[0];
    kbs_assert_same('GENERAL_EXPENSES', $account['code'] ?? null);
    kbs_assert_same('EXPENSE', $account['type'] ?? null);
});

kbs_test('expense update blocks expenses that already have recorded payment journals', function (): void {
    kbs_test_add_user([
        'ID' => 403,
        'user_email' => 'expense-locked@example.com',
        'display_name' => 'Locked Expense User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(403);
    kbs_test_set_user_meta(403, 'vy_active_org_id', 43);
    kbs_test_seed_org_membership(403, 43, 'company_admin', true, 'Expense Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [[
        'id' => 1,
        'org_id' => 43,
        'contact_id' => null,
        'expense_date' => '2026-04-03',
        'category' => 'Travel',
        'payee' => 'Airline Vendor',
        'description' => 'Flight booking',
        'amount' => 5400.0,
        'currency' => 'INR',
        'gst_rate' => 0.0,
        'gst_amount' => 0.0,
        'gst_type' => 'GST',
        'is_gst_input_eligible' => 1,
        'status' => 'POSTED',
        'payment_journal_id' => 99,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);

    $request = kbs_test_make_request('PUT', '/vy/v1/expenses/1', ['id' => 1], [
        'category' => 'Travel',
        'payee' => 'Airline Vendor',
        'amount' => 6000.0,
    ]);

    $result = VyRestExpenses::update_expense($request);
    kbs_assert_wp_error($result, 'vy_expense_locked', 400);
});

kbs_test('expense update persists edited fields for unpaid active expenses', function (): void {
    kbs_test_add_user([
        'ID' => 404,
        'user_email' => 'expense-edit@example.com',
        'display_name' => 'Editable Expense User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(404);
    kbs_test_set_user_meta(404, 'vy_active_org_id', 44);
    kbs_test_seed_org_membership(404, 44, 'company_admin', true, 'Expense Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_contacts', [[
        'id' => 7,
        'org_id' => 44,
        'type' => 'VENDOR',
        'name' => 'Original Vendor',
        'email' => 'vendor@example.com',
        'phone' => '9999999999',
        'gstin' => '',
        'billing_address' => '',
        'shipping_address' => '',
        'notes' => '',
        'status' => 'ACTIVE',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [[
        'id' => 2,
        'org_id' => 44,
        'contact_id' => 7,
        'expense_date' => '2026-04-03',
        'category' => 'Meals',
        'payee' => 'Original Vendor',
        'description' => 'Team lunch',
        'amount' => 1500.0,
        'currency' => 'INR',
        'gst_rate' => 5.0,
        'gst_amount' => 75.0,
        'gst_type' => 'GST',
        'is_gst_input_eligible' => 1,
        'status' => 'POSTED',
        'payment_journal_id' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);

    $request = kbs_test_make_request('PUT', '/vy/v1/expenses/2', ['id' => 2], [
        'contact_id' => 7,
        'expense_date' => '2026-04-05',
        'category' => 'Client Meals',
        'payee' => 'Original Vendor',
        'description' => 'Client dinner meeting',
        'amount' => 2000.0,
        'currency' => 'INR',
        'gst_rate' => 12.0,
        'gst_type' => 'GST',
        'is_gst_input_eligible' => false,
    ]);

    $result = VyRestExpenses::update_expense($request);
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same('Client Meals', $data['category'] ?? null);
    kbs_assert_same('Client dinner meeting', $data['description'] ?? null);
    kbs_assert_same(2000.0, (float) ($data['amount'] ?? 0));
    kbs_assert_same(240.0, (float) ($data['gst_amount'] ?? 0));
    kbs_assert_same(true, $data['can_edit'] ?? null);
    kbs_assert_count(1, $data['history'] ?? [], 'Expense detail responses should expose audit history after update.');
    kbs_assert_same('updated', $data['history'][0]['action'] ?? null);

    $expense = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_expenses')[0];
    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');
    kbs_assert_same('Client Meals', $expense['category'] ?? null);
    kbs_assert_same('Client dinner meeting', $expense['description'] ?? null);
    kbs_assert_same(2000.0, (float) ($expense['amount'] ?? 0));
    kbs_assert_same(240.0, (float) ($expense['gst_amount'] ?? 0));
    kbs_assert_count(1, $history, 'Expense updates should write an audit history row.');
});

kbs_test('expense archive marks unpaid expenses as archived and blocks journalized expenses', function (): void {
    kbs_test_add_user([
        'ID' => 405,
        'user_email' => 'expense-archive@example.com',
        'display_name' => 'Archiver',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(405);
    kbs_test_set_user_meta(405, 'vy_active_org_id', 45);
    kbs_test_seed_org_membership(405, 45, 'company_admin', true, 'Expense Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [
        [
            'id' => 3,
            'org_id' => 45,
            'contact_id' => null,
            'expense_date' => '2026-04-03',
            'category' => 'Software',
            'payee' => 'SaaS Vendor',
            'description' => 'Monthly tool',
            'amount' => 999.0,
            'currency' => 'INR',
            'gst_rate' => 18.0,
            'gst_amount' => 179.82,
            'gst_type' => 'GST',
            'is_gst_input_eligible' => 1,
            'status' => 'POSTED',
            'payment_journal_id' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 4,
            'org_id' => 45,
            'contact_id' => null,
            'expense_date' => '2026-04-04',
            'category' => 'Travel',
            'payee' => 'Taxi Vendor',
            'description' => 'Airport pickup',
            'amount' => 850.0,
            'currency' => 'INR',
            'gst_rate' => 0.0,
            'gst_amount' => 0.0,
            'gst_type' => 'GST',
            'is_gst_input_eligible' => 1,
            'status' => 'POSTED',
            'payment_journal_id' => 88,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);

    $archiveAllowed = VyRestExpenses::archive_expense(
        kbs_test_make_request('POST', '/vy/v1/expenses/3/archive', ['id' => 3])
    );
    $response = kbs_assert_response($archiveAllowed, 200);
    $data = $response->get_data();
    kbs_assert_same('archived', $data['action'] ?? null);

    $expenses = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_expenses');
    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');
    kbs_assert_same('ARCHIVED', $expenses[0]['status'] ?? null);
    kbs_assert_count(1, $history, 'Expense archive should write an audit history row.');
    kbs_assert_same('archived', $history[0]['action'] ?? null);

    $archiveLocked = VyRestExpenses::archive_expense(
        kbs_test_make_request('POST', '/vy/v1/expenses/4/archive', ['id' => 4])
    );
    kbs_assert_wp_error($archiveLocked, 'vy_expense_archive_locked', 400);
});
