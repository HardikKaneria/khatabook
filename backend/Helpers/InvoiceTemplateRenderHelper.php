<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_get_invoice_render_variant_styles')) {
    function vy_get_invoice_render_variant_styles(): array
    {
        return [
            'modern-clean-blue' => [
                'primary'       => '#4f46e5',
                'accent'        => '#4f46e5',
                'text'          => '#111827',
                'muted'         => '#6b7280',
                'line'          => '#d7ddff',
                'fallback_font' => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
            ],
            'corporate-orange' => [
                'primary'       => '#f97316',
                'accent'        => '#1e3a8a',
                'text'          => '#1f2937',
                'muted'         => '#6b7280',
                'line'          => '#d6d6d6',
                'fallback_font' => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
            ],
            'minimal-grey-elegant' => [
                'primary'       => '#2b2b2b',
                'accent'        => '#d1d5db',
                'text'          => '#202020',
                'muted'         => '#6b7280',
                'line'          => '#d1d5db',
                'fallback_font' => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
            ],
            'yellow-modern-minimal' => [
                'primary'       => '#facc15',
                'accent'        => '#2f3240',
                'text'          => '#18181b',
                'muted'         => '#71717a',
                'line'          => '#d4d4d8',
                'fallback_font' => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
            ],
        ];
    }
}

if (!function_exists('vy_build_invoice_template_context')) {
    function vy_build_invoice_template_context(string $templateId, $invoice, $items, $org, $settings): array
    {
        $variant = vy_get_invoice_render_variant_styles()[$templateId] ?? vy_get_invoice_render_variant_styles()['modern-clean-blue'];

        return [
            'template'      => vy_get_invoice_template($templateId),
            'template_data' => vy_build_invoice_template_view_model($templateId, $invoice, $items, $org, $settings, $variant),
            'invoice'       => $invoice,
            'items'         => $items,
            'org'           => $org,
            'settings'      => $settings,
        ];
    }
}

if (!function_exists('vy_render_invoice_template_document')) {
    function vy_render_invoice_template_document(string $templateId, $invoice, $items, $org, $settings): ?string
    {
        $templatePath = vy_get_invoice_template_path($templateId);
        if (!file_exists($templatePath)) {
            return null;
        }

        return vy_render_invoice_template_file(
            $templatePath,
            vy_build_invoice_template_context($templateId, $invoice, $items, $org, $settings)
        );
    }
}

if (!function_exists('vy_render_invoice_template_file')) {
    function vy_render_invoice_template_file(string $path, array $context): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        ob_start();
        extract($context, EXTR_SKIP);
        require $path;
        return (string) ob_get_clean();
    }
}

