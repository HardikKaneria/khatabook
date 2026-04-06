<?php

if (!function_exists('vy_get_date_range_defaults')) {
    function vy_get_date_range_defaults(?string $from, ?string $to): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('first day of this month');
        $rangeFrom = $from && strtotime($from) ? (new DateTimeImmutable($from))->format('Y-m-d') : $start->format('Y-m-d');
        $rangeTo = $to && strtotime($to) ? (new DateTimeImmutable($to))->format('Y-m-d') : $today->format('Y-m-d');
        if ($rangeFrom > $rangeTo) {
            [$rangeFrom, $rangeTo] = [$rangeTo, $rangeFrom];
        }
        return [$rangeFrom, $rangeTo];
    }
}

if (!function_exists('vy_fetch_org_settings_category')) {
    function vy_fetch_org_settings_category(int $org_id, string $category): array
    {
        static $cache = [];

        $cache_key = $org_id . ':' . $category;
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT settings_json
             FROM {$wpdb->prefix}kbs_settings
             WHERE org_id = %d AND category = %s
             LIMIT 1",
            $org_id,
            $category
        ));

        if (!$row || empty($row->settings_json)) {
            $cache[$cache_key] = [];
            return $cache[$cache_key];
        }

        $decoded = json_decode($row->settings_json, true);
        $cache[$cache_key] = is_array($decoded) ? $decoded : [];

        return $cache[$cache_key];
    }
}

if (!function_exists('vy_calculate_account_balance_components')) {
    function vy_calculate_account_balance_components(int $org_id, int $account_id, ?string $date_from = null, ?string $date_to = null): array
    {
        global $wpdb;
        $lines_table = $wpdb->prefix . 'vy_journal_lines';
        $entries_table = $wpdb->prefix . 'vy_journal_entries';

        $sql = "
            SELECT
                COALESCE(SUM(l.debit), 0)  AS total_debit,
                COALESCE(SUM(l.credit), 0) AS total_credit
            FROM {$lines_table} l
            INNER JOIN {$entries_table} e ON l.journal_id = e.id
            WHERE l.org_id = %d AND l.account_id = %d
        ";
        $params = [$org_id, $account_id];
        if ($date_from) {
            $sql .= " AND e.entry_date >= %s";
            $params[] = $date_from;
        }
        if ($date_to) {
            $sql .= " AND e.entry_date <= %s";
            $params[] = $date_to;
        }

        $totals = $wpdb->get_row($wpdb->prepare($sql, ...$params));

        return [
            'total_debit'  => $totals ? (float) $totals->total_debit : 0.0,
            'total_credit' => $totals ? (float) $totals->total_credit : 0.0,
        ];
    }
}

if (!function_exists('vy_format_account_balance')) {
    function vy_format_account_balance(string $account_type, float $total_debit, float $total_credit): float
    {
        $type = strtoupper($account_type);
        if (in_array($type, ['ASSET', 'EXPENSE'], true)) {
            return $total_debit - $total_credit;
        }
        if (in_array($type, ['INCOME', 'LIABILITY', 'EQUITY'], true)) {
            return $total_credit - $total_debit;
        }
        return $total_debit - $total_credit;
    }
}

if (!function_exists('vy_get_profit_summary')) {
    function vy_get_profit_summary(int $org_id, string $date_from, string $date_to): array
    {
        global $wpdb;
        $accounts_table = $wpdb->prefix . 'vy_accounts';
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, type FROM {$accounts_table} WHERE org_id = %d AND type IN ('INCOME','EXPENSE')",
            $org_id
        ));

        $income_total = 0;
        $expense_total = 0;
        $income_by_account = [];
        $expense_by_account = [];

        foreach ($accounts as $acc) {
            $totals = vy_calculate_account_balance_components($org_id, (int) $acc->id, $date_from, $date_to);
            $amount = vy_format_account_balance($acc->type, $totals['total_debit'], $totals['total_credit']);
            if ($acc->type === 'INCOME') {
                $income_total += $amount;
                $income_by_account[] = [
                    'account_id' => (int) $acc->id,
                    'name'       => $acc->name,
                    'amount'     => $amount,
                ];
            } else {
                $expense_total += $amount;
                $expense_by_account[] = [
                    'account_id' => (int) $acc->id,
                    'name'       => $acc->name,
                    'amount'     => $amount,
                ];
            }
        }

        return [
            'income_total'       => $income_total,
            'expense_total'      => $expense_total,
            'profit'             => $income_total - $expense_total,
            'income_by_account'  => $income_by_account,
            'expense_by_account' => $expense_by_account,
        ];
    }
}

