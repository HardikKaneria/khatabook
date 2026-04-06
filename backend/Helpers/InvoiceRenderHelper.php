<?php

use KBS\Core\SystemLogger;
use Mpdf\QrCode\Output\Png as QrPngOutput;
use Mpdf\QrCode\Output\Svg as QrSvgOutput;
use Mpdf\QrCode\QrCode;

defined('ABSPATH') || exit;

if (!function_exists('vy_invoice_setting_value')) {
    function vy_invoice_setting_value($settings, string $key, $default = null)
    {
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        if (is_object($settings) && property_exists($settings, $key)) {
            return $settings->{$key};
        }

        return $default;
    }
}

if (!function_exists('vy_invoice_should_show_tax_breakup')) {
    function vy_invoice_should_show_tax_breakup($settings): bool
    {
        return (int) vy_invoice_setting_value($settings, 'show_tax_breakup', 1) === 1;
    }
}

if (!function_exists('vy_invoice_should_show_qr_code')) {
    function vy_invoice_should_show_qr_code($settings): bool
    {
        return (int) vy_invoice_setting_value($settings, 'show_qr_code', 0) === 1;
    }
}

if (!function_exists('vy_invoice_line_base_amount')) {
    function vy_invoice_line_base_amount($item): float
    {
        $qty = (float) vy_invoice_setting_value($item, 'quantity', 0);
        $unitPrice = (float) vy_invoice_setting_value($item, 'unit_price', 0);
        return round($qty * $unitPrice, 2);
    }
}

if (!function_exists('vy_invoice_line_tax_amount')) {
    function vy_invoice_line_tax_amount($item): float
    {
        $explicit = vy_invoice_setting_value($item, 'tax_amount', null);
        if ($explicit !== null && $explicit !== '') {
            return round((float) $explicit, 2);
        }

        $taxRate = (float) vy_invoice_setting_value($item, 'tax_rate', 0);
        return round(vy_invoice_line_base_amount($item) * $taxRate / 100, 2);
    }
}

if (!function_exists('vy_invoice_line_total_amount')) {
    function vy_invoice_line_total_amount($item): float
    {
        $explicit = vy_invoice_setting_value($item, 'line_total', null);
        if ($explicit !== null && $explicit !== '') {
            return round((float) $explicit, 2);
        }

        return round(vy_invoice_line_base_amount($item) + vy_invoice_line_tax_amount($item), 2);
    }
}

if (!function_exists('vy_invoice_tax_breakdown')) {
    function vy_invoice_tax_breakdown(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $taxAmount = vy_invoice_line_tax_amount($item);
            if ($taxAmount <= 0) {
                continue;
            }

            $taxRate = (float) vy_invoice_setting_value($item, 'tax_rate', 0);
            $taxType = sanitize_text_field((string) vy_invoice_setting_value($item, 'tax_type', 'Tax'));
            if ($taxType === '') {
                $taxType = 'Tax';
            }

            $label = $taxRate > 0
                ? sprintf('%s (%s%%)', $taxType, number_format($taxRate, 2))
                : $taxType;

            if (!isset($groups[$label])) {
                $groups[$label] = [
                    'label'  => $label,
                    'amount' => 0.0,
                ];
            }

            $groups[$label]['amount'] += $taxAmount;
        }

        foreach ($groups as &$group) {
            $group['amount'] = round((float) $group['amount'], 2);
        }
        unset($group);

        return array_values($groups);
    }
}

