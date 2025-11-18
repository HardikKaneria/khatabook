<?php
$primary_color = $settings->primary_color ?: '#1f2937';
$accent_color  = $settings->accent_color ?: '#6c5ce7';
$font_family   = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif";
$logo_url      = !empty($settings->logo_url) ? $settings->logo_url : ($org->logo_url ?? '');
$currency      = $invoice->currency ?: 'INR';
$formatMoney   = fn($amount) => sprintf('%s %s', $currency, number_format((float) $amount, 2));
$customerLines = array_filter([
    $invoice->customer_name ?? '',
    $invoice->customer_email ?? '',
    $invoice->customer_phone ?? '',
]);
$companyLines = array_filter([
    $org->org_name ?? '',
    $org->address ?? '',
    $org->city ?? '',
    $org->state ?? '',
    $org->country ?? '',
    $org->zip ?? '',
    $org->phone ?? '',
    $org->email ?? '',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            background: #f7f7f7;
            font-family: <?php echo $font_family; ?>;
            color: #1f2933;
        }
        .wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #dedede;
            border-radius: 6px;
            padding: 36px 40px;
        }
        .org-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .org-header img {
            max-height: 60px;
            width: auto;
            margin-bottom: 12px;
        }
        .org-header h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .org-header p {
            margin: 6px 0;
            font-size: 13px;
            color: #4b5563;
        }
        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 24px 0;
        }
        .grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .panel {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
        }
        .panel h3 {
            margin: 0 0 10px;
            font-size: 15px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: <?php echo esc_html($accent_color); ?>;
        }
        .panel p {
            margin: 4px 0;
            font-size: 13px;
        }
        .invoice-meta table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-meta td {
            padding: 4px 0;
            font-size: 13px;
        }
        .invoice-meta td:first-child {
            color: #6b7280;
            width: 45%;
        }
        .invoice-meta strong {
            color: #111827;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.items thead {
            background: #f5f5f5;
        }
        table.items th,
        table.items td {
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        table.items th {
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 12px;
        }
        table.items td:last-child,
        table.items th:last-child {
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
        .totals table {
            border-collapse: collapse;
            min-width: 280px;
        }
        .totals td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .totals tr.total td {
            font-weight: 700;
            color: <?php echo esc_html($accent_color); ?>;
            border-top-width: 2px;
            font-size: 15px;
        }
        .bottom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="org-header">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" />
            <?php endif; ?>
            <?php if (!empty($org->org_name)): ?>
                <h1><?php echo esc_html($org->org_name); ?></h1>
            <?php endif; ?>
            <?php foreach ($companyLines as $line): ?>
                <p><?php echo esc_html($line); ?></p>
            <?php endforeach; ?>
        </div>

        <hr />

        <div class="grid-two">
            <div class="panel">
                <h3>Bill To</h3>
                <?php if ($customerLines): ?>
                    <?php foreach ($customerLines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No customer details provided.</p>
                <?php endif; ?>
            </div>
            <div class="panel invoice-meta">
                <h3>Invoice</h3>
                <table>
                    <tr>
                        <td>Invoice #</td>
                        <td><strong><?php echo esc_html($invoice->invoice_number ?? '—'); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Date</td>
                        <td><?php echo esc_html($invoice->date ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <td>Due Date</td>
                        <td><?php echo esc_html($invoice->due_date ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td><?php echo esc_html(strtoupper($invoice->status ?? 'SENT')); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="items" style="margin-top: 12px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Tax</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                        $qty = isset($item->quantity) ? (float) $item->quantity : 0;
                        $rate = isset($item->unit_price) ? (float) $item->unit_price : 0;
                        $lineTotal = isset($item->line_total) ? (float) $item->line_total : $qty * $rate;
                        $taxRate = isset($item->tax_rate) ? (float) $item->tax_rate : 0;
                        $taxAmount = isset($item->tax_amount) ? (float) $item->tax_amount : ($lineTotal * $taxRate / 100);
                    ?>
                    <tr>
                        <td><?php echo esc_html($item->description ?? 'Item'); ?></td>
                        <td><?php echo number_format($qty, 2); ?></td>
                        <td><?php echo esc_html($formatMoney($rate)); ?></td>
                        <td><?php echo number_format($taxRate, 2); ?>%</td>
                        <td><?php echo esc_html($formatMoney($lineTotal + $taxAmount)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td><?php echo esc_html($formatMoney($invoice->subtotal ?? 0)); ?></td>
                </tr>
                <tr>
                    <td>Tax</td>
                    <td><?php echo esc_html($formatMoney($invoice->tax_total ?? 0)); ?></td>
                </tr>
                <tr class="total">
                    <td colspan="2">Total: <?php echo esc_html($formatMoney($invoice->total ?? 0)); ?></td>
                </tr>
            </table>
        </div>

        <div class="bottom-grid">
            <div>
                <h3 style="font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:<?php echo esc_html($accent_color); ?>;">Terms &amp; Conditions</h3>
                <?php if (!empty($settings->terms_and_conditions)): ?>
                    <p style="font-size:13px; line-height:1.5;"><?php echo nl2br(esc_html($settings->terms_and_conditions)); ?></p>
                <?php else: ?>
                    <p style="font-size:13px; line-height:1.5;">No terms provided.</p>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:<?php echo esc_html($accent_color); ?>;">Payment Details</h3>
                <?php if (!empty($settings->bank_details)): ?>
                    <p style="font-size:13px; line-height:1.5;"><?php echo nl2br(esc_html($settings->bank_details)); ?></p>
                <?php else: ?>
                    <p style="font-size:13px; line-height:1.5;">Bank information not provided.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($settings->footer_text)): ?>
            <p class="footer-note"><?php echo wp_kses_post($settings->footer_text); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
