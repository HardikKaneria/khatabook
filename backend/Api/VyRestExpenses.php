<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use KBS\Accounting\VyJournalEngine;
use KBS\Core\RecordAuditLogger;
use KBS\Notifications\InternalDocumentNotifier;

defined('ABSPATH') || exit;

class VyRestExpenses
{
    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/expenses', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_expenses'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/expenses', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_expense'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/expenses/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_expense'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/expenses/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_expense'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/expenses/(?P<id>\d+)/archive', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'archive_expense'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function list_expenses(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $table = $wpdb->prefix . 'vy_expenses';

        $where = ['org_id = %d'];
        $params = [$org];

        if ($from = $request->get_param('from')) {
            $where[] = 'expense_date >= %s';
            $params[] = $from;
        }
        if ($to = $request->get_param('to')) {
            $where[] = 'expense_date <= %s';
            $params[] = $to;
        }
        if ($category = $request->get_param('category')) {
            $where[] = 'category = %s';
            $params[] = sanitize_text_field($category);
        }
        $status = strtoupper((string) ($request->get_param('status') ?? ''));
        if ($status === 'ACTIVE') {
            $where[] = "status <> 'ARCHIVED'";
        } elseif ($status === 'ARCHIVED') {
            $where[] = 'status = %s';
            $params[] = 'ARCHIVED';
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY expense_date DESC, id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE " . implode(' AND ', array_slice($where, 0)),
            ...array_slice($params, 0, count($params) - 2)
        ));

        $contactMap = self::prime_contacts((int) $org, $rows ?: []);

        $payload = array_map(fn($row) => self::format_expense_payload((array) $row, $contactMap), $rows ?: []);

        return new WP_REST_Response([
            'data' => $payload,
            'pagination' => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $count,
            ],
        ], 200);
    }

    public static function create_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $body = $request->get_json_params();

        $date   = $body['expense_date'] ?? gmdate('Y-m-d');
        $category = sanitize_text_field($body['category'] ?? '');
        $amount = (float) ($body['amount'] ?? 0);
        if (!$category || $amount <= 0) {
            return new WP_Error('vy_bad_expense', 'Category and positive amount are required.', ['status' => 400]);
        }

        $contactData = self::resolve_vendor_contact((int) $org, $body);
        if (is_wp_error($contactData)) {
            return $contactData;
        }

        $expenseAccount = isset($body['expense_account_id']) ? (int) $body['expense_account_id'] : 0;
        if ($expenseAccount <= 0) {
            $expenseAccount = self::ensure_default_expense_account((int) $org);
        }

        $gstRate = isset($body['gst_rate']) ? max(0, (float) $body['gst_rate']) : 0;
        $gstAmount = $gstRate > 0 ? round($amount * $gstRate / 100, 2) : 0;
        $gstType = sanitize_text_field($body['gst_type'] ?? 'GST');
        $gstEligible = isset($body['is_gst_input_eligible']) ? (int) (bool) $body['is_gst_input_eligible'] : 1;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_expenses',
            [
                'org_id'        => $org,
                'contact_id'    => $contactData['contact_id'],
                'expense_date'  => $date,
                'category'      => $category,
                'payee'         => $contactData['name'],
                'description'   => wp_kses_post($body['description'] ?? ''),
                'amount'        => $amount,
                'currency'      => strtoupper($body['currency'] ?? 'INR'),
                'gst_rate'      => $gstRate,
                'gst_amount'    => $gstAmount,
                'gst_type'      => $gstType,
                'is_gst_input_eligible' => $gstEligible,
                'status'        => 'POSTED',
                'created_at'    => current_time('mysql', true),
                'updated_at'    => current_time('mysql', true),
            ],
            ['%d','%d','%s','%s','%s','%s','%f','%s','%f','%f','%s','%d','%s','%s','%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_expense_insert_failed', 'Failed to create expense.', ['status' => 500]);
        }

        $expense_id = (int) $wpdb->insert_id;
        $journalId = null;

        $payFrom = isset($body['pay_from_account_id']) ? (int) $body['pay_from_account_id'] : 0;
        if ($payFrom > 0) {
            $journal = VyJournalEngine::create_journal_entry([
                'org_id'        => (int) $org,
                'date'          => $date,
                'type'          => 'EXPENSE',
                'description'   => sprintf('%s - %s', $category, $body['description'] ?? ''),
                'source_module' => 'expense',
                'source_id'     => $expense_id,
                'lines'         => [
                    [
                        'account_id' => $expenseAccount,
                        'debit'      => $amount,
                        'credit'     => 0,
                        'line_memo'  => 'Expense',
                    ],
                    [
                        'account_id' => $payFrom,
                        'debit'      => 0,
                        'credit'     => $amount,
                        'line_memo'  => 'Expense payment',
                    ],
                ],
            ]);

            if (is_wp_error($journal)) {
                return $journal;
            }
            $journalId = $journal;

            $wpdb->update(
                $wpdb->prefix . 'vy_expenses',
                ['payment_journal_id' => $journalId, 'updated_at' => current_time('mysql', true)],
                ['id' => $expense_id],
                ['%d','%s'],
                ['%d']
            );
        }

        RecordAuditLogger::log(
            (int) $org,
            'expense',
            $expense_id,
            'created',
            sprintf('Created expense %s', $category),
            [
                'lines' => [
                    'Payee: ' . ($contactData['name'] ?: 'Vendor'),
                    'Amount: ' . RecordAuditLogger::money($amount, strtoupper((string) ($body['currency'] ?? 'INR'))),
                    'GST: ' . ($gstRate > 0 ? rtrim(rtrim(number_format($gstRate, 2, '.', ''), '0'), '.') . '%' : 'No GST'),
                    $journalId ? ('Payment journal: #' . $journalId) : 'Payment journal: not recorded',
                ],
            ]
        );

        try {
            InternalDocumentNotifier::notify_expense_created((int) $org, $expense_id);
        } catch (\Throwable $throwable) {
            error_log(sprintf('[Vyavhar Email] Internal expense notification failed for expense %d: %s', $expense_id, $throwable->getMessage()));
        }

        return new WP_REST_Response(['id' => $expense_id, 'payment_journal_id' => $journalId], 201);
    }

    public static function get_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        return self::get_expense_response((int) $org, (int) $request['id']);
    }

    public static function update_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $expense = self::fetch_expense((int) $org, (int) $request['id']);
        if (!$expense) {
            return new WP_Error('vy_not_found', 'Expense not found.', ['status' => 404]);
        }

        $editState = vy_expense_edit_state($expense);
        if (!$editState['can_edit']) {
            return new WP_Error('vy_expense_locked', $editState['edit_reason'] ?: 'This expense can no longer be edited.', ['status' => 400]);
        }

        global $wpdb;
        $body = $request->get_json_params() ?: [];
        $category = sanitize_text_field($body['category'] ?? $expense['category'] ?? '');
        $amount = array_key_exists('amount', $body) ? (float) $body['amount'] : (float) ($expense['amount'] ?? 0);
        if ($category === '' || $amount <= 0) {
            return new WP_Error('vy_bad_expense', 'Category and positive amount are required.', ['status' => 400]);
        }

        $body['payee'] = sanitize_text_field($body['payee'] ?? ($expense['payee'] ?? ''));
        $body['contact_id'] = array_key_exists('contact_id', $body)
            ? (int) $body['contact_id']
            : (int) ($expense['contact_id'] ?? 0);
        $contactData = self::resolve_vendor_contact((int) $org, $body);
        if (is_wp_error($contactData)) {
            return $contactData;
        }

        $previousExpense = $expense;

        $gstRate = array_key_exists('gst_rate', $body)
            ? max(0, (float) $body['gst_rate'])
            : (float) ($expense['gst_rate'] ?? 0);
        $gstAmount = $gstRate > 0 ? round($amount * $gstRate / 100, 2) : 0.0;
        $gstType = sanitize_text_field($body['gst_type'] ?? ($expense['gst_type'] ?? 'GST'));
        $gstEligible = array_key_exists('is_gst_input_eligible', $body)
            ? (int) (bool) $body['is_gst_input_eligible']
            : (int) ($expense['is_gst_input_eligible'] ?? 1);

        $wpdb->update(
            $wpdb->prefix . 'vy_expenses',
            [
                'contact_id'    => $contactData['contact_id'],
                'expense_date'  => sanitize_text_field($body['expense_date'] ?? ($expense['expense_date'] ?? gmdate('Y-m-d'))),
                'category'      => $category,
                'payee'         => $contactData['name'],
                'description'   => array_key_exists('description', $body)
                    ? wp_kses_post($body['description'] ?? '')
                    : ($expense['description'] ?? ''),
                'amount'        => $amount,
                'currency'      => strtoupper($body['currency'] ?? ($expense['currency'] ?? 'INR')),
                'gst_rate'      => $gstRate,
                'gst_amount'    => $gstAmount,
                'gst_type'      => $gstType,
                'is_gst_input_eligible' => $gstEligible,
                'updated_at'    => current_time('mysql', true),
            ],
            ['org_id' => $org, 'id' => (int) $expense['id']],
            ['%d','%s','%s','%s','%s','%f','%s','%f','%f','%s','%d','%s'],
            ['%d','%d']
        );

        $updatedExpense = array_merge($previousExpense, [
            'contact_id'            => $contactData['contact_id'],
            'expense_date'          => sanitize_text_field($body['expense_date'] ?? ($expense['expense_date'] ?? gmdate('Y-m-d'))),
            'category'              => $category,
            'payee'                 => $contactData['name'],
            'description'           => array_key_exists('description', $body)
                ? wp_kses_post($body['description'] ?? '')
                : ($expense['description'] ?? ''),
            'amount'                => $amount,
            'currency'              => strtoupper($body['currency'] ?? ($expense['currency'] ?? 'INR')),
            'gst_rate'              => $gstRate,
            'gst_amount'            => $gstAmount,
            'gst_type'              => $gstType,
            'is_gst_input_eligible' => $gstEligible,
        ]);

        RecordAuditLogger::log(
            (int) $org,
            'expense',
            (int) $expense['id'],
            'updated',
            sprintf('Updated expense %s', (string) ($expense['category'] ?? '')),
            [
                'lines' => self::build_expense_update_audit_lines($previousExpense, $updatedExpense),
            ]
        );

        return self::get_expense_response((int) $org, (int) $expense['id']);
    }

    public static function archive_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $expense = self::fetch_expense((int) $org, (int) $request['id']);
        if (!$expense) {
            return new WP_Error('vy_not_found', 'Expense not found.', ['status' => 404]);
        }

        $editState = vy_expense_edit_state($expense);
        if (!$editState['can_archive']) {
            return new WP_Error('vy_expense_archive_locked', $editState['archive_reason'] ?: 'This expense can no longer be archived.', ['status' => 400]);
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'vy_expenses',
            ['status' => 'ARCHIVED', 'updated_at' => current_time('mysql', true)],
            ['org_id' => $org, 'id' => (int) $expense['id']],
            ['%s','%s'],
            ['%d','%d']
        );

        RecordAuditLogger::log(
            (int) $org,
            'expense',
            (int) $expense['id'],
            'archived',
            sprintf('Archived expense %s', (string) ($expense['category'] ?? '')),
            [
                'lines' => [
                    'Payee: ' . (string) ($expense['payee'] ?? '—'),
                    'Amount: ' . RecordAuditLogger::money((float) ($expense['amount'] ?? 0), (string) ($expense['currency'] ?? 'INR')),
                ],
            ]
        );

        return new WP_REST_Response(['success' => true, 'action' => 'archived'], 200);
    }

    private static function ensure_default_expense_account(int $org_id): int
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vy_accounts WHERE org_id = %d AND code = %s LIMIT 1",
            $org_id,
            'GENERAL_EXPENSES'
        ));
        if ($row) {
            return (int) $row->id;
        }

        $wpdb->insert(
            $wpdb->prefix . 'vy_accounts',
            [
                'org_id'               => $org_id,
                'code'                 => 'GENERAL_EXPENSES',
                'name'                 => 'General Expenses',
                'type'                 => 'EXPENSE',
                'sub_type'             => 'SYSTEM',
                'currency'             => 'INR',
                'is_system'            => 1,
                'status'               => 'ACTIVE',
                'opening_balance'      => 0,
                'opening_balance_type' => 'DEBIT',
                'created_at'           => current_time('mysql', true),
                'updated_at'           => current_time('mysql', true),
            ],
            ['%d','%s','%s','%s','%s','%s','%d','%s','%f','%s','%s','%s']
        );

        return (int) $wpdb->insert_id;
    }
    private static function resolve_vendor_contact(int $org_id, array $body)
    {
        $contactId = isset($body['contact_id']) ? (int) $body['contact_id'] : 0;
        $name = sanitize_text_field($body['payee'] ?? '');
        if ($contactId > 0) {
            $contact = self::fetch_contact($org_id, $contactId);
            if (!$contact || !in_array($contact['type'], ['VENDOR', 'BOTH'], true)) {
                return new WP_Error('vy_bad_contact', 'Invalid vendor contact.', ['status' => 400]);
            }
            if ($name === '') {
                $name = $contact['name'] ?? '';
            }
            return [
                'contact_id' => $contactId,
                'name'       => $name,
            ];
        }
        if ($name === '') {
            return new WP_Error('vy_expense_payee_required', 'Payee is required.', ['status' => 400]);
        }
        $newId = self::insert_contact($org_id, [
            'type' => 'VENDOR',
            'name' => $name,
        ]);
        if (is_wp_error($newId)) {
            return $newId;
        }
        return [
            'contact_id' => $newId,
            'name'       => $name,
        ];
    }

    private static function insert_contact(int $org_id, array $data)
    {
        global $wpdb;
        $payload = [
            'org_id'           => $org_id,
            'type'             => $data['type'],
            'name'             => $data['name'],
            'email'            => $data['email'] ?? null,
            'phone'            => $data['phone'] ?? null,
            'gstin'            => $data['gstin'] ?? null,
            'billing_address'  => $data['billing_address'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'status'           => 'ACTIVE',
            'created_at'       => current_time('mysql', true),
            'updated_at'       => current_time('mysql', true),
        ];
        $result = $wpdb->insert(
            $wpdb->prefix . 'vy_contacts',
            $payload,
            ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
        );
        if ($result === false) {
            return new WP_Error('vy_contact_insert_failed', 'Failed to create contact.', ['status' => 500]);
        }
        return (int) $wpdb->insert_id;
    }

    private static function fetch_contact(int $org_id, int $contact_id): ?array
    {
        if ($contact_id <= 0) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, email, phone, type FROM {$wpdb->prefix}vy_contacts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $contact_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function format_contact_summary(?array $contact): ?array
    {
        if (!$contact) return null;
        return [
            'id'    => (int) $contact['id'],
            'name'  => $contact['name'],
            'email' => $contact['email'],
            'phone' => $contact['phone'],
            'type'  => $contact['type'],
        ];
    }

    private static function prime_contacts(int $org_id, array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row->contact_id)) {
                $ids[] = (int) $row->contact_id;
            }
        }
        $ids = array_unique(array_filter($ids));
        if (!$ids) {
            return [];
        }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT id, name, email, phone, type FROM {$wpdb->prefix}vy_contacts WHERE org_id = %d AND id IN ({$placeholders})";
        $params = array_merge([$org_id], $ids);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[$row['id']] = $row;
        }
        return $map;
    }

    private static function fetch_expense(int $org_id, int $expense_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_expenses WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $expense_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function get_expense_response(int $org_id, int $expense_id)
    {
        $expense = self::fetch_expense($org_id, $expense_id);
        if (!$expense) {
            return new WP_Error('vy_not_found', 'Expense not found.', ['status' => 404]);
        }

        $contactMap = [];
        if (!empty($expense['contact_id'])) {
            $contactMap[(int) $expense['contact_id']] = self::fetch_contact($org_id, (int) $expense['contact_id']);
        }
        $payload = self::format_expense_payload($expense, $contactMap);
        $payload['history'] = RecordAuditLogger::list_for_record($org_id, 'expense', $expense_id);

        return new WP_REST_Response($payload, 200);
    }

    private static function format_expense_payload(array $row, array $contactMap = []): array
    {
        $contactId = !empty($row['contact_id']) ? (int) $row['contact_id'] : null;
        $contact = $contactId ? ($contactMap[$contactId] ?? null) : null;
        $editState = vy_expense_edit_state($row);

        return [
            'id'          => (int) $row['id'],
            'contact_id'  => $contactId,
            'contact'     => self::format_contact_summary($contact),
            'expense_date'=> $row['expense_date'] ?? null,
            'category'    => $row['category'] ?? '',
            'payee'       => $row['payee'] ?? '',
            'description' => $row['description'] ?? '',
            'amount'      => (float) ($row['amount'] ?? 0),
            'currency'    => $row['currency'] ?? 'INR',
            'gst_rate'    => isset($row['gst_rate']) ? (float) $row['gst_rate'] : 0.0,
            'gst_amount'  => isset($row['gst_amount']) ? (float) $row['gst_amount'] : 0.0,
            'gst_type'    => $row['gst_type'] ?? 'GST',
            'is_gst_input_eligible' => isset($row['is_gst_input_eligible']) ? (bool) $row['is_gst_input_eligible'] : true,
            'status'      => $row['status'] ?? 'POSTED',
            'payment_journal_id' => !empty($row['payment_journal_id']) ? (int) $row['payment_journal_id'] : null,
            'journal_id'  => !empty($row['payment_journal_id']) ? (int) $row['payment_journal_id'] : null,
            'can_edit'    => $editState['can_edit'],
            'can_archive' => $editState['can_archive'],
            'edit_block_reason' => $editState['edit_reason'],
            'archive_block_reason' => $editState['archive_reason'],
        ];
    }

    private static function build_expense_update_audit_lines(array $before, array $after): array
    {
        $lines = [];

        self::append_change_line($lines, 'Category', (string) ($before['category'] ?? ''), (string) ($after['category'] ?? ''));
        self::append_change_line($lines, 'Payee', (string) ($before['payee'] ?? ''), (string) ($after['payee'] ?? ''));
        self::append_change_line($lines, 'Expense date', (string) ($before['expense_date'] ?? ''), (string) ($after['expense_date'] ?? ''));
        self::append_change_line(
            $lines,
            'Amount',
            RecordAuditLogger::money((float) ($before['amount'] ?? 0), (string) ($before['currency'] ?? 'INR')),
            RecordAuditLogger::money((float) ($after['amount'] ?? 0), (string) ($after['currency'] ?? 'INR'))
        );
        self::append_change_line($lines, 'GST rate', (string) ($before['gst_rate'] ?? '0'), (string) ($after['gst_rate'] ?? '0'));

        $beforeDescription = trim((string) ($before['description'] ?? ''));
        $afterDescription = trim((string) ($after['description'] ?? ''));
        if ($beforeDescription !== $afterDescription) {
            $lines[] = $afterDescription === '' ? 'Description cleared.' : 'Description updated.';
        }

        if (!$lines) {
            $lines[] = 'Expense details were saved again without a visible field change.';
        }

        return $lines;
    }

    private static function append_change_line(array &$lines, string $label, string $before, string $after): void
    {
        $before = trim($before);
        $after = trim($after);
        if ($before === $after) {
            return;
        }

        $beforeLabel = $before !== '' ? $before : '—';
        $afterLabel = $after !== '' ? $after : '—';
        $lines[] = sprintf('%s: %s -> %s', $label, $beforeLabel, $afterLabel);
    }
}
