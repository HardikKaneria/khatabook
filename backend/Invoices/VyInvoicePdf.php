<?php

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\MpdfException;

defined('ABSPATH') || exit;

class Vy_Invoice_Pdf
{
    public static function generate(int $org_id, int $invoice_id)
    {
        global $wpdb;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $invoice_id
        ));
        if (!$invoice) {
            return new WP_Error('vy_invoice_missing', 'Invoice not found for this organization.', ['status' => 404]);
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoice_items WHERE org_id = %d AND invoice_id = %d ORDER BY id ASC",
            $org_id,
            $invoice_id
        ));

        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d LIMIT 1",
            $org_id
        ));
        if (!$org) {
            return new WP_Error('vy_org_missing', 'Organization not found.', ['status' => 404]);
        }

        $settingsData = vy_fetch_invoice_template_settings($org_id);
        $settings = (object) $settingsData;

        $templateId = $invoice->template_id ?: ($settings->default_template_id ?: 'minimal-clean');
        $templatePath = self::resolve_template_path($templateId);
        if (!file_exists($templatePath)) {
            $templateId = 'minimal-clean';
            $templatePath = self::resolve_template_path($templateId);
        }
        $template = vy_get_invoice_template($templateId);

        $html = self::render_template($templatePath, [
            'invoice'  => $invoice,
            'items'    => $items,
            'org'      => $org,
            'settings' => $settings,
            'template' => $template,
        ]);
        if ($html === null) {
            return new WP_Error('vy_pdf_template_error', 'Unable to render invoice template.', ['status' => 500]);
        }

        $pdfBinary = self::render_pdf($html);
        if (is_wp_error($pdfBinary)) {
            return $pdfBinary;
        }

        return self::store_pdf($org_id, $invoice, $templateId, $pdfBinary);
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

    private static function render_pdf(string $html)
    {
        try {
            $mpdf = new Mpdf([
                'tempDir' => self::ensure_temp_dir(),
                'mode'    => 'utf-8',
                'format'  => 'A4',
            ]);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', Destination::STRING_RETURN);
        } catch (MpdfException $exception) {
            return new WP_Error('vy_pdf_render_failed', $exception->getMessage(), ['status' => 500]);
        }
    }

    private static function ensure_temp_dir(): string
    {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'vyavhar/tmp';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    private static function store_pdf(int $org_id, object $invoice, string $template_id, string $binary)
    {
        global $wpdb;
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('vy_upload_error', $uploads['error'], ['status' => 500]);
        }

        $dir = trailingslashit($uploads['basedir']) . 'vyavhar/invoices/' . $org_id;
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('vy_pdf_dir_failed', 'Unable to prepare PDF directory.', ['status' => 500]);
        }

        $baseName = $invoice->invoice_number ?: 'invoice-' . (int) $invoice->id;
        $fileName = sanitize_file_name($baseName . '.pdf');
        if (!$fileName) {
            $fileName = 'invoice-' . (int) $invoice->id . '.pdf';
        }
        $fileName = wp_unique_filename($dir, $fileName);
        $path = trailingslashit($dir) . $fileName;

        if (false === file_put_contents($path, $binary)) {
            return new WP_Error('vy_pdf_write_failed', 'Failed to save PDF to disk.', ['status' => 500]);
        }

        $urlBase = trailingslashit($uploads['baseurl']) . 'vyavhar/invoices/' . $org_id;
        $pdfUrl = trailingslashit($urlBase) . $fileName;

        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_invoices',
            [
                'pdf_url'     => $pdfUrl,
                'template_id' => $template_id,
                'updated_at'  => current_time('mysql', true),
            ],
            [
                'id'     => (int) $invoice->id,
                'org_id' => $org_id,
            ],
            ['%s','%s','%s'],
            ['%d','%d']
        );

        if ($updated === false) {
            return new WP_Error('vy_pdf_update_failed', 'Failed to update invoice record.', ['status' => 500]);
        }

        return $pdfUrl;
    }
}
