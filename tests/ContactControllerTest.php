<?php

use KBS\Api\VyRestContacts;

kbs_test('customer statement returns opening balance, range activity, and outstanding invoices from live vy data', function (): void {
    kbs_test_add_user([
        'ID' => 701,
        'user_email' => 'contact-statement@example.com',
        'display_name' => 'Contact Statement User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(701);
    kbs_test_set_user_meta(701, 'vy_active_org_id', 71);
    kbs_test_seed_org_membership(701, 71, 'company_admin', true, 'Statement Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_contacts', [[
        'id' => 1,
        'org_id' => 71,
        'type' => 'CUSTOMER',
        'name' => 'Acme Retail',
        'email' => 'acme@example.com',
        'phone' => '9999999999',
        'gstin' => '',
        'billing_address' => '',
        'shipping_address' => '',
        'notes' => '',
        'status' => 'ACTIVE',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        [
            'id' => 1,
            'org_id' => 71,
            'contact_id' => 1,
            'invoice_number' => 'INV-001',
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-03-25',
            'due_date' => '2026-03-30',
            'total' => 1000.0,
            'status' => 'SENT',
        ],
        [
            'id' => 2,
            'org_id' => 71,
            'contact_id' => 1,
            'invoice_number' => 'INV-002',
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-04-02',
            'due_date' => '2026-04-10',
            'total' => 600.0,
            'status' => 'PARTIAL',
        ],
        [
            'id' => 3,
            'org_id' => 71,
            'contact_id' => 1,
            'invoice_number' => 'INV-003',
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-04-15',
            'due_date' => '2026-04-15',
            'total' => 400.0,
            'status' => 'PAID',
        ],
        [
            'id' => 4,
            'org_id' => 71,
            'contact_id' => 1,
            'invoice_number' => 'INV-DRAFT',
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-04-18',
            'due_date' => '2026-04-18',
            'total' => 999.0,
            'status' => 'DRAFT',
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [
        [
            'id' => 1,
            'org_id' => 71,
            'invoice_id' => 1,
            'journal_id' => 91,
            'amount' => 200.0,
            'date' => '2026-03-28',
            'created_at' => current_time('mysql'),
        ],
        [
            'id' => 2,
            'org_id' => 71,
            'invoice_id' => 1,
            'journal_id' => 92,
            'amount' => 100.0,
            'date' => '2026-04-05',
            'created_at' => current_time('mysql'),
        ],
        [
            'id' => 3,
            'org_id' => 71,
            'invoice_id' => 2,
            'journal_id' => 93,
            'amount' => 250.0,
            'date' => '2026-04-12',
            'created_at' => current_time('mysql'),
        ],
        [
            'id' => 4,
            'org_id' => 71,
            'invoice_id' => 3,
            'journal_id' => 94,
            'amount' => 400.0,
            'date' => '2026-04-15',
            'created_at' => current_time('mysql'),
        ],
    ]);

    $response = VyRestContacts::get_statement(kbs_test_make_request('GET', '/vy/v1/contacts/1/statement', [
        'id' => 1,
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]));
    $data = kbs_assert_response($response, 200)->get_data();

    kbs_assert_same('Acme Retail', $data['contact']['name'] ?? null);
    kbs_assert_same(800.0, (float) ($data['summary']['opening_balance'] ?? 0));
    kbs_assert_same(1000.0, (float) ($data['summary']['invoiced_total'] ?? 0));
    kbs_assert_same(750.0, (float) ($data['summary']['payments_total'] ?? 0));
    kbs_assert_same(1050.0, (float) ($data['summary']['closing_balance'] ?? 0));
    kbs_assert_same(1050.0, (float) ($data['summary']['outstanding_balance'] ?? 0));
    kbs_assert_same(1050.0, (float) ($data['summary']['overdue_balance'] ?? 0));
    kbs_assert_same(2, (int) ($data['summary']['open_invoice_count'] ?? 0));

    $entries = $data['entries'] ?? [];
    kbs_assert_count(5, $entries, 'Statement should expose invoice and payment entries inside the selected range.');
    kbs_assert_same('2026-04-02', $entries[0]['date'] ?? null);
    kbs_assert_same('INVOICE', $entries[0]['entry_type'] ?? null);
    kbs_assert_same('2026-04-15', $entries[4]['date'] ?? null);
    kbs_assert_same(1050.0, (float) ($entries[4]['running_balance'] ?? 0));

    $openInvoices = $data['open_invoices'] ?? [];
    kbs_assert_count(2, $openInvoices, 'Only invoices with an unpaid balance should stay open.');
    kbs_assert_same('INV-001', $openInvoices[0]['invoice_number'] ?? null);
    kbs_assert_same(700.0, (float) ($openInvoices[0]['balance_due'] ?? 0));
});

kbs_test('customer statement rejects vendor-only contacts', function (): void {
    kbs_test_add_user([
        'ID' => 702,
        'user_email' => 'vendor-statement@example.com',
        'display_name' => 'Vendor Statement User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(702);
    kbs_test_set_user_meta(702, 'vy_active_org_id', 72);
    kbs_test_seed_org_membership(702, 72, 'company_admin', true, 'Vendor Statement Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_contacts', [[
        'id' => 2,
        'org_id' => 72,
        'type' => 'VENDOR',
        'name' => 'Only Vendor',
        'email' => 'vendor@example.com',
        'phone' => '8888888888',
        'gstin' => '',
        'billing_address' => '',
        'shipping_address' => '',
        'notes' => '',
        'status' => 'ACTIVE',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]]);

    $result = VyRestContacts::get_statement(kbs_test_make_request('GET', '/vy/v1/contacts/2/statement', [
        'id' => 2,
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]));

    kbs_assert_wp_error($result, 'vy_contact_not_customer', 400);
});
