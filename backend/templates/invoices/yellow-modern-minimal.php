<?php

defined('ABSPATH') || exit;

$template_data = $template_data ?? vy_build_invoice_template_view_model(
    'yellow-modern-minimal',
    $invoice ?? null,
    $items ?? [],
    $org ?? (object) [],
    $settings ?? []
);

$invoiceItems  = $template_data['items'] ?? [];
$summaryRows   = $template_data['summary_rows'] ?? [];
$bankPairs     = $template_data['bank_detail_pairs'] ?? [];
$termsLines    = $template_data['terms_lines'] ?? [];
$notesLines    = $template_data['notes_lines'] ?? [];
$footerLines   = $template_data['footer_lines'] ?? [];
$taxBreakdown  = $template_data['tax_breakdown'] ?? [];

$companyName   = trim((string) ($template_data['company_name'] ?? ''));
$companyTag    = trim((string) ($template_data['company_tagline'] ?? ''));
$customerName  = trim((string) ($template_data['customer_name'] ?? ''));
$customerLines = $template_data['customer_lines'] ?? [];
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
            padding: 18px;
            background: #ececec;
            color: var(--tpl-text);
            font-family: <?php echo esc_html($template_data['font_family']); ?>;
            font-size: 12px;
            line-height: 1.45;
        }

        @page {
            margin: 14px;
        }

        .sheet {
            max-width: 760px;
            margin: 0 auto;
            background: #ffffff;
            padding: 26px 34px 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-row td,
        .info-row td,
        .footer-row td,
        .sign-row td {
            vertical-align: top;
        }

        .top-left {
            width: 60%;
            padding-right: 14px;
        }

        .top-right {
            width: 40%;
            text-align: right;
        }

        .brand-name {
            margin: 0;
            font-size: 24px;
            line-height: 1.05;
            font-weight: 700;
            color: #18181b;
        }

        .brand-tag {
            margin: 5px 0 0;
            font-size: 10px;
            line-height: 1.2;
            color: var(--tpl-muted);
            text-transform: uppercase;
            letter-spacing: 0.16em;
        }

        .logo {
            max-width: 90px;
            max-height: 48px;
            display: inline-block;
        }

        .invoice-band {
            margin: 20px 0 18px;
        }

        .invoice-band td {
            vertical-align: middle;
        }

        .bar-cell {
            width: calc((100% - 210px) / 2);
        }

        .bar {
            height: 20px;
            background: var(--tpl-primary);
        }

        .invoice-title {
            width: 210px;
            text-align: center;
            font-size: 42px;
            line-height: 1;
            font-weight: 300;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #111827;
        }

        .info-row .left {
            width: 58%;
            padding-right: 16px;
        }

        .info-row .right {
            width: 42%;
            padding-left: 16px;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 16px;
            line-height: 1.2;
            font-weight: 700;
            color: #111827;
        }

        .customer-name {
            margin: 0;
            font-size: 14px;
            line-height: 1.35;
            font-weight: 700;
            color: #111827;
        }

        .line {
            margin: 3px 0 0;
            color: #52525b;
        }

        .meta-line {
            margin: 4px 0 0;
            color: #27272a;
        }

        .meta-line .label {
            display: inline-block;
            min-width: 84px;
            font-weight: 700;
            color: #111827;
        }

        .items-table {
            margin-top: 20px;
            border: 1px solid var(--tpl-line);
        }

        .items-table thead th {
            padding: 8px 10px;
            background: var(--tpl-accent);
            color: #ffffff;
            text-align: left;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 700;
        }

        .items-table thead th:last-child,
        .items-table tbody td:last-child {
            text-align: right;
        }

        .items-table tbody td {
            padding: 10px 10px;
            border-top: 1px solid var(--tpl-line);
            color: #27272a;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f7f7f8;
        }

        .items-table .col-sl {
            width: 10%;
        }

        .items-table .col-desc {
            width: 46%;
        }

        .items-table .col-price {
            width: 16%;
        }

        .items-table .col-qty {
            width: 12%;
        }

        .items-table .col-total {
            width: 16%;
        }

        .summary-box {
            width: 38%;
            margin-left: auto;
            margin-top: 10px;
        }

        .summary-box td {
            padding: 4px 0;
            font-size: 15px;
            color: #18181b;
        }

        .summary-box td:last-child {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .summary-box .grand td {
            background: var(--tpl-primary);
            padding: 8px 12px;
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .footer-row {
            margin-top: 18px;
        }

        .footer-row .left {
            width: 60%;
            padding-right: 18px;
        }

        .footer-row .right {
            width: 40%;
            padding-left: 18px;
        }

        .small-title {
            margin: 0 0 6px;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
            color: #111827;
        }

        .copy {
            margin: 4px 0 0;
            color: #4b5563;
        }

        .spacer-title {
            margin-top: 14px;
        }

        .tax-block,
        .qr-block {
            margin-top: 12px;
        }

        .tax-line {
            margin: 4px 0 0;
            color: #27272a;
        }

        .tax-line strong {
            float: right;
        }

        .qr-block img {
            width: 104px;
            height: 104px;
            display: block;
        }

        .sign-row {
            margin-top: 20px;
        }

        .rule-cell {
            width: calc((100% - 170px) / 2);
            vertical-align: middle;
        }

        .yellow-rule {
            height: 3px;
            background: var(--tpl-primary);
        }

        .signature-title {
            width: 170px;
            text-align: center;
            font-weight: 700;
            color: #27272a;
            vertical-align: middle;
        }

        .contact-strip {
            margin-top: 8px;
            text-align: center;
            color: var(--tpl-accent);
            letter-spacing: 0.02em;
        }

        .empty-text {
            color: var(--tpl-muted);
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .sheet {
                max-width: 100%;
            }

            .items-table tr,
            .footer-row tr,
            .sign-row tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="top-row">
            <tr>
                <td class="top-left">
                    <p class="brand-name"><?php echo esc_html($companyName ?: 'Your Company'); ?></p>
                    <p class="brand-tag"><?php echo esc_html($companyTag ?: 'Tagline space here'); ?></p>
                </td>
                <td class="top-right">
                    <?php if (!empty($template_data['logo_url'])) : ?>
                        <img
                            class="logo"
                            src="<?php echo esc_url($template_data['logo_url']); ?>"
                            alt="Company logo"
                        />
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table class="invoice-band">
            <tr>
                <td class="bar-cell"><div class="bar"></div></td>
                <td class="invoice-title">Invoice</td>
                <td class="bar-cell"><div class="bar"></div></td>
            </tr>
        </table>

        <table class="info-row">
            <tr>
                <td class="left">
                    <p class="section-title">Invoice to:</p>
                    <p class="customer-name"><?php echo esc_html($customerName ?: 'Client Name'); ?></p>

                    <?php if (!empty($customerLines)) : ?>
                        <?php foreach ($customerLines as $line) : ?>
                            <p class="line"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="line empty-text">Customer details not provided.</p>
                    <?php endif; ?>
                </td>

                <td class="right">
                    <p class="meta-line">
                        <span class="label">Invoice#</span>
                        <?php echo esc_html($template_data['invoice_number'] ?? ''); ?>
                    </p>
                    <p class="meta-line">
                        <span class="label">Date</span>
                        <?php echo esc_html($template_data['invoice_date_display_short'] ?? ''); ?>
                    </p>
                    <p class="meta-line">
                        <span class="label">Due Date</span>
                        <?php echo esc_html($template_data['invoice_due_display_short'] ?? ''); ?>
                    </p>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-sl">Sl.</th>
                    <th class="col-desc">Item Description</th>
                    <th class="col-price">Price</th>
                    <th class="col-qty">Qty.</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoiceItems)) : ?>
                    <?php foreach ($invoiceItems as $item) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($item['index'] ?? '')); ?></td>
                            <td><?php echo esc_html($item['description'] ?? ''); ?></td>
                            <td><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['unit_price'] ?? 0))); ?></td>
                            <td><?php echo esc_html(rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2), '0'), '.')); ?></td>
                            <td><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['line_total'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" class="empty-text" style="padding:12px 10px;">No invoice items available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="summary-box">
            <?php foreach ($summaryRows as $row) : ?>
                <?php
                $label  = (string) ($row['label'] ?? '');
                $kind   = (string) ($row['kind'] ?? '');
                $amount = (float) ($row['amount'] ?? 0);

                if (in_array($label, ['Discount', 'Deposit Requested', 'Deposit Due'], true) && $amount <= 0) {
                    continue;
                }
                ?>
                <tr class="<?php echo esc_attr($kind === 'grand' ? 'grand' : ''); ?>">
                    <td><?php echo esc_html($kind === 'grand' ? 'Total:' : ($label . ':')); ?></td>
                    <td>
                        <?php
                        echo esc_html(
                            ($kind === 'discount' && $amount > 0 ? '-' : '') .
                            vy_invoice_money($template_data['currency'], $amount)
                        );
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table class="footer-row">
            <tr>
                <td class="left">
                    <p class="small-title">Thank you for your business</p>

                    <p class="small-title spacer-title">Terms &amp; Conditions</p>
                    <?php if (!empty($termsLines)) : ?>
                        <?php foreach ($termsLines as $line) : ?>
                            <p class="copy"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="copy">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Fusce dignissim pretium consectetur.</p>
                    <?php endif; ?>

                    <p class="small-title spacer-title">Payment Info:</p>
                    <?php if (!empty($bankPairs)) : ?>
                        <?php foreach ($bankPairs as $pair) : ?>
                            <p class="copy">
                                <strong><?php echo esc_html($pair['label'] ?? ''); ?>:</strong>
                                <?php echo esc_html($pair['value'] ?? ''); ?>
                            </p>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($notesLines)) : ?>
                        <p class="small-title spacer-title">Notes</p>
                        <?php foreach ($notesLines as $line) : ?>
                            <p class="copy"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>

                <td class="right">
                    <?php if (!empty($taxBreakdown)) : ?>
                        <div class="tax-block">
                            <p class="small-title">Tax Breakup</p>
                            <?php foreach ($taxBreakdown as $tax) : ?>
                                <p class="tax-line">
                                    <?php echo esc_html($tax['label'] ?? 'Tax'); ?>
                                    <strong><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($tax['amount'] ?? 0))); ?></strong>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($template_data['qr_code'])) : ?>
                        <div class="qr-block">
                            <img src="<?php echo esc_url($template_data['qr_code']); ?>" alt="Payment QR code" />
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table class="sign-row">
            <tr>
                <td class="rule-cell"><div class="yellow-rule"></div></td>
                <td class="signature-title">Authorised Sign</td>
                <td class="rule-cell"><div class="yellow-rule"></div></td>
            </tr>
        </table>

        <p class="contact-strip">
            <?php echo esc_html($footerLines[0] ?? 'Phone # | Address | Website'); ?>
        </p>
    </div>
</body>
</html>
