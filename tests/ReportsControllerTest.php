<?php

use KBS\Api\VyRestReports;

kbs_test('receivables summary reports outstanding balances, aging, top customers, and invoice status mix', function (): void {
    kbs_test_add_user([
        'ID' => 501,
        'user_email' => 'reports@example.com',
        'display_name' => 'Reports User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(501);
    kbs_test_set_user_meta(501, 'vy_active_org_id', 51);
    kbs_test_seed_org_membership(501, 51, 'company_admin', true, 'Reports Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        [
            'id' => 1,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'total' => 1000.0,
            'status' => 'SENT',
        ],
        [
            'id' => 2,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'Bright Traders',
            'customer_email' => 'bright@example.com',
            'date' => '2026-04-02',
            'due_date' => '2026-04-10',
            'total' => 800.0,
            'status' => 'PARTIAL',
        ],
        [
            'id' => 3,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'Acme Retail',
            'customer_email' => 'acme@example.com',
            'date' => '2026-04-03',
            'due_date' => '2026-04-03',
            'total' => 600.0,
            'status' => 'PAID',
        ],
        [
            'id' => 4,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'Draft Customer',
            'customer_email' => 'draft@example.com',
            'date' => '2026-04-04',
            'due_date' => '2026-04-12',
            'total' => 400.0,
            'status' => 'DRAFT',
        ],
        [
            'id' => 5,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'Void Customer',
            'customer_email' => 'void@example.com',
            'date' => '2026-04-05',
            'due_date' => '2026-04-15',
            'total' => 700.0,
            'status' => 'VOID',
        ],
        [
            'id' => 6,
            'org_id' => 51,
            'contact_id' => null,
            'customer_name' => 'City Stores',
            'customer_email' => 'city@example.com',
            'date' => '2026-05-01',
            'due_date' => '2026-05-10',
            'total' => 1200.0,
            'status' => 'SENT',
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [
        [
            'id' => 1,
            'org_id' => 51,
            'invoice_id' => 2,
            'journal_id' => 11,
            'amount' => 300.0,
            'date' => '2026-04-07',
            'created_at' => current_time('mysql'),
        ],
        [
            'id' => 2,
            'org_id' => 51,
            'invoice_id' => 3,
            'journal_id' => 12,
            'amount' => 600.0,
            'date' => '2026-04-03',
            'created_at' => current_time('mysql'),
        ],
    ]);

    $response = VyRestReports::receivables_summary(kbs_test_make_request('GET', '/vy/v1/reports/receivables-summary', [
        'from' => '2026-04-01',
        'to' => '2026-04-30',
        'as_of' => '2026-04-20',
    ]));
    $data = kbs_assert_response($response, 200)->get_data();

    kbs_assert_same('2026-04-20', $data['as_of'] ?? null);
    kbs_assert_same(2, (int) ($data['open_invoice_count'] ?? 0));
    kbs_assert_same(1500.0, (float) ($data['outstanding_amount'] ?? 0));
    kbs_assert_same(2, (int) ($data['overdue_count'] ?? 0));
    kbs_assert_same(1500.0, (float) ($data['overdue_amount'] ?? 0));
    kbs_assert_same(12.5, (float) ($data['average_days_overdue'] ?? 0));

    $aging = [];
    foreach (($data['aging_buckets'] ?? []) as $bucket) {
        $aging[$bucket['bucket']] = $bucket;
    }
    kbs_assert_same(0, (int) ($aging['current']['count'] ?? 0));
    kbs_assert_same(0.0, (float) ($aging['current']['amount'] ?? 0));
    kbs_assert_same(2, (int) ($aging['1_30']['count'] ?? 0));
    kbs_assert_same(1500.0, (float) ($aging['1_30']['amount'] ?? 0));

    $status = [];
    foreach (($data['invoice_status'] ?? []) as $row) {
        $status[$row['status']] = $row;
    }
    kbs_assert_same(1, (int) ($status['DRAFT']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['SENT']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['PARTIAL']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['PAID']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['VOID']['count'] ?? 0));

    $topCustomers = $data['top_customers'] ?? [];
    kbs_assert_true(count($topCustomers) >= 2, 'Receivables summary should expose top customers.');
    kbs_assert_same('Acme Retail', $topCustomers[0]['label'] ?? null);
    kbs_assert_same(1000.0, (float) ($topCustomers[0]['outstanding_amount'] ?? 0));
});

kbs_test('monthly trends group live invoice and expense totals by month', function (): void {
    kbs_test_add_user([
        'ID' => 502,
        'user_email' => 'trend@example.com',
        'display_name' => 'Trend User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(502);
    kbs_test_set_user_meta(502, 'vy_active_org_id', 52);
    kbs_test_seed_org_membership(502, 52, 'company_admin', true, 'Trend Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        ['id' => 1, 'org_id' => 52, 'date' => '2026-04-02', 'due_date' => '2026-04-10', 'total' => 1500.0, 'status' => 'SENT'],
        ['id' => 2, 'org_id' => 52, 'date' => '2026-04-11', 'due_date' => '2026-04-20', 'total' => 500.0, 'status' => 'PARTIAL'],
        ['id' => 3, 'org_id' => 52, 'date' => '2026-04-18', 'due_date' => '2026-04-25', 'total' => 900.0, 'status' => 'VOID'],
        ['id' => 4, 'org_id' => 52, 'date' => '2026-05-06', 'due_date' => '2026-05-20', 'total' => 2200.0, 'status' => 'PAID'],
        ['id' => 5, 'org_id' => 52, 'date' => '2026-05-09', 'due_date' => '2026-05-19', 'total' => 800.0, 'status' => 'DRAFT'],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [
        ['id' => 1, 'org_id' => 52, 'expense_date' => '2026-04-03', 'amount' => 300.0, 'status' => 'POSTED'],
        ['id' => 2, 'org_id' => 52, 'expense_date' => '2026-05-01', 'amount' => 450.0, 'status' => 'POSTED'],
        ['id' => 3, 'org_id' => 52, 'expense_date' => '2026-05-04', 'amount' => 999.0, 'status' => 'ARCHIVED'],
    ]);

    $response = VyRestReports::monthly_trends(kbs_test_make_request('GET', '/vy/v1/reports/monthly-trends', [
        'from' => '2026-04-01',
        'to' => '2026-05-31',
    ]));
    $data = kbs_assert_response($response, 200)->get_data();

    $months = [];
    foreach (($data['months'] ?? []) as $month) {
        $months[$month['month']] = $month;
    }

    kbs_assert_same(2000.0, (float) ($months['2026-04']['revenue_total'] ?? 0));
    kbs_assert_same(300.0, (float) ($months['2026-04']['expense_total'] ?? 0));
    kbs_assert_same(2, (int) ($months['2026-04']['invoice_count'] ?? 0));
    kbs_assert_same(1, (int) ($months['2026-04']['expense_count'] ?? 0));

    kbs_assert_same(2200.0, (float) ($months['2026-05']['revenue_total'] ?? 0));
    kbs_assert_same(450.0, (float) ($months['2026-05']['expense_total'] ?? 0));
    kbs_assert_same(1, (int) ($months['2026-05']['invoice_count'] ?? 0));
    kbs_assert_same(1, (int) ($months['2026-05']['expense_count'] ?? 0));
});

kbs_test('payables summary reports open vendor bills, aging, and top vendors from the live expense model', function (): void {
    kbs_test_add_user([
        'ID' => 503,
        'user_email' => 'payables@example.com',
        'display_name' => 'Payables User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(503);
    kbs_test_set_user_meta(503, 'vy_active_org_id', 53);
    kbs_test_seed_org_membership(503, 53, 'company_admin', true, 'Payables Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [
        [
            'id' => 1,
            'org_id' => 53,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'reference_number' => 'BILL-001',
            'payee' => 'Alpha Supplies',
            'amount' => 1200.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => null,
        ],
        [
            'id' => 2,
            'org_id' => 53,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-02',
            'due_date' => '2026-04-20',
            'reference_number' => 'BILL-002',
            'payee' => 'Beta Services',
            'amount' => 800.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => null,
        ],
        [
            'id' => 3,
            'org_id' => 53,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-03',
            'due_date' => '2026-04-09',
            'reference_number' => 'BILL-003',
            'payee' => 'Alpha Supplies',
            'amount' => 500.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => 700,
        ],
        [
            'id' => 4,
            'org_id' => 53,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-04',
            'due_date' => '2026-04-12',
            'reference_number' => 'BILL-004',
            'payee' => 'Gamma Logistics',
            'amount' => 950.0,
            'currency' => 'INR',
            'status' => 'ARCHIVED',
            'payment_journal_id' => null,
        ],
        [
            'id' => 5,
            'org_id' => 53,
            'document_type' => 'EXPENSE',
            'expense_date' => '2026-04-04',
            'due_date' => null,
            'reference_number' => '',
            'payee' => 'Taxi Vendor',
            'amount' => 300.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => null,
        ],
    ]);

    $response = VyRestReports::payables_summary(kbs_test_make_request('GET', '/vy/v1/reports/payables-summary', [
        'from' => '2026-04-01',
        'to' => '2026-04-30',
        'as_of' => '2026-04-10',
    ]));
    $data = kbs_assert_response($response, 200)->get_data();

    kbs_assert_same('2026-04-10', $data['as_of'] ?? null);
    kbs_assert_same(2, (int) ($data['open_bill_count'] ?? 0));
    kbs_assert_same(2000.0, (float) ($data['outstanding_amount'] ?? 0));
    kbs_assert_same(1200.0, (float) ($data['overdue_amount'] ?? 0));
    kbs_assert_same(1, (int) ($data['overdue_count'] ?? 0));
    kbs_assert_same(800.0, (float) ($data['upcoming_amount'] ?? 0));

    $aging = [];
    foreach (($data['aging_buckets'] ?? []) as $bucket) {
        $aging[$bucket['bucket']] = $bucket;
    }
    kbs_assert_same(1, (int) ($aging['1_30']['count'] ?? 0));
    kbs_assert_same(1200.0, (float) ($aging['1_30']['amount'] ?? 0));
    kbs_assert_same(1, (int) ($aging['current']['count'] ?? 0));
    kbs_assert_same(800.0, (float) ($aging['current']['amount'] ?? 0));

    $status = [];
    foreach (($data['bill_status'] ?? []) as $row) {
        $status[$row['status']] = $row;
    }
    kbs_assert_same(2, (int) ($status['OPEN']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['PAID']['count'] ?? 0));
    kbs_assert_same(1, (int) ($status['ARCHIVED']['count'] ?? 0));

    $topVendors = $data['top_vendors'] ?? [];
    kbs_assert_true(count($topVendors) >= 2, 'Payables summary should expose top vendors.');
    kbs_assert_same('Alpha Supplies', $topVendors[0]['label'] ?? null);
    kbs_assert_same(1200.0, (float) ($topVendors[0]['outstanding_amount'] ?? 0));
    kbs_assert_same(1200.0, (float) ($topVendors[0]['overdue_amount'] ?? 0));
});

kbs_test('billing health score and owner daily brief are grounded in live receivable, payable, promise, and daily movement data', function (): void {
    kbs_test_add_user([
        'ID' => 504,
        'user_email' => 'health@example.com',
        'display_name' => 'Health User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(504);
    kbs_test_set_user_meta(504, 'vy_active_org_id', 54);
    kbs_test_seed_org_membership(504, 54, 'company_admin', true, 'Health Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        [
            'id' => 1,
            'org_id' => 54,
            'contact_id' => null,
            'invoice_number' => 'INV-H-001',
            'customer_name' => 'Alpha Customer',
            'customer_email' => 'alpha-health@example.com',
            'date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'total' => 1000.0,
            'status' => 'SENT',
        ],
        [
            'id' => 2,
            'org_id' => 54,
            'contact_id' => null,
            'invoice_number' => 'INV-H-002',
            'customer_name' => 'Beta Customer',
            'customer_email' => 'beta-health@example.com',
            'date' => '2026-04-09',
            'due_date' => '2026-04-10',
            'total' => 800.0,
            'status' => 'PARTIAL',
        ],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [
        [
            'id' => 1,
            'org_id' => 54,
            'invoice_id' => 2,
            'journal_id' => 901,
            'amount' => 200.0,
            'date' => '2026-04-09',
            'created_at' => current_time('mysql'),
        ],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_expenses', [
        [
            'id' => 1,
            'org_id' => 54,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-09',
            'due_date' => '2026-04-10',
            'reference_number' => 'BILL-H-001',
            'payee' => 'Vendor Today',
            'amount' => 700.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => null,
        ],
        [
            'id' => 2,
            'org_id' => 54,
            'document_type' => 'BILL',
            'expense_date' => '2026-04-05',
            'due_date' => '2026-04-06',
            'reference_number' => 'BILL-H-002',
            'payee' => 'Vendor Overdue',
            'amount' => 500.0,
            'currency' => 'INR',
            'status' => 'POSTED',
            'payment_journal_id' => null,
        ],
    ]);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_promises', [
        [
            'id' => 1,
            'org_id' => 54,
            'invoice_id' => 1,
            'contact_id' => null,
            'promised_date' => '2026-04-10',
            'promised_amount' => 300.0,
            'status' => 'OPEN',
            'notes' => 'Customer said today',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => 2,
            'org_id' => 54,
            'invoice_id' => 2,
            'contact_id' => null,
            'promised_date' => '2026-04-08',
            'promised_amount' => 250.0,
            'status' => 'OPEN',
            'notes' => 'Customer missed it',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);

    $healthResponse = VyRestReports::billing_health(kbs_test_make_request('GET', '/vy/v1/reports/billing-health', [
        'from' => '2026-04-01',
        'to' => '2026-04-10',
        'as_of' => '2026-04-10',
    ]));
    $health = kbs_assert_response($healthResponse, 200)->get_data();

    kbs_assert_same('2026-04-10', $health['as_of'] ?? null);
    kbs_assert_same('critical', $health['status'] ?? null);
    kbs_assert_same(38, (int) ($health['score'] ?? 0));
    kbs_assert_count(5, $health['components'] ?? [], 'Billing health should expose component-level reasons.');
    kbs_assert_true(count($health['actions'] ?? []) >= 3, 'Billing health should suggest actions when the score is weak.');

    $briefResponse = VyRestReports::owner_daily_brief(kbs_test_make_request('GET', '/vy/v1/reports/owner-daily-brief', [
        'as_of' => '2026-04-10',
    ]));
    $brief = kbs_assert_response($briefResponse, 200)->get_data();

    kbs_assert_same('2026-04-10', $brief['as_of'] ?? null);
    kbs_assert_same('2026-04-09', $brief['yesterday'] ?? null);
    kbs_assert_same(200.0, (float) ($brief['summary']['collections_yesterday_amount'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['collections_yesterday_count'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['invoices_created_yesterday_count'] ?? 0));
    kbs_assert_same(800.0, (float) ($brief['summary']['invoices_created_yesterday_amount'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['expenses_added_yesterday_count'] ?? 0));
    kbs_assert_same(700.0, (float) ($brief['summary']['expenses_added_yesterday_amount'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['due_today_invoice_count'] ?? 0));
    kbs_assert_same(600.0, (float) ($brief['summary']['due_today_invoice_amount'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['due_today_bill_count'] ?? 0));
    kbs_assert_same(700.0, (float) ($brief['summary']['due_today_bill_amount'] ?? 0));
    kbs_assert_same(1, (int) ($brief['summary']['broken_promise_count'] ?? 0));
    kbs_assert_same(250.0, (float) ($brief['summary']['broken_promise_amount'] ?? 0));
    kbs_assert_true(count($brief['priorities'] ?? []) >= 3, 'Daily brief should surface today’s urgent priorities.');
});

kbs_test('revenue leak detector reports grounded recurring, promise, overdue, unemailed, and draft warnings', function (): void {
    kbs_test_add_user([
        'ID' => 505,
        'user_email' => 'leaks@example.com',
        'display_name' => 'Leak User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(505);
    kbs_test_set_user_meta(505, 'vy_active_org_id', 55);
    kbs_test_seed_org_membership(505, 55, 'company_admin', true, 'Leak Org');

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoices', [
        [
            'id' => 1,
            'org_id' => 55,
            'invoice_number' => 'INV-L-001',
            'customer_name' => 'Alpha Retail',
            'customer_email' => 'alpha-leaks@example.com',
            'date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'total' => 1500.0,
            'status' => 'SENT',
            'email_sent_at' => '2026-04-01 09:00:00',
        ],
        [
            'id' => 2,
            'org_id' => 55,
            'invoice_number' => 'INV-L-002',
            'customer_name' => 'Beta Retail',
            'customer_email' => 'beta-leaks@example.com',
            'date' => '2026-04-07',
            'due_date' => '2026-04-20',
            'total' => 900.0,
            'status' => 'SENT',
            'email_sent_at' => null,
        ],
        [
            'id' => 3,
            'org_id' => 55,
            'invoice_number' => 'INV-L-003',
            'customer_name' => 'Draft Customer',
            'customer_email' => 'draft-leaks@example.com',
            'date' => '2026-04-04',
            'due_date' => '2026-04-12',
            'total' => 600.0,
            'status' => 'DRAFT',
        ],
        [
            'id' => 4,
            'org_id' => 55,
            'invoice_number' => 'INV-L-004',
            'customer_name' => 'Gamma Retail',
            'customer_email' => 'gamma-leaks@example.com',
            'date' => '2026-04-02',
            'due_date' => '2026-04-04',
            'total' => 1200.0,
            'status' => 'PARTIAL',
            'email_sent_at' => '2026-04-02 10:00:00',
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_payments', [
        [
            'id' => 1,
            'org_id' => 55,
            'invoice_id' => 4,
            'journal_id' => 910,
            'amount' => 300.0,
            'date' => '2026-04-03',
            'created_at' => current_time('mysql'),
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_promises', [
        [
            'id' => 1,
            'org_id' => 55,
            'invoice_id' => 4,
            'contact_id' => null,
            'promised_date' => '2026-04-06',
            'promised_amount' => 500.0,
            'status' => 'OPEN',
            'notes' => 'Customer promised by Monday',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_recurring_profiles', [
        [
            'id' => 1,
            'org_id' => 55,
            'source_invoice_id' => 1,
            'profile_name' => 'Monthly Alpha Billing',
            'customer_name' => 'Alpha Retail',
            'customer_email' => 'alpha-leaks@example.com',
            'start_date' => '2026-01-01',
            'frequency' => 'MONTHLY',
            'interval_count' => 1,
            'due_days' => 7,
            'currency' => 'INR',
            'invoice_status' => 'SENT',
            'status' => 'ACTIVE',
            'next_run_date' => '2026-04-03',
            'last_run_date' => '2026-03-03',
        ],
    ]);

    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'vy_invoice_recurring_items', [
        [
            'id' => 1,
            'org_id' => 55,
            'profile_id' => 1,
            'description' => 'Monthly service retainer',
            'quantity' => 1,
            'unit_price' => 1000.0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'tax_type' => 'GST',
            'line_total' => 1000.0,
        ],
    ]);

    $response = VyRestReports::revenue_leaks(kbs_test_make_request('GET', '/vy/v1/reports/revenue-leaks', [
        'from' => '2026-04-01',
        'to' => '2026-04-10',
        'as_of' => '2026-04-10',
    ]));
    $data = kbs_assert_response($response, 200)->get_data();

    kbs_assert_same('2026-04-10', $data['as_of'] ?? null);
    kbs_assert_same('critical', $data['status'] ?? null);
    kbs_assert_same(5, (int) ($data['alert_count'] ?? 0));
    kbs_assert_same(5, (int) ($data['affected_record_count'] ?? 0));
    kbs_assert_same(4900.0, (float) ($data['estimated_amount'] ?? 0));

    $alerts = [];
    foreach (($data['alerts'] ?? []) as $alert) {
        $alerts[$alert['key']] = $alert;
    }

    kbs_assert_same('critical', $alerts['missed_recurring_runs']['severity'] ?? null);
    kbs_assert_same(1000.0, (float) ($alerts['missed_recurring_runs']['estimated_amount'] ?? 0));
    kbs_assert_same('Monthly Alpha Billing', $alerts['missed_recurring_runs']['records'][0]['label'] ?? null);

    kbs_assert_same('critical', $alerts['broken_payment_promises']['severity'] ?? null);
    kbs_assert_same(900.0, (float) ($alerts['broken_payment_promises']['estimated_amount'] ?? 0));
    kbs_assert_same('INV-L-004', $alerts['broken_payment_promises']['records'][0]['label'] ?? null);

    kbs_assert_same(1500.0, (float) ($alerts['overdue_invoices_without_promises']['estimated_amount'] ?? 0));
    kbs_assert_same('INV-L-001', $alerts['overdue_invoices_without_promises']['records'][0]['label'] ?? null);

    kbs_assert_same(900.0, (float) ($alerts['sent_invoices_without_email']['estimated_amount'] ?? 0));
    kbs_assert_same('INV-L-002', $alerts['sent_invoices_without_email']['records'][0]['label'] ?? null);

    kbs_assert_same(600.0, (float) ($alerts['stale_draft_invoices']['estimated_amount'] ?? 0));
    kbs_assert_same('INV-L-003', $alerts['stale_draft_invoices']['records'][0]['label'] ?? null);
});
