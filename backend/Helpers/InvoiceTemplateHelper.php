<?php

if (!function_exists('vy_get_invoice_templates_registry')) {
    function vy_get_invoice_templates_registry(): array
    {
        return [
            'minimal-clean' => [
                'id'          => 'minimal-clean',
                'name'        => 'Minimal Clean',
                'description' => 'Clean split layout with balanced whitespace and a modern B2B document hierarchy.',
            ],
            'bordered-classic' => [
                'id'          => 'bordered-classic',
                'name'        => 'Classic Corporate',
                'description' => 'Traditional bordered invoice with formal spacing for conservative businesses.',
            ],
            'bold-header' => [
                'id'          => 'bold-header',
                'name'        => 'Bold Header',
                'description' => 'Strong branded header band with confident hierarchy and print-safe contrast.',
            ],
            'accent-panel' => [
                'id'          => 'accent-panel',
                'name'        => 'Modern Accent',
                'description' => 'Sidebar-led composition that gives brand details a strong visual anchor.',
            ],
            'compact-grid' => [
                'id'          => 'compact-grid',
                'name'        => 'Compact Grid',
                'description' => 'Dense but readable format for invoice-heavy teams that print often.',
            ],
            'elegant-professional' => [
                'id'          => 'elegant-professional',
                'name'        => 'Elegant Professional',
                'description' => 'Soft premium styling with restrained serif typography for service businesses.',
            ],
            'executive-blue' => [
                'id'          => 'executive-blue',
                'name'        => 'Executive Blue',
                'description' => 'Statement-style blue layout for finance, consulting, and corporate billing.',
            ],
            'soft-premium' => [
                'id'          => 'soft-premium',
                'name'        => 'Soft Premium',
                'description' => 'Warm premium invoice style with gentle accents and polished spacing.',
            ],
            'formal-ledger' => [
                'id'          => 'formal-ledger',
                'name'        => 'Formal Ledger',
                'description' => 'Ledger-inspired structured layout with compact financial presentation.',
            ],
            'contemporary-statement' => [
                'id'          => 'contemporary-statement',
                'name'        => 'Contemporary Statement',
                'description' => 'Contemporary statement layout with crisp totals and executive readability.',
            ],
        ];
    }
}

if (!function_exists('vy_get_invoice_template')) {
    function vy_get_invoice_template(string $template_id): array
    {
        $registry = vy_get_invoice_templates_registry();
        return $registry[$template_id] ?? $registry['minimal-clean'];
    }
}

if (!function_exists('vy_get_invoice_template_default_settings')) {
    function vy_get_invoice_template_default_settings(): array
    {
        return [
            'default_template_id'   => 'minimal-clean',
            'logo_url'              => null,
            'primary_color'         => null,
            'accent_color'          => null,
            'font_family'           => null,
            'footer_text'           => null,
            'terms_and_conditions'  => null,
            'bank_details'          => null,
            'show_tax_breakup'      => 1,
            'show_qr_code'          => 0,
            'auto_email_on_create'  => 0,
            'email_subject_template'=> null,
            'email_body_template'   => null,
        ];
    }
}

if (!function_exists('vy_normalize_invoice_template_settings')) {
    function vy_normalize_invoice_template_settings(array $settings): array
    {
        $defaults = vy_get_invoice_template_default_settings();
        $normalized = $defaults;
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $normalized[$key] = $value;
            }
        }
        $normalized['show_tax_breakup'] = isset($normalized['show_tax_breakup']) ? (int) $normalized['show_tax_breakup'] : 1;
        $normalized['show_qr_code'] = isset($normalized['show_qr_code']) ? (int) $normalized['show_qr_code'] : 0;
        $normalized['auto_email_on_create'] = isset($normalized['auto_email_on_create']) ? (int) $normalized['auto_email_on_create'] : 0;
        return $normalized;
    }
}

if (!function_exists('vy_fetch_invoice_template_settings')) {
    function vy_fetch_invoice_template_settings(int $org_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vy_invoice_template_settings';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d LIMIT 1",
            $org_id
        ), ARRAY_A);
        return vy_normalize_invoice_template_settings($row ?: []);
    }
}

if (!function_exists('vy_get_invoice_template_path')) {
    function vy_get_invoice_template_path(string $template_id): string
    {
        return trailingslashit(plugin_dir_path(KHATABOOK_PLUGIN_FILE) . 'backend/templates/invoices') . $template_id . '.php';
    }
}

if (!function_exists('vy_resolve_invoice_template_id')) {
    function vy_resolve_invoice_template_id($settings, $invoice = null, ?string $override = null): string
    {
        $registry = vy_get_invoice_templates_registry();

        $overrideId = sanitize_key((string) $override);
        if ($overrideId !== '' && isset($registry[$overrideId])) {
            return $overrideId;
        }

        $settingsId = sanitize_key((string) vy_invoice_setting_value($settings, 'default_template_id', ''));
        if ($settingsId !== '' && isset($registry[$settingsId])) {
            return $settingsId;
        }

        $invoiceId = sanitize_key((string) vy_invoice_setting_value($invoice, 'template_id', ''));
        if ($invoiceId !== '' && isset($registry[$invoiceId])) {
            return $invoiceId;
        }

        return 'minimal-clean';
    }
}