if (!function_exists('vy_get_gst_summary')) {
    function vy_get_gst_summary(int $org_id, string $date_from, string $date_to): array
    {
        $config = vy_get_org_tax_config($org_id);
        if (!$config['is_gst_registered']) {
            return [
                'output_tax'        => 0.0,
                'input_tax'         => 0.0,
                'net_gst_payable'   => 0.0,
                'is_gst_registered' => false,
                'gst_type'          => $config['gst_type'],
            ];
        }

        global $wpdb;
        $items_table = $wpdb->prefix . 'vy_invoice_items';
        $invoices_table = $wpdb->prefix . 'vy_invoices';
        $expenses_table = $wpdb->prefix . 'vy_expenses';

        $output_tax = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN ii.tax_amount IS NOT NULL AND ii.tax_amount > 0 THEN ii.tax_amount
                    WHEN ii.tax_rate > 0 THEN (ii.line_total - (ii.line_total / (1 + (ii.tax_rate / 100))))
                    ELSE 0
                END
            ), 0)
             FROM {$items_table} ii
             INNER JOIN {$invoices_table} i ON ii.invoice_id = i.id
             WHERE ii.org_id = %d AND i.date BETWEEN %s AND %s",
            $org_id,
            $date_from,
            $date_to
        ));

        $input_tax = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(gst_amount), 0)
             FROM {$expenses_table}
             WHERE org_id = %d
               AND expense_date BETWEEN %s AND %s
               AND is_gst_input_eligible = 1",
            $org_id,
            $date_from,
            $date_to
        ));

        return [
            'output_tax'        => $output_tax,
            'input_tax'         => $input_tax,
            'net_gst_payable'   => $output_tax - $input_tax,
            'is_gst_registered' => true,
            'gst_type'          => $config['gst_type'],
        ];
    }
}

if (!function_exists('vy_normalize_report_date')) {
    function vy_normalize_report_date(?string $date, ?string $fallback = null): string
    {
        if ($date && strtotime($date)) {
            return (new DateTimeImmutable($date))->format('Y-m-d');
        }

        if ($fallback && strtotime($fallback)) {
            return (new DateTimeImmutable($fallback))->format('Y-m-d');
        }

        return gmdate('Y-m-d');
    }
}

