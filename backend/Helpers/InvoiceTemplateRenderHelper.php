<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_get_invoice_render_variant_styles')) {
    function vy_get_invoice_render_variant_styles(): array
    {
        return [
            'minimal-clean' => [
                'layout'         => 'split',
                'page_bg'        => '#eef2f7',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f8fafc',
                'line'           => '#d8e1ec',
                'muted'          => '#64748b',
                'text'           => '#0f172a',
                'fallback_font'  => "'Urbanist', 'Lato', Arial, sans-serif",
                'hero_shape'     => 'none',
                'summary_style'  => 'card',
                'table_density'  => 'comfortable',
            ],
            'bordered-classic' => [
                'layout'         => 'classic',
                'page_bg'        => '#f5f5f4',
                'surface'        => '#ffffff',
                'panel_bg'       => '#ffffff',
                'line'           => '#d6d3d1',
                'muted'          => '#57534e',
                'text'           => '#1c1917',
                'fallback_font'  => "'Merriweather', Georgia, serif",
                'hero_shape'     => 'rule',
                'summary_style'  => 'table',
                'table_density'  => 'comfortable',
            ],
            'bold-header' => [
                'layout'         => 'band',
                'page_bg'        => '#ecf3fb',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f8fafc',
                'line'           => '#dbe5f0',
                'muted'          => '#516174',
                'text'           => '#0f172a',
                'fallback_font'  => "'Urbanist', 'Lato', Arial, sans-serif",
                'hero_shape'     => 'band',
                'summary_style'  => 'card',
                'table_density'  => 'comfortable',
            ],
            'accent-panel' => [
                'layout'         => 'sidebar',
                'page_bg'        => '#f4f7fb',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f8fafc',
                'line'           => '#d9e2ec',
                'muted'          => '#61748a',
                'text'           => '#102033',
                'fallback_font'  => "'Urbanist', 'Lato', Arial, sans-serif",
                'hero_shape'     => 'sidebar',
                'summary_style'  => 'card',
                'table_density'  => 'comfortable',
            ],
            'compact-grid' => [
                'layout'         => 'compact',
                'page_bg'        => '#eff3f7',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f8fafc',
                'line'           => '#d3dce6',
                'muted'          => '#556273',
                'text'           => '#102033',
                'fallback_font'  => "'Inter', 'Urbanist', Arial, sans-serif",
                'hero_shape'     => 'cards',
                'summary_style'  => 'stack',
                'table_density'  => 'compact',
            ],
            'elegant-professional' => [
                'layout'         => 'split',
                'page_bg'        => '#faf7f2',
                'surface'        => '#ffffff',
                'panel_bg'       => '#fbf8f4',
                'line'           => '#e5ddd2',
                'muted'          => '#73685a',
                'text'           => '#2a241d',
                'fallback_font'  => "'Cormorant Garamond', Georgia, serif",
                'hero_shape'     => 'soft-band',
                'summary_style'  => 'card',
                'table_density'  => 'comfortable',
            ],
            'executive-blue' => [
                'layout'         => 'statement',
                'page_bg'        => '#edf3fa',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f4f8fc',
                'line'           => '#d4e0ed',
                'muted'          => '#54677e',
                'text'           => '#0b1f33',
                'fallback_font'  => "'Inter', 'Urbanist', Arial, sans-serif",
                'hero_shape'     => 'band',
                'summary_style'  => 'panel',
                'table_density'  => 'comfortable',
            ],
            'soft-premium' => [
                'layout'         => 'soft',
                'page_bg'        => '#fbf7f8',
                'surface'        => '#ffffff',
                'panel_bg'       => '#fff6f7',
                'line'           => '#ecd9df',
                'muted'          => '#7a6470',
                'text'           => '#30232a',
                'fallback_font'  => "'DM Serif Display', Georgia, serif",
                'hero_shape'     => 'soft-band',
                'summary_style'  => 'card',
                'table_density'  => 'comfortable',
            ],
            'formal-ledger' => [
                'layout'         => 'ledger',
                'page_bg'        => '#f7f6f3',
                'surface'        => '#ffffff',
                'panel_bg'       => '#faf9f7',
                'line'           => '#d8d3ca',
                'muted'          => '#5e564c',
                'text'           => '#1f1a14',
                'fallback_font'  => "'Source Serif 4', Georgia, serif",
                'hero_shape'     => 'rule',
                'summary_style'  => 'table',
                'table_density'  => 'compact',
            ],
            'contemporary-statement' => [
                'layout'         => 'statement',
                'page_bg'        => '#f4f7fb',
                'surface'        => '#ffffff',
                'panel_bg'       => '#f7fafc',
                'line'           => '#d9e2ec',
                'muted'          => '#5f7084',
                'text'           => '#102033',
                'fallback_font'  => "'Inter', 'Urbanist', Arial, sans-serif",
                'hero_shape'     => 'cards',
                'summary_style'  => 'panel',
                'table_density'  => 'comfortable',
            ],
        ];
    }
}

