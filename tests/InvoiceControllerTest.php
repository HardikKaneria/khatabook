<?php

use KBS\Api\VyRestInvoices;

kbs_test('invoice create persists the invoice, items, and customer contact for the active org', function (): void {
    kbs_test_add_user([
        'ID' => 301,
        'user_email' => 'invoices@example.com',
        'display_name' => 'Invoice Owner',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(301);
    kbs_test_set_user_meta(301, 'vy_active_org_id', 31);
    kbs_test_seed_org_membership(301, 31, 'company_admin', true, 'Invoice Org');

    $request = kbs_test_make_request('POST', '/vy/v1/invoices', [], [
        'invoice_number' => 'INV-TEST-001',
        'customer_name' => 'Beta Customer',
        'customer_email' => 'customer@example.com',
        'customer_phone' => '9999999999',
        'date' => '2026-04-03',
        'status' => 'DRAFT',
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => 2,
                'unit_price' => 1000,
                'tax_rate' => 18,
            ],
            [
                'description' => 'Support',
                'quantity' => 1,
                'unit_price' => 500,
                'tax_rate' => 0,
            ],
        ],
    ]);

    $result = VyRestInvoices::create_invoice($request);
    $response = kbs_assert_response($result, 201);
    $data = $response->get_data();

    kbs_assert_true((int) ($data['id'] ?? 0) > 0, 'Invoice creation should return a persisted invoice ID.');

    $contacts = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_contacts');
    $invoices = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoices');
    $items = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items');
    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');

    kbs_assert_count(1, $contacts, 'Invoice creation should create a customer contact when no contact_id is supplied.');
    kbs_assert_count(1, $invoices, 'One invoice row should be created.');
    kbs_assert_count(2, $items, 'Each submitted invoice item should be persisted.');
    kbs_assert_count(1, $history, 'Invoice creation should write an audit history row.');

    $invoice = $invoices[0];
    $historyRow = $history[0];
    kbs_assert_same(31, (int) ($invoice['org_id'] ?? 0));
    kbs_assert_same('INV-TEST-001', $invoice['invoice_number'] ?? null);
    kbs_assert_same('Beta Customer', $invoice['customer_name'] ?? null);
    kbs_assert_same('DRAFT', $invoice['status'] ?? null);
    kbs_assert_same('minimal-clean', $invoice['template_id'] ?? null);
    kbs_assert_same(2500.0, (float) ($invoice['subtotal'] ?? 0));
    kbs_assert_same(360.0, (float) ($invoice['tax_total'] ?? 0));
    kbs_assert_same(2860.0, (float) ($invoice['total'] ?? 0));
    kbs_assert_same('invoice', $historyRow['record_type'] ?? null);
    kbs_assert_same('created', $historyRow['action'] ?? null);
});

