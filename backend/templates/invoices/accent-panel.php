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
$orgLines = array_filter([
    $org->org_name ?? '',
    $org->industry ?? '',
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
            padding: 0;
            font-family: <?php echo $font_family; ?>;
            color: #111827;
            background: #f7f8fa;
        }
        .invoice-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            background: #f7f8fa;
        }
        .accent-panel {
            width: 28%;
            min-width: 220px;
            background: <?php echo esc_html($primary_color); ?>;
            color: #fff;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .accent-panel img {
            max-width: 140px;
            height: auto;
            margin-bottom: 12px;
        }
        .accent-panel h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .accent-panel p {
            margin: 4px 0;
            font-size: 13px;
            line-height: 1.5;
        }
        .content-area {
            width: 72%;
            padding: 32px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .header h2 {
            margin: 0;
            font-size: 32px;
            text-transform: uppercase;
            color: <?php echo esc_html($accent_color); ?>;
        }
        .header div span {
            display: block;
            font-size: 14px;
            color: #6b7280;
        }
        .bill-to, .meta {
            margin-bottom: 20px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        .bill-to h3, .meta h3 {
            margin: 0 0 12px;
            font-size: 16px;
            letter-spacing: 0.04em;
            color: <?php echo esc_html($primary_color); ?>;
            text-transform: uppercase;
        }
        .bill-to p, .meta p {
            margin: 4px 0;
            font-size: 14px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .meta-item span {
            display: block;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .meta-item strong {
            font-size: 15px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            border-radius: 12px;
            overflow: hidden;
        }
        .items-table th {
            background: #f3f4f6;
            color: #1f2937;
            text-align: left;
            padding: 12px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .items-table td:last-child,
        .items-table th:last-child {
            text-align: right;
        }
        .totals-box {
            margin-top: 20px;
            margin-left: auto;
            width: min(280px, 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .totals-box div {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            font-size: 14px;
        }
        .totals-box div:nth-child(odd) {
            background: #fafafa;
        }
        .totals-box div.total {
            background: <?php echo esc_html($primary_color); ?>;
            color: #fff;
            font-weight: 600;
            font-size: 15px;
        }
        .footer {
            margin-top: 32px;
            font-size: 13px;
            color: #4b5563;
        }
        .footer h4 {
            margin: 0 0 6px;
            font-size: 13px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: <?php echo esc_html($primary_color); ?>;
        }
        .footer p {
            margin: 4px 0 12px;
            line-height: 1.5;
        }
        .footer-note {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        <aside class="accent-panel">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" />
            <?php endif; ?>
            <?php if (!empty($org->org_name)): ?>
                <h1><?php echo esc_html($org->org_name); ?></h1>
            <?php endif; ?>
            <?php foreach ($orgLines as $line): ?>
                <p><?php echo esc_html($line); ?></p>
            <?php endforeach; ?>
        </aside>

        <main class="content-area">
            <div class="header">
                <div>
                    <span>Issued To</span>
                    <strong><?php echo esc_html($invoice->customer_name ?? ''); ?></strong>
                </div>
                <div style="text-align:right;">
                    <h2>Invoice</h2>
                    <span>No: <?php echo esc_html($invoice->invoice_number ?? '—'); ?></span>
                    <span>Date: <?php echo esc_html($invoice->date ?? '—'); ?></span>
                </div>
            </div>

            <section class="bill-to">
                <h3>Bill To</h3>
                <?php if ($customerLines): ?>
                    <?php foreach ($customerLines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No customer contact details provided.</p>
                <?php endif; ?>
            </section>

            <section class="meta">
                <h3>Invoice Details</h3>
                <div class="meta-grid">
                    <div class="meta-item">
                        <span>Invoice No</span>
                        <strong><?php echo esc_html($invoice->invoice_number ?? '—'); ?></strong>
                    </div>
                    <div class="meta-item">
                        <span>Invoice Date</span>
                        <strong><?php echo esc_html($invoice->date ?? '—'); ?></strong>
                    </div>
                    <?php if (!empty($invoice->due_date)): ?>
                    <div class="meta-item">
                        <span>Due Date</span>
                        <strong><?php echo esc_html($invoice->due_date); ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <span>Status</span>
                        <strong><?php echo esc_html(strtoupper($invoice->status ?? 'SENT')); ?></strong>
                    </div>
                </div>
            </section>

            <table class="items-table">
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
                            $lineTax = isset($item->tax_amount)
                                ? (float) $item->tax_amount
                                : $lineTotal * ((float) ($item->tax_rate ?? 0) / 100);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($item->description ?? 'Item'); ?></strong>
                            </td>
                            <td><?php echo number_format($qty, 2); ?></td>
                            <td><?php echo esc_html($formatMoney($rate)); ?></td>
                            <td><?php echo esc_html(number_format((float) ($item->tax_rate ?? 0), 2)); ?>%</td>
                            <td><?php echo esc_html($formatMoney($lineTotal + $lineTax)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-box">
                <div>
                    <span>Subtotal</span>
                    <span><?php echo esc_html($formatMoney($invoice->subtotal ?? 0)); ?></span>
                </div>
                <div>
                    <span>Tax</span>
                    <span><?php echo esc_html($formatMoney($invoice->tax_total ?? 0)); ?></span>
                </div>
                <div class="total">
                    <span>Total</span>
                    <span><?php echo esc_html($formatMoney($invoice->total ?? 0)); ?></span>
                </div>
            </div>

            <section class="footer">
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
            </section>
        </main>
    </div>
</body>
</html>
