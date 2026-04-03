<?php

kbs_test('invoice edit rules allow unpaid sent invoices and block paid states', function (): void {
    $editable = vy_invoice_edit_state([
        'status' => 'SENT',
        'paid_amount' => 0,
    ]);
    kbs_assert_true($editable['can_edit'], 'Unpaid sent invoices should remain editable.');

    $locked = vy_invoice_edit_state([
        'status' => 'SENT',
        'paid_amount' => 100,
    ]);
    kbs_assert_false($locked['can_edit'], 'Invoices with payments should be locked.');

    $partial = vy_invoice_edit_state([
        'status' => 'PARTIAL',
        'paid_amount' => 10,
    ]);
    kbs_assert_false($partial['can_edit'], 'Partial invoices should be locked.');
});

kbs_test('preview sample invoice generator returns realistic totals and selected template', function (): void {
    $sample = vy_build_preview_sample_invoice(
        (object) ['org_id' => 77, 'org_name' => 'Demo Org'],
        (object) [
            'default_template_id' => 'minimal-clean',
            'bank_details' => "Demo Bank\n1234567890",
            'show_qr_code' => 1,
        ],
        'accent-panel'
    );

    kbs_assert_same('accent-panel', $sample['invoice']->template_id);
    kbs_assert_same(3, count($sample['items']));
    kbs_assert_true($sample['invoice']->subtotal > 0, 'Sample subtotal should be positive.');
    kbs_assert_true($sample['invoice']->tax_total > 0, 'Sample tax should be positive.');
    kbs_assert_true($sample['invoice']->total > $sample['invoice']->subtotal, 'Sample total should include tax.');
});

kbs_test('preview QR generator returns an embeddable data URI when enabled', function (): void {
    $sample = vy_build_preview_sample_invoice(
        (object) ['org_id' => 77, 'org_name' => 'Demo Org'],
        (object) [
            'default_template_id' => 'minimal-clean',
            'bank_details' => "Demo Bank\n1234567890",
            'show_qr_code' => 1,
        ],
        'minimal-clean'
    );

    $dataUri = vy_invoice_qr_data_uri(
        $sample['invoice'],
        (object) ['org_id' => 77, 'org_name' => 'Demo Org'],
        (object) [
            'bank_details' => "Demo Bank\n1234567890",
            'show_qr_code' => 1,
        ]
    );

    kbs_assert_true(
        strpos((string) $dataUri, 'data:image/png;base64,') === 0 || strpos((string) $dataUri, 'data:image/svg+xml;base64,') === 0,
        'QR previews should return an embeddable PNG or SVG data URI.'
    );
});