if (!function_exists('vy_build_invoice_template_view_model')) {
    function vy_build_invoice_template_view_model(string $templateId, $invoice, $items, $org, $settings, ?array $variant = null): array
    {
        $variant = $variant ?: (vy_get_invoice_render_variant_styles()[$templateId] ?? vy_get_invoice_render_variant_styles()['modern-clean-blue']);
        $settings = is_array($settings) ? (object) $settings : $settings;
        $invoice = is_array($invoice) ? (object) $invoice : $invoice;
        $org = is_array($org) ? (object) $org : $org;
        $items = is_array($items) ? $items : (array) $items;

        $orgId = (int) vy_invoice_setting_value($org, 'org_id', vy_invoice_setting_value($invoice, 'org_id', 0));
        $companySettings = function_exists('vy_fetch_org_settings_category') && $orgId > 0
            ? vy_fetch_org_settings_category($orgId, 'company')
            : [];
        $contactProfile = vy_invoice_fetch_contact_document_profile($invoice, $orgId);

        $fontFamily = vy_invoice_safe_font_family(
            (string) vy_invoice_setting_value($settings, 'font_family', ''),
            $variant['fallback_font']
        );

        $primary = vy_normalize_invoice_hex_color(
            (string) vy_invoice_setting_value($settings, 'primary_color', ''),
            $variant['primary']
        ) ?: $variant['primary'];

        $accent = vy_normalize_invoice_hex_color(
            (string) vy_invoice_setting_value($settings, 'accent_color', ''),
            $variant['accent']
        ) ?: $variant['accent'];

        $companyName = trim((string) ($companySettings['display_name'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($companySettings['business_name'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = trim((string) ($org->org_name ?? 'Vyavhar'));
        }

        $companyTagline = trim((string) ($companySettings['tagline'] ?? $org->industry ?? ''));
        $companyLines = vy_invoice_collect_company_lines($companySettings);
        if (!$companyLines && $companyTagline !== '') {
            $companyLines[] = $companyTagline;
        }

        $customerName = trim((string) ($contactProfile['name'] ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($invoice->customer_name ?? 'Customer'));
        }

        $customerAddressText = trim((string) (
            vy_invoice_setting_value($invoice, 'billing_address', '')
            ?: vy_invoice_setting_value($invoice, 'customer_billing_address', '')
            ?: ($contactProfile['billing_address'] ?? '')
            ?: ($contactProfile['shipping_address'] ?? '')
        ));
        $customerAddressLines = vy_invoice_split_text_lines($customerAddressText);
        $customerEmail = trim((string) (vy_invoice_setting_value($invoice, 'customer_email', '') ?: ($contactProfile['email'] ?? '')));
        $customerPhone = trim((string) (vy_invoice_setting_value($invoice, 'customer_phone', '') ?: ($contactProfile['phone'] ?? '')));

        $customerLines = $customerAddressLines;
        if ($customerEmail !== '') {
            $customerLines[] = $customerEmail;
        }
        if ($customerPhone !== '') {
            $customerLines[] = $customerPhone;
        }

        $invoiceDate = trim((string) ($invoice->date ?? ''));
        $dueDate = trim((string) ($invoice->due_date ?? ''));
        $subtotal = round((float) ($invoice->subtotal ?? 0), 2);
        $taxTotal = round((float) ($invoice->tax_total ?? 0), 2);
        $total = round((float) ($invoice->total ?? 0), 2);
        $amountDue = round((float) vy_invoice_setting_value($invoice, 'balance_due', $total), 2);
        $isPreviewSample = (int) ($invoice->id ?? 0) === 0;

        $discountAmount = (float) vy_invoice_setting_value($invoice, 'discount_amount', 0);
        if ($discountAmount <= 0 && $isPreviewSample) {
            $discountAmount = round(max(0, ($subtotal + $taxTotal) - $total), 2);
        }

        $depositRequested = vy_invoice_setting_value($invoice, 'deposit_requested', null);
        if (($depositRequested === null || $depositRequested === '') && $isPreviewSample) {
            $depositRequested = round($amountDue * 0.1, 2);
        }
        $depositRequested = ($depositRequested === null || $depositRequested === '') ? null : round((float) $depositRequested, 2);

        $depositDue = vy_invoice_setting_value($invoice, 'deposit_due', $depositRequested);
        $depositDue = ($depositDue === null || $depositDue === '') ? null : round((float) $depositDue, 2);

        $currency = trim((string) ($invoice->currency ?? 'INR'));
        if ($currency === '') {
            $currency = 'INR';
        }

        $normalizedItems = [];
        foreach (array_values($items) as $index => $item) {
            $description = trim((string) vy_invoice_setting_value($item, 'description', 'Item'));
            $detail = trim((string) vy_invoice_setting_value($item, 'detail', ''));
            $quantity = (float) vy_invoice_setting_value($item, 'quantity', 0);
            $unitPrice = (float) vy_invoice_setting_value($item, 'unit_price', 0);
            $taxRate = (float) vy_invoice_setting_value($item, 'tax_rate', 0);
            $taxAmount = vy_invoice_line_tax_amount($item);
            $lineTotal = vy_invoice_line_total_amount($item);

            $normalizedItems[] = [
                'index'          => $index + 1,
                'description'    => $description !== '' ? $description : 'Item',
                'detail'         => $detail,
                'quantity'       => $quantity,
                'unit_price'     => $unitPrice,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'line_total'     => $lineTotal,
                'base_amount'    => vy_invoice_line_base_amount($item),
                'tax_rate_label' => $taxRate > 0 ? ('+' . rtrim(rtrim(number_format($taxRate, 2), '0'), '.') . '% Tax') : '',
            ];
        }

        $detailRows = [
            ['label' => 'Date Issued', 'value' => vy_invoice_format_display_date($invoiceDate, 'd/m/Y')],
            ['label' => 'Invoice Number', 'value' => trim((string) ($invoice->invoice_number ?? '—'))],
            ['label' => 'Due Date', 'value' => $dueDate !== '' ? vy_invoice_format_display_date($dueDate, 'd/m/Y') : '—'],
            ['label' => 'Amount Due', 'value' => vy_invoice_money($currency, $amountDue)],
        ];

        $summaryRows = [
            [
                'label' => 'Subtotal',
                'amount' => $subtotal,
                'kind' => 'normal',
            ],
            [
                'label' => 'Discount',
                'amount' => $discountAmount,
                'kind' => 'discount',
            ],
            [
                'label' => 'Tax',
                'amount' => $taxTotal,
                'kind' => 'normal',
            ],
            [
                'label' => 'Total',
                'amount' => $total,
                'kind' => 'grand',
            ],
        ];

        if ($depositRequested !== null) {
            $summaryRows[] = [
                'label' => 'Deposit Requested',
                'amount' => $depositRequested,
                'kind' => 'normal',
            ];
        }

        if ($depositDue !== null) {
            $summaryRows[] = [
                'label' => 'Deposit Due',
                'amount' => $depositDue,
                'kind' => 'deposit',
            ];
        }

        $notes = trim((string) ($invoice->notes ?? ''));
        $footerText = trim((string) vy_invoice_setting_value($settings, 'footer_text', ''));
        $terms = trim((string) vy_invoice_setting_value($settings, 'terms_and_conditions', ''));
        $bankDetails = trim((string) vy_invoice_setting_value($settings, 'bank_details', ''));
        $logoUrl = vy_invoice_effective_logo_url($settings, $org);
        $taxBreakdown = vy_invoice_should_show_tax_breakup($settings) ? vy_invoice_tax_breakdown($items) : [];
        $qrCode = vy_invoice_qr_data_uri($invoice, $org, $settings);
        $bankDetailPairs = vy_invoice_parse_bank_detail_pairs($bankDetails);
        $bankDetailLines = vy_invoice_split_text_lines($bankDetails);

        $bottomSections = [];
        if ($bankDetails !== '') {
            $bottomSections[] = [
                'key' => 'payment-details',
                'title' => 'Payment Details',
                'type' => 'text',
                'content' => $bankDetails,
            ];
        }
        if ($terms !== '') {
            $bottomSections[] = [
                'key' => 'terms',
                'title' => 'Terms & Conditions',
                'type' => 'text',
                'content' => $terms,
            ];
        }
        if ($taxBreakdown) {
            $bottomSections[] = [
                'key' => 'tax-breakup',
                'title' => 'Tax Breakup',
                'type' => 'tax_breakdown',
                'lines' => $taxBreakdown,
            ];
        }
        if ($qrCode) {
            $bottomSections[] = [
                'key' => 'payment-qr',
                'title' => 'Payment QR',
                'type' => 'qr',
                'image' => $qrCode,
                'caption' => 'Scan to view payment reference details.',
            ];
        }

        return [
            'template_id'                 => $templateId,
            'primary'                     => $primary,
            'accent'                      => $accent,
            'text'                        => $variant['text'],
            'muted'                       => $variant['muted'],
            'line'                        => $variant['line'],
            'font_family'                 => $fontFamily,
            'company_name'                => $companyName,
            'company_tagline'             => $companyTagline,
            'company_lines'               => $companyLines,
            'customer_name'               => $customerName,
            'customer_lines'              => $customerLines,
            'customer_email'              => $customerEmail,
            'customer_phone'              => $customerPhone,
            'invoice_label'               => 'Invoice',
            'invoice_number'              => trim((string) ($invoice->invoice_number ?? '—')),
            'invoice_date'                => $invoiceDate,
            'invoice_date_display_short'  => vy_invoice_format_display_date($invoiceDate, 'd/m/Y'),
            'invoice_date_display_long'   => vy_invoice_format_display_date($invoiceDate, 'j F, Y'),
            'invoice_due'                 => $dueDate,
            'invoice_due_display_short'   => $dueDate !== '' ? vy_invoice_format_display_date($dueDate, 'd/m/Y') : '—',
            'invoice_due_display_long'    => $dueDate !== '' ? vy_invoice_format_display_date($dueDate, 'j F, Y') : '—',
            'invoice_status'              => strtoupper(trim((string) ($invoice->status ?? 'SENT'))),
            'currency'                    => $currency,
            'subtotal'                    => $subtotal,
            'tax_total'                   => $taxTotal,
            'total'                       => $total,
            'amount_due'                  => $amountDue,
            'discount_amount'             => $discountAmount,
            'deposit_requested'           => $depositRequested,
            'deposit_due'                 => $depositDue,
            'items'                       => $normalizedItems,
            'detail_rows'                 => $detailRows,
            'summary_rows'                => $summaryRows,
            'notes'                       => $notes,
            'notes_lines'                 => vy_invoice_split_text_lines($notes),
            'footer_text'                 => $footerText,
            'footer_lines'                => vy_invoice_split_text_lines($footerText),
            'terms'                       => $terms,
            'terms_lines'                 => vy_invoice_split_text_lines($terms),
            'bank_details'                => $bankDetails,
            'bank_detail_lines'           => $bankDetailLines,
            'bank_detail_pairs'           => $bankDetailPairs,
            'tax_breakdown'               => $taxBreakdown,
            'qr_code'                     => $qrCode,
            'bottom_sections'             => $bottomSections,
            'logo_url'                    => $logoUrl,
            'is_preview_sample'           => $isPreviewSample,
            'contact_line'                => $companyTagline !== '' ? $companyTagline : 'Thank you for your business.',
        ];
    }
}

if (!function_exists('vy_invoice_collect_company_lines')) {
    function vy_invoice_collect_company_lines(array $companySettings): array
    {
        $addressLines = [];

        foreach (['address', 'address_line_1', 'address_line_2'] as $key) {
            foreach (vy_invoice_split_text_lines((string) ($companySettings[$key] ?? '')) as $line) {
                $addressLines[] = $line;
            }
        }

        $cityBits = [];
        foreach (['city', 'district'] as $key) {
            $value = trim((string) ($companySettings[$key] ?? ''));
            if ($value !== '') {
                $cityBits[] = $value;
            }
        }

        $state = trim((string) ($companySettings['state'] ?? ''));
        if ($state !== '') {
            $cityBits[] = $state;
        }

        $postal = trim((string) ($companySettings['postal_code'] ?? $companySettings['pin_code'] ?? $companySettings['pincode'] ?? $companySettings['zip'] ?? ''));
        if ($postal !== '') {
            $cityBits[] = $postal;
        }

        if ($cityBits) {
            $addressLines[] = implode(', ', $cityBits);
        }

        $country = trim((string) ($companySettings['country'] ?? ''));
        if ($country !== '') {
            $addressLines[] = $country;
        }

        $phone = trim((string) ($companySettings['phone'] ?? ''));
        if ($phone !== '') {
            $addressLines[] = $phone;
        }

        if (!empty($companySettings['gst_registered']) && !empty($companySettings['gstin'])) {
            $addressLines[] = 'GSTIN: ' . strtoupper((string) $companySettings['gstin']);
        }

        return array_values(array_filter(array_unique(array_map('trim', $addressLines))));
    }
}

if (!function_exists('vy_invoice_fetch_contact_document_profile')) {
    function vy_invoice_fetch_contact_document_profile($invoice, int $orgId): array
    {
        $contactId = (int) vy_invoice_setting_value($invoice, 'contact_id', 0);
        if ($orgId <= 0 || $contactId <= 0) {
            return [];
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, email, phone, billing_address, shipping_address, gstin
             FROM {$wpdb->prefix}vy_contacts
             WHERE org_id = %d AND id = %d
             LIMIT 1",
            $orgId,
            $contactId
        ), ARRAY_A);

        return is_array($row) ? $row : [];
    }
}

if (!function_exists('vy_invoice_parse_bank_detail_pairs')) {
    function vy_invoice_parse_bank_detail_pairs(string $value): array
    {
        $lines = vy_invoice_split_text_lines($value);
        $pairs = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$label, $content] = array_pad(explode(':', $line, 2), 2, '');
                $label = trim($label);
                $content = trim($content);
                if ($label !== '' && $content !== '') {
                    $pairs[] = [
                        'label' => $label,
                        'value' => $content,
                    ];
                }
            }
        }

        if ($pairs) {
            return array_slice($pairs, 0, 4);
        }

        $fallbackLabels = ['Account No', 'Bank', 'A/C Name', 'Reference'];
        foreach (array_slice($lines, 0, 4) as $index => $line) {
            $pairs[] = [
                'label' => $fallbackLabels[$index] ?? ('Detail ' . ($index + 1)),
                'value' => $line,
            ];
        }

        return $pairs;
    }
}