kbs_test('invoice update blocks invoices that already have recorded payments', function (): void {
    kbs_test_add_user([
        'ID' => 302,
        'user_email' => 'locked@example.com',
        'display_name' => 'Locked Invoice User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(302);
    kbs_test_set_user_meta(302, 'vy_active_org_id', 32);
    kbs_test_seed_org_membership(302, 32, 'company_admin', true, 'Locked Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 1,
        'org_id' => 32,
        'contact_id' => null,
        'invoice_number' => 'INV-LOCKED-001',
        'customer_name' => 'Locked Customer',
        'customer_email' => 'locked-customer@example.com',
        'customer_phone' => '7777777777',
        'date' => '2026-04-03',
        'due_date' => '2026-04-10',
        'currency' => 'INR',
        'subtotal' => 1000.0,
        'tax_total' => 0.0,
        'total' => 1000.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => '',
        'pdf_url' => 'https://example.test/invoice.pdf',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [[
        'id' => 1,
        'org_id' => 32,
        'invoice_id' => 1,
        'journal_id' => 15,
        'amount' => 250.0,
        'date' => '2026-04-04',
        'created_at' => current_time('mysql'),
    ]]);

    $request = kbs_test_make_request('PUT', '/vy/v1/invoices/1', ['id' => 1], [
        'customer_name' => 'Locked Customer',
        'items' => [[
            'description' => 'Updated line',
            'quantity' => 1,
            'unit_price' => 1200,
            'tax_rate' => 0,
        ]],
    ]);

    $result = VyRestInvoices::update_invoice($request);
    kbs_assert_wp_error($result, 'vy_invoice_locked', 400);
});

kbs_test('invoice update replaces invoice items and clears stale pdf references for editable invoices', function (): void {
    kbs_test_add_user([
        'ID' => 303,
        'user_email' => 'editable@example.com',
        'display_name' => 'Editable Invoice User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(303);
    kbs_test_set_user_meta(303, 'vy_active_org_id', 33);
    kbs_test_seed_org_membership(303, 33, 'company_admin', true, 'Editable Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 2,
        'org_id' => 33,
        'contact_id' => null,
        'invoice_number' => 'INV-EDIT-001',
        'customer_name' => 'Editable Customer',
        'customer_email' => 'editable-customer@example.com',
        'customer_phone' => '6666666666',
        'date' => '2026-04-03',
        'due_date' => '2026-04-10',
        'currency' => 'INR',
        'subtotal' => 1000.0,
        'tax_total' => 0.0,
        'total' => 1000.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => 'Old notes',
        'pdf_url' => 'https://example.test/old.pdf',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items', [[
        'id' => 1,
        'org_id' => 33,
        'invoice_id' => 2,
        'description' => 'Old line',
        'quantity' => 1,
        'unit_price' => 1000.0,
        'tax_rate' => 0.0,
        'tax_amount' => 0.0,
        'tax_type' => 'GST',
        'line_total' => 1000.0,
        'created_at' => current_time('mysql'),
    ]]);

    $request = kbs_test_make_request('PUT', '/vy/v1/invoices/2', ['id' => 2], [
        'customer_name' => 'Updated Customer',
        'customer_email' => 'updated@example.com',
        'customer_phone' => '1231231234',
        'date' => '2026-04-05',
        'status' => 'SENT',
        'notes' => 'Updated notes',
        'items' => [
            [
                'description' => 'New line one',
                'quantity' => 2,
                'unit_price' => 400,
                'tax_rate' => 18,
            ],
            [
                'description' => 'New line two',
                'quantity' => 1,
                'unit_price' => 300,
                'tax_rate' => 0,
            ],
        ],
    ]);

    $result = VyRestInvoices::update_invoice($request);
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same(true, $data['success'] ?? null);
    kbs_assert_true(array_key_exists('pdf_url', $data), 'Invoice update responses should always include the pdf field.');
    kbs_assert_same(null, $data['pdf_url']);

    $invoice = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoices')[0];
    $items = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items');
    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');

    kbs_assert_same('INV-EDIT-001', $invoice['invoice_number'] ?? null);
    kbs_assert_same('Updated Customer', $invoice['customer_name'] ?? null);
    kbs_assert_same('Updated notes', $invoice['notes'] ?? null);
    kbs_assert_true(array_key_exists('pdf_url', $invoice), 'Updated invoices should keep the pdf_url field.');
    kbs_assert_same(null, $invoice['pdf_url']);
    kbs_assert_same(1100.0, (float) ($invoice['subtotal'] ?? 0));
    kbs_assert_same(144.0, (float) ($invoice['tax_total'] ?? 0));
    kbs_assert_same(1244.0, (float) ($invoice['total'] ?? 0));
    kbs_assert_count(2, $items, 'Invoice update should replace old invoice items with the submitted set.');
    kbs_assert_count(1, $history, 'Invoice update should write an audit history row.');
    kbs_assert_same('updated', $history[0]['action'] ?? null);
});

kbs_test('invoice create rolls back partial writes when an invoice item insert fails', function (): void {
    kbs_test_add_user([
        'ID' => 305,
        'user_email' => 'invoice-rollback@example.com',
        'display_name' => 'Rollback User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(305);
    kbs_test_set_user_meta(305, 'vy_active_org_id', 35);
    kbs_test_seed_org_membership(305, 35, 'company_admin', true, 'Rollback Org');

    kbs_test_fail_next_insert($GLOBALS['wpdb']->prefix . 'vy_invoice_items');

    $request = kbs_test_make_request('POST', '/vy/v1/invoices', [], [
        'invoice_number' => 'INV-ROLLBACK-001',
        'customer_name' => 'Rollback Customer',
        'customer_email' => 'rollback@example.com',
        'date' => '2026-04-03',
        'status' => 'SENT',
        'items' => [[
            'description' => 'Implementation',
            'quantity' => 1,
            'unit_price' => 500,
            'tax_rate' => 0,
        ]],
    ]);

    $result = VyRestInvoices::create_invoice($request);
    kbs_assert_wp_error($result, 'vy_invoice_items_insert_failed', 500);

    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_contacts'), 'Invoice rollback should remove the transient customer contact.');
    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoices'), 'Invoice rollback should remove the invoice row.');
    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items'), 'Invoice rollback should remove invoice items.');
    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history'), 'Invoice rollback should not leave audit history behind.');
});

kbs_test('invoice payment writes payment audit history and appears in invoice detail history', function (): void {
    kbs_test_add_user([
        'ID' => 304,
        'user_email' => 'collector@example.com',
        'display_name' => 'Collector',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(304);
    kbs_test_set_user_meta(304, 'vy_active_org_id', 34);
    kbs_test_seed_org_membership(304, 34, 'company_admin', true, 'Collections Org');
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 7,
        'org_id' => 34,
        'contact_id' => null,
        'invoice_number' => 'INV-PAY-001',
        'customer_name' => 'Receivable Customer',
        'customer_email' => 'receivable@example.com',
        'customer_phone' => '5555555555',
        'date' => '2026-04-03',
        'due_date' => '2026-04-10',
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
            'id' => 81,
            'org_id' => 34,
            'code' => 'BANK-1',
            'name' => 'Bank',
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
            'id' => 82,
            'org_id' => 34,
            'code' => 'REV-1',
            'name' => 'Revenue',
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

    $payment = VyRestInvoices::pay_invoice(kbs_test_make_request('POST', '/vy/v1/invoices/7/pay', ['id' => 7], [
        'amount' => 400,
        'date' => '2026-04-04',
        'to_account_id' => 81,
        'income_account_id' => 82,
    ]));
    $paymentResponse = kbs_assert_response($payment, 201);
    $paymentData = $paymentResponse->get_data();
    kbs_assert_true((int) ($paymentData['payment_id'] ?? 0) > 0, 'Invoice payment should return the created payment ID.');

    $history = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_record_history');
    kbs_assert_count(1, $history, 'Invoice payment should write a payment audit entry.');
    kbs_assert_same('payment', $history[0]['record_type'] ?? null);
    kbs_assert_same('invoice', $history[0]['related_record_type'] ?? null);

    $detail = VyRestInvoices::get_invoice(kbs_test_make_request('GET', '/vy/v1/invoices/7', ['id' => 7]));
    $detailResponse = kbs_assert_response($detail, 200);
    $detailData = $detailResponse->get_data();

    kbs_assert_count(1, $detailData['payments'] ?? [], 'Invoice detail should still expose the recorded payment.');
    kbs_assert_count(1, $detailData['history'] ?? [], 'Invoice detail should expose related payment history.');
    kbs_assert_same('recorded', $detailData['history'][0]['action'] ?? null);

    $payments = VyRestInvoices::list_payments(kbs_test_make_request('GET', '/vy/v1/payments'));
    $paymentsResponse = kbs_assert_response($payments, 200);
    $paymentsData = $paymentsResponse->get_data();

    kbs_assert_count(1, $paymentsData['data'] ?? [], 'Payments list should expose the recorded payment.');
    kbs_assert_same('INV-PAY-001', $paymentsData['data'][0]['invoice']['invoice_number'] ?? null);
    kbs_assert_same('Bank', $paymentsData['data'][0]['account']['name'] ?? null);
});

kbs_test('invoice payment rolls back journal writes when payment persistence fails', function (): void {
    kbs_test_add_user([
        'ID' => 306,
        'user_email' => 'payment-rollback@example.com',
        'display_name' => 'Payment Rollback User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(306);
    kbs_test_set_user_meta(306, 'vy_active_org_id', 36);
    kbs_test_seed_org_membership(306, 36, 'company_admin', true, 'Payment Rollback Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [[
        'id' => 8,
        'org_id' => 36,
        'contact_id' => null,
        'invoice_number' => 'INV-ROLLBACK-PAY',
        'customer_name' => 'Rollback Customer',
        'customer_email' => 'rollback-pay@example.com',
        'customer_phone' => '5555555555',
        'date' => '2026-04-03',
        'due_date' => '2026-04-10',
        'currency' => 'INR',
        'subtotal' => 1200.0,
        'tax_total' => 0.0,
        'total' => 1200.0,
        'status' => 'SENT',
        'template_id' => 'minimal-clean',
        'notes' => '',
        'pdf_url' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_accounts', [
        [
            'id' => 83,
            'org_id' => 36,
            'code' => 'BANK-RB',
            'name' => 'Rollback Bank',
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
            'id' => 84,
            'org_id' => 36,
            'code' => 'REV-RB',
            'name' => 'Rollback Revenue',
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

    kbs_test_fail_next_insert($GLOBALS['wpdb']->prefix . 'vy_invoice_payments');

    $result = VyRestInvoices::pay_invoice(kbs_test_make_request('POST', '/vy/v1/invoices/8/pay', ['id' => 8], [
        'amount' => 500,
        'date' => '2026-04-06',
        'to_account_id' => 83,
        'income_account_id' => 84,
    ]));
    kbs_assert_wp_error($result, 'vy_payment_insert_failed', 500);

    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments'), 'Failed payment writes should not leave payment rows behind.');
    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_journal_entries'), 'Failed payment writes should roll back journal entries.');
    kbs_assert_count(0, kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_journal_lines'), 'Failed payment writes should roll back journal lines.');
    kbs_assert_same('SENT', kbs_test_get_table($GLOBALS['wpdb']->prefix . 'vy_invoices')[0]['status'] ?? null);
});

kbs_test('invoice detail exposes rule-based risk flags before send or PDF actions', function (): void {
    kbs_test_add_user([
        'ID' => 307,
        'user_email' => 'risk@example.com',
        'display_name' => 'Risk User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(307);
    kbs_test_set_user_meta(307, 'vy_active_org_id', 37);
    kbs_test_seed_org_membership(307, 37, 'company_admin', true, 'Risk Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        [
            'id' => 9,
            'org_id' => 37,
            'contact_id' => null,
            'invoice_number' => 'INV-RISK-001',
        'customer_name' => 'Risk Customer',
        'customer_email' => '',
        'customer_phone' => '9990001111',
        'date' => '2020-04-01',
        'due_date' => '2020-04-05',
            'currency' => 'INR',
            'subtotal' => 1000.0,
            'tax_total' => 0.0,
            'total' => 1500.0,
            'status' => 'DRAFT',
            'template_id' => 'minimal-clean',
            'notes' => '',
            'pdf_url' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 10,
            'org_id' => 37,
            'contact_id' => null,
            'invoice_number' => 'INV-HIST-001',
            'customer_name' => 'Risk Customer',
            'customer_email' => 'risk-history@example.com',
            'customer_phone' => '',
            'date' => '2026-03-01',
            'due_date' => '2026-03-08',
            'currency' => 'INR',
            'subtotal' => 400.0,
            'tax_total' => 0.0,
            'total' => 400.0,
            'status' => 'PAID',
            'template_id' => 'minimal-clean',
            'notes' => '',
            'pdf_url' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 11,
            'org_id' => 37,
            'contact_id' => null,
            'invoice_number' => 'INV-HIST-002',
            'customer_name' => 'Risk Customer',
            'customer_email' => 'risk-history@example.com',
            'customer_phone' => '',
            'date' => '2026-02-15',
            'due_date' => '2026-02-22',
            'currency' => 'INR',
            'subtotal' => 450.0,
            'tax_total' => 0.0,
            'total' => 450.0,
            'status' => 'PAID',
            'template_id' => 'minimal-clean',
            'notes' => '',
            'pdf_url' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 12,
            'org_id' => 37,
            'contact_id' => null,
            'invoice_number' => 'INV-HIST-003',
            'customer_name' => 'Risk Customer',
            'customer_email' => 'risk-history@example.com',
            'customer_phone' => '',
            'date' => '2026-01-10',
            'due_date' => '2026-01-17',
            'currency' => 'INR',
            'subtotal' => 500.0,
            'tax_total' => 0.0,
            'total' => 500.0,
            'status' => 'PAID',
            'template_id' => 'minimal-clean',
            'notes' => '',
            'pdf_url' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_items', [
        [
            'id' => 1,
            'org_id' => 37,
            'invoice_id' => 9,
            'description' => 'Retainer',
            'quantity' => 1,
            'unit_price' => 1000.0,
            'tax_rate' => 0.0,
            'tax_amount' => 0.0,
            'tax_type' => 'GST',
            'line_total' => 1000.0,
            'created_at' => current_time('mysql'),
        ],
    ]);

    $detail = VyRestInvoices::get_invoice(kbs_test_make_request('GET', '/vy/v1/invoices/9', ['id' => 9]));
    $response = kbs_assert_response($detail, 200);
    $data = $response->get_data();

    kbs_assert_same('critical', $data['risk_summary']['level'] ?? null);
    kbs_assert_same(false, $data['risk_summary']['ready_to_send'] ?? true);
    kbs_assert_true((int) ($data['risk_summary']['issue_count'] ?? 0) >= 3, 'Risk summary should expose multiple actionable checks.');

    $codes = array_map(static fn(array $issue): string => (string) ($issue['code'] ?? ''), $data['risk_summary']['issues'] ?? []);
    kbs_assert_true(in_array('total_mismatch', $codes, true), 'Invoice risk checks should flag stored-total mismatch.');
    kbs_assert_true(in_array('missing_customer_email', $codes, true), 'Invoice risk checks should flag a missing customer email.');
    kbs_assert_true(in_array('draft_past_due', $codes, true), 'Invoice risk checks should flag stale draft due dates.');
    kbs_assert_true(in_array('amount_anomaly', $codes, true), 'Invoice risk checks should flag unusual totals for the same customer.');
});
