<?php

use KBS\Notifications\InternalDocumentNotifier;

kbs_test('internal document notifier removes invalid and duplicate recipient emails', function (): void {
    $emails = InternalDocumentNotifier::normalize_recipient_emails([
        'admin@example.com',
        'manager@example.com',
        'admin@example.com',
        'invalid-email',
        '',
    ]);

    kbs_assert_same(
        ['admin@example.com', 'manager@example.com'],
        $emails,
        'Internal notifications should target each valid email only once.'
    );
});