if (!function_exists('vy_invoice_split_text_lines')) {
    function vy_invoice_split_text_lines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value));
        if (!is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($line): string {
            return trim((string) $line);
        }, $lines)));
    }
}

if (!function_exists('vy_invoice_format_display_date')) {
    function vy_invoice_format_display_date(string $value, string $format = 'd/m/Y'): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format($format);
        } catch (Throwable $throwable) {
            return $value;
        }
    }
}

if (!function_exists('vy_invoice_safe_font_family')) {
    function vy_invoice_safe_font_family(string $requested, string $fallback): string
    {
        return vy_normalize_invoice_font_family($requested, $fallback);
    }
}

if (!function_exists('vy_invoice_money')) {
    function vy_invoice_money(string $currency, float $amount): string
    {
        return sprintf('%s %s', $currency, number_format($amount, 2));
    }
}

if (!function_exists('vy_invoice_effective_logo_url')) {
    function vy_invoice_effective_logo_url($settings, $org): string
    {
        $logoUrl = trim((string) vy_invoice_setting_value($settings, 'logo_url', ''));
        if ($logoUrl !== '') {
            return esc_url_raw($logoUrl);
        }

        $legacyOrgLogo = trim((string) vy_invoice_setting_value($org, 'logo_url', ''));
        return $legacyOrgLogo !== '' ? esc_url_raw($legacyOrgLogo) : '';
    }
}
