<?php

namespace KBS\Accounting;

use WP_Error;

defined('ABSPATH') || exit;

class VyJournalEngine
{
    private static function entries_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vy_journal_entries';
    }

    private static function lines_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vy_journal_lines';
    }

    public static function create_journal_entry(array $args)
    {
        global $wpdb;

        $org_id = isset($args['org_id']) ? (int) $args['org_id'] : 0;
        $lines  = $args['lines'] ?? [];
        if ($org_id <= 0) {
            return new WP_Error('vy_journal_bad_org', 'Missing organization for journal entry.');
        }
        if (!is_array($lines) || count($lines) < 2) {
            return new WP_Error('vy_journal_bad_lines', 'Journal entry requires at least two lines.');
        }

        $totDebit = 0;
        $totCredit = 0;
        foreach ($lines as $line) {
            $acct = isset($line['account_id']) ? (int) $line['account_id'] : 0;
            if ($acct <= 0) {
                return new WP_Error('vy_journal_bad_account', 'Journal line missing account.');
            }
            $debit = isset($line['debit']) ? (float) $line['debit'] : 0;
            $credit = isset($line['credit']) ? (float) $line['credit'] : 0;
            if ($debit < 0 || $credit < 0) {
                return new WP_Error('vy_journal_bad_amount', 'Debit/Credit cannot be negative.');
            }
            $totDebit  += $debit;
            $totCredit += $credit;
        }
        if (round($totDebit, 2) !== round($totCredit, 2)) {
            return new WP_Error('vy_journal_unbalanced', 'Journal entry must balance debits and credits.');
        }

        $entry = [
            'org_id'        => $org_id,
            'entry_date'    => $args['date'] ?? gmdate('Y-m-d'),
            'type'          => sanitize_text_field($args['type'] ?? 'GENERAL'),
            'description'   => wp_kses_post($args['description'] ?? ''),
            'reference'     => isset($args['reference']) ? sanitize_text_field($args['reference']) : null,
            'source_module' => isset($args['source_module']) ? sanitize_text_field($args['source_module']) : null,
            'source_id'     => isset($args['source_id']) ? (int) $args['source_id'] : null,
            'created_at'    => current_time('mysql', true),
        ];

        if ($entry['reference'] === null) {
            unset($entry['reference']);
        }
        if ($entry['source_module'] === null) {
            unset($entry['source_module']);
        }
        if ($entry['source_id'] === null) {
            unset($entry['source_id']);
        }

        $inserted = $wpdb->insert(self::entries_table(), $entry);
        if ($inserted === false) {
            return new WP_Error('vy_journal_insert_failed', 'Failed to record journal entry.');
        }
        $journal_id = (int) $wpdb->insert_id;

        foreach ($lines as $line) {
            $wpdb->insert(
                self::lines_table(),
                [
                    'journal_id' => $journal_id,
                    'org_id'     => $org_id,
                    'account_id' => (int) $line['account_id'],
                    'debit'      => isset($line['debit']) ? (float) $line['debit'] : 0,
                    'credit'     => isset($line['credit']) ? (float) $line['credit'] : 0,
                    'line_memo'  => sanitize_text_field($line['line_memo'] ?? ''),
                    'created_at' => current_time('mysql', true),
                ],
                ['%d','%d','%d','%f','%f','%s','%s']
            );
        }

        return $journal_id;
    }

    public static function get_account_balance(int $org_id, int $account_id, ?array $accountRow = null): float
    {
        $accountMeta = $accountRow ?? self::get_account_meta($org_id, $account_id);
        $type = strtoupper($accountMeta['type'] ?? 'ASSET');

        $opening = self::get_opening_component($org_id, $account_id, $accountMeta);
        $totals = self::get_journal_totals($org_id, $account_id);
        $raw = $opening + ($totals['debit'] - $totals['credit']);

        return round(self::apply_account_nature($type, $raw), 2);
    }

    public static function get_account_statement(
        int $org_id,
        int $account_id,
        ?string $date_from = null,
        ?string $date_to = null,
        int $page = 1,
        int $per_page = 50
    ): array {
        global $wpdb;

        $page = max(1, $page);
        $per_page = min(200, max(1, $per_page));

        $conditions = ['l.org_id = %d', 'l.account_id = %d'];
        $params = [$org_id, $account_id];

        if ($date_from) {
            $conditions[] = 'e.entry_date >= %s';
            $params[] = $date_from;
        }
        if ($date_to) {
            $conditions[] = 'e.entry_date <= %s';
            $params[] = $date_to;
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT l.id, l.journal_id, l.debit, l.credit, l.line_memo,
                   e.entry_date, e.type, e.description
            FROM " . self::lines_table() . " l
            INNER JOIN " . self::entries_table() . " e ON e.id = l.journal_id
            WHERE {$where}
            ORDER BY e.entry_date ASC, l.id ASC
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $accountMeta = self::get_account_meta($org_id, $account_id);
        $type = strtoupper($accountMeta['type'] ?? 'ASSET');

        $openingBase = self::get_opening_component($org_id, $account_id, $accountMeta);
        $openingRaw = $date_from ? self::balance_before_date($org_id, $account_id, $date_from) : $openingBase;
        $offset = ($page - 1) * $per_page;

        $runningRaw = $openingRaw;
        $lines = [];
        foreach ($rows as $index => $row) {
            $runningRaw += (float) $row['debit'] - (float) $row['credit'];
            if ($index < $offset || count($lines) >= $per_page) {
                continue;
            }
            $lines[] = [
                'journal_id' => (int) $row['journal_id'],
                'date'       => $row['entry_date'],
                'type'       => $row['type'],
                'description'=> $row['description'] ?: $row['line_memo'],
                'debit'      => round((float) $row['debit'], 2),
                'credit'     => round((float) $row['credit'], 2),
                'balance'    => round(self::apply_account_nature($type, $runningRaw), 2),
            ];
        }

        return [
            'lines'           => $lines,
            'opening_balance' => round(self::apply_account_nature($type, $openingRaw), 2),
            'page'            => $page,
            'per_page'        => $per_page,
            'total'           => count($rows),
        ];
    }

    private static function balance_before_date(int $org_id, int $account_id, string $date): float
    {
        $opening = self::get_opening_component($org_id, $account_id);
        $totals = self::get_journal_totals($org_id, $account_id, $date);
        return round($opening + ($totals['debit'] - $totals['credit']), 2);
    }

    private static function get_journal_totals(int $org_id, int $account_id, ?string $beforeDate = null): array
    {
        global $wpdb;
        $conditions = ['l.org_id = %d', 'l.account_id = %d'];
        $params = [$org_id, $account_id];
        if ($beforeDate !== null) {
            $conditions[] = 'e.entry_date < %s';
            $params[] = $beforeDate;
        }
        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT COALESCE(SUM(l.debit),0) AS debit, COALESCE(SUM(l.credit),0) AS credit
            FROM " . self::lines_table() . " l
            INNER JOIN " . self::entries_table() . " e ON e.id = l.journal_id
            WHERE {$where}
        ";
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params));
        return [
            'debit' => $row ? (float) $row->debit : 0.0,
            'credit' => $row ? (float) $row->credit : 0.0,
        ];
    }

    private static function get_opening_component(int $org_id, int $account_id, ?array $accountRow = null): float
    {
        static $cache = [];
        $key = "{$org_id}:{$account_id}";
        if ($accountRow === null && isset($cache[$key])) {
            return $cache[$key];
        }

        if ($accountRow === null) {
            $accountRow = self::get_account_meta($org_id, $account_id);
        }

        $opening = 0.0;
        if ($accountRow) {
            $amount = (float) ($accountRow['opening_balance'] ?? 0);
            $type = strtoupper($accountRow['opening_balance_type'] ?? 'DEBIT');
            $opening = $type === 'CREDIT' ? -1 * $amount : $amount;
        }

        $cache[$key] = $opening;
        return $opening;
    }

    private static function get_account_meta(int $org_id, int $account_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, type, sub_type, opening_balance, opening_balance_type
             FROM {$wpdb->prefix}vy_accounts
             WHERE org_id = %d AND id = %d
             LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function apply_account_nature(string $type, float $value): float
    {
        return self::is_credit_nature($type) ? ($value * -1) : $value;
    }

    private static function is_credit_nature(string $type): bool
    {
        return in_array(strtoupper($type), ['INCOME', 'LIABILITY', 'EQUITY'], true);
    }

    public static function get_income_total(int $org_id): float
    {
        return self::sum_accounts_by_types($org_id, ['INCOME']);
    }

    public static function get_expense_total(int $org_id): float
    {
        return self::sum_accounts_by_types($org_id, ['EXPENSE']);
    }

    public static function get_profit_summary(int $org_id): array
    {
        $income = self::get_income_total($org_id);
        $expense = self::get_expense_total($org_id);
        return [
            'income'  => $income,
            'expense' => $expense,
            'profit'  => $income - $expense,
        ];
    }

    private static function sum_accounts_by_types(int $org_id, array $types): float
    {
        if (!$types) {
            return 0.0;
        }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "
            SELECT id, type, opening_balance, opening_balance_type
            FROM {$wpdb->prefix}vy_accounts
            WHERE org_id = %d AND type IN ({$placeholders})
        ";
        $params = array_merge([$org_id], $types);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (!$rows) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += self::get_account_balance($org_id, (int) $row['id'], $row);
        }
        return $sum;
    }
}
