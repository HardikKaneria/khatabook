<?php

if (!function_exists('vy_send_invoice_email')) {
    function vy_send_invoice_email(int $invoice_id, array $override_emails = [])
    {
        global $wpdb;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE id = %d LIMIT 1",
            $invoice_id
        ));
        if (!$invoice) {
            return new WP_Error('vy_invoice_missing', 'Invoice not found.', ['status' => 404]);
        }

        $orgId = (int) $invoice->org_id;
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d LIMIT 1",
            $orgId
        ));
        if (!$org) {
            return new WP_Error('vy_org_missing', 'Organization not found.', ['status' => 404]);
        }

        $settings = vy_fetch_invoice_template_settings($orgId);

        $pdfData = vy_ensure_invoice_pdf($orgId, $invoice_id, $invoice->pdf_url);
        if (is_wp_error($pdfData)) {
            return $pdfData;
        }

        $recipients = vy_prepare_invoice_recipients($invoice, $override_emails);
        if (is_wp_error($recipients)) {
            return $recipients;
        }

        $placeholders = [
            '{{org_name}}'       => $org->org_name ?? '',
            '{{invoice_number}}' => $invoice->invoice_number,
            '{{invoice_total}}'  => trim(($invoice->currency ?? 'INR') . ' ' . number_format((float) $invoice->total, 2)),
            '{{invoice_date}}'   => $invoice->date ?? '',
            '{{customer_name}}'  => $invoice->customer_name ?? '',
        ];

        $subjectTemplate = !empty($settings['email_subject_template'])
            ? $settings['email_subject_template']
            : 'Invoice {{invoice_number}} from {{org_name}}';
        $bodyTemplate = !empty($settings['email_body_template'])
            ? $settings['email_body_template']
            : "Dear {{customer_name}},\n\nPlease find attached invoice {{invoice_number}} for {{invoice_total}}.\n\nThanks,\n{{org_name}}";

        $subject = strtr($subjectTemplate, $placeholders);
        $bodyRaw = strtr($bodyTemplate, $placeholders);
        $emailBranding = vy_resolve_invoice_email_branding($orgId, $settings, $org);
        $emailArgs = [
            'eyebrow' => !empty($org->org_name) ? 'Invoice from ' . $org->org_name : 'Invoice',
            'greeting' => !empty($invoice->customer_name) ? 'Hi ' . $invoice->customer_name . ',' : 'Hello,',
            'cta_label' => !empty($pdfData['url']) ? 'Open PDF invoice' : '',
            'cta_url' => !empty($pdfData['url']) ? $pdfData['url'] : '',
            'logo_url' => $emailBranding['logo_url'],
            'brand' => $emailBranding['brand'],
            'attachments' => [$pdfData['path']],
            'summary_rows' => [
                ['label' => 'Invoice', 'value' => (string) $invoice->invoice_number],
                ['label' => 'Customer', 'value' => (string) ($invoice->customer_name ?? 'Customer')],
                ['label' => 'Amount', 'value' => trim(($invoice->currency ?? 'INR') . ' ' . number_format((float) $invoice->total, 2))],
                ['label' => 'Invoice date', 'value' => (string) ($invoice->date ?? '')],
                ['label' => 'Due date', 'value' => (string) ($invoice->due_date ?? 'On receipt')],
            ],
            'footer' => !empty($org->org_name)
                ? sprintf('Issued by %s via Vyavhar.<br>Reply to this email if you need help with the attached invoice.', esc_html($org->org_name))
                : 'Issued via Vyavhar.<br>Reply to this email if you need help with the attached invoice.',
        ];

        $sent = function_exists('kbs_send_email')
            ? kbs_send_email($recipients, $subject, $bodyRaw, $emailArgs)
            : wp_mail($recipients, $subject, kbs_render_email_body($bodyRaw, $emailArgs), ['Content-Type: text/html; charset=UTF-8'], [$pdfData['path']]);
        if (!$sent) {
            return new WP_Error('vy_email_failed', 'Failed to send invoice email.', ['status' => 500]);
        }

        $sentAt = current_time('mysql', true);
        $wpdb->update(
            $wpdb->prefix . 'vy_invoices',
            [
                'email_sent_at' => $sentAt,
                'email_sent_to' => implode(',', $recipients),
                'pdf_url'       => $pdfData['url'],
                'updated_at'    => $sentAt,
            ],
            [
                'id'     => $invoice_id,
                'org_id' => $orgId,
            ],
            ['%s','%s','%s','%s'],
            ['%d','%d']
        );

        return [
            'recipients' => $recipients,
            'sent_at'    => $sentAt,
            'pdf_url'    => $pdfData['url'],
        ];
    }
}

if (!function_exists('vy_prepare_invoice_recipients')) {
    function vy_prepare_invoice_recipients(object $invoice, array $override): array|WP_Error
    {
        $emails = [];
        foreach ($override as $email) {
            $sanitized = sanitize_email($email);
            if ($sanitized) {
                $emails[] = $sanitized;
            }
        }

        if (!$emails && !empty($invoice->customer_email)) {
            $sanitized = sanitize_email($invoice->customer_email);
            if ($sanitized) {
                $emails[] = $sanitized;
            }
        }

        $emails = array_values(array_unique(array_filter($emails)));
        if (!$emails) {
            return new WP_Error('vy_no_recipient', 'No email recipients were provided.', ['status' => 400]);
        }

        return $emails;
    }
}

if (!function_exists('vy_ensure_invoice_pdf')) {
    function vy_ensure_invoice_pdf(int $org_id, int $invoice_id, ?string $existing_url): array|WP_Error
    {
        $path = vy_invoice_pdf_path_from_url($existing_url);
        if (!$path || !file_exists($path)) {
            $result = Vy_Invoice_Pdf::generate($org_id, $invoice_id);
            if (is_wp_error($result)) {
                return $result;
            }
            $path = vy_invoice_pdf_path_from_url($result);
            if (!$path || !file_exists($path)) {
                return new WP_Error('vy_pdf_missing', 'Invoice PDF could not be located.', ['status' => 500]);
            }
            $existing_url = $result;
        }

        return [
            'url'  => $existing_url,
            'path' => $path,
        ];
    }
}

if (!function_exists('vy_invoice_pdf_path_from_url')) {
    function vy_invoice_pdf_path_from_url(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['baseurl'])) {
            $baseUrl = trailingslashit($uploads['baseurl']);
            if (strpos($url, $baseUrl) === 0) {
                $relative = substr($url, strlen($baseUrl));
                return wp_normalize_path(trailingslashit($uploads['basedir']) . $relative);
            }
        }
        $parsed = wp_parse_url($url, PHP_URL_PATH);
        if (!$parsed) {
            return null;
        }
        $path = realpath(ABSPATH . ltrim($parsed, '/'));
        return $path ? wp_normalize_path($path) : null;
    }
}

if (!function_exists('vy_resolve_invoice_email_branding')) {
    function vy_resolve_invoice_email_branding(int $org_id, array $settings, object $org): array
    {
        $companySettings = function_exists('vy_fetch_org_settings_category')
            ? vy_fetch_org_settings_category($org_id, 'company')
            : [];

        $logoUrl = '';
        if (!empty($settings['logo_url'])) {
            $logoUrl = esc_url_raw((string) $settings['logo_url']);
        } elseif (!empty($companySettings['logo_url'])) {
            $logoUrl = esc_url_raw((string) $companySettings['logo_url']);
        }

        return [
            'brand' => 'Vyavhar',
            'logo_url' => $logoUrl,
            'org_name' => sanitize_text_field((string) ($org->org_name ?? '')),
        ];
    }
}