if (!function_exists('vy_render_invoice_template_html')) {
    function vy_render_invoice_template_html(string $templateId, $invoice, $items, $org, $settings): string
    {
        $variant = vy_get_invoice_render_variant_styles()[$templateId] ?? vy_get_invoice_render_variant_styles()['minimal-clean'];
        $model = vy_build_invoice_template_view_model($templateId, $invoice, $items, $org, $settings, $variant);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px;
            background: <?php echo esc_html($model['page_bg']); ?>;
            color: <?php echo esc_html($model['text']); ?>;
            font-family: <?php echo esc_html($model['font_family']); ?>;
            font-size: 13px;
            line-height: 1.55;
        }
        .invoice-page {
            width: 100%;
            background: <?php echo esc_html($model['surface']); ?>;
            border: 1px solid <?php echo esc_html($model['line']); ?>;
            border-radius: 18px;
            overflow: hidden;
        }
        .invoice-shell { width: 100%; border-collapse: collapse; }
        .invoice-shell td { vertical-align: top; }
        .page-padding { padding: 28px 30px; }
        .band-header {
            background: <?php echo esc_html($model['primary']); ?>;
            color: #ffffff;
        }
        .band-header h1,
        .band-header h2,
        .band-header h3,
        .band-header p,
        .band-header span,
        .band-header td {
            color: #ffffff;
        }
        .sidebar-pane {
            width: 30%;
            background: <?php echo esc_html($model['primary']); ?>;
            color: #ffffff;
            padding: 28px 24px;
        }
        .sidebar-pane h1,
        .sidebar-pane h2,
        .sidebar-pane h3,
        .sidebar-pane p,
        .sidebar-pane span,
        .sidebar-pane td {
            color: #ffffff;
        }
        .soft-header {
            background: <?php echo esc_html($model['panel_bg']); ?>;
            border-bottom: 1px solid <?php echo esc_html($model['line']); ?>;
        }
        .invoice-title {
            font-size: 30px;
            line-height: 1.1;
            margin: 0;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .invoice-kicker {
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: <?php echo esc_html($model['muted']); ?>;
            margin: 0 0 8px;
        }
        .brand-name {
            font-size: 24px;
            line-height: 1.2;
            margin: 0;
            color: <?php echo esc_html($model['brand_text']); ?>;
            word-break: break-word;
        }
        .logo {
            max-height: 72px;
            max-width: 180px;
            width: auto;
            height: auto;
            display: block;
            margin-bottom: 14px;
        }
        .org-lines,
        .customer-lines,
        .meta-lines {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .org-lines li,
        .customer-lines li,
        .meta-lines li {
            margin: 4px 0 0;
            word-break: break-word;
        }
        .accent-rule {
            height: 4px;
            background: <?php echo esc_html($model['accent']); ?>;
        }
        .section-grid,
        .summary-grid,
        .bottom-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px;
            margin: 0 -16px;
        }
        .section-card,
        .summary-card,
        .bottom-card {
            width: 50%;
            background: <?php echo esc_html($model['panel_bg']); ?>;
            border: 1px solid <?php echo esc_html($model['line']); ?>;
            border-radius: 16px;
            padding: 18px 18px 16px;
            page-break-inside: avoid;
        }
        .summary-card {
            background: <?php echo esc_html($model['summary_bg']); ?>;
        }
        .section-card h3,
        .summary-card h3,
        .bottom-card h3 {
            margin: 0 0 10px;
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: <?php echo esc_html($model['muted']); ?>;
        }
        .statement-total {
            background: <?php echo esc_html($model['primary']); ?>;
            color: #ffffff;
            border-radius: 18px;
            padding: 18px 22px;
            text-align: right;
        }
        .statement-total small {
            display: block;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.85;
        }
        .statement-total strong {
            display: block;
            font-size: 28px;
            line-height: 1.15;
            margin-top: 6px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            table-layout: fixed;
        }
        .items-table thead { display: table-header-group; }
        .items-table tr { page-break-inside: avoid; }
        .items-table th {
            background: <?php echo esc_html($model['primary']); ?>;
            color: #ffffff;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-align: left;
            padding: <?php echo esc_html($model['table_padding']); ?>;
            border: 1px solid <?php echo esc_html($model['primary']); ?>;
        }
        .items-table td {
            padding: <?php echo esc_html($model['table_padding']); ?>;
            border-bottom: 1px solid <?php echo esc_html($model['line']); ?>;
            vertical-align: top;
            word-break: break-word;
        }
        .items-table td.num,
        .items-table th.num {
            text-align: right;
        }
        .items-table .desc {
            width: 40%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 0;
            border-bottom: 1px solid <?php echo esc_html($model['line']); ?>;
        }
        .totals-table td:last-child {
            text-align: right;
            white-space: nowrap;
        }
        .totals-table .grand-total td {
            border-bottom: 0;
            color: <?php echo esc_html($model['brand_text']); ?>;
            font-size: 18px;
            font-weight: 700;
            padding-top: 12px;
        }
        .tax-breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tax-breakdown-table td {
            padding: 6px 0;
            border-bottom: 1px solid <?php echo esc_html($model['line']); ?>;
        }
        .tax-breakdown-table tr:last-child td {
            border-bottom: 0;
        }
        .tax-breakdown-table td:last-child {
            text-align: right;
            white-space: nowrap;
        }
        .qr-block {
            text-align: center;
        }
        .qr-block img {
            width: 132px;
            height: 132px;
            margin: 4px auto 0;
            display: block;
            border: 1px solid <?php echo esc_html($model['line']); ?>;
            border-radius: 14px;
            background: #ffffff;
            padding: 8px;
        }
        .notes-card,
        .footer-note {
            margin-top: 18px;
        }
        .notes-card {
            background: <?php echo esc_html($model['panel_bg']); ?>;
            border: 1px solid <?php echo esc_html($model['line']); ?>;
            border-radius: 14px;
            padding: 16px 18px;
            page-break-inside: avoid;
        }
        .footer-note {
            color: <?php echo esc_html($model['muted']); ?>;
            font-size: 12px;
            text-align: center;
        }
        .muted {
            color: <?php echo esc_html($model['muted']); ?>;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .meta-table td:first-child {
            color: <?php echo esc_html($model['muted']); ?>;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            width: 40%;
        }
        .statement-strip {
            background: <?php echo esc_html($model['panel_bg']); ?>;
            border: 1px solid <?php echo esc_html($model['line']); ?>;
            border-radius: 18px;
            padding: 18px 20px;
        }
    </style>
</head>
<body>
<?php echo vy_render_invoice_template_body($model); ?>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_build_invoice_template_view_model')) {
    function vy_build_invoice_template_view_model(string $templateId, $invoice, $items, $org, $settings, array $variant): array
    {
        $settings = is_array($settings) ? (object) $settings : $settings;
        $items = is_array($items) ? $items : (array) $items;

        $companySettings = function_exists('vy_fetch_org_settings_category') && !empty($org->org_id)
            ? vy_fetch_org_settings_category((int) $org->org_id, 'company')
            : [];

        $fontFamily = trim((string) vy_invoice_setting_value($settings, 'font_family', ''));
        if ($fontFamily === '') {
            $fontFamily = $variant['fallback_font'];
        }

        $primary = trim((string) vy_invoice_setting_value($settings, 'primary_color', ''));
        if ($primary === '') {
            $primary = [
                'minimal-clean' => '#0f172a',
                'bordered-classic' => '#1f2937',
                'bold-header' => '#15406a',
                'accent-panel' => '#345f8f',
                'compact-grid' => '#234b6f',
                'elegant-professional' => '#5f4a35',
                'executive-blue' => '#0f3c63',
                'soft-premium' => '#8b5f73',
                'formal-ledger' => '#4d3d2e',
                'contemporary-statement' => '#1e3a5f',
            ][$templateId] ?? '#0f172a';
        }

        $accent = trim((string) vy_invoice_setting_value($settings, 'accent_color', ''));
        if ($accent === '') {
            $accent = [
                'minimal-clean' => '#3b82f6',
                'bordered-classic' => '#9a6c2f',
                'bold-header' => '#7ec8ff',
                'accent-panel' => '#f59e0b',
                'compact-grid' => '#10b981',
                'elegant-professional' => '#c79b5e',
                'executive-blue' => '#60a5fa',
                'soft-premium' => '#f6b8c8',
                'formal-ledger' => '#8b7355',
                'contemporary-statement' => '#14b8a6',
            ][$templateId] ?? '#3b82f6';
        }

        $companyName = trim((string) ($companySettings['display_name'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($companySettings['business_name'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = trim((string) ($org->org_name ?? 'Vyavhar'));
        }

        $companyLines = [];
        if (!empty($companySettings['business_name']) && $companySettings['business_name'] !== $companyName) {
            $companyLines[] = (string) $companySettings['business_name'];
        } elseif (!empty($org->industry)) {
            $companyLines[] = (string) $org->industry;
        }
        if (!empty($companySettings['state'])) {
            $companyLines[] = 'State: ' . $companySettings['state'];
        }
        if (!empty($companySettings['gst_registered']) && !empty($companySettings['gstin'])) {
            $companyLines[] = 'GSTIN: ' . strtoupper((string) $companySettings['gstin']);
        }

        $customerLines = array_values(array_filter([
            trim((string) ($invoice->customer_name ?? '')),
            trim((string) ($invoice->customer_email ?? '')),
            trim((string) ($invoice->customer_phone ?? '')),
        ]));

        $notes = trim((string) ($invoice->notes ?? ''));
        $footerText = trim((string) vy_invoice_setting_value($settings, 'footer_text', ''));
        $terms = trim((string) vy_invoice_setting_value($settings, 'terms_and_conditions', ''));
        $bankDetails = trim((string) vy_invoice_setting_value($settings, 'bank_details', ''));
        $logoUrl = vy_invoice_effective_logo_url($settings, $org);
        $taxBreakdown = vy_invoice_should_show_tax_breakup($settings) ? vy_invoice_tax_breakdown($items) : [];
        $qrCode = vy_invoice_qr_data_uri($invoice, $org, $settings);
        $currency = trim((string) ($invoice->currency ?? 'INR'));
        if ($currency === '') {
            $currency = 'INR';
        }

        $normalizedItems = [];
        foreach ($items as $item) {
            $description = trim((string) vy_invoice_setting_value($item, 'description', 'Item'));
            $quantity = (float) vy_invoice_setting_value($item, 'quantity', 0);
            $unitPrice = (float) vy_invoice_setting_value($item, 'unit_price', 0);
            $taxRate = (float) vy_invoice_setting_value($item, 'tax_rate', 0);
            $taxAmount = vy_invoice_line_tax_amount($item);
            $lineTotal = vy_invoice_line_total_amount($item);

            $normalizedItems[] = [
                'description' => $description !== '' ? $description : 'Item',
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'tax_rate'    => $taxRate,
                'tax_amount'  => $taxAmount,
                'line_total'  => $lineTotal,
            ];
        }

        return [
            'template_id'    => $templateId,
            'layout'         => $variant['layout'],
            'page_bg'        => $variant['page_bg'],
            'surface'        => $variant['surface'],
            'panel_bg'       => $variant['panel_bg'],
            'line'           => $variant['line'],
            'muted'          => $variant['muted'],
            'text'           => $variant['text'],
            'primary'        => $primary,
            'accent'         => $accent,
            'brand_text'     => $templateId === 'soft-premium' ? '#513844' : $primary,
            'summary_bg'     => in_array($variant['summary_style'], ['panel', 'card'], true) ? $variant['panel_bg'] : '#ffffff',
            'font_family'    => $fontFamily,
            'table_padding'  => $variant['table_density'] === 'compact' ? '10px 10px' : '13px 12px',
            'invoice_label'  => 'Invoice',
            'company_name'   => $companyName,
            'company_lines'  => $companyLines,
            'customer_lines' => $customerLines,
            'logo_url'       => $logoUrl,
            'invoice_number' => trim((string) ($invoice->invoice_number ?? '—')),
            'invoice_date'   => trim((string) ($invoice->date ?? '—')),
            'invoice_due'    => trim((string) ($invoice->due_date ?? '')),
            'invoice_status' => strtoupper(trim((string) ($invoice->status ?? 'SENT'))),
            'subtotal'       => (float) ($invoice->subtotal ?? 0),
            'tax_total'      => (float) ($invoice->tax_total ?? 0),
            'total'          => (float) ($invoice->total ?? 0),
            'currency'       => $currency,
            'items'          => $normalizedItems,
            'notes'          => $notes,
            'footer_text'    => $footerText,
            'terms'          => $terms,
            'bank_details'   => $bankDetails,
            'tax_breakdown'  => $taxBreakdown,
            'qr_code'        => $qrCode,
            'show_total_card'=> in_array($variant['layout'], ['statement'], true),
        ];
    }
}

if (!function_exists('vy_render_invoice_template_body')) {
    function vy_render_invoice_template_body(array $model): string
    {
        ob_start();

        if ($model['layout'] === 'sidebar') {
            ?>
            <div class="invoice-page">
                <table class="invoice-shell">
                    <tr>
                        <td class="sidebar-pane">
                            <?php echo vy_render_invoice_brand_block($model, true); ?>
                        </td>
                        <td>
                            <div class="page-padding">
                                <?php echo vy_render_invoice_title_block($model, false); ?>
                                <?php echo vy_render_invoice_content_sections($model); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        } elseif ($model['layout'] === 'band') {
            ?>
            <div class="invoice-page">
                <div class="band-header page-padding">
                    <table class="invoice-shell">
                        <tr>
                            <td style="width:58%;"><?php echo vy_render_invoice_brand_block($model, false, true); ?></td>
                            <td style="width:42%;"><?php echo vy_render_invoice_title_block($model, true, true); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="page-padding">
                    <?php echo vy_render_invoice_content_sections($model); ?>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="invoice-page">
                <?php if (in_array($model['layout'], ['classic', 'ledger'], true)): ?>
                    <div class="accent-rule"></div>
                <?php endif; ?>
                <div class="<?php echo in_array($model['layout'], ['soft', 'compact', 'statement'], true) ? 'soft-header page-padding' : 'page-padding'; ?>">
                    <table class="invoice-shell">
                        <tr>
                            <td style="width:58%;"><?php echo vy_render_invoice_brand_block($model); ?></td>
                            <td style="width:42%;"><?php echo vy_render_invoice_title_block($model, $model['show_total_card']); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="page-padding">
                    <?php echo vy_render_invoice_content_sections($model); ?>
                </div>
            </div>
            <?php
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_render_invoice_brand_block')) {
    function vy_render_invoice_brand_block(array $model, bool $invert = false, bool $bandMode = false): string
    {
        ob_start();
        ?>
        <div>
            <?php if (!empty($model['logo_url'])): ?>
                <img class="logo" src="<?php echo esc_url($model['logo_url']); ?>" alt="Company logo" />
            <?php endif; ?>
            <p class="invoice-kicker"<?php echo $invert || $bandMode ? ' style="color:rgba(255,255,255,0.82);"' : ''; ?>>Bill From</p>
            <h1 class="brand-name"<?php echo $invert || $bandMode ? ' style="color:#ffffff;"' : ''; ?>><?php echo esc_html($model['company_name']); ?></h1>
            <?php if ($model['company_lines']): ?>
                <ul class="org-lines muted"<?php echo $invert || $bandMode ? ' style="color:rgba(255,255,255,0.88);"' : ''; ?>>
                    <?php foreach ($model['company_lines'] as $line): ?>
                        <li><?php echo esc_html($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_render_invoice_title_block')) {
    function vy_render_invoice_title_block(array $model, bool $showTotalCard = false, bool $bandMode = false): string
    {
        ob_start();
        ?>
        <div class="text-right">
            <?php if ($showTotalCard): ?>
                <div class="statement-total">
                    <small>Total Amount</small>
                    <strong><?php echo esc_html(vy_invoice_money($model['currency'], $model['total'])); ?></strong>
                </div>
            <?php endif; ?>
            <p class="invoice-kicker"<?php echo $bandMode ? ' style="color:rgba(255,255,255,0.82); margin-top:' . ($showTotalCard ? '16px' : '0') . ';"' : ($showTotalCard ? ' style="margin-top:16px;"' : ''); ?>><?php echo esc_html($model['invoice_label']); ?></p>
            <h2 class="invoice-title"<?php echo $bandMode ? ' style="color:#ffffff;"' : ''; ?>><?php echo esc_html($model['invoice_number']); ?></h2>
            <ul class="meta-lines muted"<?php echo $bandMode ? ' style="color:rgba(255,255,255,0.88);"' : ''; ?>>
                <li>Date: <?php echo esc_html($model['invoice_date']); ?></li>
                <?php if ($model['invoice_due'] !== ''): ?>
                    <li>Due Date: <?php echo esc_html($model['invoice_due']); ?></li>
                <?php endif; ?>
                <li>Status: <?php echo esc_html($model['invoice_status']); ?></li>
            </ul>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_render_invoice_content_sections')) {
    function vy_render_invoice_content_sections(array $model): string
    {
        ob_start();
        ?>
        <table class="section-grid">
            <tr>
                <td class="section-card">
                    <h3>Bill To</h3>
                    <?php if ($model['customer_lines']): ?>
                        <ul class="customer-lines">
                            <?php foreach ($model['customer_lines'] as $line): ?>
                                <li><?php echo esc_html($line); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">Customer details were not provided.</p>
                    <?php endif; ?>
                </td>
                <td class="section-card">
                    <h3>Invoice Details</h3>
                    <table class="meta-table">
                        <tr><td>Invoice No</td><td><?php echo esc_html($model['invoice_number']); ?></td></tr>
                        <tr><td>Issue Date</td><td><?php echo esc_html($model['invoice_date']); ?></td></tr>
                        <?php if ($model['invoice_due'] !== ''): ?>
                            <tr><td>Due Date</td><td><?php echo esc_html($model['invoice_due']); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Status</td><td><?php echo esc_html($model['invoice_status']); ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <?php echo vy_render_invoice_items_table($model); ?>

        <table class="summary-grid">
            <tr>
                <td style="width:52%; padding: 0 16px 0 16px;">
                    <?php if ($model['notes'] !== ''): ?>
                        <div class="notes-card">
                            <h3 style="margin:0 0 8px; font-size:11px; letter-spacing:0.14em; text-transform:uppercase; color:<?php echo esc_html($model['muted']); ?>;">Notes</h3>
                            <div><?php echo wp_kses_post(nl2br(esc_html($model['notes']))); ?></div>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="width:48%; padding: 0 16px 0 16px;">
                    <div class="summary-card">
                        <h3>Totals</h3>
                        <table class="totals-table">
                            <tr>
                                <td>Subtotal</td>
                                <td><?php echo esc_html(vy_invoice_money($model['currency'], $model['subtotal'])); ?></td>
                            </tr>
                            <?php if ($model['tax_total'] > 0): ?>
                                <tr>
                                    <td>Tax</td>
                                    <td><?php echo esc_html(vy_invoice_money($model['currency'], $model['tax_total'])); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="grand-total">
                                <td>Total</td>
                                <td><?php echo esc_html(vy_invoice_money($model['currency'], $model['total'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <?php echo vy_render_invoice_bottom_blocks($model); ?>

        <?php if ($model['footer_text'] !== ''): ?>
            <div class="footer-note"><?php echo wp_kses_post($model['footer_text']); ?></div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_render_invoice_items_table')) {
    function vy_render_invoice_items_table(array $model): string
    {
        ob_start();
        ?>
        <table class="items-table">
            <thead>
                <tr>
                    <th class="desc">Description</th>
                    <th class="num">Qty</th>
                    <th class="num">Rate</th>
                    <th class="num">Tax</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($model['items'] as $item): ?>
                    <tr>
                        <td class="desc"><?php echo esc_html($item['description']); ?></td>
                        <td class="num"><?php echo esc_html(number_format((float) $item['quantity'], 2)); ?></td>
                        <td class="num"><?php echo esc_html(vy_invoice_money($model['currency'], $item['unit_price'])); ?></td>
                        <td class="num">
                            <?php if ((float) $item['tax_rate'] > 0): ?>
                                <?php echo esc_html(number_format((float) $item['tax_rate'], 2)); ?>%
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo esc_html(vy_invoice_money($model['currency'], $item['line_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_render_invoice_bottom_blocks')) {
    function vy_render_invoice_bottom_blocks(array $model): string
    {
        $blocks = [];

        if ($model['bank_details'] !== '') {
            $blocks[] = [
                'title' => 'Payment Details',
                'body'  => wp_kses_post(nl2br(esc_html($model['bank_details']))),
            ];
        }

        if ($model['terms'] !== '') {
            $blocks[] = [
                'title' => 'Terms & Conditions',
                'body'  => wp_kses_post(nl2br(esc_html($model['terms']))),
            ];
        }

        if ($model['tax_breakdown']) {
            ob_start();
            ?>
            <table class="tax-breakdown-table">
                <?php foreach ($model['tax_breakdown'] as $taxLine): ?>
                    <tr>
                        <td><?php echo esc_html($taxLine['label']); ?></td>
                        <td><?php echo esc_html(vy_invoice_money($model['currency'], $taxLine['amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php
            $blocks[] = [
                'title' => 'Tax Breakup',
                'body'  => (string) ob_get_clean(),
            ];
        }

        if ($model['qr_code']) {
            $blocks[] = [
                'title' => 'Payment QR',
                'body'  => '<div class="qr-block"><img src="' . esc_url($model['qr_code']) . '" alt="Invoice QR code" /><p class="muted" style="margin:10px 0 0;">Scan to view payment reference details.</p></div>',
            ];
        }

        if (!$blocks) {
            return '';
        }

        ob_start();
        ?>
        <table class="bottom-grid">
            <?php for ($index = 0; $index < count($blocks); $index += 2): ?>
                <tr>
                    <?php for ($cell = 0; $cell < 2; $cell++): ?>
                        <?php $block = $blocks[$index + $cell] ?? null; ?>
                        <td style="width:50%; padding: 0 16px 0 16px;">
                            <?php if ($block): ?>
                                <div class="bottom-card">
                                    <h3><?php echo esc_html($block['title']); ?></h3>
                                    <div><?php echo wp_kses_post($block['body']); ?></div>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
        </table>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_invoice_money')) {
    function vy_invoice_money(string $currency, float $amount): string
    {
        return sprintf('%s %s', $currency, number_format($amount, 2));
    }
}

if (!function_exists('vy_invoice_effective_logo_url')) {
    function vy_invoice_effective_logo_url($settings, $org): string
    {
        $logoUrl = trim((string) vy_invoice_setting_value($settings, 'logo_url', ''));
        if ($logoUrl !== '') {
            return esc_url_raw($logoUrl);
        }

        $legacyOrgLogo = trim((string) vy_invoice_setting_value($org, 'logo_url', ''));
        return $legacyOrgLogo !== '' ? esc_url_raw($legacyOrgLogo) : '';
    }
}
