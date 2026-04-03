<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_expense_value')) {
    function vy_expense_value($expense, string $key, $default = null)
    {
        if (is_array($expense)) {
            return array_key_exists($key, $expense) ? $expense[$key] : $default;
        }

        if (is_object($expense) && property_exists($expense, $key)) {
            return $expense->{$key};
        }

        return $default;
    }
}

if (!function_exists('vy_expense_edit_state')) {
    function vy_expense_edit_state($expense): array
    {
        $status = strtoupper((string) vy_expense_value($expense, 'status', 'POSTED'));
        $journalId = (int) vy_expense_value($expense, 'payment_journal_id', vy_expense_value($expense, 'journal_id', 0));

        if ($status === 'ARCHIVED') {
            return [
                'can_edit'            => false,
                'can_archive'         => false,
                'edit_reason'         => 'Archived expenses can no longer be edited.',
                'archive_reason'      => 'This expense is already archived.',
            ];
        }

        if ($journalId > 0) {
            return [
                'can_edit'            => false,
                'can_archive'         => false,
                'edit_reason'         => 'Expenses with recorded payment journals can no longer be edited.',
                'archive_reason'      => 'Expenses with recorded payment journals cannot be archived.',
            ];
        }

        return [
            'can_edit'        => true,
            'can_archive'     => true,
            'edit_reason'     => null,
            'archive_reason'  => null,
        ];
    }
}
