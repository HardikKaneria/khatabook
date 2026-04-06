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