if (!function_exists('vy_fetch_report_invoice_rows')) {
    function vy_fetch_report_invoice_rows(int $org_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contact_id, invoice_number, customer_name, customer_email, date, due_date, total, status
             FROM {$wpdb->prefix}vy_invoices
             WHERE org_id = %d
             ORDER BY date ASC, id ASC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_fetch_report_payment_rows')) {
    function vy_fetch_report_payment_rows(int $org_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_id, amount, date
             FROM {$wpdb->prefix}vy_invoice_payments
             WHERE org_id = %d
             ORDER BY date ASC, id ASC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_fetch_report_expense_rows')) {
    function vy_fetch_report_expense_rows(int $org_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT expense_date, amount, status
             FROM {$wpdb->prefix}vy_expenses
             WHERE org_id = %d
             ORDER BY expense_date ASC, id ASC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_receivables_bucket_label')) {
    function vy_receivables_bucket_label(string $bucket): string
    {
        return match ($bucket) {
            'current' => 'Current',
            '1_30' => '1-30 days',
            '31_60' => '31-60 days',
            '61_90' => '61-90 days',
            '91_plus' => '91+ days',
            default => ucfirst(str_replace('_', ' ', $bucket)),
        };
    }
}

if (!function_exists('vy_get_receivables_summary')) {
    function vy_get_receivables_summary(int $org_id, string $date_from, string $date_to, ?string $as_of = null): array
    {
        $as_of = vy_normalize_report_date($as_of);
        $as_of_time = strtotime($as_of) ?: time();

        $payments_by_invoice = [];
        foreach (vy_fetch_report_payment_rows($org_id) as $payment) {
            $invoice_id = (int) ($payment['invoice_id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            $payments_by_invoice[$invoice_id] = ($payments_by_invoice[$invoice_id] ?? 0.0) + (float) ($payment['amount'] ?? 0);
        }

        $note_totals_map = vy_invoice_note_totals_map($org_id);

        $status_summary = [
            'DRAFT'   => ['status' => 'DRAFT', 'count' => 0, 'amount' => 0.0],
            'SENT'    => ['status' => 'SENT', 'count' => 0, 'amount' => 0.0],
            'PARTIAL' => ['status' => 'PARTIAL', 'count' => 0, 'amount' => 0.0],
            'PAID'    => ['status' => 'PAID', 'count' => 0, 'amount' => 0.0],
            'VOID'    => ['status' => 'VOID', 'count' => 0, 'amount' => 0.0],
        ];

        $aging_buckets = [
            'current' => ['bucket' => 'current', 'label' => vy_receivables_bucket_label('current'), 'count' => 0, 'amount' => 0.0],
            '1_30'    => ['bucket' => '1_30', 'label' => vy_receivables_bucket_label('1_30'), 'count' => 0, 'amount' => 0.0],
            '31_60'   => ['bucket' => '31_60', 'label' => vy_receivables_bucket_label('31_60'), 'count' => 0, 'amount' => 0.0],
            '61_90'   => ['bucket' => '61_90', 'label' => vy_receivables_bucket_label('61_90'), 'count' => 0, 'amount' => 0.0],
            '91_plus' => ['bucket' => '91_plus', 'label' => vy_receivables_bucket_label('91_plus'), 'count' => 0, 'amount' => 0.0],
        ];

        $customer_balances = [];
        $outstanding_amount = 0.0;
        $overdue_amount = 0.0;
        $overdue_count = 0;
        $open_invoice_count = 0;
        $overdue_days_total = 0.0;

        foreach (vy_fetch_report_invoice_rows($org_id) as $invoice) {
            $invoice_date = vy_normalize_report_date($invoice['date'] ?? null, $date_from);
            $status = strtoupper(trim((string) ($invoice['status'] ?? 'SENT')));
            if (!isset($status_summary[$status])) {
                $status = 'SENT';
            }

            $total = (float) ($invoice['total'] ?? 0);
            $invoice_id = (int) ($invoice['id'] ?? 0);
            $financials = vy_invoice_apply_adjustments(
                [
                    'id' => $invoice_id,
                    'total' => $total,
                ],
                (float) ($payments_by_invoice[$invoice_id] ?? 0.0),
                $note_totals_map[$invoice_id] ?? null
            );
            $adjusted_total = (float) ($financials['adjusted_total'] ?? $total);
            if ($invoice_date >= $date_from && $invoice_date <= $date_to) {
                $status_summary[$status]['count']++;
                $status_summary[$status]['amount'] += $adjusted_total;
            }

            if (!in_array($status, ['SENT', 'PARTIAL'], true)) {
                continue;
            }

            $balance_due = (float) ($financials['balance_due'] ?? 0);
            if ($balance_due <= 0) {
                continue;
            }
            if ($invoice_date > $as_of) {
                continue;
            }

            $open_invoice_count++;
            $outstanding_amount += $balance_due;

            $due_date = vy_normalize_report_date($invoice['due_date'] ?? null, $invoice_date);
            $due_time = strtotime($due_date) ?: $as_of_time;
            $days_overdue = (int) floor(($as_of_time - $due_time) / 86400);
            $is_overdue = $days_overdue > 0;

            if ($is_overdue) {
                $overdue_amount += $balance_due;
                $overdue_count++;
                $overdue_days_total += $days_overdue;
            }

            $bucket_key = 'current';
            if ($days_overdue >= 91) {
                $bucket_key = '91_plus';
            } elseif ($days_overdue >= 61) {
                $bucket_key = '61_90';
            } elseif ($days_overdue >= 31) {
                $bucket_key = '31_60';
            } elseif ($days_overdue >= 1) {
                $bucket_key = '1_30';
            }

            $aging_buckets[$bucket_key]['count']++;
            $aging_buckets[$bucket_key]['amount'] += $balance_due;

            $customer_name = trim((string) ($invoice['customer_name'] ?? ''));
            $customer_email = sanitize_email((string) ($invoice['customer_email'] ?? ''));
            $customer_key = $customer_email !== ''
                ? strtolower($customer_email)
                : ($customer_name !== '' ? strtolower($customer_name) : 'invoice-' . $invoice_id);

            if (!isset($customer_balances[$customer_key])) {
                $customer_balances[$customer_key] = [
                    'label'              => $customer_name !== '' ? $customer_name : ($customer_email !== '' ? $customer_email : 'Walk-in customer'),
                    'email'              => $customer_email ?: null,
                    'invoice_count'      => 0,
                    'outstanding_amount' => 0.0,
                    'overdue_amount'     => 0.0,
                ];
            }

            $customer_balances[$customer_key]['invoice_count']++;
            $customer_balances[$customer_key]['outstanding_amount'] += $balance_due;
            if ($is_overdue) {
                $customer_balances[$customer_key]['overdue_amount'] += $balance_due;
            }
        }

        $top_customers = array_values($customer_balances);
        usort($top_customers, static function (array $left, array $right): int {
            return ($right['outstanding_amount'] <=> $left['outstanding_amount']);
        });
        $top_customers = array_slice($top_customers, 0, 5);

        $average_days_overdue = $overdue_count > 0
            ? round($overdue_days_total / $overdue_count, 1)
            : 0.0;

        return [
            'from'                => $date_from,
            'to'                  => $date_to,
            'as_of'               => $as_of,
            'open_invoice_count'  => $open_invoice_count,
            'outstanding_amount'  => round($outstanding_amount, 2),
            'overdue_amount'      => round($overdue_amount, 2),
            'overdue_count'       => $overdue_count,
            'average_days_overdue'=> $average_days_overdue,
            'aging_buckets'       => array_values($aging_buckets),
            'invoice_status'      => array_values($status_summary),
            'top_customers'       => array_map(static function (array $row): array {
                $row['outstanding_amount'] = round((float) $row['outstanding_amount'], 2);
                $row['overdue_amount'] = round((float) $row['overdue_amount'], 2);
                return $row;
            }, $top_customers),
        ];
    }
}

if (!function_exists('vy_build_monthly_slots')) {
    function vy_build_monthly_slots(string $date_from, string $date_to): array
    {
        $start = new DateTimeImmutable(substr($date_from, 0, 7) . '-01');
        $end = new DateTimeImmutable(substr($date_to, 0, 7) . '-01');
        $months = [];

        while ($start <= $end) {
            $key = $start->format('Y-m');
            $months[$key] = [
                'month'         => $key,
                'label'         => $start->format('M Y'),
                'revenue_total' => 0.0,
                'expense_total' => 0.0,
                'invoice_count' => 0,
                'expense_count' => 0,
            ];
            $start = $start->modify('+1 month');
        }

        return $months;
    }
}

if (!function_exists('vy_get_monthly_document_trends')) {
    function vy_get_monthly_document_trends(int $org_id, string $date_from, string $date_to): array
    {
        $months = vy_build_monthly_slots($date_from, $date_to);

        foreach (vy_fetch_report_invoice_rows($org_id) as $invoice) {
            $status = strtoupper(trim((string) ($invoice['status'] ?? 'SENT')));
            if (in_array($status, ['DRAFT', 'VOID'], true)) {
                continue;
            }

            $invoice_date = vy_normalize_report_date($invoice['date'] ?? null, $date_from);
            if ($invoice_date < $date_from || $invoice_date > $date_to) {
                continue;
            }

            $month_key = substr($invoice_date, 0, 7);
            if (!isset($months[$month_key])) {
                continue;
            }

            $months[$month_key]['revenue_total'] += (float) ($invoice['total'] ?? 0);
            $months[$month_key]['invoice_count']++;
        }

        foreach (vy_fetch_report_expense_rows($org_id) as $expense) {
            $status = strtoupper(trim((string) ($expense['status'] ?? 'POSTED')));
            if ($status === 'ARCHIVED') {
                continue;
            }

            $expense_date = vy_normalize_report_date($expense['expense_date'] ?? null, $date_from);
            if ($expense_date < $date_from || $expense_date > $date_to) {
                continue;
            }

            $month_key = substr($expense_date, 0, 7);
            if (!isset($months[$month_key])) {
                continue;
            }

            $months[$month_key]['expense_total'] += (float) ($expense['amount'] ?? 0);
            $months[$month_key]['expense_count']++;
        }

        return [
            'from'   => $date_from,
            'to'     => $date_to,
            'months' => array_values(array_map(static function (array $month): array {
                $month['revenue_total'] = round((float) $month['revenue_total'], 2);
                $month['expense_total'] = round((float) $month['expense_total'], 2);
                return $month;
            }, $months)),
        ];
    }
}

if (!function_exists('vy_get_customer_statement')) {
    function vy_get_customer_statement(int $org_id, int $contact_id, string $date_from, string $date_to, array $contact = []): array
    {
        if ($date_from > $date_to) {
            [$date_from, $date_to] = [$date_to, $date_from];
        }

        $eligible_statuses = ['SENT', 'PARTIAL', 'PAID'];
        $invoices = [];
        foreach (vy_fetch_report_invoice_rows($org_id) as $invoice) {
            if ((int) ($invoice['contact_id'] ?? 0) !== $contact_id) {
                continue;
            }

            $status = strtoupper(trim((string) ($invoice['status'] ?? 'SENT')));
            if (!in_array($status, $eligible_statuses, true)) {
                continue;
            }

            $invoice_id = (int) ($invoice['id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            $invoice_date = vy_normalize_report_date($invoice['date'] ?? null, $date_from);
            $invoices[$invoice_id] = [
                'id'             => $invoice_id,
                'invoice_number' => trim((string) ($invoice['invoice_number'] ?? ('INV-' . $invoice_id))),
                'date'           => $invoice_date,
                'due_date'       => vy_normalize_report_date($invoice['due_date'] ?? null, $invoice_date),
                'customer_name'  => trim((string) ($invoice['customer_name'] ?? '')),
                'customer_email' => sanitize_email((string) ($invoice['customer_email'] ?? '')),
                'status'         => $status,
                'total'          => round((float) ($invoice['total'] ?? 0), 2),
            ];
        }

        $payments_by_invoice = [];
        foreach (vy_fetch_report_payment_rows($org_id) as $payment) {
            $invoice_id = (int) ($payment['invoice_id'] ?? 0);
            if (!isset($invoices[$invoice_id])) {
                continue;
            }

            $payments_by_invoice[$invoice_id][] = [
                'id'     => (int) ($payment['id'] ?? 0),
                'amount' => round((float) ($payment['amount'] ?? 0), 2),
                'date'   => vy_normalize_report_date($payment['date'] ?? null, $date_from),
            ];
        }

        $notes_by_invoice = [];
        foreach (vy_fetch_invoice_note_rows($org_id) as $note) {
            $invoice_id = (int) ($note['invoice_id'] ?? 0);
            if (!isset($invoices[$invoice_id])) {
                continue;
            }

            $notes_by_invoice[$invoice_id][] = [
                'id' => (int) ($note['id'] ?? 0),
                'note_number' => (string) ($note['note_number'] ?? ''),
                'note_type' => strtoupper((string) ($note['note_type'] ?? '')),
                'amount' => round((float) ($note['amount'] ?? 0), 2),
                'date' => vy_normalize_report_date($note['note_date'] ?? null, $date_from),
                'status' => strtoupper((string) ($note['status'] ?? 'POSTED')),
            ];
        }

        $opening_balance = 0.0;
        $invoiced_total = 0.0;
        $debit_notes_total = 0.0;
        $credit_notes_total = 0.0;
        $payments_total = 0.0;
        $outstanding_balance = 0.0;
        $overdue_balance = 0.0;
        $open_invoice_count = 0;
        $entries = [];
        $open_invoices = [];

        foreach ($invoices as $invoice_id => $invoice) {
            $paid_before_range = 0.0;
            $paid_in_range = 0.0;
            $paid_to_end = 0.0;

            foreach ($payments_by_invoice[$invoice_id] ?? [] as $payment) {
                if ($payment['date'] < $date_from) {
                    $paid_before_range += $payment['amount'];
                }
                if ($payment['date'] >= $date_from && $payment['date'] <= $date_to) {
                    $paid_in_range += $payment['amount'];
                    $entries[] = [
                        'id'          => $payment['id'],
                        'date'        => $payment['date'],
                        'entry_type'  => 'PAYMENT',
                        'reference'   => $invoice['invoice_number'],
                        'description' => 'Payment received',
                        'debit'       => 0.0,
                        'credit'      => round($payment['amount'], 2),
                    ];
                }
                if ($payment['date'] <= $date_to) {
                    $paid_to_end += $payment['amount'];
                }
            }

            $debits_before_range = 0.0;
            $credits_before_range = 0.0;
            $debits_in_range = 0.0;
            $credits_in_range = 0.0;
            foreach ($notes_by_invoice[$invoice_id] ?? [] as $note) {
                if (($note['status'] ?? 'POSTED') !== 'POSTED') {
                    continue;
                }

                $isDebit = ($note['note_type'] ?? '') === 'DEBIT';
                if ($note['date'] < $date_from) {
                    if ($isDebit) {
                        $debits_before_range += $note['amount'];
                    } else {
                        $credits_before_range += $note['amount'];
                    }
                }

                if ($note['date'] >= $date_from && $note['date'] <= $date_to) {
                    if ($isDebit) {
                        $debits_in_range += $note['amount'];
                        $entries[] = [
                            'id' => $note['id'],
                            'date' => $note['date'],
                            'entry_type' => 'DEBIT_NOTE',
                            'reference' => $note['note_number'] ?: $invoice['invoice_number'],
                            'description' => 'Debit note posted',
                            'debit' => round($note['amount'], 2),
                            'credit' => 0.0,
                        ];
                    } else {
                        $credits_in_range += $note['amount'];
                        $entries[] = [
                            'id' => $note['id'],
                            'date' => $note['date'],
                            'entry_type' => 'CREDIT_NOTE',
                            'reference' => $note['note_number'] ?: $invoice['invoice_number'],
                            'description' => 'Credit note posted',
                            'debit' => 0.0,
                            'credit' => round($note['amount'], 2),
                        ];
                    }
                }
            }

            if ($invoice['date'] < $date_from) {
                $opening_balance += $invoice['total'];
            }
            $opening_balance += $debits_before_range;
            $opening_balance -= $credits_before_range;
            $opening_balance -= $paid_before_range;

            if ($invoice['date'] >= $date_from && $invoice['date'] <= $date_to) {
                $invoiced_total += $invoice['total'];
                $entries[] = [
                    'id'          => $invoice['id'],
                    'date'        => $invoice['date'],
                    'entry_type'  => 'INVOICE',
                    'reference'   => $invoice['invoice_number'],
                    'description' => 'Invoice issued',
                    'debit'       => round($invoice['total'], 2),
                    'credit'      => 0.0,
                ];
            }

            $debit_notes_total += $debits_in_range;
            $credit_notes_total += $credits_in_range;
            $payments_total += $paid_in_range;
            $adjusted_total = round($invoice['total'] + $debits_before_range + $debits_in_range - $credits_before_range - $credits_in_range, 2);
            $balance_due = max(0, round($adjusted_total - $paid_to_end, 2));
            if ($invoice['date'] <= $date_to && $balance_due > 0) {
                $outstanding_balance += $balance_due;
                $open_invoice_count++;
                if ($invoice['due_date'] < $date_to) {
                    $overdue_balance += $balance_due;
                }
                $open_invoices[] = [
                    'id'             => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'date'           => $invoice['date'],
                    'due_date'       => $invoice['due_date'],
                    'status'         => $invoice['status'],
                    'total'          => $invoice['total'],
                    'adjusted_total' => $adjusted_total,
                    'credit_total'   => round($credits_before_range + $credits_in_range, 2),
                    'debit_total'    => round($debits_before_range + $debits_in_range, 2),
                    'paid_amount'    => round($paid_to_end, 2),
                    'balance_due'    => $balance_due,
                ];
            }
        }

        usort($entries, static function (array $left, array $right): int {
            $date_compare = strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
            if ($date_compare !== 0) {
                return $date_compare;
            }

            $type_weight = [
                'INVOICE' => 0,
                'DEBIT_NOTE' => 1,
                'PAYMENT' => 2,
                'CREDIT_NOTE' => 3,
            ];
            $left_weight = $type_weight[$left['entry_type'] ?? 'PAYMENT'] ?? 99;
            $right_weight = $type_weight[$right['entry_type'] ?? 'PAYMENT'] ?? 99;
            if ($left_weight !== $right_weight) {
                return $left_weight <=> $right_weight;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        usort($open_invoices, static function (array $left, array $right): int {
            $due_compare = strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? ''));
            if ($due_compare !== 0) {
                return $due_compare;
            }

            return strcmp((string) ($left['invoice_number'] ?? ''), (string) ($right['invoice_number'] ?? ''));
        });

        $running_balance = round($opening_balance, 2);
        foreach ($entries as &$entry) {
            $running_balance += (float) ($entry['debit'] ?? 0);
            $running_balance -= (float) ($entry['credit'] ?? 0);
            $entry['running_balance'] = round($running_balance, 2);
        }
        unset($entry);

        $contact_name = trim((string) ($contact['name'] ?? ''));
        $contact_email = sanitize_email((string) ($contact['email'] ?? ''));
        if ($contact_name === '' && $invoices) {
            $first_invoice = reset($invoices);
            $contact_name = (string) ($first_invoice['customer_name'] ?? '');
            $contact_email = (string) ($first_invoice['customer_email'] ?? '');
        }

        return [
            'from'          => $date_from,
            'to'            => $date_to,
            'contact'       => [
                'id'     => $contact_id,
                'name'   => $contact_name,
                'email'  => $contact_email ?: null,
                'phone'  => !empty($contact['phone']) ? (string) $contact['phone'] : null,
                'type'   => !empty($contact['type']) ? (string) $contact['type'] : null,
                'status' => !empty($contact['status']) ? (string) $contact['status'] : null,
            ],
            'summary'       => [
                'opening_balance'     => round($opening_balance, 2),
                'invoiced_total'      => round($invoiced_total, 2),
                'debit_notes_total'   => round($debit_notes_total, 2),
                'credit_notes_total'  => round($credit_notes_total, 2),
                'payments_total'      => round($payments_total, 2),
                'closing_balance'     => round($opening_balance + $invoiced_total + $debit_notes_total - $credit_notes_total - $payments_total, 2),
                'outstanding_balance' => round($outstanding_balance, 2),
                'overdue_balance'     => round($overdue_balance, 2),
                'open_invoice_count'  => $open_invoice_count,
            ],
            'entries'       => $entries,
            'open_invoices' => $open_invoices,
        ];
    }
}

if (!function_exists('vy_get_org_tax_config')) {
    function vy_get_org_tax_config(int $org_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_organizations';
        $company_settings = vy_fetch_org_settings_category($org_id, 'company');
        $tax_settings = vy_fetch_org_settings_category($org_id, 'tax');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT default_income_tax_rate, is_gst_registered FROM {$table} WHERE org_id = %d LIMIT 1",
            $org_id
        ), ARRAY_A);

        $legacy_income_tax_rate = isset($row['default_income_tax_rate']) ? (float) $row['default_income_tax_rate'] : 25.0;
        $legacy_is_gst_registered = isset($row['is_gst_registered']) ? (bool) $row['is_gst_registered'] : true;

        $income_tax_rate = array_key_exists('income_tax_rate', $tax_settings) && is_numeric($tax_settings['income_tax_rate'])
            ? (float) $tax_settings['income_tax_rate']
            : $legacy_income_tax_rate;

        $is_gst_registered = array_key_exists('gst_registered', $company_settings)
            ? (bool) $company_settings['gst_registered']
            : $legacy_is_gst_registered;

        $gst_type = isset($tax_settings['gst_type']) && is_string($tax_settings['gst_type']) && $tax_settings['gst_type'] !== ''
            ? sanitize_key($tax_settings['gst_type'])
            : 'regular';

        return [
            'income_tax_rate'   => $income_tax_rate,
            'is_gst_registered' => $is_gst_registered,
            'gst_type'          => $gst_type,
        ];
    }
}

if (!function_exists('vy_get_tax_estimate')) {
    function vy_get_tax_estimate(int $org_id, string $date_from, string $date_to): array
    {
        $profitSummary = vy_get_profit_summary($org_id, $date_from, $date_to);
        $config = vy_get_org_tax_config($org_id);
        $profit = $profitSummary['profit'];
        $rate = $config['income_tax_rate'];
        $estimate = max($profit, 0) * ($rate / 100);

        return [
            'profit'              => $profit,
            'income_tax_rate'     => $rate,
            'estimated_income_tax'=> $estimate,
        ];
    }
}
