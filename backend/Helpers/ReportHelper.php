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
            "SELECT id, contact_id, invoice_number, customer_name, customer_email, date, due_date, total, status, email_sent_at
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
            "SELECT id, contact_id, document_type, expense_date, due_date, reference_number, payee, amount, currency, status, payment_journal_id
             FROM {$wpdb->prefix}vy_expenses
             WHERE org_id = %d
             ORDER BY expense_date ASC, id ASC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_fetch_due_recurring_profile_rows')) {
    function vy_fetch_due_recurring_profile_rows(int $org_id, string $as_of): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_recurring_profiles
             WHERE status = 'ACTIVE'
               AND next_run_date IS NOT NULL
               AND next_run_date <= %s
               AND org_id = %d
             ORDER BY next_run_date ASC, id ASC
             LIMIT 25",
            $as_of,
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_fetch_report_recurring_items')) {
    function vy_fetch_report_recurring_items(int $org_id, int $profile_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT description, quantity, unit_price, tax_rate, tax_amount, tax_type, line_total
             FROM {$wpdb->prefix}vy_invoice_recurring_items
             WHERE org_id = %d AND profile_id = %d
             ORDER BY id ASC",
            $org_id,
            $profile_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_payables_bucket_label')) {
    function vy_payables_bucket_label(string $bucket): string
    {
        return vy_receivables_bucket_label($bucket);
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

if (!function_exists('vy_get_payables_summary')) {
    function vy_get_payables_summary(int $org_id, string $date_from, string $date_to, ?string $as_of = null): array
    {
        $as_of = vy_normalize_report_date($as_of);
        $as_of_time = strtotime($as_of) ?: time();

        $status_summary = [
            'OPEN'     => ['status' => 'OPEN', 'count' => 0, 'amount' => 0.0],
            'PAID'     => ['status' => 'PAID', 'count' => 0, 'amount' => 0.0],
            'ARCHIVED' => ['status' => 'ARCHIVED', 'count' => 0, 'amount' => 0.0],
        ];

        $aging_buckets = [
            'current' => ['bucket' => 'current', 'label' => vy_payables_bucket_label('current'), 'count' => 0, 'amount' => 0.0],
            '1_30'    => ['bucket' => '1_30', 'label' => vy_payables_bucket_label('1_30'), 'count' => 0, 'amount' => 0.0],
            '31_60'   => ['bucket' => '31_60', 'label' => vy_payables_bucket_label('31_60'), 'count' => 0, 'amount' => 0.0],
            '61_90'   => ['bucket' => '61_90', 'label' => vy_payables_bucket_label('61_90'), 'count' => 0, 'amount' => 0.0],
            '91_plus' => ['bucket' => '91_plus', 'label' => vy_payables_bucket_label('91_plus'), 'count' => 0, 'amount' => 0.0],
        ];

        $vendor_balances = [];
        $open_bill_count = 0;
        $outstanding_amount = 0.0;
        $overdue_amount = 0.0;
        $overdue_count = 0;
        $due_today_amount = 0.0;
        $due_today_count = 0;
        $upcoming_amount = 0.0;
        $upcoming_count = 0;
        $overdue_days_total = 0.0;

        foreach (vy_fetch_report_expense_rows($org_id) as $expense) {
            $document_type = strtoupper(trim((string) ($expense['document_type'] ?? 'EXPENSE')));
            if ($document_type !== 'BILL') {
                continue;
            }

            $expense_date = vy_normalize_report_date($expense['expense_date'] ?? null, $date_from);
            $status = strtoupper(trim((string) ($expense['status'] ?? 'POSTED')));
            $payment_journal_id = !empty($expense['payment_journal_id']) ? (int) $expense['payment_journal_id'] : 0;
            $amount = (float) ($expense['amount'] ?? 0);
            $workflow_status = $status === 'ARCHIVED' ? 'ARCHIVED' : ($payment_journal_id > 0 ? 'PAID' : 'OPEN');

            if ($expense_date >= $date_from && $expense_date <= $date_to) {
                $status_summary[$workflow_status]['count']++;
                $status_summary[$workflow_status]['amount'] += $amount;
            }

            if ($status === 'ARCHIVED' || $payment_journal_id > 0 || $expense_date > $as_of) {
                continue;
            }

            $open_bill_count++;
            $outstanding_amount += $amount;

            $due_date = vy_normalize_report_date($expense['due_date'] ?? null, $expense_date);
            $due_time = strtotime($due_date) ?: $as_of_time;
            $days_overdue = (int) floor(($as_of_time - $due_time) / 86400);
            $is_overdue = $days_overdue > 0;

            if ($is_overdue) {
                $overdue_amount += $amount;
                $overdue_count++;
                $overdue_days_total += $days_overdue;
            } elseif ($due_date === $as_of) {
                $due_today_amount += $amount;
                $due_today_count++;
            } else {
                $upcoming_amount += $amount;
                $upcoming_count++;
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
            $aging_buckets[$bucket_key]['amount'] += $amount;

            $vendor_name = trim((string) ($expense['payee'] ?? ''));
            $vendor_key = $vendor_name !== '' ? strtolower($vendor_name) : 'vendor-' . (int) ($expense['contact_id'] ?? $expense['id'] ?? 0);
            if (!isset($vendor_balances[$vendor_key])) {
                $vendor_balances[$vendor_key] = [
                    'label' => $vendor_name !== '' ? $vendor_name : 'Vendor',
                    'bill_count' => 0,
                    'outstanding_amount' => 0.0,
                    'overdue_amount' => 0.0,
                ];
            }

            $vendor_balances[$vendor_key]['bill_count']++;
            $vendor_balances[$vendor_key]['outstanding_amount'] += $amount;
            if ($is_overdue) {
                $vendor_balances[$vendor_key]['overdue_amount'] += $amount;
            }
        }

        $top_vendors = array_values($vendor_balances);
        usort($top_vendors, static function (array $left, array $right): int {
            return $right['outstanding_amount'] <=> $left['outstanding_amount'];
        });
        $top_vendors = array_slice($top_vendors, 0, 5);

        return [
            'from' => $date_from,
            'to' => $date_to,
            'as_of' => $as_of,
            'open_bill_count' => $open_bill_count,
            'outstanding_amount' => round($outstanding_amount, 2),
            'overdue_amount' => round($overdue_amount, 2),
            'overdue_count' => $overdue_count,
            'due_today_amount' => round($due_today_amount, 2),
            'due_today_count' => $due_today_count,
            'upcoming_amount' => round($upcoming_amount, 2),
            'upcoming_count' => $upcoming_count,
            'average_days_overdue' => $overdue_count > 0 ? round($overdue_days_total / $overdue_count, 1) : 0.0,
            'aging_buckets' => array_values($aging_buckets),
            'bill_status' => array_values(array_map(static function (array $row): array {
                $row['amount'] = round((float) $row['amount'], 2);
                return $row;
            }, $status_summary)),
            'top_vendors' => array_map(static function (array $row): array {
                $row['outstanding_amount'] = round((float) $row['outstanding_amount'], 2);
                $row['overdue_amount'] = round((float) $row['overdue_amount'], 2);
                return $row;
            }, $top_vendors),
        ];
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

if (!function_exists('vy_fetch_report_promise_rows')) {
    function vy_fetch_report_promise_rows(int $org_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_promises
             WHERE org_id = %d
             ORDER BY promised_date ASC, id DESC",
            $org_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('vy_build_revenue_leak_record')) {
    function vy_build_revenue_leak_record(
        string $record_type,
        int $record_id,
        string $label,
        ?string $secondary_label,
        float $amount,
        string $status,
        string $date,
        int $days_open,
        string $detail,
        string $link_path
    ): array {
        return [
            'record_type' => $record_type,
            'record_id' => $record_id,
            'label' => $label,
            'secondary_label' => $secondary_label,
            'amount' => round($amount, 2),
            'status' => $status,
            'date' => $date,
            'days_open' => max(0, $days_open),
            'detail' => $detail,
            'link_path' => $link_path,
        ];
    }
}

if (!function_exists('vy_build_revenue_leak_alert')) {
    function vy_build_revenue_leak_alert(
        string $key,
        string $label,
        string $severity,
        string $detail,
        string $action,
        array $records
    ): array {
        usort($records, static function (array $left, array $right): int {
            if ((int) ($right['days_open'] ?? 0) !== (int) ($left['days_open'] ?? 0)) {
                return ((int) ($right['days_open'] ?? 0)) <=> ((int) ($left['days_open'] ?? 0));
            }

            return ((float) ($right['amount'] ?? 0)) <=> ((float) ($left['amount'] ?? 0));
        });

        $amount = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $records));
        $top_records = array_slice($records, 0, 5);

        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'detail' => $detail,
            'action' => $action,
            'record_count' => count($records),
            'estimated_amount' => round($amount, 2),
            'records' => $top_records,
            'remaining_count' => max(0, count($records) - count($top_records)),
        ];
    }
}

if (!function_exists('vy_get_revenue_leak_detector')) {
    function vy_get_revenue_leak_detector(int $org_id, string $date_from, string $date_to, ?string $as_of = null): array
    {
        $as_of = vy_normalize_report_date($as_of, $date_to);
        $as_of_time = strtotime($as_of) ?: time();

        $invoices = vy_fetch_report_invoice_rows($org_id);
        $payments = vy_fetch_report_payment_rows($org_id);
        $promises = vy_fetch_report_promise_rows($org_id);
        $note_totals_map = vy_invoice_note_totals_map($org_id);

        $payments_by_invoice = [];
        foreach ($payments as $payment) {
            $invoice_id = (int) ($payment['invoice_id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            $payments_by_invoice[$invoice_id] = ($payments_by_invoice[$invoice_id] ?? 0.0) + (float) ($payment['amount'] ?? 0);
        }

        $invoice_map = [];
        foreach ($invoices as $invoice) {
            $invoice_id = (int) ($invoice['id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            $invoice_date = vy_normalize_report_date($invoice['date'] ?? null, $as_of);
            $due_date = vy_normalize_report_date($invoice['due_date'] ?? null, $invoice_date);
            $status = strtoupper((string) ($invoice['status'] ?? 'SENT'));
            $financials = vy_invoice_apply_adjustments(
                ['id' => $invoice_id, 'total' => (float) ($invoice['total'] ?? 0)],
                (float) ($payments_by_invoice[$invoice_id] ?? 0.0),
                $note_totals_map[$invoice_id] ?? null
            );

            $invoice_map[$invoice_id] = [
                'id' => $invoice_id,
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ('Invoice #' . $invoice_id)),
                'customer_name' => trim((string) ($invoice['customer_name'] ?? '')) ?: 'Customer',
                'customer_email' => sanitize_email((string) ($invoice['customer_email'] ?? '')) ?: null,
                'invoice_date' => $invoice_date,
                'due_date' => $due_date,
                'status' => $status,
                'email_sent_at' => (string) ($invoice['email_sent_at'] ?? ''),
                'adjusted_total' => round((float) ($financials['adjusted_total'] ?? ($invoice['total'] ?? 0)), 2),
                'balance_due' => round((float) ($financials['balance_due'] ?? 0), 2),
            ];
        }

        $open_promises_by_invoice = [];
        $broken_promise_records = [];
        foreach ($promises as $promise) {
            if (strtoupper((string) ($promise['status'] ?? 'OPEN')) !== 'OPEN') {
                continue;
            }

            $invoice_id = (int) ($promise['invoice_id'] ?? 0);
            if ($invoice_id <= 0) {
                continue;
            }

            $open_promises_by_invoice[$invoice_id][] = $promise;
            $promised_date = vy_normalize_report_date($promise['promised_date'] ?? null, $as_of);
            if ($promised_date >= $as_of) {
                continue;
            }

            $invoice = $invoice_map[$invoice_id] ?? null;
            $days_open = (int) floor(($as_of_time - (strtotime($promised_date) ?: $as_of_time)) / DAY_IN_SECONDS);
            $promised_amount = round((float) ($promise['promised_amount'] ?? 0), 2);
            $balance_due = round((float) ($invoice['balance_due'] ?? $promised_amount), 2);

            $broken_promise_records[] = vy_build_revenue_leak_record(
                'promise',
                (int) ($promise['id'] ?? 0),
                (string) ($invoice['invoice_number'] ?? ('Invoice #' . $invoice_id)),
                $invoice ? (string) ($invoice['customer_name'] ?? 'Customer') : null,
                $balance_due > 0 ? $balance_due : $promised_amount,
                'OPEN',
                $promised_date,
                $days_open,
                sprintf(
                    'Payment promise for ₹ %s expired on %s and still remains open.',
                    number_format($promised_amount, 2, '.', ''),
                    $promised_date
                ),
                '/payments'
            );
        }

        $missed_recurring_records = [];
        foreach (vy_fetch_due_recurring_profile_rows($org_id, $as_of) as $profile) {
            $profile_id = (int) ($profile['id'] ?? 0);
            if ($profile_id <= 0) {
                continue;
            }

            $next_run_date = vy_normalize_report_date($profile['next_run_date'] ?? null, $as_of);
            $days_open = (int) floor(($as_of_time - (strtotime($next_run_date) ?: $as_of_time)) / DAY_IN_SECONDS);
            $estimated_total = 0.0;
            foreach (vy_fetch_report_recurring_items($org_id, $profile_id) as $item) {
                $estimated_total += (float) ($item['line_total'] ?? 0);
            }

            if ($estimated_total <= 0 && !empty($profile['source_invoice_id']) && isset($invoice_map[(int) $profile['source_invoice_id']])) {
                $estimated_total = (float) ($invoice_map[(int) $profile['source_invoice_id']]['adjusted_total'] ?? 0);
            }

            $missed_recurring_records[] = vy_build_revenue_leak_record(
                'recurring_profile',
                $profile_id,
                trim((string) ($profile['profile_name'] ?? ('Recurring Plan #' . $profile_id))),
                trim((string) ($profile['customer_name'] ?? '')) ?: null,
                $estimated_total,
                (string) ($profile['status'] ?? 'ACTIVE'),
                $next_run_date,
                $days_open,
                sprintf(
                    'Recurring plan was due on %s and has not generated its next invoice yet.',
                    $next_run_date
                ),
                '/invoices'
            );
        }

        $overdue_unpromised_records = [];
        $unemailed_records = [];
        $stale_draft_records = [];

        foreach ($invoice_map as $invoice) {
            $invoice_date = (string) ($invoice['invoice_date'] ?? $as_of);
            $due_date = (string) ($invoice['due_date'] ?? $invoice_date);
            $status = strtoupper((string) ($invoice['status'] ?? 'SENT'));
            $balance_due = (float) ($invoice['balance_due'] ?? 0);
            $adjusted_total = (float) ($invoice['adjusted_total'] ?? 0);
            $invoice_id = (int) ($invoice['id'] ?? 0);

            $invoice_age_days = (int) floor(($as_of_time - (strtotime($invoice_date) ?: $as_of_time)) / DAY_IN_SECONDS);
            $due_age_days = (int) floor(($as_of_time - (strtotime($due_date) ?: $as_of_time)) / DAY_IN_SECONDS);

            if ($status === 'DRAFT' && $adjusted_total > 0 && $invoice_date <= $as_of && $invoice_age_days >= 3) {
                $stale_draft_records[] = vy_build_revenue_leak_record(
                    'invoice',
                    $invoice_id,
                    (string) ($invoice['invoice_number'] ?? ('Invoice #' . $invoice_id)),
                    (string) ($invoice['customer_name'] ?? 'Customer'),
                    $adjusted_total,
                    'DRAFT',
                    $invoice_date,
                    $invoice_age_days,
                    sprintf('Draft invoice has been sitting since %s without being finalized or sent.', $invoice_date),
                    '/invoices/' . $invoice_id
                );
            }

            if ($status === 'SENT' && $balance_due > 0 && empty($invoice['email_sent_at']) && $invoice_date < $as_of && $invoice_age_days >= 1) {
                $detail = sprintf('Invoice is marked sent from %s but no email delivery has been recorded yet.', $invoice_date);
                if (empty($invoice['customer_email'])) {
                    $detail .= ' Customer email is also missing.';
                }

                $unemailed_records[] = vy_build_revenue_leak_record(
                    'invoice',
                    $invoice_id,
                    (string) ($invoice['invoice_number'] ?? ('Invoice #' . $invoice_id)),
                    (string) ($invoice['customer_name'] ?? 'Customer'),
                    $balance_due,
                    'SENT',
                    $invoice_date,
                    $invoice_age_days,
                    $detail,
                    '/invoices/' . $invoice_id
                );
            }

            if (in_array($status, ['SENT', 'PARTIAL'], true) && $balance_due > 0 && $due_date < $as_of && empty($open_promises_by_invoice[$invoice_id])) {
                $overdue_unpromised_records[] = vy_build_revenue_leak_record(
                    'invoice',
                    $invoice_id,
                    (string) ($invoice['invoice_number'] ?? ('Invoice #' . $invoice_id)),
                    (string) ($invoice['customer_name'] ?? 'Customer'),
                    $balance_due,
                    $status,
                    $due_date,
                    $due_age_days,
                    sprintf('Invoice is %d day%s overdue and no open promise to pay is recorded.', $due_age_days, $due_age_days === 1 ? '' : 's'),
                    '/invoices/' . $invoice_id
                );
            }
        }

        $alerts = [];
        $unique_risk_amounts = [];
        $actions = [];

        $push_alert = static function (array $alert, array $records) use (&$alerts, &$unique_risk_amounts, &$actions): void {
            $alerts[] = $alert;
            if (!empty($alert['action'])) {
                $actions[$alert['action']] = $alert['action'];
            }
            foreach ($records as $record) {
                $key = (string) ($record['record_type'] ?? 'record') . '-' . (int) ($record['record_id'] ?? 0);
                $amount = round((float) ($record['amount'] ?? 0), 2);
                if (!isset($unique_risk_amounts[$key]) || $amount > $unique_risk_amounts[$key]) {
                    $unique_risk_amounts[$key] = $amount;
                }
            }
        };

        if ($missed_recurring_records) {
            $max_days = max(array_map(static fn(array $row): int => (int) ($row['days_open'] ?? 0), $missed_recurring_records));
            $push_alert(vy_build_revenue_leak_alert(
                'missed_recurring_runs',
                'Recurring invoices waiting to be generated',
                $max_days >= 7 ? 'critical' : 'warning',
                sprintf(
                    '%d recurring plan%s are already due, with an estimated ₹ %s still not invoiced.',
                    count($missed_recurring_records),
                    count($missed_recurring_records) === 1 ? '' : 's',
                    number_format(array_sum(array_column($missed_recurring_records, 'amount')), 2, '.', '')
                ),
                'Generate due recurring plans now or pause outdated plans.',
                $missed_recurring_records
            ), $missed_recurring_records);
        }

        if ($broken_promise_records) {
            $max_days = max(array_map(static fn(array $row): int => (int) ($row['days_open'] ?? 0), $broken_promise_records));
            $push_alert(vy_build_revenue_leak_alert(
                'broken_payment_promises',
                'Broken payment promises still remain open',
                $max_days >= 3 ? 'critical' : 'warning',
                sprintf(
                    '%d promise%s to pay already slipped past the promised date, covering ₹ %s.',
                    count($broken_promise_records),
                    count($broken_promise_records) === 1 ? '' : 's',
                    number_format(array_sum(array_column($broken_promise_records, 'amount')), 2, '.', '')
                ),
                'Follow up on broken payment promises before they slip further.',
                $broken_promise_records
            ), $broken_promise_records);
        }

        if ($overdue_unpromised_records) {
            $max_days = max(array_map(static fn(array $row): int => (int) ($row['days_open'] ?? 0), $overdue_unpromised_records));
            $push_alert(vy_build_revenue_leak_alert(
                'overdue_invoices_without_promises',
                'Overdue invoices have no active promise to pay',
                $max_days >= 30 ? 'critical' : 'warning',
                sprintf(
                    '%d overdue invoice%s worth ₹ %s have no open commitment recorded.',
                    count($overdue_unpromised_records),
                    count($overdue_unpromised_records) === 1 ? '' : 's',
                    number_format(array_sum(array_column($overdue_unpromised_records, 'amount')), 2, '.', '')
                ),
                'Collect overdue invoices that still have no open promise to pay.',
                $overdue_unpromised_records
            ), $overdue_unpromised_records);
        }

        if ($unemailed_records) {
            $push_alert(vy_build_revenue_leak_alert(
                'sent_invoices_without_email',
                'Invoices are marked sent without an email trace',
                'warning',
                sprintf(
                    '%d sent invoice%s worth ₹ %s still have no recorded email delivery.',
                    count($unemailed_records),
                    count($unemailed_records) === 1 ? '' : 's',
                    number_format(array_sum(array_column($unemailed_records, 'amount')), 2, '.', '')
                ),
                'Review unsent invoice emails so customers actually receive the bill.',
                $unemailed_records
            ), $unemailed_records);
        }

        if ($stale_draft_records) {
            $max_days = max(array_map(static fn(array $row): int => (int) ($row['days_open'] ?? 0), $stale_draft_records));
            $push_alert(vy_build_revenue_leak_alert(
                'stale_draft_invoices',
                'Draft invoices are aging without being sent',
                $max_days >= 7 ? 'warning' : 'info',
                sprintf(
                    '%d draft invoice%s worth ₹ %s have been left unfinished for at least three days.',
                    count($stale_draft_records),
                    count($stale_draft_records) === 1 ? '' : 's',
                    number_format(array_sum(array_column($stale_draft_records, 'amount')), 2, '.', '')
                ),
                'Finish or archive stale draft invoices so revenue does not stall in draft form.',
                $stale_draft_records
            ), $stale_draft_records);
        }

        usort($alerts, static function (array $left, array $right): int {
            $rank = ['critical' => 3, 'warning' => 2, 'info' => 1];
            $left_rank = $rank[$left['severity'] ?? 'info'] ?? 0;
            $right_rank = $rank[$right['severity'] ?? 'info'] ?? 0;
            if ($right_rank !== $left_rank) {
                return $right_rank <=> $left_rank;
            }

            return ((float) ($right['estimated_amount'] ?? 0)) <=> ((float) ($left['estimated_amount'] ?? 0));
        });

        $severity_counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($alerts as $alert) {
            $severity = (string) ($alert['severity'] ?? 'info');
            if (isset($severity_counts[$severity])) {
                $severity_counts[$severity]++;
            }
        }

        $status = 'clear';
        if ($severity_counts['critical'] > 0) {
            $status = 'critical';
        } elseif ($severity_counts['warning'] > 0) {
            $status = 'watch';
        } elseif ($severity_counts['info'] > 0) {
            $status = 'steady';
        }

        $headline = match ($status) {
            'critical' => 'Revenue is leaking through overdue recurring, broken promise, or overdue invoice gaps.',
            'watch' => 'Several revenue leaks need follow-up before they turn into missed cash.',
            'steady' => 'A few low-grade revenue leaks still need cleanup.',
            default => 'No grounded revenue leak signals were found right now.',
        };

        return [
            'from' => $date_from,
            'to' => $date_to,
            'as_of' => $as_of,
            'status' => $status,
            'headline' => $headline,
            'alert_count' => count($alerts),
            'affected_record_count' => count($unique_risk_amounts),
            'estimated_amount' => round(array_sum($unique_risk_amounts), 2),
            'severity_counts' => $severity_counts,
            'actions' => array_values($actions),
            'alerts' => $alerts,
        ];
    }
}

if (!function_exists('vy_build_health_component')) {
    function vy_build_health_component(
        string $key,
        string $label,
        int $points,
        int $max_points,
        string $detail,
        array $metrics = [],
        ?string $action = null
    ): array {
        $status = 'good';
        $ratio = $max_points > 0 ? ($points / $max_points) : 0;
        if ($ratio < 0.35) {
            $status = 'critical';
        } elseif ($ratio < 0.65) {
            $status = 'watch';
        } elseif ($ratio < 1) {
            $status = 'steady';
        }

        return [
            'key' => $key,
            'label' => $label,
            'points' => $points,
            'max_points' => $max_points,
            'status' => $status,
            'detail' => $detail,
            'metrics' => $metrics,
            'action' => $action,
        ];
    }
}

if (!function_exists('vy_score_ratio_band')) {
    function vy_score_ratio_band(?float $ratio, array $thresholds): int
    {
        if ($ratio === null || $ratio <= $thresholds[0]) {
            return 20;
        }
        if ($ratio <= $thresholds[1]) {
            return 16;
        }
        if ($ratio <= $thresholds[2]) {
            return 10;
        }
        if ($ratio <= $thresholds[3]) {
            return 6;
        }
        return 2;
    }
}

if (!function_exists('vy_get_billing_health_score')) {
    function vy_get_billing_health_score(int $org_id, string $date_from, string $date_to, ?string $as_of = null): array
    {
        $as_of = vy_normalize_report_date($as_of, $date_to);
        $receivables = vy_get_receivables_summary($org_id, $date_from, $date_to, $as_of);
        $payables = vy_get_payables_summary($org_id, $date_from, $date_to, $as_of);
        $profit = vy_get_profit_summary($org_id, $date_from, $date_to);
        $promises = vy_fetch_report_promise_rows($org_id);

        $openPromiseCount = 0;
        $openPromiseAmount = 0.0;
        $overduePromiseCount = 0;
        $overduePromiseAmount = 0.0;
        $dueTodayPromiseCount = 0;
        $dueTodayPromiseAmount = 0.0;

        foreach ($promises as $promise) {
            if (strtoupper((string) ($promise['status'] ?? 'OPEN')) !== 'OPEN') {
                continue;
            }

            $openPromiseCount++;
            $amount = round((float) ($promise['promised_amount'] ?? 0), 2);
            $openPromiseAmount += $amount;
            $promisedDate = vy_normalize_report_date($promise['promised_date'] ?? null, $as_of);
            if ($promisedDate < $as_of) {
                $overduePromiseCount++;
                $overduePromiseAmount += $amount;
            } elseif ($promisedDate === $as_of) {
                $dueTodayPromiseCount++;
                $dueTodayPromiseAmount += $amount;
            }
        }

        $receivableOverdueRatio = (float) ($receivables['outstanding_amount'] ?? 0) > 0
            ? (float) ($receivables['overdue_amount'] ?? 0) / (float) $receivables['outstanding_amount']
            : null;
        $payableOverdueRatio = (float) ($payables['outstanding_amount'] ?? 0) > 0
            ? (float) ($payables['overdue_amount'] ?? 0) / (float) $payables['outstanding_amount']
            : null;
        $promiseOverdueRatio = $openPromiseAmount > 0
            ? $overduePromiseAmount / $openPromiseAmount
            : ($openPromiseCount > 0 ? ($overduePromiseCount / $openPromiseCount) : null);

        $incomeTotal = (float) ($profit['income_total'] ?? 0);
        $expenseTotal = (float) ($profit['expense_total'] ?? 0);
        $profitTotal = (float) ($profit['profit'] ?? 0);
        $profitMargin = $incomeTotal > 0 ? ($profitTotal / $incomeTotal) : null;

        $collectionsPoints = (float) ($receivables['outstanding_amount'] ?? 0) <= 0
            ? 20
            : vy_score_ratio_band($receivableOverdueRatio, [0.10, 0.25, 0.50, 0.75]);
        $payablesPoints = (float) ($payables['outstanding_amount'] ?? 0) <= 0
            ? 20
            : vy_score_ratio_band($payableOverdueRatio, [0.05, 0.15, 0.35, 0.60]);
        $promisePoints = $openPromiseCount <= 0
            ? 20
            : vy_score_ratio_band($promiseOverdueRatio, [0.10, 0.25, 0.50, 0.75]);

        if ($incomeTotal <= 0 && $expenseTotal <= 0) {
            $marginPoints = 10;
        } elseif ($incomeTotal <= 0 && $expenseTotal > 0) {
            $marginPoints = 2;
        } elseif ($profitMargin !== null && $profitMargin >= 0.30) {
            $marginPoints = 20;
        } elseif ($profitMargin !== null && $profitMargin >= 0.15) {
            $marginPoints = 16;
        } elseif ($profitMargin !== null && $profitMargin >= 0) {
            $marginPoints = 10;
        } elseif ($profitMargin !== null && $profitMargin >= -0.10) {
            $marginPoints = 6;
        } else {
            $marginPoints = 2;
        }

        $attentionLoad = (int) ($receivables['overdue_count'] ?? 0)
            + (int) ($payables['overdue_count'] ?? 0)
            + (int) ($payables['due_today_count'] ?? 0)
            + $overduePromiseCount
            + $dueTodayPromiseCount;
        if ($attentionLoad <= 0) {
            $attentionPoints = 20;
        } elseif ($attentionLoad <= 2) {
            $attentionPoints = 16;
        } elseif ($attentionLoad <= 4) {
            $attentionPoints = 10;
        } elseif ($attentionLoad <= 6) {
            $attentionPoints = 6;
        } else {
            $attentionPoints = 2;
        }

        $components = [
            vy_build_health_component(
                'collections_control',
                'Collections Control',
                $collectionsPoints,
                20,
                (float) ($receivables['outstanding_amount'] ?? 0) > 0
                    ? sprintf(
                        '₹ %.2f overdue across %d of %d open invoice%s.',
                        (float) ($receivables['overdue_amount'] ?? 0),
                        (int) ($receivables['overdue_count'] ?? 0),
                        (int) ($receivables['open_invoice_count'] ?? 0),
                        ((int) ($receivables['open_invoice_count'] ?? 0)) === 1 ? '' : 's'
                    )
                    : 'No customer receivables are currently open.',
                [
                    'outstanding_amount' => round((float) ($receivables['outstanding_amount'] ?? 0), 2),
                    'overdue_amount' => round((float) ($receivables['overdue_amount'] ?? 0), 2),
                    'overdue_count' => (int) ($receivables['overdue_count'] ?? 0),
                ],
                (int) ($receivables['overdue_count'] ?? 0) > 0 ? 'Collect overdue invoices before issuing more credit.' : null
            ),
            vy_build_health_component(
                'payables_control',
                'Vendor Payment Control',
                $payablesPoints,
                20,
                (float) ($payables['outstanding_amount'] ?? 0) > 0
                    ? sprintf(
                        '₹ %.2f overdue across %d of %d open vendor bill%s.',
                        (float) ($payables['overdue_amount'] ?? 0),
                        (int) ($payables['overdue_count'] ?? 0),
                        (int) ($payables['open_bill_count'] ?? 0),
                        ((int) ($payables['open_bill_count'] ?? 0)) === 1 ? '' : 's'
                    )
                    : 'No open vendor bills need payment right now.',
                [
                    'outstanding_amount' => round((float) ($payables['outstanding_amount'] ?? 0), 2),
                    'overdue_amount' => round((float) ($payables['overdue_amount'] ?? 0), 2),
                    'overdue_count' => (int) ($payables['overdue_count'] ?? 0),
                    'due_today_count' => (int) ($payables['due_today_count'] ?? 0),
                ],
                ((int) ($payables['overdue_count'] ?? 0) > 0 || (int) ($payables['due_today_count'] ?? 0) > 0)
                    ? 'Prioritize overdue or due-today vendor bills to protect supplier relationships.'
                    : null
            ),
            vy_build_health_component(
                'promise_reliability',
                'Promise Reliability',
                $promisePoints,
                20,
                $openPromiseCount > 0
                    ? sprintf(
                        '%d of %d open promise%s are overdue, worth ₹ %.2f.',
                        $overduePromiseCount,
                        $openPromiseCount,
                        $openPromiseCount === 1 ? '' : 's',
                        $overduePromiseAmount
                    )
                    : 'No open payment promises require follow-up.',
                [
                    'open_count' => $openPromiseCount,
                    'open_amount' => round($openPromiseAmount, 2),
                    'overdue_count' => $overduePromiseCount,
                    'overdue_amount' => round($overduePromiseAmount, 2),
                    'due_today_count' => $dueTodayPromiseCount,
                    'due_today_amount' => round($dueTodayPromiseAmount, 2),
                ],
                $overduePromiseCount > 0 ? 'Follow up on broken payment commitments already recorded in promises to pay.' : null
            ),
            vy_build_health_component(
                'margin_health',
                'Operating Margin',
                $marginPoints,
                20,
                $incomeTotal > 0
                    ? sprintf(
                        'Profit is ₹ %.2f on income of ₹ %.2f for the selected period.',
                        $profitTotal,
                        $incomeTotal
                    )
                    : ($expenseTotal > 0 ? 'Expenses were posted without matching income in the selected period.' : 'No income or expense activity in the selected period.'),
                [
                    'income_total' => round($incomeTotal, 2),
                    'expense_total' => round($expenseTotal, 2),
                    'profit' => round($profitTotal, 2),
                    'margin_ratio' => $profitMargin !== null ? round($profitMargin, 4) : null,
                ],
                $profitTotal < 0 ? 'Review current-period spending or boost collections to restore profitability.' : null
            ),
            vy_build_health_component(
                'attention_load',
                'Today’s Attention Load',
                $attentionPoints,
                20,
                $attentionLoad > 0
                    ? sprintf('%d urgent follow-up item%s are due today or already overdue.', $attentionLoad, $attentionLoad === 1 ? '' : 's')
                    : 'No overdue or due-today follow-ups need immediate attention.',
                [
                    'urgent_count' => $attentionLoad,
                    'receivables_overdue_count' => (int) ($receivables['overdue_count'] ?? 0),
                    'payables_overdue_count' => (int) ($payables['overdue_count'] ?? 0),
                    'payables_due_today_count' => (int) ($payables['due_today_count'] ?? 0),
                    'overdue_promise_count' => $overduePromiseCount,
                    'due_today_promise_count' => $dueTodayPromiseCount,
                ],
                $attentionLoad > 0 ? 'Work today’s due and overdue items before they turn into older debt.' : null
            ),
        ];

        $score = array_sum(array_map(static fn(array $component): int => (int) ($component['points'] ?? 0), $components));
        $status = 'strong';
        if ($score < 50) {
            $status = 'critical';
        } elseif ($score < 70) {
            $status = 'watch';
        } elseif ($score < 85) {
            $status = 'steady';
        }

        $headline = match ($status) {
            'critical' => 'Billing health needs immediate attention.',
            'watch' => 'Billing health is under pressure.',
            'steady' => 'Billing health is stable, with a few areas to tighten.',
            default => 'Billing health is strong today.',
        };

        $actions = [];
        foreach ($components as $component) {
            if (!empty($component['action']) && (int) ($component['points'] ?? 0) < (int) ($component['max_points'] ?? 0)) {
                $actions[$component['action']] = $component['action'];
            }
        }

        return [
            'from' => $date_from,
            'to' => $date_to,
            'as_of' => $as_of,
            'score' => $score,
            'max_score' => 100,
            'status' => $status,
            'headline' => $headline,
            'components' => $components,
            'actions' => array_values($actions),
        ];
    }
}

if (!function_exists('vy_get_owner_daily_brief')) {
    function vy_get_owner_daily_brief(int $org_id, ?string $as_of = null): array
    {
        $as_of = vy_normalize_report_date($as_of);
        $yesterday = (new DateTimeImmutable($as_of))->modify('-1 day')->format('Y-m-d');
        $month_start = (new DateTimeImmutable($as_of))->modify('first day of this month')->format('Y-m-d');

        $payments = vy_fetch_report_payment_rows($org_id);
        $invoices = vy_fetch_report_invoice_rows($org_id);
        $expenses = vy_fetch_report_expense_rows($org_id);
        $promises = vy_fetch_report_promise_rows($org_id);
        $receivables = vy_get_receivables_summary($org_id, $month_start, $as_of, $as_of);
        $payables = vy_get_payables_summary($org_id, $month_start, $as_of, $as_of);
        $noteTotalsMap = vy_invoice_note_totals_map($org_id);

        $collectionsYesterdayAmount = 0.0;
        $collectionsYesterdayCount = 0;
        foreach ($payments as $payment) {
            if (vy_normalize_report_date($payment['date'] ?? null, $yesterday) !== $yesterday) {
                continue;
            }
            $collectionsYesterdayAmount += (float) ($payment['amount'] ?? 0);
            $collectionsYesterdayCount++;
        }

        $paymentsByInvoice = [];
        foreach ($payments as $payment) {
            $invoiceId = (int) ($payment['invoice_id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }
            $paymentsByInvoice[$invoiceId] = ($paymentsByInvoice[$invoiceId] ?? 0.0) + (float) ($payment['amount'] ?? 0);
        }

        $invoicesCreatedYesterdayCount = 0;
        $invoicesCreatedYesterdayAmount = 0.0;
        $dueTodayInvoiceCount = 0;
        $dueTodayInvoiceAmount = 0.0;
        foreach ($invoices as $invoice) {
            $invoiceDate = vy_normalize_report_date($invoice['date'] ?? null, $as_of);
            $status = strtoupper((string) ($invoice['status'] ?? 'SENT'));
            $invoiceId = (int) ($invoice['id'] ?? 0);
            $financials = vy_invoice_apply_adjustments(
                ['id' => $invoiceId, 'total' => (float) ($invoice['total'] ?? 0)],
                (float) ($paymentsByInvoice[$invoiceId] ?? 0.0),
                $noteTotalsMap[$invoiceId] ?? null
            );
            $balanceDue = (float) ($financials['balance_due'] ?? 0);

            if ($invoiceDate === $yesterday && $status !== 'VOID') {
                $invoicesCreatedYesterdayCount++;
                $invoicesCreatedYesterdayAmount += (float) ($financials['adjusted_total'] ?? $invoice['total'] ?? 0);
            }

            if (in_array($status, ['SENT', 'PARTIAL'], true) && $balanceDue > 0) {
                $dueDate = vy_normalize_report_date($invoice['due_date'] ?? null, $invoiceDate);
                if ($dueDate === $as_of) {
                    $dueTodayInvoiceCount++;
                    $dueTodayInvoiceAmount += $balanceDue;
                }
            }
        }

        $expensesYesterdayCount = 0;
        $expensesYesterdayAmount = 0.0;
        foreach ($expenses as $expense) {
            if (strtoupper((string) ($expense['status'] ?? 'POSTED')) === 'ARCHIVED') {
                continue;
            }
            if (vy_normalize_report_date($expense['expense_date'] ?? null, $yesterday) !== $yesterday) {
                continue;
            }
            $expensesYesterdayCount++;
            $expensesYesterdayAmount += (float) ($expense['amount'] ?? 0);
        }

        $openPromisesTodayCount = 0;
        $openPromisesTodayAmount = 0.0;
        $brokenPromisesCount = 0;
        $brokenPromisesAmount = 0.0;
        foreach ($promises as $promise) {
            if (strtoupper((string) ($promise['status'] ?? 'OPEN')) !== 'OPEN') {
                continue;
            }
            $promisedDate = vy_normalize_report_date($promise['promised_date'] ?? null, $as_of);
            $amount = (float) ($promise['promised_amount'] ?? 0);
            if ($promisedDate === $as_of) {
                $openPromisesTodayCount++;
                $openPromisesTodayAmount += $amount;
            } elseif ($promisedDate < $as_of) {
                $brokenPromisesCount++;
                $brokenPromisesAmount += $amount;
            }
        }

        $priorities = [];
        if ($brokenPromisesCount > 0) {
            $priorities[] = sprintf('Follow up on %d overdue promise%s worth ₹ %.2f.', $brokenPromisesCount, $brokenPromisesCount === 1 ? '' : 's', round($brokenPromisesAmount, 2));
        }
        if ((int) ($receivables['overdue_count'] ?? 0) > 0) {
            $priorities[] = sprintf('Collect %d overdue invoice%s worth ₹ %.2f.', (int) ($receivables['overdue_count'] ?? 0), ((int) ($receivables['overdue_count'] ?? 0)) === 1 ? '' : 's', round((float) ($receivables['overdue_amount'] ?? 0), 2));
        }
        if ($dueTodayInvoiceCount > 0) {
            $priorities[] = sprintf('%d invoice%s worth ₹ %.2f become due today.', $dueTodayInvoiceCount, $dueTodayInvoiceCount === 1 ? '' : 's', round($dueTodayInvoiceAmount, 2));
        }
        if ((int) ($payables['due_today_count'] ?? 0) > 0) {
            $priorities[] = sprintf('%d vendor bill%s worth ₹ %.2f need payment today.', (int) ($payables['due_today_count'] ?? 0), ((int) ($payables['due_today_count'] ?? 0)) === 1 ? '' : 's', round((float) ($payables['due_today_amount'] ?? 0), 2));
        }
        if ((int) ($payables['overdue_count'] ?? 0) > 0) {
            $priorities[] = sprintf('%d vendor bill%s are already overdue, worth ₹ %.2f.', (int) ($payables['overdue_count'] ?? 0), ((int) ($payables['overdue_count'] ?? 0)) === 1 ? '' : 's', round((float) ($payables['overdue_amount'] ?? 0), 2));
        }
        if ($collectionsYesterdayCount <= 0 && (int) ($receivables['open_invoice_count'] ?? 0) > 0) {
            $priorities[] = 'No collections were recorded yesterday even though open invoices remain.';
        }
        $priorities = array_slice($priorities, 0, 4);

        return [
            'as_of' => $as_of,
            'yesterday' => $yesterday,
            'summary' => [
                'collections_yesterday_count' => $collectionsYesterdayCount,
                'collections_yesterday_amount' => round($collectionsYesterdayAmount, 2),
                'invoices_created_yesterday_count' => $invoicesCreatedYesterdayCount,
                'invoices_created_yesterday_amount' => round($invoicesCreatedYesterdayAmount, 2),
                'expenses_added_yesterday_count' => $expensesYesterdayCount,
                'expenses_added_yesterday_amount' => round($expensesYesterdayAmount, 2),
                'overdue_invoice_count' => (int) ($receivables['overdue_count'] ?? 0),
                'overdue_invoice_amount' => round((float) ($receivables['overdue_amount'] ?? 0), 2),
                'due_today_invoice_count' => $dueTodayInvoiceCount,
                'due_today_invoice_amount' => round($dueTodayInvoiceAmount, 2),
                'overdue_bill_count' => (int) ($payables['overdue_count'] ?? 0),
                'overdue_bill_amount' => round((float) ($payables['overdue_amount'] ?? 0), 2),
                'due_today_bill_count' => (int) ($payables['due_today_count'] ?? 0),
                'due_today_bill_amount' => round((float) ($payables['due_today_amount'] ?? 0), 2),
                'promises_due_today_count' => $openPromisesTodayCount,
                'promises_due_today_amount' => round($openPromisesTodayAmount, 2),
                'broken_promise_count' => $brokenPromisesCount,
                'broken_promise_amount' => round($brokenPromisesAmount, 2),
            ],
            'priorities' => $priorities,
        ];
    }
}
