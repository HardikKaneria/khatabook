<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_invoice_payment_total')) {
    function vy_invoice_payment_total(array $payments): float
    {
        $total = 0.0;
        foreach ($payments as $payment) {
            if (is_array($payment)) {
                $total += (float) ($payment['amount'] ?? 0);
                continue;
            }

            if (is_object($payment)) {
                $total += (float) ($payment->amount ?? 0);
            }
        }

        return round($total, 2);
    }
}

if (!function_exists('vy_invoice_edit_state')) {
    function vy_invoice_edit_state($invoice, array $payments = []): array
    {
        $status = strtoupper((string) vy_invoice_setting_value($invoice, 'status', 'SENT'));
        $paidAmount = (float) vy_invoice_setting_value($invoice, 'paid_amount', vy_invoice_payment_total($payments));
        $creditTotal = (float) vy_invoice_setting_value($invoice, 'credit_total', 0);
        $debitTotal = (float) vy_invoice_setting_value($invoice, 'debit_total', 0);

        if ($paidAmount > 0 || !empty($payments)) {
            return [
                'can_edit' => false,
                'reason'   => 'Invoices with recorded payments can no longer be edited.',
            ];
        }

        if ($creditTotal > 0 || $debitTotal > 0) {
            return [
                'can_edit' => false,
                'reason'   => 'Invoices with posted credit or debit notes can no longer be edited.',
            ];
        }

        if ($status === 'VOID') {
            return [
                'can_edit' => false,
                'reason'   => 'Voided invoices can no longer be edited.',
            ];
        }

        if (in_array($status, ['PAID', 'PARTIAL'], true)) {
            return [
                'can_edit' => false,
                'reason'   => 'Paid or partially paid invoices can no longer be edited.',
            ];
        }

        if (!in_array($status, ['DRAFT', 'SENT'], true)) {
            return [
                'can_edit' => false,
                'reason'   => 'Only draft or sent invoices can be edited.',
            ];
        }

        return [
            'can_edit' => true,
            'reason'   => null,
        ];
    }
}
