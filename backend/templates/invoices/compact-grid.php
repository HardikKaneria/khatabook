<?php
$primary_color = $settings->primary_color ?: '#6c5ce7';
$accent_color  = $settings->accent_color ?: '#0f172a';
$font_family   = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif";
$logo_url      = !empty($settings->logo_url) ? $settings->logo_url : ($org->logo_url ?? '');
$currency      = $invoice->currency ?: 'INR';
$formatMoney   = fn($amount) => sprintf('%s %s', $currency, number_format((float) $amount, 2));
$companyLines  = array_filter([
    $org->org_name ?? '',
    $org->address ?? '',
    $org->city ?? '',
    $org->state ?? '',
    $org->country ?? '',
    $org->zip ?? '',
    $org->phone ?? '',
    $org->email ?? '',
]);
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
            padding: 24px;
            background: #f0f2f5;
            font-family: <?php echo $font_family; ?>;
            color: #1f2933;
        }
        .invoice-container {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .top-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }
        .top-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
        }
        .top-card h3 {
            margin: 0 0 8px;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: <?php echo esc_html($primary_color); ?>;
        }
        .company-block img {
            max-width: 120px;
            height: auto;
            margin-bottom: 8px;
        }
        .company-block p,
        .customer-block p {
            margin: 2px 0;
            font-size: 13px;
        }
        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-info td {
            padding: 3px 0;
            font-size: 13px;
        }
        .invoice-info td:first-child {
            color: #6b7280;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 20px;
            font-size: 13px;
        }
        .items-table th {
            background: #f7f7f7;
            text-align: left;
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: <?php echo esc_html($accent_color); ?>;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #eef0f3;
        }
        .items-table td:last-child,
        .items-table th:last-child {
            text-align: right;
        }
        .bottom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }
        .terms-card,
        .totals-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
        }
        .terms-card h4,
        .totals-card h4 {
            margin: 0 0 10px;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: <?php echo esc_html($primary_color); ?>;
        }
        .terms-card p {
            font-size: 13px;
            line-height: 1.4;
            margin: 6px 0;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .totals-table td {
            padding: 6px 0;
        }
        .totals-table td:first-child {
            color: #6b7280;
        }
        .totals-table tr.total td {
            border-top: 1px solid #d1d5db;
            font-weight: 700;
            color: <?php echo esc_html($accent_color); ?>;
            padding-top: 10px;
        }
        .payment-details {
            margin-top: 12px;
            font-size: 13px;
        }
        .payment-details strong {
            color: <?php echo esc_html($primary_color); ?>;
        }
        .notes-block {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="top-grid">
            <div class="top-card company-block">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" />
                <?php endif; ?>
                <?php foreach ($companyLines as $line): ?>
                    <p><?php echo esc_html($line); ?></p>
                <?php endforeach; ?>
            </div>
            <div class="top-card invoice-info">
                <h3>Invoice</h3>
                <table>
                    <tr>
                        <td>Invoice No</td>
                        <td><?php echo esc_html($invoice->invoice_number ?? '—'); ?></td>
                    </tr>
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
            <div class="top-card customer-block">
                <h3>Bill To</h3>
                <?php if ($customerLines): ?>
                    <?php foreach ($customerLines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No customer details provided.</p>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>HSN/SAC</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Tax %</th>
                    <th>Tax Amt</th>
                    <th>Line Total</th>
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
                        <td><?php echo esc_html($item->description ?? ''); ?></td>
                        <td><?php echo esc_html($item->hsn ?? $item->hsn_sac ?? '—'); ?></td>
                        <td><?php echo number_format($qty, 2); ?></td>
                        <td><?php echo esc_html($formatMoney($rate)); ?></td>
                        <td><?php echo number_format($taxRate, 2); ?>%</td>
                        <td><?php echo esc_html($formatMoney($taxAmount)); ?></td>
                        <td><?php echo esc_html($formatMoney($lineTotal + $taxAmount)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="bottom-grid">
            <div class="terms-card">
                <h4>Terms &amp; Notes</h4>
                <?php if (!empty($settings->terms_and_conditions)): ?>
                    <p><?php echo nl2br(esc_html($settings->terms_and_conditions)); ?></p>
                <?php else: ?>
                    <p>No terms specified.</p>
                <?php endif; ?>

                <?php if (!empty($invoice->notes)): ?>
                    <div class="notes-block">
                        <strong>Additional Notes:</strong>
                        <p><?php echo nl2br(esc_html($invoice->notes)); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="totals-card">
                <h4>Totals</h4>
                <table class="totals-table">
                    <tr>
                        <td>Subtotal</td>
                        <td><?php echo esc_html($formatMoney($invoice->subtotal ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td><?php echo esc_html($formatMoney($invoice->tax_total ?? 0)); ?></td>
                    </tr>
                    <tr class="total">
                        <td>Total</td>
                        <td><?php echo esc_html($formatMoney($invoice->total ?? 0)); ?></td>
                    </tr>
                </table>

                <?php if (!empty($settings->bank_details)): ?>
                    <div class="payment-details">
                        <strong>Payment Details:</strong>
                        <p><?php echo nl2br(esc_html($settings->bank_details)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
