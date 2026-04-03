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
