<?php

use KBS\Api\VyRestInvoicePreview;

kbs_test('invoice template registry exposes four approved templates only', function (): void {
    $registry = vy_get_invoice_templates_registry();

    kbs_assert_same(4, count($registry), 'Expected four invoice templates in the registry.');

    foreach ([
        'modern-clean-blue',
        'corporate-orange',
        'minimal-grey-elegant',
        'yellow-modern-minimal',
    ] as $templateId) {
        kbs_assert_true(isset($registry[$templateId]), "Missing template {$templateId}.");
        kbs_assert_true(file_exists(vy_get_invoice_template_path($templateId)), "Missing template file for {$templateId}.");
    }
});

kbs_test('invoice template resolution uses settings first and maps removed template ids safely', function (): void {
    $invoice = (object) ['template_id' => 'bold-header'];

    kbs_assert_same(
        'modern-clean-blue',
        vy_resolve_invoice_template_id(['default_template_id' => 'executive-blue'], $invoice),
        'Removed settings template ids should map to the replacement catalog.'
    );

    kbs_assert_same(
        'yellow-modern-minimal',
        vy_resolve_invoice_template_id(['default_template_id' => 'modern-clean-blue'], $invoice, 'yellow-modern-minimal'),
        'Explicit preview overrides should win when present.'
    );

    kbs_assert_same(
        'corporate-orange',
        vy_resolve_invoice_template_id(['default_template_id' => 'missing-template'], $invoice),
        'Legacy invoice template ids should map to the new catalog when settings are invalid.'
    );
});

kbs_test('invoice font family options are curated and normalize legacy input safely', function (): void {
    $options = vy_get_invoice_font_family_options();

    kbs_assert_true(count($options) >= 4, 'Expected several curated invoice font options.');
    kbs_assert_same(
        vy_get_default_invoice_font_family(),
        vy_normalize_invoice_font_family('Times New Roman, serif'),
        'Serif requests should fall back to the default supported stack.'
    );
    kbs_assert_same(
        '"Trebuchet MS", Arial, Helvetica, "DejaVu Sans", sans-serif',
        vy_normalize_invoice_font_family('Trebuchet MS, Arial, Helvetica, "DejaVu Sans", sans-serif'),
        'Known supported font stacks should normalize to the canonical option.'
    );
});

kbs_test('invoice color settings normalize to safe hex values', function (): void {
    kbs_assert_same('#4f46e5', vy_normalize_invoice_hex_color('#4F46E5'), 'Valid colors should normalize to lowercase hex.');
    kbs_assert_same('#aabbcc', vy_normalize_invoice_hex_color('#abc'), 'Short hex colors should expand to full-length hex.');
    kbs_assert_same('#1e3a8a', vy_normalize_invoice_hex_color('not-a-color', '#1e3a8a'), 'Invalid colors should fall back to the current safe value.');
    kbs_assert_same(null, vy_normalize_invoice_hex_color('nope'), 'Invalid colors without a fallback should resolve to null.');
});

