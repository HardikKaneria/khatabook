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

        if (!$invoice) {
            return self::html_response('<html><body><p style="font-family:sans-serif;padding:24px;">No invoice available for preview.</p></body></html>');
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoice_items WHERE org_id = %d AND invoice_id = %d ORDER BY id ASC",
            $org,
            $invoice->id
        ));

        $orgRow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d LIMIT 1",
            $org
        ));
        if (!$orgRow) {
            return new WP_Error('vy_org_missing', 'Organization not found.', ['status' => 404]);
        }

        $settingsData = vy_fetch_invoice_template_settings($org);
        $settings = (object) $settingsData;

        $overrides = [
            'primary_color' => $request->get_param('primary_color'),
            'accent_color'  => $request->get_param('accent_color'),
            'logo_url'      => $request->get_param('logo_url'),
        ];
        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                if ($key === 'logo_url') {
                    $settings->$key = esc_url_raw($value);
                } else {
                    $settings->$key = sanitize_text_field($value);
                }
            }
        }

        $templateId = sanitize_key($request->get_param('template_id'));
        if (!$templateId) {
            $templateId = $invoice->template_id ?: ($settings->default_template_id ?? 'minimal-clean');
        }
        if (!$templateId) {
            $templateId = 'minimal-clean';
        }

        $templatePath = self::resolve_template_path($templateId);
        if (!file_exists($templatePath)) {
            $templateId = 'minimal-clean';
            $templatePath = self::resolve_template_path($templateId);
        }
        $template = vy_get_invoice_template($templateId);

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

    private static function resolve_template_path(string $template_id): string
    {
        $base = trailingslashit(plugin_dir_path(KHATABOOK_PLUGIN_FILE) . 'backend/templates/invoices');
        return $base . $template_id . '.php';
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
