<?php

defined('ABSPATH') || exit;

$template_data = $template_data ?? vy_build_invoice_template_view_model(
    'minimal-grey-elegant',
    $invoice ?? null,
    $items ?? [],
    $org ?? (object) [],
    $settings ?? []
);

$invoiceItems = $template_data['items'] ?? [];
$summaryRows = $template_data['summary_rows'] ?? [];
$termsLines = $template_data['terms_lines'] ?? [];
$bankPairs = $template_data['bank_detail_pairs'] ?? [];
$footerLines = $template_data['footer_lines'] ?? [];
$taxBreakdown = $template_data['tax_breakdown'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?php echo esc_html(($template_data['invoice_label'] ?? 'Invoice') . ' ' . ($template_data['invoice_number'] ?? '')); ?></title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        :root {
            --tpl-primary: <?php echo esc_html($template_data['primary']); ?>;
            --tpl-accent: <?php echo esc_html($template_data['accent']); ?>;
            --tpl-text: <?php echo esc_html($template_data['text']); ?>;
            --tpl-muted: <?php echo esc_html($template_data['muted']); ?>;
            --tpl-line: <?php echo esc_html($template_data['line']); ?>;
        }
        body {
            margin: 0;
            padding: 18px;
            background: #f1f1f1;
            color: var(--tpl-text);
            font-family: <?php echo esc_html($template_data['font_family']); ?>;
            font-size: 13px;
            line-height: 1.45;
        }
        @page { margin: 16px; }
        .sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 22px 26px 18px;
        }
        table { width: 100%; border-collapse: collapse; }
        .top-grid td,
        .meta-grid td,
        .footer-grid td,
        .bottom-grid td { vertical-align: top; }
        .shape-cell { width: 22%; }
        .shape-cell svg { width: 120px; height: 110px; }
        .company-cell {
            width: 78%;
            text-align: right;
        }
        .company-name {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
            font-weight: 700;
        }
        .company-line {
            margin: 3px 0 0;
            color: var(--tpl-muted);
        }
        .invoice-title {
            margin: 12px 0 0;
            font-size: 56px;
            line-height: 0.95;
            font-weight: 300;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .meta-grid {
            margin-top: 18px;
        }
        .meta-grid td {
            width: 50%;
            padding-right: 10px;
        }
        .meta-grid td:last-child {
            padding-right: 0;
            text-align: right;
        }
        .meta-label {
            margin: 0;
            font-weight: 700;
        }
        .meta-value {
            margin: 3px 0 0;
            color: var(--tpl-muted);
        }
        .bill-grid {
            margin-top: 12px;
        }
        .bill-name {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
        }
        .bill-line {
            margin: 3px 0 0;
            color: var(--tpl-muted);
        }
        .dotted-divider {
            margin: 16px 0 10px;
            border-top: 1px dotted var(--tpl-accent);
        }
        .items thead th {
            padding: 8px 0 10px;
            text-align: left;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700;
            border-bottom: 1px solid var(--tpl-accent);
        }
        .items thead th:last-child,
        .items tbody td:last-child { text-align: right; }
        .items tbody td {
            padding: 11px 0;
            border-bottom: 1px dotted var(--tpl-line);
            color: var(--tpl-text);
        }
        .item-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: var(--tpl-muted);
        }
        .summary-wrap {
            width: 36%;
            margin-left: auto;
            margin-top: 10px;
        }
        .summary-wrap td {
            padding: 5px 0;
            font-size: 17px;
        }
        .summary-wrap td:last-child {
            text-align: right;
            white-space: nowrap;
            font-weight: 700;
        }
        .summary-wrap .total-row td {
            font-weight: 800;
            font-size: 18px;
        }
        .footer-grid {
            margin-top: 18px;
        }
        .footer-grid .left {
            width: 58%;
            padding-right: 18px;
        }
        .footer-grid .right {
            width: 42%;
            padding-left: 18px;
        }
        .section-title {
            margin: 0 0 8px;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
        }
        .copy {
            margin: 4px 0 0;
            color: var(--tpl-muted);
        }
        .signature-block {
            margin-top: 12px;
            text-align: right;
        }
        .signature-line {
            width: 140px;
            margin-left: auto;
            border-top: 1px solid var(--tpl-accent);
            padding-top: 6px;
            color: var(--tpl-muted);
        }
        .contact-strip {
            margin-top: 18px;
            border-top: 1px solid var(--tpl-accent);
            padding-top: 12px;
            color: var(--tpl-muted);
            text-align: center;
        }
        .qr-block {
            margin-top: 12px;
            text-align: left;
        }
        .qr-block img {
            width: 100px;
            height: 100px;
        }
        .tax-block {
            margin-top: 12px;
        }
        .tax-line {
            margin: 4px 0 0;
        }
        .tax-line strong {
            float: right;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="top-grid">
            <tr>
                <td class="shape-cell">
                    <svg viewBox="0 0 130 110" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <polygon points="0,36 38,0 64,26 26,62" fill="<?php echo esc_attr($template_data['accent']); ?>"></polygon>
                        <polygon points="18,62 68,12 98,42 48,92" fill="<?php echo esc_attr($template_data['line']); ?>"></polygon>
                        <polygon points="60,104 108,56 126,74 78,110" fill="<?php echo esc_attr($template_data['muted']); ?>"></polygon>
                    </svg>
                </td>
                <td class="company-cell">
                    <p class="company-name"><?php echo esc_html($template_data['company_name']); ?></p>
                    <?php foreach ($template_data['company_lines'] as $line): ?>
                        <p class="company-line"><?php echo esc_html($line); ?></p>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <h1 class="invoice-title">Invoice</h1>

        <table class="meta-grid">
            <tr>
                <td>
                    <p class="meta-label">Invoice No:</p>
                    <p class="meta-value"><?php echo esc_html($template_data['invoice_number']); ?></p>
                </td>
                <td>
                    <p class="meta-label">Date:</p>
                    <p class="meta-value"><?php echo esc_html($template_data['invoice_date_display_long']); ?></p>
                </td>
            </tr>
        </table>

        <div class="bill-grid">
            <p class="meta-label">Bill to:</p>
            <p class="bill-name"><?php echo esc_html($template_data['customer_name']); ?></p>
            <?php foreach ($template_data['customer_lines'] as $line): ?>
                <p class="bill-line"><?php echo esc_html($line); ?></p>
            <?php endforeach; ?>
        </div>

        <div class="dotted-divider"></div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:12%;">Item No</th>
                    <th style="width:42%;">Description</th>
                    <th style="width:12%;">Qty</th>
                    <th style="width:16%;">Unit Price</th>
                    <th style="width:18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceItems as $item): ?>
                    <tr>
                        <td><?php echo esc_html((string) $item['index'] . '.'); ?></td>
                        <td>
                            <?php echo esc_html($item['description']); ?>
                            <?php if (!empty($item['detail'])): ?>
                                <span class="item-note"><?php echo esc_html($item['detail']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.')); ?></td>
                        <td><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) $item['unit_price'])); ?></td>
                        <td><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) $item['line_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="summary-wrap">
            <?php foreach ($summaryRows as $row): ?>
                <?php if (in_array(($row['label'] ?? ''), ['Discount', 'Deposit Requested', 'Deposit Due'], true) && (float) ($row['amount'] ?? 0) <= 0) { continue; } ?>
                <tr class="<?php echo esc_attr(($row['kind'] ?? '') === 'grand' ? 'total-row' : ''); ?>">
                    <td><?php echo esc_html($row['label'] ?? ''); ?></td>
                    <td><?php echo esc_html((($row['kind'] ?? '') === 'discount' && (float) ($row['amount'] ?? 0) > 0 ? '-' : '') . vy_invoice_money($template_data['currency'], (float) ($row['amount'] ?? 0))); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table class="footer-grid">
            <tr>
                <td class="left">
                    <p class="section-title">Payment Terms:</p>
                    <?php if ($termsLines): ?>
                        <?php foreach ($termsLines as $line): ?>
                            <p class="copy"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="copy">Payment terms, such as “Payment due within 30 days”.</p>
                    <?php endif; ?>

                    <p class="section-title" style="margin-top:14px;">Bank Details:</p>
                    <?php if ($bankPairs): ?>
                        <?php foreach ($bankPairs as $pair): ?>
                            <p class="copy"><strong><?php echo esc_html($pair['label']); ?>:</strong> <?php echo esc_html($pair['value']); ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="copy"><strong>Bank Name:</strong> ABC Bank</p>
                        <p class="copy"><strong>Bank Account:</strong> 0123 4567 8901</p>
                    <?php endif; ?>

                    <?php if ($taxBreakdown): ?>
                        <div class="tax-block">
                            <p class="section-title">Tax Breakup</p>
                            <?php foreach ($taxBreakdown as $tax): ?>
                                <p class="tax-line"><?php echo esc_html($tax['label'] ?? 'Tax'); ?><strong><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($tax['amount'] ?? 0))); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="right">
                    <?php if (!empty($template_data['qr_code'])): ?>
                        <div class="qr-block">
                            <img src="<?php echo esc_url($template_data['qr_code']); ?>" alt="Payment QR code" />
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <p class="contact-strip">
            <?php echo esc_html($footerLines[0] ?? ('If you have any question please contact : ' . ($template_data['customer_email'] ?: 'hello@company.com'))); ?>
        </p>
    </div>
</body>
</html>
