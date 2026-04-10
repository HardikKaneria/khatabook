<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_get_invoice_risk_summary')) {
    function vy_get_invoice_risk_summary(int $org_id, array $invoice, array $items = [], array $payments = [], array $adjustments = [], array $promises = []): array
    {
        $issues = [];
        $today = gmdate('Y-m-d');
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $total = (float) ($invoice['total'] ?? 0);
        $dueDate = trim((string) ($invoice['due_date'] ?? ''));
        $customerName = trim((string) ($invoice['customer_name'] ?? ''));
        $customerEmail = sanitize_email((string) ($invoice['customer_email'] ?? ''));
        $status = strtoupper((string) ($invoice['status'] ?? 'SENT'));

        if ($customerName === '') {
            $issues[] = [
                'code' => 'missing_customer',
                'severity' => 'critical',
                'title' => 'Customer name is missing',
                'detail' => 'Add a customer before sharing or relying on this invoice.',
            ];
        }

        if ($dueDate === '') {
            $issues[] = [
                'code' => 'missing_due_date',
                'severity' => 'warning',
                'title' => 'Due date is missing',
                'detail' => 'Collection follow-up is harder when the invoice does not state when payment is due.',
            ];
        }

        if (!$items) {
            $issues[] = [
                'code' => 'missing_items',
                'severity' => 'critical',
                'title' => 'No line items are present',
                'detail' => 'Invoices should include at least one reviewed line item before they are sent.',
            ];
        }

        if ($total <= 0) {
            $issues[] = [
                'code' => 'non_positive_total',
                'severity' => 'critical',
                'title' => 'Invoice total is zero or negative',
                'detail' => 'Review quantities, pricing, and notes before sending a zero-value invoice.',
            ];
        }

        $recalculatedSubtotal = 0.0;
        $recalculatedTax = 0.0;
        $recalculatedTotal = 0.0;
        foreach ($items as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $lineBase = round($quantity * $unitPrice, 2);
            $lineTax = isset($item['tax_amount'])
                ? round((float) $item['tax_amount'], 2)
                : round($lineBase * ($taxRate / 100), 2);
            $lineTotal = isset($item['line_total'])
                ? round((float) $item['line_total'], 2)
                : round($lineBase + $lineTax, 2);

            $recalculatedSubtotal += $lineBase;
            $recalculatedTax += $lineTax;
            $recalculatedTotal += $lineTotal;

            if ($description === '') {
                $issues[] = [
                    'code' => 'item_missing_description_' . $index,
                    'severity' => 'warning',
                    'title' => 'A line item is missing its description',
                    'detail' => 'Each line item should state what the customer is being billed for.',
                ];
            }

            if ($quantity <= 0) {
                $issues[] = [
                    'code' => 'item_bad_quantity_' . $index,
                    'severity' => 'critical',
                    'title' => 'A line item quantity is zero or negative',
                    'detail' => 'Review billed quantity before sending this invoice.',
                ];
            }

            if ($unitPrice < 0) {
                $issues[] = [
                    'code' => 'item_bad_price_' . $index,
                    'severity' => 'critical',
                    'title' => 'A line item price is negative',
                    'detail' => 'Negative unit prices should be corrected or represented through notes instead.',
                ];
            }
        }

        if (abs($recalculatedSubtotal - (float) ($invoice['subtotal'] ?? 0)) > 0.5) {
            $issues[] = [
                'code' => 'subtotal_mismatch',
                'severity' => 'critical',
                'title' => 'Stored subtotal does not match the line items',
                'detail' => 'Recalculate the invoice before emailing or generating a PDF.',
            ];
        }

        if (abs($recalculatedTax - (float) ($invoice['tax_total'] ?? 0)) > 0.5) {
            $issues[] = [
                'code' => 'tax_mismatch',
                'severity' => 'warning',
                'title' => 'Stored tax total does not match the line items',
                'detail' => 'Review the tax setup on each line item before sending this invoice.',
            ];
        }

        if (abs($recalculatedTotal - $total) > 0.5) {
            $issues[] = [
                'code' => 'total_mismatch',
                'severity' => 'critical',
                'title' => 'Stored total does not match the line items',
                'detail' => 'The invoice total differs from its line items and should be corrected first.',
            ];
        }

        if ($customerEmail === '') {
            $issues[] = [
                'code' => 'missing_customer_email',
                'severity' => 'info',
                'title' => 'Customer email is not stored',
                'detail' => 'Sending is still possible with manual recipients, but the customer record is incomplete.',
            ];
        }

        if ($status === 'DRAFT' && $dueDate !== '' && $dueDate < $today) {
            $issues[] = [
                'code' => 'draft_past_due',
                'severity' => 'warning',
                'title' => 'Draft invoice is already past due',
                'detail' => 'Review the invoice date or due date before converting this draft into an active receivable.',
            ];
        }

        $openPromiseCount = 0;
        $overduePromiseCount = 0;
        foreach ($promises as $promise) {
            if (strtoupper((string) ($promise['status'] ?? 'OPEN')) !== 'OPEN') {
                continue;
            }
            $openPromiseCount++;
            if (!empty($promise['promised_date']) && (string) $promise['promised_date'] < $today) {
                $overduePromiseCount++;
            }
        }
        if ($overduePromiseCount > 0) {
            $issues[] = [
                'code' => 'overdue_promises',
                'severity' => 'warning',
                'title' => 'Existing promises to pay are overdue',
                'detail' => sprintf(
                    '%d of %d open payment promise%s already slipped past the promised date.',
                    $overduePromiseCount,
                    $openPromiseCount,
                    $openPromiseCount === 1 ? '' : 's'
                ),
            ];
        }

        $historyInvoices = array_values(array_filter(
            vy_fetch_report_invoice_rows($org_id),
            static function (array $row) use ($invoiceId, $customerName, $customerEmail): bool {
                if ((int) ($row['id'] ?? 0) === $invoiceId) {
                    return false;
                }
                if (in_array(strtoupper((string) ($row['status'] ?? 'SENT')), ['VOID', 'DRAFT'], true)) {
                    return false;
                }

                $rowEmail = sanitize_email((string) ($row['customer_email'] ?? ''));
                if ($customerEmail !== '' && $rowEmail !== '') {
                    return strtolower($rowEmail) === strtolower($customerEmail);
                }

                return $customerName !== '' && strtolower(trim((string) ($row['customer_name'] ?? ''))) === strtolower($customerName);
            }
        ));

        if (count($historyInvoices) >= 3 && $total > 0) {
            $historyTotals = array_map(
                static fn(array $row): float => (float) ($row['total'] ?? 0),
                $historyInvoices
            );
            $averageTotal = array_sum($historyTotals) / count($historyTotals);
            if ($averageTotal > 0) {
                $ratio = $total / $averageTotal;
                if ($ratio >= 2.5 || $ratio <= 0.4) {
                    $issues[] = [
                        'code' => 'amount_anomaly',
                        'severity' => 'warning',
                        'title' => 'Invoice amount is unusual for this customer',
                        'detail' => sprintf(
                            'Current total %.2f looks materially different from the recent average of %.2f for this customer.',
                            $total,
                            round($averageTotal, 2)
                        ),
                    ];
                }
            }
        }

        $severityWeight = [
            'critical' => 3,
            'warning' => 2,
            'info' => 1,
        ];
        usort($issues, static function (array $left, array $right) use ($severityWeight): int {
            $leftWeight = $severityWeight[$left['severity'] ?? 'info'] ?? 0;
            $rightWeight = $severityWeight[$right['severity'] ?? 'info'] ?? 0;
            if ($leftWeight !== $rightWeight) {
                return $rightWeight <=> $leftWeight;
            }
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        $level = 'clear';
        if (array_filter($issues, static fn(array $issue): bool => ($issue['severity'] ?? '') === 'critical')) {
            $level = 'critical';
        } elseif (array_filter($issues, static fn(array $issue): bool => ($issue['severity'] ?? '') === 'warning')) {
            $level = 'warning';
        } elseif ($issues) {
            $level = 'info';
        }

        $headline = match ($level) {
            'critical' => 'Correct these invoice issues before relying on this record.',
            'warning' => 'Review this invoice before sharing it with the customer.',
            'info' => 'This invoice is usable, but a few quality checks are worth reviewing.',
            default => 'This invoice passes the current rule-based risk checks.',
        };

        return [
            'level' => $level,
            'ready_to_send' => $level !== 'critical',
            'issue_count' => count($issues),
            'headline' => $headline,
            'issues' => array_values($issues),
        ];
    }
}
