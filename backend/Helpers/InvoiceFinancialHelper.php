<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_fetch_invoice_note_rows')) {
    function vy_fetch_invoice_note_rows(int $org_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_id, note_number, note_type, note_date, amount, reason, status
             FROM {$wpdb->prefix}vy_invoice_notes
             WHERE org_id = %d
             ORDER BY note_date ASC, id ASC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_invoice_note_totals_map')) {
    function vy_invoice_note_totals_map(int $org_id): array
    {
        $totals = [];

        foreach (vy_fetch_invoice_note_rows($org_id) as $note) {
            if (strtoupper((string) ($note['status'] ?? 'POSTED')) !== 'POSTED') {
                continue;
            }

            $invoice_id = (int) ($note['invoice_id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            if (!isset($totals[$invoice_id])) {
                $totals[$invoice_id] = [
                    'credit_total' => 0.0,
                    'debit_total' => 0.0,
                ];
            }

            $amount = round((float) ($note['amount'] ?? 0), 2);
            $type = strtoupper((string) ($note['note_type'] ?? ''));
            if ($type === 'CREDIT') {
                $totals[$invoice_id]['credit_total'] += $amount;
            } elseif ($type === 'DEBIT') {
                $totals[$invoice_id]['debit_total'] += $amount;
            }
        }

        foreach ($totals as &$row) {
            $row['credit_total'] = round((float) ($row['credit_total'] ?? 0), 2);
            $row['debit_total'] = round((float) ($row['debit_total'] ?? 0), 2);
        }
        unset($row);

        return $totals;
    }
}

if (!function_exists('vy_invoice_note_totals_for_invoice')) {
    function vy_invoice_note_totals_for_invoice(int $org_id, int $invoice_id): array
    {
        $map = vy_invoice_note_totals_map($org_id);
        $row = $map[$invoice_id] ?? null;

        return [
            'credit_total' => round((float) ($row['credit_total'] ?? 0), 2),
            'debit_total' => round((float) ($row['debit_total'] ?? 0), 2),
        ];
    }
}

if (!function_exists('vy_invoice_apply_adjustments')) {
    function vy_invoice_apply_adjustments(array $invoice, float $paid_amount = 0.0, ?array $note_totals = null): array
    {
        $base_total = round((float) ($invoice['total'] ?? 0), 2);
        $credit_total = round((float) (($note_totals['credit_total'] ?? 0) ?: 0), 2);
        $debit_total = round((float) (($note_totals['debit_total'] ?? 0) ?: 0), 2);
        $adjusted_total = round(max(0, $base_total + $debit_total - $credit_total), 2);
        $paid_amount = round(max(0, $paid_amount), 2);

        $invoice['total'] = $base_total;
        $invoice['credit_total'] = $credit_total;
        $invoice['debit_total'] = $debit_total;
        $invoice['adjusted_total'] = $adjusted_total;
        $invoice['paid_amount'] = $paid_amount;
        $invoice['balance_due'] = round(max(0, $adjusted_total - $paid_amount), 2);

        return $invoice;
    }
}

if (!function_exists('vy_invoice_refund_total')) {
    function vy_invoice_refund_total(array $refunds): float
    {
        $total = 0.0;
        foreach ($refunds as $refund) {
            if (is_array($refund)) {
                $total += (float) ($refund['amount'] ?? 0);
                continue;
            }

            if (is_object($refund)) {
                $total += (float) ($refund->amount ?? 0);
            }
        }

        return round($total, 2);
    }
}
