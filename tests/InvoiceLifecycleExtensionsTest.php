<?php

use KBS\Api\VyRestInvoices;

kbs_test('recurring billing profiles snapshot an invoice and generate the next due invoice on the vy model', function (): void {
    kbs_test_add_user([
        'ID' => 601,
        'user_email' => 'recurring@example.com',
        'display_name' => 'Recurring User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(601);
    kbs_test_set_user_meta(601, 'vy_active_org_id', 61);
    kbs_test_seed_org_membership(601, 61, 'company_admin', true, 'Recurring Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_contacts', [[
        'id' => 41,
        'org_id' => 61,
        'type' => 'CUSTOMER',
        'name' => 'Alpha Customer',
        'email' => 'alpha@example.com',
        'phone' => '9999991111',
        'status' => 'ACTIVE',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 1,
        'org_id' => 61,
        'contact_id' => 41,
        'invoice_number' => 'INV-SOURCE-001',
        'customer_name' => 'Alpha Customer',
        'customer_email' => 'alpha@example.com',
        'customer_phone' => '9999991111',
        'date' => '2026-04-01',
        'due_date' => '2026-04-08',
        'currency' => 'INR',
        'subtotal' => 1000.0,
        'tax_total' => 180.0,
        'total' => 1180.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => 'Monthly retainership',
        'pdf_url' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items', [
        [
            'id' => 1,
            'org_id' => 61,
            'invoice_id' => 1,
            'description' => 'Retainer',
            'quantity' => 1,
            'unit_price' => 1000.0,
            'tax_rate' => 18.0,
            'tax_amount' => 180.0,
            'tax_type' => 'GST',
            'line_total' => 1180.0,
            'created_at' => current_time('mysql'),
        ],
    ]);

    $create = VyRestInvoices::create_recurring_profile(kbs_test_make_request('POST', '/vy/v1/invoices/1/recurring', ['id' => 1], [
        'profile_name' => 'Alpha Monthly Retainer',
        'frequency' => 'MONTHLY',
        'interval_count' => 1,
        'start_date' => '2026-05-01',
        'due_days' => 7,
        'invoice_status' => 'DRAFT',
    ]));
    $createData = kbs_assert_response($create, 201)->get_data();

    kbs_assert_true((int) ($createData['id'] ?? 0) > 0, 'Recurring plan creation should return a persisted profile ID.');
    kbs_assert_same('2026-05-01', $createData['next_run_date'] ?? null);
    kbs_assert_same('Alpha Monthly Retainer', $createData['profile_name'] ?? null);

    $generate = VyRestInvoices::generate_recurring_profile(kbs_test_make_request('POST', '/vy/v1/recurring-invoices/' . $createData['id'] . '/generate', ['id' => $createData['id']], [
        'as_of' => '2026-05-01',
    ]));
    $generateData = kbs_assert_response($generate, 200)->get_data();

    kbs_assert_count(1, $generateData['generated'] ?? [], 'Generating a due recurring plan should create one invoice cycle.');

    $invoices = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoices');
    kbs_assert_count(2, $invoices, 'Recurring generation should append a new invoice row.');
    kbs_assert_same((int) $createData['id'], (int) ($invoices[1]['recurring_profile_id'] ?? 0));
    kbs_assert_same('2026-05-01', $invoices[1]['date'] ?? null);
    kbs_assert_same('2026-05-08', $invoices[1]['due_date'] ?? null);

    $profiles = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_recurring_profiles');
    kbs_assert_same(1, (int) ($profiles[0]['generated_count'] ?? 0));
    kbs_assert_same('2026-05-01', $profiles[0]['last_run_date'] ?? null);
    kbs_assert_same('2026-06-01', $profiles[0]['next_run_date'] ?? null);
});

kbs_test('credit notes reduce the effective invoice balance and prevent over-crediting', function (): void {
    kbs_test_add_user([
        'ID' => 602,
        'user_email' => 'notes@example.com',
        'display_name' => 'Adjustment User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(602);
    kbs_test_set_user_meta(602, 'vy_active_org_id', 62);
    kbs_test_seed_org_membership(602, 62, 'company_admin', true, 'Adjustment Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 2,
        'org_id' => 62,
        'contact_id' => null,
        'invoice_number' => 'INV-NOTE-001',
        'customer_name' => 'Note Customer',
        'customer_email' => 'note@example.com',
        'customer_phone' => '8888888888',
        'date' => '2026-04-01',
        'due_date' => '2026-04-08',
        'currency' => 'INR',
        'subtotal' => 1000.0,
        'tax_total' => 0.0,
        'total' => 1000.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => '',
        'pdf_url' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [[
        'id' => 1,
        'org_id' => 62,
        'invoice_id' => 2,
        'journal_id' => 91,
        'amount' => 300.0,
        'date' => '2026-04-02',
        'created_at' => current_time('mysql'),
    ]]);

    $firstNote = VyRestInvoices::create_invoice_note(kbs_test_make_request('POST', '/vy/v1/invoices/2/notes', ['id' => 2], [
        'note_type' => 'CREDIT',
        'note_date' => '2026-04-03',
        'amount' => 600,
        'reason' => 'Commercial settlement',
    ]));
    $firstNoteData = kbs_assert_response($firstNote, 201)->get_data();

    kbs_assert_same('CRN/2026/0001', $firstNoteData['note']['note_number'] ?? null);
    kbs_assert_same(100.0, (float) ($firstNoteData['financials']['balance_due'] ?? 0));
    kbs_assert_same(600.0, (float) ($firstNoteData['financials']['credit_total'] ?? 0));

    $secondNote = VyRestInvoices::create_invoice_note(kbs_test_make_request('POST', '/vy/v1/invoices/2/notes', ['id' => 2], [
        'note_type' => 'CREDIT',
        'note_date' => '2026-04-04',
        'amount' => 200,
        'reason' => 'Too much credit',
    ]));
    kbs_assert_wp_error($secondNote, 'vy_credit_too_large', 400);
});

kbs_test('payment promises supersede prior open promises and are auto-kept when the matching payment is recorded', function (): void {
    kbs_test_add_user([
        'ID' => 603,
        'user_email' => 'promise@example.com',
        'display_name' => 'Collections Promise User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(603);
    kbs_test_set_user_meta(603, 'vy_active_org_id', 63);
    kbs_test_seed_org_membership(603, 63, 'company_admin', true, 'Promise Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 3,
        'org_id' => 63,
        'contact_id' => null,
        'invoice_number' => 'INV-PROMISE-001',
        'customer_name' => 'Promise Customer',
        'customer_email' => 'promise-customer@example.com',
        'customer_phone' => '7777770000',
        'date' => '2026-04-01',
        'due_date' => '2026-04-08',
        'currency' => 'INR',
        'subtotal' => 1000.0,
        'tax_total' => 0.0,
        'total' => 1000.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => '',
        'pdf_url' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_accounts', [
        [
            'id' => 101,
            'org_id' => 63,
            'code' => 'BANK-P',
            'name' => 'Promise Bank',
            'type' => 'ASSET',
            'sub_type' => 'BANK',
            'currency' => 'INR',
            'is_system' => 0,
            'status' => 'ACTIVE',
            'opening_balance' => 0,
            'opening_balance_type' => 'DEBIT',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 102,
            'org_id' => 63,
            'code' => 'REV-P',
            'name' => 'Promise Revenue',
            'type' => 'INCOME',
            'sub_type' => 'OPERATING',
            'currency' => 'INR',
            'is_system' => 0,
            'status' => 'ACTIVE',
            'opening_balance' => 0,
            'opening_balance_type' => 'CREDIT',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);

    $firstPromise = VyRestInvoices::create_invoice_promise(kbs_test_make_request('POST', '/vy/v1/invoices/3/promises', ['id' => 3], [
        'promised_date' => '2026-04-10',
        'promised_amount' => 400,
        'notes' => 'Customer committed for Friday',
    ]));
    $firstPromiseData = kbs_assert_response($firstPromise, 201)->get_data();
    kbs_assert_same('OPEN', $firstPromiseData['promise']['status'] ?? null);

    $secondPromise = VyRestInvoices::create_invoice_promise(kbs_test_make_request('POST', '/vy/v1/invoices/3/promises', ['id' => 3], [
        'promised_date' => '2026-04-15',
        'promised_amount' => 300,
        'notes' => 'Revised customer commitment',
    ]));
    $secondPromiseData = kbs_assert_response($secondPromise, 201)->get_data();
    kbs_assert_same('OPEN', $secondPromiseData['promise']['status'] ?? null);

    $promises = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_promises');
    kbs_assert_same('SUPERSEDED', $promises[0]['status'] ?? null);
    kbs_assert_same('OPEN', $promises[1]['status'] ?? null);

    $payment = VyRestInvoices::pay_invoice(kbs_test_make_request('POST', '/vy/v1/invoices/3/pay', ['id' => 3], [
        'amount' => 300,
        'date' => '2026-04-15',
        'to_account_id' => 101,
        'income_account_id' => 102,
    ]));
    kbs_assert_response($payment, 201);

    $promisesAfterPayment = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_promises');
    kbs_assert_same('KEPT', $promisesAfterPayment[1]['status'] ?? null);
});
