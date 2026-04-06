<?php

use KBS\Core\SystemLogger;

kbs_test('system logger stores structured operational events in the system log table', function (): void {
    kbs_test_add_user([
        'ID' => 601,
        'user_email' => 'logger@example.com',
        'display_name' => 'Logger User',
        'roles' => ['company_admin'],
    ]);
    kbs_test_set_current_user(601);

    SystemLogger::log_event(
        'invoice_auto_email_failed',
        'Auto email failed after invoice creation.',
        [
            'org_id' => 88,
            'invoice_id' => 501,
            'error_message' => 'SMTP timeout',
        ],
        601,
        'backend/Api/VyRestInvoices.php'
    );

    $logs = kbs_test_get_table($GLOBALS['wpdb']->prefix . 'kbs_system_logs');
    kbs_assert_count(1, $logs, 'Structured operational logs should be written to kbs_system_logs.');
    kbs_assert_same('invoice_auto_email_failed', $logs[0]['action'] ?? null);
    kbs_assert_same(601, (int) ($logs[0]['user_id'] ?? 0));
    kbs_assert_same('backend/Api/VyRestInvoices.php', $logs[0]['file_name'] ?? null);
    kbs_assert_true(str_contains((string) ($logs[0]['details'] ?? ''), 'invoice_id'), 'Log details should include serialized context.');
});
