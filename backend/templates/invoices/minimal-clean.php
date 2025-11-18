<?php
$primaryColor = $settings->primary_color ?: '#1f2937';
$accentColor  = $settings->accent_color ?: '#6c5ce7';
$fontFamily   = $settings->font_family ?: "'Inter', 'Helvetica Neue', Arial, sans-serif";
$logoUrl      = $settings->logo_url ?: ($org->logo_url ?? '');
$currency     = $invoice->currency ?: 'INR';
$formatMoney = function ($amount) use ($currency) {
    return sprintf('%s %s', $currency, number_format((float) $amount, 2));
};
$customerLines = array_filter([
    $invoice->customer_name ?? '',
    $invoice->customer_email ?? '',
    $invoice->customer_phone ?? '',
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
            font-family: <?php echo esc_html($fontFamily); ?>;
            color: #111827;
            background: #ffffff;
            font-size: 14px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }
        .logo-block img {
            max-height: 64px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 700;
            color: <?php echo esc_html($primaryColor); ?>;
        }
        .invoice-title {
            text-align: right;
            color: <?php echo esc_html($primaryColor); ?>;
        }
        .invoice-title h1 {
            margin: 0;
            font-size: 32px;
            text-transform: uppercase;
        }
        .meta-grid {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        .meta-card {
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            width: 50%;
        }
        .meta-card h3 {
            margin: 0 0 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: <?php echo esc_html($accentColor); ?>;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        table.items th {
            background: <?php echo esc_html($accentColor); ?>;
            color: #ffffff;
            text-align: left;
            padding: 12px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.items td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals {
            width: 100%;
            margin-top: 24px;
        }
        .totals td {
            padding: 8px;
        }
        .totals .label {
            text-align: right;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.05em;
        }
        .totals .value {
            text-align: right;
            font-weight: 600;
            color: <?php echo esc_html($primaryColor); ?>;
        }
        .footer-section {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #4b5563;
        }
        .footer-section h4 {
            margin: 0 0 6px;
            color: <?php echo esc_html($primaryColor); ?>;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-block">
            <?php if ($logoUrl): ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="Logo" />
            <?php endif; ?>
            <div class="company-name"><?php echo esc_html($org->org_name ?? ''); ?></div>
            <?php if (!empty($org->industry)): ?>
                <div><?php echo esc_html($org->industry); ?></div>
            <?php endif; ?>
        </div>
        <div class="invoice-title">
            <h1>Invoice</h1>
            <div><strong>#:</strong> <?php echo esc_html($invoice->invoice_number); ?></div>
            <div><strong>Date:</strong> <?php echo esc_html($invoice->date); ?></div>
            <?php if (!empty($invoice->due_date)): ?>
                <div><strong>Due:</strong> <?php echo esc_html($invoice->due_date); ?></div>
            <?php endif; ?>
            <div><strong>Status:</strong> <?php echo esc_html(strtoupper($invoice->status)); ?></div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-card">
            <h3>Bill To</h3>
            <?php if ($customerLines): ?>
                <?php foreach ($customerLines as $line): ?>
                    <div><?php echo esc_html($line); ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div>No customer details provided.</div>
            <?php endif; ?>
        </div>
        <div class="meta-card">
            <h3>Invoice Details</h3>
            <div><strong>Invoice #:</strong> <?php echo esc_html($invoice->invoice_number); ?></div>
            <div><strong>Issued:</strong> <?php echo esc_html($invoice->date); ?></div>
            <?php if (!empty($invoice->due_date)): ?>
                <div><strong>Due Date:</strong> <?php echo esc_html($invoice->due_date); ?></div>
            <?php endif; ?>
            <div><strong>Status:</strong> <?php echo esc_html(ucfirst(strtolower($invoice->status))); ?></div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
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
                $lineTax = isset($item->tax_amount) ? (float) $item->tax_amount : ($lineTotal * ((float) ($item->tax_rate ?? 0) / 100));
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->description ?? 'Item'); ?></strong>
                    </td>
                    <td><?php echo esc_html(number_format($qty, 2)); ?></td>
                    <td><?php echo esc_html($formatMoney($rate)); ?></td>
                    <td><?php echo esc_html(number_format((float) ($item->tax_rate ?? 0), 2)); ?>%</td>
                    <td><?php echo esc_html($formatMoney($lineTotal + $lineTax)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value"><?php echo esc_html($formatMoney($invoice->subtotal)); ?></td>
        </tr>
        <tr>
            <td class="label">Tax</td>
            <td class="value"><?php echo esc_html($formatMoney($invoice->tax_total)); ?></td>
        </tr>
        <tr>
            <td class="label">Total</td>
            <td class="value"><?php echo esc_html($formatMoney($invoice->total)); ?></td>
        </tr>
    </table>

    <?php if (!empty($settings->bank_details)): ?>
        <div class="footer-section">
            <h4>Bank Details</h4>
            <div><?php echo wp_kses_post(nl2br($settings->bank_details)); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings->terms_and_conditions)): ?>
        <div class="footer-section">
            <h4>Terms &amp; Conditions</h4>
            <div><?php echo wp_kses_post(nl2br($settings->terms_and_conditions)); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings->footer_text)): ?>
        <div class="footer-section" style="text-align:center;">
            <?php echo wp_kses_post($settings->footer_text); ?>
        </div>
    <?php endif; ?>
</body>
</html>
