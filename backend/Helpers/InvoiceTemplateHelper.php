<?php

if (!function_exists('vy_get_invoice_templates_registry')) {
    function vy_get_invoice_templates_registry(): array
    {
        return [
            'modern-clean-blue' => [
                'id'          => 'modern-clean-blue',
                'name'        => 'Modern Clean Blue',
                'description' => 'Minimal blue invoice based on the approved click-to-edit reference layout.',
            ],
            'corporate-orange' => [
                'id'          => 'corporate-orange',
                'name'        => 'Corporate Orange',
                'description' => 'Orange and navy corporate invoice with the approved diagonal header structure.',
            ],
            'minimal-grey-elegant' => [
                'id'          => 'minimal-grey-elegant',
                'name'        => 'Minimal Grey Elegant',
                'description' => 'Grey editorial invoice with dotted separators and the approved time-based layout.',
            ],
            'yellow-modern-minimal' => [
                'id'          => 'yellow-modern-minimal',
                'name'        => 'Yellow Modern Minimal',
                'description' => 'Yellow-accent invoice matching the approved minimal vector reference.',
            ],
        ];
    }
}

if (!function_exists('vy_get_invoice_font_family_options')) {
    function vy_get_invoice_font_family_options(): array
    {
        return [
            [
                'value'       => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
                'label'       => 'Professional Sans',
                'description' => 'Clean default for most invoices. Best balance for browser preview and PDF output.',
            ],
            [
                'value'       => '"Segoe UI", Arial, Helvetica, "DejaVu Sans", sans-serif',
                'label'       => 'Office Sans',
                'description' => 'Softer office-style look with stable fallback to Arial and DejaVu Sans.',
            ],
            [
                'value'       => '"Trebuchet MS", Arial, Helvetica, "DejaVu Sans", sans-serif',
                'label'       => 'Humanist Sans',
                'description' => 'Slightly more modern and open without becoming decorative.',
            ],
            [
                'value'       => 'Tahoma, Arial, Helvetica, "DejaVu Sans", sans-serif',
                'label'       => 'Compact Sans',
                'description' => 'Tighter spacing for dense invoices and compact layouts.',
            ],
            [
                'value'       => 'Verdana, Arial, Helvetica, "DejaVu Sans", sans-serif',
                'label'       => 'Wide Sans',
                'description' => 'More generous character width for readability-heavy documents.',
            ],
            [
                'value'       => '"DejaVu Sans", Arial, Helvetica, "Segoe UI", sans-serif',
                'label'       => 'PDF First Sans',
                'description' => 'Biases toward DejaVu Sans for more deterministic PDF rendering.',
            ],
        ];
    }
}

if (!function_exists('vy_get_default_invoice_font_family')) {
    function vy_get_default_invoice_font_family(): string
    {
        $options = vy_get_invoice_font_family_options();
        return (string) ($options[0]['value'] ?? 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif');
    }
}

if (!function_exists('vy_normalize_invoice_hex_color')) {
    function vy_normalize_invoice_hex_color(?string $requested, ?string $fallback = null): ?string
    {
        $requested = trim((string) $requested);
        $fallback = trim((string) $fallback);

        $normalize = static function (string $value): ?string {
            if ($value === '') {
                return null;
            }

            if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                return null;
            }

            $hex = strtolower(substr($value, 1));
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            return '#' . $hex;
        };

        $normalized = $normalize($requested);
        if ($normalized !== null) {
            return $normalized;
        }

        return $normalize($fallback);
    }
}