kbs_test('all invoice templates render stable direct html without bullets or serif fallbacks', function (): void {
    $org = (object) [
        'org_id' => 77,
        'org_name' => 'Demo Org',
        'industry' => 'Accounting Services',
    ];

    foreach (array_keys(vy_get_invoice_templates_registry()) as $templateId) {
        $settings = (object) [
            'default_template_id' => $templateId,
            'font_family' => '"DejaVu Sans", Arial, Helvetica, "Segoe UI", sans-serif',
            'show_tax_breakup' => 1,
            'show_qr_code' => 1,
            'bank_details' => "Demo Bank\n1234567890",
            'footer_text' => 'Thanks for your business.',
            'terms_and_conditions' => 'Payment due within 7 days.',
        ];
        $sample = vy_build_preview_sample_invoice($org, $settings, $templateId);
        $html = vy_render_invoice_template_document($templateId, $sample['invoice'], $sample['items'], $org, $settings);
        $decodedHtml = html_entity_decode($html, ENT_QUOTES);
        $taxBreakdown = vy_invoice_tax_breakdown($sample['items']);

        kbs_assert_true(strpos($html, '<!DOCTYPE html>') !== false, "Template {$templateId} should render a full HTML document.");
        kbs_assert_true(strpos($html, $sample['invoice']->invoice_number) !== false, "Template {$templateId} should render the invoice number.");
        kbs_assert_true(strpos($html, '<ul') === false, "Template {$templateId} should not use unordered lists.");
        kbs_assert_true(strpos($html, 'Times New Roman') === false, "Template {$templateId} should not reference serif fallback fonts.");
        kbs_assert_true(strpos($decodedHtml, '"DejaVu Sans", Arial, Helvetica, "Segoe UI", sans-serif') !== false, "Template {$templateId} should render the selected supported font stack.");
        foreach ($taxBreakdown as $taxRow) {
            kbs_assert_true(
                strpos($html, (string) ($taxRow['label'] ?? '')) !== false,
                "Template {$templateId} should render the tax breakdown rows when enabled."
            );
        }
    }
});

kbs_test('all invoice templates render selected primary and accent colors from settings', function (): void {
    $org = (object) [
        'org_id' => 88,
        'org_name' => 'Color Demo Org',
        'industry' => 'Creative Services',
    ];

    foreach (array_keys(vy_get_invoice_templates_registry()) as $templateId) {
        $settings = (object) [
            'default_template_id' => $templateId,
            'font_family' => vy_get_default_invoice_font_family(),
            'primary_color' => '#123abc',
            'accent_color' => '#456def',
            'show_tax_breakup' => 1,
            'show_qr_code' => 1,
            'bank_details' => "Demo Bank\n1234567890",
            'footer_text' => 'Thanks for your business.',
            'terms_and_conditions' => 'Payment due within 7 days.',
        ];

        $sample = vy_build_preview_sample_invoice($org, $settings, $templateId);
        $html = vy_render_invoice_template_document($templateId, $sample['invoice'], $sample['items'], $org, $settings);

        kbs_assert_true(strpos($html, '#123abc') !== false, "Template {$templateId} should render the selected primary color.");
        kbs_assert_true(strpos($html, '#456def') !== false, "Template {$templateId} should render the selected accent color.");
    }
});

kbs_test('invoice template files render direct php html documents per template file', function (): void {
    foreach (array_keys(vy_get_invoice_templates_registry()) as $templateId) {
        $contents = file_get_contents(vy_get_invoice_template_path($templateId));
        kbs_assert_true(is_string($contents) && strpos($contents, '<!DOCTYPE html>') !== false, "Template {$templateId} should contain a direct HTML document.");
        kbs_assert_true(is_string($contents) && strpos($contents, '<ul') === false, "Template {$templateId} should avoid list markup in PDF templates.");
    }
});

kbs_test('invoice preview route renders a direct template document from the sample fallback when no invoice exists', function (): void {
    kbs_test_add_user([
        'ID' => 711,
        'user_email' => 'preview-user@example.com',
        'display_name' => 'Preview User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_current_user(711);
    kbs_test_seed_org_membership(711, 80, 'company_admin', true, 'Preview Org');

    $response = kbs_assert_response(VyRestInvoicePreview::render_preview(
        kbs_test_make_request('GET', '/vy/v1/invoices/preview', [
            'template_id' => 'corporate-orange',
            'show_tax_breakup' => '1',
            'show_qr_code' => '1',
        ])
    ), 200);

    $html = (string) $response->get_data();

    kbs_assert_true(strpos($html, '<!DOCTYPE html>') !== false, 'Preview should return a full HTML document.');
    kbs_assert_true(strpos($html, 'Preview Org') !== false, 'Preview should render the current organization name.');
    kbs_assert_true(strpos($html, 'Tax Breakup') !== false, 'Preview should render tax breakup when enabled.');
});
