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
            'output_tax'      => $output_tax,
            'input_tax'       => $input_tax,
            'net_gst_payable' => $output_tax - $input_tax,
        ];
    }
}

if (!function_exists('vy_get_org_tax_config')) {
    function vy_get_org_tax_config(int $org_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_organizations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT default_income_tax_rate, is_gst_registered FROM {$table} WHERE org_id = %d LIMIT 1",
            $org_id
        ), ARRAY_A);

        return [
            'income_tax_rate' => isset($row['default_income_tax_rate']) ? (float) $row['default_income_tax_rate'] : 25.0,
            'is_gst_registered' => isset($row['is_gst_registered']) ? (bool) $row['is_gst_registered'] : true,
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
