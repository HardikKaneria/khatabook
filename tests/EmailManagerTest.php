<?php

use KBS\Email\EmailManager;

kbs_test('transactional email shell defaults to Vyavhar branding with structured summary rows', function (): void {
    $html = EmailManager::render('Please review the attached invoice.', [
        'eyebrow' => 'Invoice from Alpha Traders',
        'summary_rows' => [
            ['label' => 'Invoice', 'value' => 'INV-2026-001'],
            ['label' => 'Amount', 'value' => 'INR 1,250.00'],
        ],
    ]);

    kbs_assert_true(str_contains($html, 'Vyavhar'), 'Email shell should default to the Vyavhar brand.');
    kbs_assert_true(str_contains($html, 'Invoice from Alpha Traders'), 'Email shell should render the optional eyebrow.');
    kbs_assert_true(str_contains($html, 'INV-2026-001'), 'Email shell should render summary-row values.');
    kbs_assert_true(str_contains($html, 'fav_icon.png'), 'Email shell should fall back to the raster email logo.');
});

kbs_test('canonical email sender preserves attachments and array recipients', function (): void {
    $sent = kbs_send_email(['one@example.com', 'two@example.com'], 'Invoice ready', 'Please see the attached PDF.', [
        'attachments' => ['/tmp/invoice.pdf'],
    ]);

    kbs_assert_true($sent, 'Transactional email helper should report success when wp_mail succeeds.');

    $mail = kbs_test_last_mail();
    kbs_assert_same(['one@example.com', 'two@example.com'], $mail['to'] ?? null);
    kbs_assert_same(['/tmp/invoice.pdf'], $mail['attachments'] ?? null);
    kbs_assert_true(str_contains((string) ($mail['message'] ?? ''), 'Please see the attached PDF.'), 'Rendered email body should include the original message.');
});