if (!function_exists('vy_invoice_qr_payload')) {
    function vy_invoice_qr_payload($invoice, $org, $settings): string
    {
        $orgName = trim((string) vy_invoice_setting_value($org, 'org_name', 'Vyavhar Demo Pvt Ltd'));
        $invoiceNumber = trim((string) vy_invoice_setting_value($invoice, 'invoice_number', 'INV-PREVIEW-001'));
        $currency = trim((string) vy_invoice_setting_value($invoice, 'currency', 'INR'));
        $total = number_format((float) vy_invoice_setting_value($invoice, 'total', 0), 2, '.', '');
        $date = trim((string) vy_invoice_setting_value($invoice, 'date', gmdate('Y-m-d')));
        $dueDate = trim((string) vy_invoice_setting_value($invoice, 'due_date', ''));
        $bankDetails = trim((string) vy_invoice_setting_value($settings, 'bank_details', ''));

        $lines = [
            'Vyavhar Invoice Payment Reference',
            'Organization: ' . $orgName,
            'Invoice: ' . $invoiceNumber,
            'Amount: ' . $currency . ' ' . $total,
            'Date: ' . $date,
        ];

        if ($dueDate !== '') {
            $lines[] = 'Due Date: ' . $dueDate;
        }

        if ($bankDetails !== '') {
            $lines[] = 'Bank Details:';
            foreach (preg_split('/\r\n|\r|\n/', $bankDetails) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $orgId = (int) vy_invoice_setting_value($org, 'org_id', 0);
        $invoiceId = (int) vy_invoice_setting_value($invoice, 'id', 0);
        if ($orgId > 0) {
            $targetPath = $invoiceId > 0 ? '/invoices/' . $invoiceId : '/invoices';
            $lines[] = 'App Link: ' . add_query_arg('org_id', $orgId, home_url($targetPath));
        }

        return trim(implode("\n", $lines));
    }
}

if (!function_exists('vy_invoice_qr_data_uri')) {
    function vy_invoice_qr_data_uri($invoice, $org, $settings, int $size = 132): ?string
    {
        if (!vy_invoice_should_show_qr_code($settings) || !class_exists(QrCode::class)) {
            return null;
        }

        $payload = vy_invoice_qr_payload($invoice, $org, $settings);
        if ($payload === '') {
            return null;
        }

        try {
            $qrCode = new QrCode($payload, QrCode::ERROR_CORRECTION_LOW);
            $qrCode->disableBorder();

            if (class_exists(QrPngOutput::class) && function_exists('imagecreatetruecolor')) {
                $png = (new QrPngOutput())->output($qrCode, $size);
                if (is_string($png) && $png !== '') {
                    return 'data:image/png;base64,' . base64_encode($png);
                }
            }

            if (class_exists(QrSvgOutput::class)) {
                $svg = (new QrSvgOutput())->output($qrCode, $size, 'white', 'black');
                if (is_string($svg) && $svg !== '') {
                    return 'data:image/svg+xml;base64,' . base64_encode($svg);
                }
            }

            return null;
        } catch (\Throwable $throwable) {
            SystemLogger::log_event(
                'invoice_qr_preview_failed',
                'Failed to generate invoice QR preview.',
                ['error_message' => $throwable->getMessage()],
                0,
                'backend/Helpers/InvoiceRenderHelper.php'
            );
            return null;
        }
    }
}

if (!function_exists('vy_build_preview_sample_invoice')) {
    function vy_build_preview_sample_invoice($org, $settings, ?string $templateId = null): array
    {
        $issueDate = gmdate('Y-m-d');
        $dueDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+7 days')
            ->format('Y-m-d');
        $currency = trim((string) vy_invoice_setting_value($settings, 'currency', 'INR'));
        if ($currency === '') {
            $currency = 'INR';
        }

        $items = [
            (object) [
                'description' => 'Monthly bookkeeping retainer',
                'quantity'    => 1,
                'unit_price'  => 8500,
                'tax_rate'    => 18,
                'tax_amount'  => 1530,
                'tax_type'    => 'GST',
                'line_total'  => 10030,
            ],
            (object) [
                'description' => 'GST filing support',
                'quantity'    => 2,
                'unit_price'  => 1200,
                'tax_rate'    => 18,
                'tax_amount'  => 432,
                'tax_type'    => 'GST',
                'line_total'  => 2832,
            ],
            (object) [
                'description' => 'Staff travel reimbursement',
                'quantity'    => 1,
                'unit_price'  => 750,
                'tax_rate'    => 0,
                'tax_amount'  => 0,
                'tax_type'    => 'GST',
                'line_total'  => 750,
            ],
        ];

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $total = 0.0;
        foreach ($items as $item) {
            $subtotal += vy_invoice_line_base_amount($item);
            $taxTotal += vy_invoice_line_tax_amount($item);
            $total += vy_invoice_line_total_amount($item);
        }

        $invoice = (object) [
            'id'             => 0,
            'org_id'         => (int) vy_invoice_setting_value($org, 'org_id', 0),
            'contact_id'     => 0,
            'invoice_number' => 'INV-PREVIEW-001',
            'customer_name'  => 'Acme Retail Pvt Ltd',
            'customer_email' => 'accounts@acmeretail.example',
            'customer_phone' => '+91 98765 43210',
            'date'           => $issueDate,
            'due_date'       => $dueDate,
            'currency'       => $currency,
            'subtotal'       => round($subtotal, 2),
            'tax_total'      => round($taxTotal, 2),
            'total'          => round($total, 2),
            'status'         => 'SENT',
            'template_id'    => $templateId ?: (string) vy_invoice_setting_value($settings, 'default_template_id', 'minimal-clean'),
            'pdf_url'        => null,
            'email_sent_at'  => null,
            'email_sent_to'  => null,
            'notes'          => 'Preview sample for invoice branding and document settings.',
        ];

        return [
            'invoice' => $invoice,
            'items'   => $items,
        ];
    }
}
