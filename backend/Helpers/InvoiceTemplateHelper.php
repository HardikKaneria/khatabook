<?php

if (!function_exists('vy_get_invoice_templates_registry')) {
    function vy_get_invoice_templates_registry(): array
    {
        return [
            'minimal-clean' => [
                'id'          => 'minimal-clean',
                'name'        => 'Minimal Clean',
                'description' => 'Light, whitespace-heavy layout with logo on left and invoice title on right.',
            ],
            'accent-panel' => [
                'id'          => 'accent-panel',
                'name'        => 'Accent Panel',
                'description' => 'Left colored sidebar with company details and right content area.',
            ],
            'compact-grid' => [
                'id'          => 'compact-grid',
                'name'        => 'Compact Grid',
                'description' => 'Dense, grid-based layout for print-heavy users.',
            ],
            'bold-header' => [
                'id'          => 'bold-header',
                'name'        => 'Bold Header',
                'description' => 'Full-width colored header band with large invoice title.',
            ],
            'bordered-classic' => [
                'id'          => 'bordered-classic',
                'name'        => 'Bordered Classic',
                'description' => 'Thin bordered classic layout with modern typography.',
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
