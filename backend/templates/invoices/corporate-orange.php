<?php

defined('ABSPATH') || exit;

$template_data = $template_data ?? vy_build_invoice_template_view_model(
    'corporate-orange',
    $invoice ?? null,
    $items ?? [],
    $org ?? (object) [],
    $settings ?? []
);

$invoiceItems  = $template_data['items'] ?? [];
$summaryRows   = $template_data['summary_rows'] ?? [];
$bankPairs     = $template_data['bank_detail_pairs'] ?? [];
$footerLines   = $template_data['footer_lines'] ?? [];
$termsLines    = $template_data['terms_lines'] ?? [];
$taxBreakdown  = $template_data['tax_breakdown'] ?? [];
$customerLines = $template_data['customer_lines'] ?? [];
$companyLines  = $template_data['company_lines'] ?? [];

$money = static function ($amount, string $kind = 'normal') use ($template_data): string {
    $formatted = vy_invoice_money($template_data['currency'], (float) $amount);
    return $kind === 'discount' && (float) $amount > 0 ? '-' . $formatted : $formatted;
};
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
        }

        body {
            padding: 22px;
            background: #d9d7cf;
            color: #1f2937;
            font-family: <?php echo esc_html($template_data['font_family']); ?>;
            font-size: 13px;
            line-height: 1.45;
        }

        @page {
            margin: 16px;
        }

        .sheet {
            max-width: 760px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d7d7d7;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-band td,
        .info-grid td,
        .bottom-grid td {
            vertical-align: top;
        }

        .header-band {
            table-layout: fixed;
        }

        .orange-pane {
            width: 40%;
            background: var(--tpl-primary);
            color: #ffffff;
            padding: 26px 22px 20px 28px;
        }

        .split-pane {
            width: 16%;
            background: #ffffff;
            padding: 0;
        }

        .navy-pane {
            width: 44%;
            background: var(--tpl-accent);
            color: #ffffff;
            padding: 24px 24px 18px 20px;
        }

        .headline {
            margin: 0 0 12px;
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .meta-line {
            margin: 4px 0 0;
            font-size: 13px;
            line-height: 1.35;
        }

        .meta-line span {
            display: inline-block;
            min-width: 96px;
            color: rgba(255,255,255,0.82);
        }

        .company-brand {
            padding-top: 6px;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            min-height: 56px;
            padding: 8px 10px;
            margin-bottom: 10px;
            background: #ffffff;
            border-radius: 4px;
        }

        .company-logo {
            display: block;
            max-width: 90px;
            max-height: 42px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .company-icon {
            width: 46px;
            height: 46px;
        }

        .company-name {
            margin: 0;
            font-size: 18px;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .company-slogan {
            margin: 4px 0 0;
            font-size: 12px;
            line-height: 1.35;
            color: rgba(255,255,255,0.88);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .content {
            padding: 20px 36px 0;
        }

        .info-grid {
            table-layout: fixed;
        }

        .invoice-to {
            width: 56%;
            padding-right: 18px;
        }

        .payment-info {
            width: 44%;
            padding-left: 18px;
        }

        .section-kicker {
            margin: 0 0 7px;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700;
            color: #565656;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .client-name {
            margin: 0;
            font-size: 19px;
            line-height: 1.05;
            font-weight: 800;
            color: #141414;
            text-transform: uppercase;
        }

        .client-line,
        .payment-line {
            margin: 4px 0 0;
            color: #444444;
            font-size: 13px;
            line-height: 1.35;
        }

        .payment-line span {
            display: inline-block;
            min-width: 102px;
            color: #252525;
            font-weight: 700;
        }

        .items {
            margin-top: 18px;
            border: 1px solid #d7d7d7;
            table-layout: fixed;
        }

        .items thead th {
            padding: 10px 12px;
            background: var(--tpl-primary);
            color: #ffffff;
            text-align: left;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .items thead th:last-child,
        .items tbody td:last-child {
            text-align: right;
        }

        .items tbody td {
            padding: 11px 12px;
            border-top: 1px solid #d8d8d8;
            color: #303030;
            font-size: 13px;
            line-height: 1.35;
        }

        .items tbody tr:nth-child(odd) td {
            background: #efefef;
        }

        .items tbody tr:nth-child(even) td {
            background: #f8f8f8;
        }

        .item-note {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #666666;
        }

        .num {
            white-space: nowrap;
        }

        .totals-row {
            margin-top: 12px;
        }

        .totals-wrap {
            width: 39%;
            margin-left: auto;
        }

        .totals-wrap td {
            padding: 4px 0;
            font-size: 15px;
            line-height: 1.35;
            color: #2e2e2e;
        }

        .totals-wrap td:last-child {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .totals-wrap .grand td {
            background: var(--tpl-primary);
            color: #ffffff;
            padding: 8px 12px;
            font-weight: 800;
        }

        .bottom-grid {
            margin-top: 18px;
            table-layout: fixed;
        }

        .terms {
            width: 58%;
            padding-right: 18px;
        }

        .signature {
            width: 42%;
            padding-left: 18px;
            text-align: center;
        }

        .copy {
            margin: 5px 0 0;
            color: #595959;
            font-size: 13px;
            line-height: 1.45;
        }

        .contact-row {
            margin-top: 16px;
        }

        .contact-line {
            margin: 5px 0 0;
            color: #575757;
            font-size: 13px;
            line-height: 1.35;
        }

        .contact-icon {
            display: inline-block;
            width: 18px;
            color: var(--tpl-primary);
            font-weight: 700;
        }

        .signature-mark {
            margin: 18px auto 0;
            width: 78%;
            padding-bottom: 4px;
            border-bottom: 1px solid #b7b7b7;
            font-size: 28px;
            line-height: 1;
            color: #2d2d2d;
            font-family: inherit;
        }

        .signature-label {
            margin: 6px 0 0;
            color: #333333;
            font-size: 18px;
            line-height: 1.1;
        }

        .thanks {
            margin-top: 12px;
            color: #444444;
            font-size: 13px;
            line-height: 1.35;
        }

        .tax-block {
            margin-top: 14px;
            padding: 10px 12px;
            border: 1px solid #d8d8d8;
            background: #fafafa;
        }

        .tax-line {
            margin: 4px 0 0;
            color: #333333;
        }

        .tax-line strong {
            float: right;
        }

        .qr-block {
            margin-top: 14px;
            padding: 10px 12px;
            border: 1px solid #d8d8d8;
            background: #ffffff;
            text-align: center;
        }

        .qr-block img {
            width: 112px;
            height: 112px;
            object-fit: contain;
        }

        .footer-accent {
            margin-top: 16px;
            width: 100%;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .sheet {
                max-width: 100%;
                box-shadow: none;
                border: 1px solid #d7d7d7;
            }

            .items tr,
            .bottom-grid tr,
            .info-grid tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="header-band">
            <tr>
                <td class="orange-pane">
                    <p class="headline">Invoice</p>
                    <p class="meta-line"><span>Invoice No</span><?php echo esc_html($template_data['invoice_number']); ?></p>
                    <p class="meta-line"><span>Invoice Date</span><?php echo esc_html($template_data['invoice_date_display_short']); ?></p>
                    <p class="meta-line"><span>Due Date</span><?php echo esc_html($template_data['invoice_due_display_short']); ?></p>
                </td>

                <td class="split-pane">
                    <svg viewBox="0 0 92 108" width="100%" height="108" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="none">
                        <polygon points="0,108 34,0 61,0 27,108" fill="<?php echo esc_attr($template_data['primary']); ?>"></polygon>
                        <polygon points="34,108 54,16 70,16 50,108" fill="#ffffff"></polygon>
                        <polygon points="56,108 78,0 92,0 70,108" fill="<?php echo esc_attr($template_data['accent']); ?>"></polygon>
                    </svg>
                </td>

                <td class="navy-pane">
                    <div class="company-brand">
                        <?php if (!empty($template_data['logo_url'])) : ?>
                            <div class="logo-badge">
                                <img class="company-logo" src="<?php echo esc_url($template_data['logo_url']); ?>" alt="Company logo" />
                            </div>
                        <?php else : ?>
                            <div class="logo-badge">
                                <svg class="company-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect x="4" y="10" width="16" height="16" stroke="<?php echo esc_attr($template_data['primary']); ?>" stroke-width="2"></rect>
                                    <rect x="18" y="18" width="16" height="16" stroke="<?php echo esc_attr($template_data['primary']); ?>" stroke-width="2"></rect>
                                </svg>
                            </div>
                        <?php endif; ?>

                        <p class="company-name"><?php echo esc_html($template_data['company_name'] ?: 'COMPANY'); ?></p>
                        <p class="company-slogan"><?php echo esc_html($template_data['company_tagline'] ?: 'Your Slogan'); ?></p>
                    </div>
                </td>
            </tr>
        </table>

        <div class="content">
            <table class="info-grid">
                <tr>
                    <td class="invoice-to">
                        <p class="section-kicker">Invoice To.</p>
                        <p class="client-name"><?php echo esc_html($template_data['customer_name'] ?: 'NAME SURNAME'); ?></p>
                        <?php foreach ($customerLines as $line) : ?>
                            <p class="client-line"><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    </td>

                    <td class="payment-info">
                        <p class="section-kicker">Payment Info :</p>
                        <?php if (!empty($bankPairs)) : ?>
                            <?php foreach (array_slice($bankPairs, 0, 3) as $pair) : ?>
                                <p class="payment-line">
                                    <span><?php echo esc_html($pair['label'] ?? ''); ?></span>
                                    <?php echo esc_html($pair['value'] ?? ''); ?>
                                </p>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="payment-line"><span>Account No</span>00 123 456</p>
                            <p class="payment-line"><span>A/C Name</span><?php echo esc_html($template_data['company_name'] ?: 'Lorem AB'); ?></p>
                            <p class="payment-line"><span>Bank Name</span>012 ABCD</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width:11%;">No</th>
                        <th style="width:43%;">Description</th>
                        <th style="width:14%;">Qty</th>
                        <th style="width:16%;">Price</th>
                        <th style="width:16%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoiceItems)) : ?>
                        <?php foreach ($invoiceItems as $item) : ?>
                            <tr>
                                <td class="num"><?php echo esc_html(str_pad((string) ($item['index'] ?? 0), 2, '0', STR_PAD_LEFT) . '.'); ?></td>
                                <td>
                                    <?php echo esc_html($item['description'] ?? ''); ?>
                                    <?php if (!empty($item['detail'])) : ?>
                                        <span class="item-note"><?php echo esc_html($item['detail']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?php echo esc_html(rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2), '0'), '.')); ?></td>
                                <td class="num"><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['unit_price'] ?? 0))); ?></td>
                                <td class="num"><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($item['line_total'] ?? 0))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No invoice items available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="totals-row">
                <table class="totals-wrap">
                    <?php foreach ($summaryRows as $row) : ?>
                        <?php if (in_array(($row['label'] ?? ''), ['Deposit Requested', 'Deposit Due'], true)) { continue; } ?>
                        <tr class="<?php echo esc_attr((($row['kind'] ?? '') === 'grand') ? 'grand' : ''); ?>">
                            <td><?php echo esc_html((($row['kind'] ?? '') === 'grand') ? 'Grand Total' : ($row['label'] ?? '')); ?></td>
                            <td><?php echo esc_html($money($row['amount'] ?? 0, (string) ($row['kind'] ?? 'normal'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <table class="bottom-grid">
                <tr>
                    <td class="terms">
                        <p class="section-kicker">Terms &amp; Condition</p>
                        <?php if (!empty($termsLines)) : ?>
                            <?php foreach ($termsLines as $line) : ?>
                                <p class="copy"><?php echo esc_html($line); ?></p>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="copy">Lorem ipsum is simply dummy text of the printing and typesetting.</p>
                        <?php endif; ?>

                        <div class="contact-row">
                            <p class="contact-line"><span class="contact-icon">☎</span><?php echo esc_html($template_data['customer_phone'] ?: '+91 123 456 7890'); ?></p>
                            <p class="contact-line"><span class="contact-icon">⌂</span><?php echo esc_html($template_data['company_tagline'] ?: 'Your Website Here'); ?></p>
                            <p class="contact-line"><span class="contact-icon">⌖</span><?php echo esc_html($companyLines[0] ?? 'Your Address Here'); ?></p>
                        </div>

                        <?php if (!empty($taxBreakdown)) : ?>
                            <div class="tax-block">
                                <p class="section-kicker">Tax Breakup</p>
                                <?php foreach ($taxBreakdown as $tax) : ?>
                                    <p class="tax-line"><?php echo esc_html($tax['label'] ?? 'Tax'); ?><strong><?php echo esc_html(vy_invoice_money($template_data['currency'], (float) ($tax['amount'] ?? 0))); ?></strong></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($template_data['qr_code'])) : ?>
                            <div class="qr-block">
                                <p class="section-kicker">Payment QR</p>
                                <img src="<?php echo esc_url($template_data['qr_code']); ?>" alt="Payment QR code" />
                            </div>
                        <?php endif; ?>
                    </td>

                    <td class="signature">
                        <div class="signature-mark"><?php echo esc_html(($template_data['signature_name'] ?? '') ?: 'Jonathan'); ?></div>
                        <p class="signature-label">Signature</p>
                        <p class="thanks"><?php echo esc_html($footerLines[0] ?? 'Thank you for your business'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="footer-accent">
                <svg viewBox="0 0 700 52" width="100%" height="52" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="none">
                    <rect x="0" y="30" width="700" height="22" fill="<?php echo esc_attr($template_data['accent']); ?>"></rect>
                    <polygon points="498,52 530,8 566,8 534,52" fill="<?php echo esc_attr($template_data['primary']); ?>"></polygon>
                    <polygon points="536,52 553,24 568,24 551,52" fill="#ffffff"></polygon>
                    <polygon points="568,52 604,8 700,8 700,52" fill="<?php echo esc_attr($template_data['primary']); ?>"></polygon>
                </svg>
            </div>
        </div>
    </div>
</body>
</html>
