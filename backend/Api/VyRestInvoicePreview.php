<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class VyRestInvoicePreview
{
    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/invoices/preview', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'render_preview'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function render_preview(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $invoiceId = absint($request->get_param('invoice_id'));
        if ($invoiceId > 0) {
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE org_id = %d AND id = %d LIMIT 1",
                $org,
                $invoiceId
            ));
        } else {
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE org_id = %d ORDER BY date DESC, id DESC LIMIT 1",
                $org
            ));
        }

        $orgRow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d LIMIT 1",
            $org
        ));
        if (!$orgRow) {
            return new WP_Error('vy_org_missing', 'Organization not found.', ['status' => 404]);
        }

        $settingsData = vy_fetch_invoice_template_settings($org);
        $settings = (object) $settingsData;

        $textOverrides = [
            'primary_color',
            'accent_color',
            'font_family',
        ];
        foreach ($textOverrides as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $settings->$key = sanitize_text_field((string) $value);
            }
        }

        $blockOverrides = [
            'footer_text',
            'terms_and_conditions',
            'bank_details',
        ];
        foreach ($blockOverrides as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $settings->$key = wp_kses_post((string) $value);
            }
        }

        foreach (['show_tax_breakup', 'show_qr_code'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $settings->{$key} = (int) ((!empty($value) && $value !== '0'));
            }
        }

        $logoUrl = $request->get_param('logo_url');
        if ($logoUrl !== null && $logoUrl !== '') {
            $settings->logo_url = esc_url_raw((string) $logoUrl);
        }

        $templateOverride = sanitize_key((string) $request->get_param('template_id'));
        $templateId = \vy_resolve_invoice_template_id($settings, $invoice, $templateOverride ?: null);
        $templatePath = \vy_get_invoice_template_path($templateId);
        if (!file_exists($templatePath)) {
            $templateId = 'minimal-clean';
            $templatePath = \vy_get_invoice_template_path($templateId);
        }
        $template = vy_get_invoice_template($templateId);

        if (!$invoice) {
            $sample = vy_build_preview_sample_invoice($orgRow, $settings, $templateId);
            $invoice = $sample['invoice'];
            $items = $sample['items'];
        } else {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vy_invoice_items WHERE org_id = %d AND invoice_id = %d ORDER BY id ASC",
                $org,
                $invoice->id
            ));
        }

        $html = self::render_template($templatePath, [
            'invoice'  => $invoice,
            'items'    => $items,
            'org'      => $orgRow,
            'settings' => $settings,
            'template' => $template,
        ]);

        if ($html === null) {
            return new WP_Error('vy_preview_render_failed', 'Unable to render template.', ['status' => 500]);
        }

        return self::html_response($html);
    }

    private static function render_template(string $path, array $context): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        ob_start();
        extract($context, EXTR_SKIP);
        require $path;
        return ob_get_clean();
    }

    private static function html_response(string $html): WP_REST_Response
    {
        $response = new WP_REST_Response($html);
        $response->set_headers(['Content-Type' => 'text/html; charset=UTF-8']);
        return $response;
    }
}
