<?php

defined('ABSPATH') || exit;

$template_data = $template_data ?? vy_build_invoice_template_view_model(
    'modern-clean-blue',
    $invoice ?? null,
    $items ?? [],
    $org ?? (object) [],
    $settings ?? []
);

$invoiceItems  = $template_data['items'] ?? [];
$summaryRows   = $template_data['summary_rows'] ?? [];
$detailRows    = $template_data['detail_rows'] ?? [];
$taxBreakdown  = $template_data['tax_breakdown'] ?? [];
$bankLines     = $template_data['bank_detail_lines'] ?? [];
$termsLines    = $template_data['terms_lines'] ?? [];
$notesLines    = $template_data['notes_lines'] ?? [];
$footerLines   = $template_data['footer_lines'] ?? [];
$companyLines  = $template_data['company_lines'] ?? [];
$customerLines = $template_data['customer_lines'] ?? [];

$money = static function ($amount, string $kind = 'normal') use ($template_data): string {
    $formatted = vy_invoice_money($template_data['currency'], (float) $amount);
    return $kind === 'discount' && (float) $amount > 0 ? '-' . $formatted : $formatted;
};

$detailValue = static function (string $label, array $rows): string {
    foreach ($rows as $row) {
        if (($row['label'] ?? '') === $label) {
            return (string) ($row['value'] ?? '—');
        }
    }
    return '—';
};

