<?php
$primary_color = $settings->primary_color ?: '#6c5ce7';
$accent_color  = $settings->accent_color ?: '#e84393';
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
            background: #f5f6fb;
            font-family: <?php echo $font_family; ?>;
            color: #1f2933;
        }
        .header {
            background: <?php echo esc_html($primary_color); ?>;
            color: #fff;
            padding: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header img {
            max-height: 60px;
            width: auto;
        }
        .header .company-info h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header .company-info p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .header .right {
            text-align: right;
        }
        .header .right h2 {
            margin: 0;
            font-size: 32px;
            letter-spacing: 0.08em;
        }
        .header .right span {
            display: block;
            margin-top: 6px;
            font-size: 14px;
        }
        .invoice-card {
            max-width: 880px;
            margin: -40px auto 40px;
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.1);
        }
        .grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .card-section {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .card-section h3 {
            margin: 0 0 12px;
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: <?php echo esc_html($primary_color); ?>;
        }
        .card-section p {
            margin: 4px 0;
            font-size: 14px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 0;
            font-size: 13px;
        }
        .meta-table td:first-child {
            color: #6b7280;
            width: 45%;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        table.items th {
            background: #f4f4f6;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.items td {
            padding: 10px;
            border-bottom: 1px solid #f0f1f5;
        }
        table.items td:last-child,
        table.items th:last-child {
            text-align: right;
        }
        .totals {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
        }
        .totals table {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            width: 320px;
        }
        .totals td {
            padding: 10px 14px;
            font-size: 14px;
        }
        .totals tr:nth-child(odd) {
            background: #fafafa;
        }
        .totals .total-row td {
            background: <?php echo esc_html($accent_color); ?>;
            color: #fff;
            font-weight: 600;
            font-size: 16px;
        }
        .footer {
            margin-top: 28px;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            font-size: 13px;
        }
        .footer h4 {
            margin: 0 0 6px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: <?php echo esc_html($primary_color); ?>;
        }
        .footer-note {
            margin-top: 12px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" />
            <?php endif; ?>
            <div class="company-info">
                <h1><?php echo esc_html($org->org_name ?? ''); ?></h1>
                <?php if ($companyLines): ?>
                    <p><?php echo esc_html(implode(' • ', $companyLines)); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="right">
            <h2>INVOICE</h2>
            <span>#<?php echo esc_html($invoice->invoice_number ?? '—'); ?></span>
            <span><?php echo esc_html($invoice->date ?? ''); ?></span>
        </div>
    </div>

    <div class="invoice-card">
        <div class="grid-two">
            <div class="card-section">
                <h3>Bill To</h3>
                <?php if ($customerLines): ?>
                    <?php foreach ($customerLines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No customer details provided.</p>
                <?php endif; ?>
            </div>
            <div class="card-section">
                <h3>Details</h3>
                <table class="meta-table">
                    <tr>
                        <td>Invoice Date</td>
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

        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Tax %</th>
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
                <tr class="total-row">
                    <td>Total</td>
                    <td><?php echo esc_html($formatMoney($invoice->total ?? 0)); ?></td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <?php if (!empty($settings->bank_details)): ?>
                <h4>Payment Details</h4>
                <p><?php echo nl2br(esc_html($settings->bank_details)); ?></p>
            <?php endif; ?>

            <?php if (!empty($settings->terms_and_conditions)): ?>
                <h4>Terms &amp; Conditions</h4>
                <p><?php echo nl2br(esc_html($settings->terms_and_conditions)); ?></p>
            <?php endif; ?>

            <?php if (!empty($settings->footer_text)): ?>
                <p class="footer-note"><?php echo wp_kses_post($settings->footer_text); ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