if (!function_exists('vy_normalize_invoice_font_family')) {
    function vy_normalize_invoice_font_family(?string $requested, ?string $fallback = null): string
    {
        $requested = trim((string) $requested);
        $fallback = trim((string) $fallback);
        $options = vy_get_invoice_font_family_options();
        $allowed = [];

        foreach ($options as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $allowed[$value] = $value;
        }

        if ($requested !== '' && isset($allowed[$requested])) {
            return $allowed[$requested];
        }

        $collapsedRequested = strtolower(preg_replace('/\s+/', '', $requested));
        if ($collapsedRequested !== '') {
            foreach ($allowed as $value) {
                if ($collapsedRequested === strtolower(preg_replace('/\s+/', '', $value))) {
                    return $value;
                }
            }

            $legacyMap = [
                'segoeui'    => '"Segoe UI", Arial, Helvetica, "DejaVu Sans", sans-serif',
                'trebuchet'  => '"Trebuchet MS", Arial, Helvetica, "DejaVu Sans", sans-serif',
                'tahoma'     => 'Tahoma, Arial, Helvetica, "DejaVu Sans", sans-serif',
                'verdana'    => 'Verdana, Arial, Helvetica, "DejaVu Sans", sans-serif',
                'dejavusans' => '"DejaVu Sans", Arial, Helvetica, "Segoe UI", sans-serif',
                'arial'      => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
                'helvetica'  => 'Arial, Helvetica, "DejaVu Sans", "Segoe UI", sans-serif',
            ];

            foreach ($legacyMap as $needle => $mapped) {
                if (strpos($collapsedRequested, $needle) !== false) {
                    return $mapped;
                }
            }
        }

        if ($fallback !== '' && isset($allowed[$fallback])) {
            return $fallback;
        }

        return vy_get_default_invoice_font_family();
    }
}

if (!function_exists('vy_get_invoice_template')) {
    function vy_get_invoice_template(string $template_id): array
    {
        $registry = vy_get_invoice_templates_registry();
        return $registry[$template_id] ?? $registry['modern-clean-blue'];
    }
}

if (!function_exists('vy_get_invoice_template_default_settings')) {
    function vy_get_invoice_template_default_settings(): array
    {
        return [
            'default_template_id'   => 'modern-clean-blue',
            'logo_url'              => null,
            'primary_color'         => null,
            'accent_color'          => null,
            'font_family'           => vy_get_default_invoice_font_family(),
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
        $normalized['default_template_id'] = vy_resolve_invoice_template_id($normalized);
        $normalized['font_family'] = vy_normalize_invoice_font_family(
            isset($normalized['font_family']) ? (string) $normalized['font_family'] : '',
            $defaults['font_family']
        );
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
        $mappedOverride = vy_map_legacy_invoice_template_id($overrideId);
        if ($mappedOverride !== '' && isset($registry[$mappedOverride])) {
            return $mappedOverride;
        }

        $settingsId = sanitize_key((string) vy_invoice_setting_value($settings, 'default_template_id', ''));
        $mappedSettings = vy_map_legacy_invoice_template_id($settingsId);
        if ($mappedSettings !== '' && isset($registry[$mappedSettings])) {
            return $mappedSettings;
        }

        $invoiceId = sanitize_key((string) vy_invoice_setting_value($invoice, 'template_id', ''));
        $mappedInvoice = vy_map_legacy_invoice_template_id($invoiceId);
        if ($mappedInvoice !== '' && isset($registry[$mappedInvoice])) {
            return $mappedInvoice;
        }

        return 'modern-clean-blue';
    }
}

if (!function_exists('vy_map_legacy_invoice_template_id')) {
    function vy_map_legacy_invoice_template_id(string $template_id): string
    {
        $template_id = sanitize_key($template_id);
        if ($template_id === '') {
            return '';
        }

        $registry = vy_get_invoice_templates_registry();
        if (isset($registry[$template_id])) {
            return $template_id;
        }

        return [
            'minimal-clean'          => 'modern-clean-blue',
            'accent-panel'           => 'modern-clean-blue',
            'executive-blue'         => 'modern-clean-blue',
            'contemporary-statement' => 'modern-clean-blue',
            'bordered-classic'       => 'corporate-orange',
            'bold-header'            => 'corporate-orange',
            'formal-ledger'          => 'minimal-grey-elegant',
            'elegant-professional'   => 'minimal-grey-elegant',
            'compact-grid'           => 'yellow-modern-minimal',
            'soft-premium'           => 'yellow-modern-minimal',
        ][$template_id] ?? '';
    }
}