$showPreviewHint = !empty($template_data['is_preview_sample']);
$hasLogo = !empty($template_data['logo_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?php echo esc_html(($template_data['invoice_label'] ?? 'Invoice') . ' ' . ($template_data['invoice_number'] ?? '')); ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        :root {
            --tpl-primary: <?php echo esc_html($template_data['primary']); ?>;
            --tpl-accent: <?php echo esc_html($template_data['accent']); ?>;
            --tpl-text: <?php echo esc_html($template_data['text']); ?>;
            --tpl-muted: <?php echo esc_html($template_data['muted']); ?>;
            --tpl-line: <?php echo esc_html($template_data['line']); ?>;
        }

        body {
            padding: 22px;
            background: #f3f4f6;
            color: var(--tpl-text);
            font-family: <?php echo esc_html($template_data['font_family']); ?>;
            font-size: 13px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        @page {
            margin: 18px;
        }

        .sheet {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            padding: 34px 40px 34px;
            border-radius: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .top td,
        .meta-grid td,
        .footer-grid td {
            vertical-align: top;
        }

        .title-cell {
            width: 56%;
            padding-right: 18px;
        }

        .company-cell {
            width: 44%;
            text-align: right;
        }

        .invoice-title {
            margin: 0;
            font-size: 62px;
            line-height: 0.95;
            font-weight: 500;
            letter-spacing: -0.055em;
            color: #000000;
        }

        .edit-line {
            margin-top: 28px;
            font-size: 42px;
            line-height: 1.05;
            font-weight: 500;
            color: #000000;
            letter-spacing: -0.04em;
            white-space: nowrap;
        }

        .edit-line svg {
            width: 40px;
            height: 40px;
            vertical-align: -6px;
            margin-right: 12px;
            stroke: #000000;
        }

        .company-logo-wrap {
            margin-bottom: 10px;
        }

        .company-logo {
            display: inline-block;
            max-width: 180px;
            max-height: 72px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .company-name {
            margin: 0 0 6px;
            font-size: 14px;
            line-height: 1.25;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--tpl-accent);
        }

        .company-line {
            margin: 1px 0 0;
            font-size: 12px;
            line-height: 1.42;
            color: var(--tpl-muted);
        }

        .meta-grid {
            margin-top: 48px;
        }

        .bill-cell {
            width: 54%;
            padding-right: 18px;
        }

        .details-cell {
            width: 46%;
        }

        .label {
            margin: 0 0 4px;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 500;
            color: var(--tpl-primary);
        }

        .bill-name {
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
            font-weight: 400;
            color: #111827;
        }

        .bill-line {
            margin: 1px 0 0;
            font-size: 12px;
            line-height: 1.4;
            color: #111827;
        }

        .inline-meta td {
            width: 33.33%;
            padding-left: 18px;
            vertical-align: top;
        }

        .inline-meta td:first-child {
            padding-left: 0;
        }

        .inline-meta .value {
            margin: 2px 0 0;
            font-size: 14px;
            line-height: 1.28;
            font-weight: 400;
            color: #111827;
            word-break: break-word;
        }

        .inline-meta .value.amount-due {
            font-weight: 700;
        }

        .due-block {
            margin-top: 22px;
        }

        .divider {
            margin: 56px 0 14px;
            border-top: 3px solid var(--tpl-primary);
        }

        .items thead th {
            padding: 0 8px 12px 0;
            text-align: left;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 500;
            color: var(--tpl-primary);
            text-transform: uppercase;
        }

        .items thead th:last-child,
        .items tbody td:last-child {
            padding-right: 0;
            text-align: right;
        }

        .items tbody td {
            padding: 16px 8px 14px 0;
            border-bottom: 1px solid var(--tpl-line);
            vertical-align: top;
        }

        .items tbody tr:last-child td {
            border-bottom: 0;
        }

        .description-title {
            margin: 0;
            font-size: 15px;
            line-height: 1.28;
            font-weight: 400;
            color: #111827;
        }

        .description-note {
            margin: 5px 0 0;
            font-size: 11px;
            line-height: 1.42;
            color: var(--tpl-muted);
        }

        .number-cell {
            white-space: nowrap;
            color: #111827;
            font-size: 14px;
        }

        .number-sub {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            line-height: 1.35;
            color: var(--tpl-muted);
        }

        .summary-area {
            width: 50%;
            margin-left: auto;
            margin-top: 24px;
        }

        .summary-area td {
            padding: 4px 0;
            font-size: 15px;
            line-height: 1.42;
            color: #111827;
        }

        .summary-area td:last-child {
            text-align: right;
            white-space: nowrap;
            font-weight: 500;
        }

        .summary-area .total-row td {
            padding-top: 11px;
            border-top: 1px solid var(--tpl-line);
        }

        .summary-area .deposit-row td {
            padding-top: 4px;
        }

        .summary-area .deposit-due td {
            padding-top: 11px;
            border-top: 3px solid var(--tpl-primary);
            font-weight: 700;
            color: var(--tpl-text);
        }

        .footer-grid {
            margin-top: 54px;
        }

        .left-footer {
            width: 48%;
            padding-right: 30px;
        }

        .right-footer {
            width: 52%;
            padding-left: 10px;
        }

        .section-title {
            margin: 0 0 5px;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 500;
            color: var(--tpl-primary);
        }

        .section-copy {
            margin: 0 0 4px;
            font-size: 12px;
            line-height: 1.45;
            color: #111827;
        }

        .footer-section-gap {
            margin-top: 18px;
        }

        .right-meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .right-meta-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.45;
            color: #111827;
        }

        .right-meta-table td:first-child {
            width: 55%;
        }

        .right-meta-table td:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .mini-divider {
            margin: 8px 0 10px;
            border-top: 1px solid var(--tpl-line);
        }

        .qr-wrap {
            margin-top: 18px;
            text-align: right;
        }

        .qr {
            width: 108px;
            height: 108px;
            display: inline-block;
            object-fit: contain;
        }

        .caption {
            margin: 6px 0 0;
            font-size: 11px;
            line-height: 1.35;
            color: var(--tpl-muted);
            text-align: right;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 11px;
            line-height: 1.4;
            color: var(--tpl-muted);
            text-align: right;
        }

        .empty-copy {
            color: var(--tpl-muted);
        }

        .logo-fallback-space {
            height: 24px;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .sheet {
                max-width: 100%;
                border-radius: 0;
            }

            .items tr,
            .meta-grid tr,
            .footer-grid tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="top">
            <tr>
                <td class="title-cell">
                    <h1 class="invoice-title">Invoice</h1>

                    <div class="edit-line">
                        <?php echo $showPreviewHint ? 'Click to edit' : esc_html($template_data['invoice_number']); ?>
                    </div>
                </td>

                <td class="company-cell">
                    <?php if ($hasLogo) : ?>
                        <div class="company-logo-wrap">
                            <img
                                class="company-logo"
                                src="<?php echo esc_url($template_data['logo_url']); ?>"
                                alt="Company logo"
                            />
                        </div>
                    <?php else : ?>
                        <div class="logo-fallback-space"></div>
                    <?php endif; ?>

                    <p class="company-name"><?php echo esc_html($template_data['company_name'] ?: 'YOUR COMPANY'); ?></p>

                    <?php if (!empty($companyLines)) : ?>
                        <?php foreach ($companyLines as $line) : ?>
                            <p class="company-line"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table class="meta-grid">
            <tr>
                <td class="bill-cell">
                    <p class="label">Billed To</p>
                    <p class="bill-name"><?php echo esc_html($template_data['customer_name'] ?: 'Your Client'); ?></p>

                    <?php if (!empty($customerLines)) : ?>
                        <?php foreach ($customerLines as $line) : ?>
                            <p class="bill-line"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>

                <td class="details-cell">
                    <table class="inline-meta">
                        <tr>
                            <td>
                                <p class="label">Date Issued</p>
                                <p class="value"><?php echo esc_html($detailValue('Date Issued', $detailRows)); ?></p>

                                <div class="due-block">
                                    <p class="label">Due Date</p>
                                    <p class="value"><?php echo esc_html($detailValue('Due Date', $detailRows)); ?></p>
                                </div>
                            </td>

                            <td>
                                <p class="label">Invoice Number</p>
                                <p class="value"><?php echo esc_html($detailValue('Invoice Number', $detailRows)); ?></p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:58%;">Description</th>
                    <th style="width:14%;">Rate</th>
                    <th style="width:10%;">Qty</th>
                    <th style="width:18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoiceItems)) : ?>
                    <?php foreach ($invoiceItems as $item) : ?>
                        <tr>
                            <td>
                                <p class="description-title"><?php echo esc_html($item['description'] ?? ''); ?></p>
                                <?php if (!empty($item['detail'])) : ?>
                                    <p class="description-note"><?php echo esc_html($item['detail']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="number-cell">
                                <?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['unit_price'] ?? 0))); ?>
                                <?php if (!empty($item['tax_rate_label'])) : ?>
                                    <span class="number-sub"><?php echo esc_html($item['tax_rate_label']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="number-cell">
                                <?php echo esc_html(rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2), '0'), '.')); ?>
                            </td>
                            <td class="number-cell">
                                <?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['line_total'] ?? 0))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4" class="empty-copy" style="padding:14px 0;">No invoice items available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="summary-area">
            <?php foreach ($summaryRows as $row) : ?>
                <?php
                $kind = (string) ($row['kind'] ?? '');
                $label = (string) ($row['label'] ?? '');
                $amount = (float) ($row['amount'] ?? 0);

                if ($label === 'Deposit Requested' && $amount <= 0) {
                    continue;
                }
                ?>
                <tr class="<?php echo esc_attr($kind === 'grand' ? 'total-row' : ($label === 'Deposit Due' ? 'deposit-due' : ($label === 'Deposit Requested' ? 'deposit-row' : ''))); ?>">
                    <td><?php echo esc_html($label); ?></td>
                    <td><?php echo esc_html($money($amount, $kind)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table class="footer-grid">
            <tr>
                <td class="left-footer">
                    <p class="section-title">Notes</p>
                    <?php if (!empty($notesLines)) : ?>
                        <?php foreach ($notesLines as $line) : ?>
                            <p class="section-copy"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="section-copy">Thank you for your business!</p>
                    <?php endif; ?>

                    <div class="footer-section-gap">
                        <p class="section-title">Terms</p>
                        <?php if (!empty($termsLines)) : ?>
                            <?php foreach ($termsLines as $line) : ?>
                                <p class="section-copy"><?php echo esc_html($line); ?></p>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="section-copy">Please pay within 30 days using the link in your invoice email.</p>
                        <?php endif; ?>
                    </div>
                </td>

                <td class="right-footer">
                    <?php if (!empty($bankLines)) : ?>
                        <table class="right-meta-table">
                            <?php foreach ($bankLines as $line) : ?>
                                <tr>
                                    <td colspan="2"><?php echo esc_html($line); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($taxBreakdown)) : ?>
                        <div class="mini-divider"></div>
                        <table class="right-meta-table">
                            <?php foreach ($taxBreakdown as $tax) : ?>
                                <tr>
                                    <td><?php echo esc_html($tax['label'] ?? 'Tax'); ?></td>
                                    <td><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($tax['amount'] ?? 0))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($template_data['qr_code'])) : ?>
                        <div class="qr-wrap">
                            <img class="qr" src="<?php echo esc_url($template_data['qr_code']); ?>" alt="Payment QR code" />
                            <p class="caption">Scan to view payment reference details.</p>
                        </div>
                    <?php elseif (!empty($footerLines)) : ?>
                        <?php foreach ($footerLines as $line) : ?>
                            <p class="footer-note"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
