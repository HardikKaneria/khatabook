<?php

kbs_test('invoice template registry exposes ten production templates', function (): void {
    $registry = vy_get_invoice_templates_registry();

    kbs_assert_same(10, count($registry), 'Expected ten invoice templates in the registry.');

    foreach ([
        'minimal-clean',
        'bordered-classic',
        'bold-header',
        'accent-panel',
        'compact-grid',
        'elegant-professional',
        'executive-blue',
        'soft-premium',
        'formal-ledger',
        'contemporary-statement',
    ] as $templateId) {
        kbs_assert_true(isset($registry[$templateId]), "Missing template {$templateId}.");
        kbs_assert_true(file_exists(vy_get_invoice_template_path($templateId)), "Missing template file for {$templateId}.");
    }
});

kbs_test('invoice template resolution uses settings as the primary source of truth', function (): void {
    $invoice = (object) ['template_id' => 'bold-header'];

    kbs_assert_same(
        'executive-blue',
        vy_resolve_invoice_template_id(['default_template_id' => 'executive-blue'], $invoice),
        'Org settings should override legacy invoice template values.'
    );

    kbs_assert_same(
        'soft-premium',
        vy_resolve_invoice_template_id(['default_template_id' => 'executive-blue'], $invoice, 'soft-premium'),
        'Explicit preview overrides should win when present.'
    );

    kbs_assert_same(
        'bold-header',
        vy_resolve_invoice_template_id(['default_template_id' => 'missing-template'], $invoice),
        'Legacy invoice template values should remain as compatibility fallback when settings are invalid.'
    );
});

kbs_test('all invoice templates render stable html through the shared renderer', function (): void {
    $org = (object) [
        'org_id' => 77,
        'org_name' => 'Demo Org',
        'industry' => 'Accounting Services',
    ];

    foreach (array_keys(vy_get_invoice_templates_registry()) as $templateId) {
        $settings = (object) [
            'default_template_id' => $templateId,
            'show_tax_breakup' => 1,
            'show_qr_code' => 1,
            'bank_details' => "Demo Bank\n1234567890",
            'footer_text' => 'Thanks for your business.',
            'terms_and_conditions' => 'Payment due within 7 days.',
        ];
        $sample = vy_build_preview_sample_invoice($org, $settings, $templateId);
        $html = vy_render_invoice_template_html($templateId, $sample['invoice'], $sample['items'], $org, $settings);

        kbs_assert_true(strpos($html, '<!DOCTYPE html>') !== false, "Template {$templateId} should render a full HTML document.");
        kbs_assert_true(strpos($html, $sample['invoice']->invoice_number) !== false, "Template {$templateId} should render the invoice number.");
        kbs_assert_true(strpos($html, 'Tax Breakup') !== false, "Template {$templateId} should render the tax breakup block when enabled.");
    }
});
