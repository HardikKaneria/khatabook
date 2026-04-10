<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use KBS\Accounting\VyJournalEngine;
use KBS\Core\RecordAuditLogger;
use KBS\Core\SystemLogger;
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

        register_rest_route(VyRestAccounts::NS, '/expenses/summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_expense_summary'],
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

        register_rest_route(VyRestAccounts::NS, '/expenses/(?P<id>\d+)/settle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'settle_expense'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function list_expenses(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        [$where, $params] = self::build_expense_filters($request, (int) $org);

        $rows = self::fetch_expense_rows($where, $params, $per_page, $offset);
        $count = self::count_expenses($where, $params);

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

    public static function get_expense_summary(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        [$where, $params] = self::build_expense_filters($request, (int) $org);
        $rows = self::fetch_expense_rows($where, $params);

        return new WP_REST_Response(self::build_expense_summary($rows, $request), 200);
    }

    public static function create_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $body = $request->get_json_params() ?: [];

        $date   = self::normalize_date_input($body['expense_date'] ?? null, gmdate('Y-m-d'));
        $documentType = self::normalize_document_type($body['document_type'] ?? 'EXPENSE');
        $dueDate = self::normalize_date_input($body['due_date'] ?? null, null);
        $category = sanitize_text_field($body['category'] ?? '');
        $amount = (float) ($body['amount'] ?? 0);
        if (!$category || $amount <= 0) {
            return new WP_Error('vy_bad_expense', 'Category and positive amount are required.', ['status' => 400]);
        }
        if ($documentType === 'BILL' && !$dueDate) {
            return new WP_Error('vy_bill_due_required', 'Vendor bills require a due date.', ['status' => 400]);
        }

        $wpdb->query('START TRANSACTION');

        $contactData = self::resolve_vendor_contact((int) $org, $body);
        if (is_wp_error($contactData)) {
            $wpdb->query('ROLLBACK');
            return $contactData;
        }

        $expenseAccount = isset($body['expense_account_id']) ? (int) $body['expense_account_id'] : 0;
        if ($expenseAccount <= 0) {
            $expenseAccount = self::ensure_default_expense_account((int) $org);
            if ($expenseAccount <= 0) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('vy_expense_account_failed', 'Failed to resolve the default expense account.', ['status' => 500]);
            }
        }

        $gstRate = isset($body['gst_rate']) ? max(0, (float) $body['gst_rate']) : 0;
        $gstAmount = $gstRate > 0 ? round($amount * $gstRate / 100, 2) : 0;
        $gstType = sanitize_text_field($body['gst_type'] ?? 'GST');
        $gstEligible = isset($body['is_gst_input_eligible']) ? (int) (bool) $body['is_gst_input_eligible'] : 1;
        $timestamp = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_expenses',
            [
                'org_id'        => $org,
                'contact_id'    => $contactData['contact_id'],
                'expense_account_id' => $expenseAccount,
                'document_type' => $documentType,
                'expense_date'  => $date,
                'due_date'      => $documentType === 'BILL' ? $dueDate : null,
                'category'      => $category,
                'reference_number' => self::sanitize_reference_number($body['reference_number'] ?? ''),
                'payee'         => $contactData['name'],
                'description'   => wp_kses_post($body['description'] ?? ''),
                'amount'        => $amount,
                'currency'      => strtoupper($body['currency'] ?? 'INR'),
                'gst_rate'      => $gstRate,
                'gst_amount'    => $gstAmount,
                'gst_type'      => $gstType,
                'is_gst_input_eligible' => $gstEligible,
                'status'        => 'POSTED',
                'created_at'    => $timestamp,
                'updated_at'    => $timestamp,
            ],
            ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%s','%f','%f','%s','%d','%s','%s','%s']
        );

        if ($inserted === false) {
            $wpdb->query('ROLLBACK');
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
                $wpdb->query('ROLLBACK');
                return $journal;
            }
            $journalId = $journal;

            $updated = $wpdb->update(
                $wpdb->prefix . 'vy_expenses',
                ['payment_journal_id' => $journalId, 'updated_at' => current_time('mysql', true)],
                ['id' => $expense_id],
                ['%d','%s'],
                ['%d']
            );

            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('vy_expense_payment_update_failed', 'Failed to link the expense payment journal.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        RecordAuditLogger::log(
            (int) $org,
            'expense',
            $expense_id,
            'created',
            sprintf('Created %s %s', $documentType === 'BILL' ? 'vendor bill' : 'expense', $category),
            [
                'lines' => [
                    'Document type: ' . ($documentType === 'BILL' ? 'Vendor bill' : 'Expense'),
                    !empty($body['reference_number']) ? ('Reference: ' . self::sanitize_reference_number($body['reference_number'])) : '',
                    'Payee: ' . ($contactData['name'] ?: 'Vendor'),
                    'Amount: ' . RecordAuditLogger::money($amount, strtoupper((string) ($body['currency'] ?? 'INR'))),
                    $documentType === 'BILL' ? ('Due date: ' . ($dueDate ?: '—')) : '',
                    'GST: ' . ($gstRate > 0 ? rtrim(rtrim(number_format($gstRate, 2, '.', ''), '0'), '.') . '%' : 'No GST'),
                    $journalId ? ('Payment journal: #' . $journalId) : 'Payment journal: not recorded',
                ],
            ]
        );

        try {
            InternalDocumentNotifier::notify_expense_created((int) $org, $expense_id);
        } catch (\Throwable $throwable) {
            SystemLogger::log_event(
                'expense_internal_notification_failed',
                'Internal expense creation notification failed.',
                [
                    'org_id' => (int) $org,
                    'expense_id' => $expense_id,
                    'error_message' => $throwable->getMessage(),
                ],
                get_current_user_id(),
                'backend/Api/VyRestExpenses.php'
            );
        }

        return new WP_REST_Response([
            'id' => $expense_id,
            'payment_journal_id' => $journalId,
            'document_type' => $documentType,
        ], 201);
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
        $documentType = array_key_exists('document_type', $body)
            ? self::normalize_document_type($body['document_type'])
            : self::normalize_document_type($expense['document_type'] ?? 'EXPENSE');
        $dueDate = array_key_exists('due_date', $body)
            ? self::normalize_date_input($body['due_date'], null)
            : self::normalize_date_input($expense['due_date'] ?? null, null);
        if ($category === '' || $amount <= 0) {
            return new WP_Error('vy_bad_expense', 'Category and positive amount are required.', ['status' => 400]);
        }
        if ($documentType === 'BILL' && !$dueDate) {
            return new WP_Error('vy_bill_due_required', 'Vendor bills require a due date.', ['status' => 400]);
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
                'expense_account_id' => array_key_exists('expense_account_id', $body)
                    ? (int) $body['expense_account_id']
                    : (int) ($expense['expense_account_id'] ?? 0),
                'document_type' => $documentType,
                'expense_date'  => sanitize_text_field($body['expense_date'] ?? ($expense['expense_date'] ?? gmdate('Y-m-d'))),
                'due_date'      => $documentType === 'BILL' ? $dueDate : null,
                'category'      => $category,
                'reference_number' => array_key_exists('reference_number', $body)
                    ? self::sanitize_reference_number($body['reference_number'])
                    : ($expense['reference_number'] ?? null),
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
            ['%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%s','%f','%f','%s','%d','%s'],
            ['%d','%d']
        );

        $updatedExpense = array_merge($previousExpense, [
            'contact_id'            => $contactData['contact_id'],
            'expense_account_id'    => array_key_exists('expense_account_id', $body)
                ? (int) $body['expense_account_id']
                : (int) ($expense['expense_account_id'] ?? 0),
            'document_type'         => $documentType,
            'expense_date'          => sanitize_text_field($body['expense_date'] ?? ($expense['expense_date'] ?? gmdate('Y-m-d'))),
            'due_date'              => $documentType === 'BILL' ? $dueDate : null,
            'category'              => $category,
            'reference_number'      => array_key_exists('reference_number', $body)
                ? self::sanitize_reference_number($body['reference_number'])
                : ($expense['reference_number'] ?? null),
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
            sprintf('Updated %s %s', $documentType === 'BILL' ? 'vendor bill' : 'expense', (string) ($expense['category'] ?? '')),
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

    public static function settle_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $expense = self::fetch_expense((int) $org, (int) $request['id']);
        if (!$expense) {
            return new WP_Error('vy_not_found', 'Expense not found.', ['status' => 404]);
        }

        $editState = vy_expense_edit_state($expense);
        if (!$editState['can_settle']) {
            return new WP_Error('vy_expense_settlement_locked', $editState['settle_reason'] ?: 'This expense can no longer be settled.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $payFromAccountId = isset($body['pay_from_account_id']) ? (int) $body['pay_from_account_id'] : 0;
        if ($payFromAccountId <= 0) {
            return new WP_Error('vy_bad_payment_account', 'A bank, cash, or wallet account is required to record payment.', ['status' => 400]);
        }

        $paymentAccount = self::fetch_account((int) $org, $payFromAccountId);
        if (!$paymentAccount || strtoupper((string) ($paymentAccount['status'] ?? 'ACTIVE')) !== 'ACTIVE') {
            return new WP_Error('vy_bad_payment_account', 'The selected payment account is not available in this organization.', ['status' => 400]);
        }

        $paymentSubType = strtoupper((string) ($paymentAccount['sub_type'] ?? ''));
        if (!in_array($paymentSubType, ['BANK', 'CASH', 'WALLET'], true)) {
            return new WP_Error('vy_bad_payment_account', 'Only bank, cash, or wallet accounts can be used to settle expenses.', ['status' => 400]);
        }

        $expenseAccountId = !empty($expense['expense_account_id']) ? (int) $expense['expense_account_id'] : 0;
        if ($expenseAccountId <= 0 || !self::fetch_account((int) $org, $expenseAccountId)) {
            return new WP_Error('vy_expense_account_missing', 'This expense is missing a valid expense account for settlement.', ['status' => 500]);
        }

        $settlementDate = self::normalize_date_input($body['date'] ?? null, gmdate('Y-m-d'));
        $amount = (float) ($expense['amount'] ?? 0);
        if ($amount <= 0) {
            return new WP_Error('vy_bad_expense', 'Only expenses with a positive amount can be settled.', ['status' => 400]);
        }

        $description = trim((string) ($body['description'] ?? ''));
        if ($description === '') {
            $description = sprintf(
                '%s settlement - %s',
                self::document_type_label((string) ($expense['document_type'] ?? 'EXPENSE')),
                (string) ($expense['category'] ?? 'Expense')
            );
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $journal = VyJournalEngine::create_journal_entry([
            'org_id'        => (int) $org,
            'date'          => $settlementDate,
            'type'          => 'EXPENSE',
            'description'   => $description,
            'reference'     => !empty($expense['reference_number']) ? (string) $expense['reference_number'] : null,
            'source_module' => 'expense',
            'source_id'     => (int) $expense['id'],
            'lines'         => [
                [
                    'account_id' => $expenseAccountId,
                    'debit'      => $amount,
                    'credit'     => 0,
                    'line_memo'  => 'Expense settlement',
                ],
                [
                    'account_id' => $payFromAccountId,
                    'debit'      => 0,
                    'credit'     => $amount,
                    'line_memo'  => 'Expense payment',
                ],
            ],
        ]);

        if (is_wp_error($journal)) {
            $wpdb->query('ROLLBACK');
            return $journal;
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_expenses',
            [
                'payment_journal_id' => (int) $journal,
                'updated_at' => current_time('mysql', true),
            ],
            [
                'org_id' => (int) $org,
                'id' => (int) $expense['id'],
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_expense_payment_update_failed', 'Failed to link the expense payment journal.', ['status' => 500]);
        }

        $wpdb->query('COMMIT');

        RecordAuditLogger::log(
            (int) $org,
            'expense',
            (int) $expense['id'],
            'settled',
            sprintf(
                'Recorded payment for %s %s',
                self::document_type_label((string) ($expense['document_type'] ?? 'EXPENSE')),
                (string) ($expense['category'] ?? '')
            ),
            [
                'lines' => [
                    'Settlement date: ' . ($settlementDate ?: '—'),
                    'Paid from: ' . (string) ($paymentAccount['name'] ?? '—'),
                    'Amount: ' . RecordAuditLogger::money($amount, (string) ($expense['currency'] ?? 'INR')),
                    'Payment journal: #' . (int) $journal,
                ],
            ]
        );

        return self::get_expense_response((int) $org, (int) $expense['id']);
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
        $expenseAccount = self::fetch_account_summary($org_id, !empty($expense['expense_account_id']) ? (int) $expense['expense_account_id'] : 0);
        $paymentAccount = self::fetch_payment_account_summary($org_id, !empty($expense['payment_journal_id']) ? (int) $expense['payment_journal_id'] : 0);
        $payload['expense_account'] = $expenseAccount;
        $payload['payment_account'] = $paymentAccount;
        $payload['pay_from_account_id'] = $paymentAccount['id'] ?? null;
        $payload['pay_from_account_name'] = $paymentAccount['name'] ?? null;
        $payload['history'] = RecordAuditLogger::list_for_record($org_id, 'expense', $expense_id);

        return new WP_REST_Response($payload, 200);
    }

    private static function format_expense_payload(array $row, array $contactMap = []): array
    {
        $contactId = !empty($row['contact_id']) ? (int) $row['contact_id'] : null;
        $contact = $contactId ? ($contactMap[$contactId] ?? null) : null;
        $editState = vy_expense_edit_state($row);
        $documentType = self::normalize_document_type($row['document_type'] ?? 'EXPENSE');
        $paymentJournalId = !empty($row['payment_journal_id']) ? (int) $row['payment_journal_id'] : null;
        $paymentState = $paymentJournalId ? 'PAID' : 'UNPAID';
        $dueDate = !empty($row['due_date']) ? (string) $row['due_date'] : null;
        $dueState = self::calculate_due_state($documentType, $dueDate, $paymentJournalId, (string) ($row['status'] ?? 'POSTED'));
        $workflowStatus = self::workflow_status($documentType, (string) ($row['status'] ?? 'POSTED'), $paymentState, $dueState);

        return [
            'id'          => (int) $row['id'],
            'contact_id'  => $contactId,
            'contact'     => self::format_contact_summary($contact),
            'expense_account_id' => !empty($row['expense_account_id']) ? (int) $row['expense_account_id'] : null,
            'document_type' => $documentType,
            'is_bill'     => $documentType === 'BILL',
            'expense_date'=> $row['expense_date'] ?? null,
            'due_date'    => $dueDate,
            'category'    => $row['category'] ?? '',
            'reference_number' => $row['reference_number'] ?? '',
            'payee'       => $row['payee'] ?? '',
            'description' => $row['description'] ?? '',
            'amount'      => (float) ($row['amount'] ?? 0),
            'currency'    => $row['currency'] ?? 'INR',
            'gst_rate'    => isset($row['gst_rate']) ? (float) $row['gst_rate'] : 0.0,
            'gst_amount'  => isset($row['gst_amount']) ? (float) $row['gst_amount'] : 0.0,
            'gst_type'    => $row['gst_type'] ?? 'GST',
            'is_gst_input_eligible' => isset($row['is_gst_input_eligible']) ? (bool) $row['is_gst_input_eligible'] : true,
            'status'      => $row['status'] ?? 'POSTED',
            'workflow_status' => $workflowStatus,
            'payment_state' => $paymentState,
            'due_state'   => $dueState,
            'is_overdue'  => $dueState === 'OVERDUE',
            'payment_journal_id' => $paymentJournalId,
            'journal_id'  => $paymentJournalId,
            'can_edit'    => $editState['can_edit'],
            'can_archive' => $editState['can_archive'],
            'can_settle'  => $editState['can_settle'],
            'edit_block_reason' => $editState['edit_reason'],
            'archive_block_reason' => $editState['archive_reason'],
            'settle_block_reason' => $editState['settle_reason'],
        ];
    }

    private static function build_expense_filters(WP_REST_Request $request, int $org_id): array
    {
        $where = ['org_id = %d'];
        $params = [$org_id];

        if ($from = self::normalize_date_input($request->get_param('from'), null)) {
            $where[] = 'expense_date >= %s';
            $params[] = $from;
        }
        if ($to = self::normalize_date_input($request->get_param('to'), null)) {
            $where[] = 'expense_date <= %s';
            $params[] = $to;
        }
        if ($category = trim((string) $request->get_param('category'))) {
            $where[] = 'category = %s';
            $params[] = sanitize_text_field($category);
        }

        $documentType = self::normalize_document_type($request->get_param('document_type') ?? 'ALL');
        if ($documentType !== 'ALL') {
            $where[] = 'document_type = %s';
            $params[] = $documentType;
        }

        $status = strtoupper((string) ($request->get_param('status') ?? ''));
        if ($status === 'ACTIVE') {
            $where[] = "status <> 'ARCHIVED'";
        } elseif ($status === 'ARCHIVED') {
            $where[] = 'status = %s';
            $params[] = 'ARCHIVED';
        }

        $paymentState = self::normalize_payment_state($request->get_param('payment_state') ?? 'ALL');
        if ($paymentState === 'PAID') {
            $where[] = 'payment_journal_id IS NOT NULL';
        } elseif ($paymentState === 'UNPAID') {
            $where[] = 'payment_journal_id IS NULL';
        }

        $dueState = self::normalize_due_state($request->get_param('due_state') ?? 'ALL');
        if ($dueState !== 'ALL') {
            $today = gmdate('Y-m-d');
            $where[] = "document_type = 'BILL'";
            $where[] = "status <> 'ARCHIVED'";
            $where[] = 'payment_journal_id IS NULL';
            $where[] = 'due_date IS NOT NULL';

            if ($dueState === 'OVERDUE') {
                $where[] = 'due_date < %s';
                $params[] = $today;
            } elseif ($dueState === 'DUE_TODAY') {
                $where[] = 'due_date = %s';
                $params[] = $today;
            } elseif ($dueState === 'UPCOMING') {
                $where[] = 'due_date > %s';
                $params[] = $today;
            }
        }

        return [$where, $params];
    }

    private static function fetch_expense_rows(array $where, array $params, ?int $limit = null, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vy_expenses';
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY expense_date DESC, id DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $limit;
            $params[] = $offset;
        }

        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    private static function count_expenses(array $where, array $params): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vy_expenses';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE " . implode(' AND ', $where),
            ...$params
        ));
    }

    private static function build_expense_summary(array $rows, WP_REST_Request $request): array
    {
        $summary = [
            'from' => self::normalize_date_input($request->get_param('from'), null),
            'to' => self::normalize_date_input($request->get_param('to'), null),
            'total_records' => 0,
            'total_amount' => 0.0,
            'expense_count' => 0,
            'expense_amount' => 0.0,
            'bill_count' => 0,
            'bill_amount' => 0.0,
            'open_bill_count' => 0,
            'open_bill_amount' => 0.0,
            'overdue_bill_count' => 0,
            'overdue_bill_amount' => 0.0,
            'due_today_bill_count' => 0,
            'due_today_bill_amount' => 0.0,
            'paid_count' => 0,
            'paid_amount' => 0.0,
            'unpaid_count' => 0,
            'unpaid_amount' => 0.0,
        ];

        foreach ($rows as $rowObject) {
            $row = (array) $rowObject;
            $summary['total_records']++;
            $amount = (float) ($row['amount'] ?? 0);
            $summary['total_amount'] += $amount;

            $documentType = self::normalize_document_type($row['document_type'] ?? 'EXPENSE');
            $paymentJournalId = !empty($row['payment_journal_id']) ? (int) $row['payment_journal_id'] : null;
            $paymentState = $paymentJournalId ? 'PAID' : 'UNPAID';
            $dueState = self::calculate_due_state(
                $documentType,
                !empty($row['due_date']) ? (string) $row['due_date'] : null,
                $paymentJournalId,
                (string) ($row['status'] ?? 'POSTED')
            );

            if ($documentType === 'BILL') {
                $summary['bill_count']++;
                $summary['bill_amount'] += $amount;

                if ($paymentState === 'UNPAID' && strtoupper((string) ($row['status'] ?? 'POSTED')) !== 'ARCHIVED') {
                    $summary['open_bill_count']++;
                    $summary['open_bill_amount'] += $amount;
                }

                if ($dueState === 'OVERDUE') {
                    $summary['overdue_bill_count']++;
                    $summary['overdue_bill_amount'] += $amount;
                } elseif ($dueState === 'DUE_TODAY') {
                    $summary['due_today_bill_count']++;
                    $summary['due_today_bill_amount'] += $amount;
                }
            } else {
                $summary['expense_count']++;
                $summary['expense_amount'] += $amount;
            }

            if ($paymentState === 'PAID') {
                $summary['paid_count']++;
                $summary['paid_amount'] += $amount;
            } else {
                $summary['unpaid_count']++;
                $summary['unpaid_amount'] += $amount;
            }
        }

        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        return $summary;
    }

    private static function fetch_account(int $org_id, int $account_id): ?array
    {
        if ($account_id <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, type, sub_type, status FROM {$wpdb->prefix}vy_accounts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function fetch_account_summary(int $org_id, int $account_id): ?array
    {
        $row = self::fetch_account($org_id, $account_id);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'sub_type' => (string) ($row['sub_type'] ?? ''),
        ];
    }

    private static function fetch_payment_account_summary(int $org_id, int $journal_id): ?array
    {
        if ($journal_id <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT account_id
             FROM {$wpdb->prefix}vy_journal_lines
             WHERE org_id = %d AND journal_id = %d AND credit > 0
             ORDER BY id ASC
             LIMIT 1",
            $org_id,
            $journal_id
        ), ARRAY_A);
        if (!$row || empty($row['account_id'])) {
            return null;
        }

        return self::fetch_account_summary($org_id, (int) $row['account_id']);
    }

    private static function build_expense_update_audit_lines(array $before, array $after): array
    {
        $lines = [];

        self::append_change_line(
            $lines,
            'Document type',
            self::document_type_label((string) ($before['document_type'] ?? 'EXPENSE')),
            self::document_type_label((string) ($after['document_type'] ?? 'EXPENSE'))
        );
        self::append_change_line($lines, 'Category', (string) ($before['category'] ?? ''), (string) ($after['category'] ?? ''));
        self::append_change_line($lines, 'Reference', (string) ($before['reference_number'] ?? ''), (string) ($after['reference_number'] ?? ''));
        self::append_change_line($lines, 'Payee', (string) ($before['payee'] ?? ''), (string) ($after['payee'] ?? ''));
        self::append_change_line($lines, 'Expense date', (string) ($before['expense_date'] ?? ''), (string) ($after['expense_date'] ?? ''));
        self::append_change_line($lines, 'Due date', (string) ($before['due_date'] ?? ''), (string) ($after['due_date'] ?? ''));
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

    private static function normalize_document_type($value): string
    {
        $documentType = strtoupper(sanitize_text_field((string) $value));
        if ($documentType === 'ALL') {
            return 'ALL';
        }

        return $documentType === 'BILL' ? 'BILL' : 'EXPENSE';
    }

    private static function normalize_payment_state($value): string
    {
        $paymentState = strtoupper(sanitize_text_field((string) $value));
        if ($paymentState === 'PAID') {
            return 'PAID';
        }
        if ($paymentState === 'UNPAID') {
            return 'UNPAID';
        }
        return 'ALL';
    }

    private static function normalize_due_state($value): string
    {
        $dueState = strtoupper(sanitize_text_field((string) $value));
        if (in_array($dueState, ['OVERDUE', 'DUE_TODAY', 'UPCOMING'], true)) {
            return $dueState;
        }
        return 'ALL';
    }

    private static function normalize_date_input($value, ?string $fallback = null): ?string
    {
        if ($value !== null && $value !== '') {
            $timestamp = strtotime((string) $value);
            if ($timestamp !== false) {
                return gmdate('Y-m-d', $timestamp);
            }
        }

        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return null;
    }

    private static function sanitize_reference_number($value): string
    {
        return trim(sanitize_text_field((string) $value));
    }

    private static function calculate_due_state(string $documentType, ?string $dueDate, ?int $paymentJournalId, string $status): ?string
    {
        if (strtoupper($status) === 'ARCHIVED' || $documentType !== 'BILL' || $paymentJournalId || !$dueDate) {
            return null;
        }

        $today = gmdate('Y-m-d');
        if ($dueDate < $today) {
            return 'OVERDUE';
        }
        if ($dueDate === $today) {
            return 'DUE_TODAY';
        }

        return 'UPCOMING';
    }

    private static function workflow_status(string $documentType, string $status, string $paymentState, ?string $dueState): string
    {
        if (strtoupper($status) === 'ARCHIVED') {
            return 'ARCHIVED';
        }
        if ($paymentState === 'PAID') {
            return 'PAID';
        }
        if ($documentType === 'BILL') {
            return $dueState === 'OVERDUE' ? 'OVERDUE' : 'OPEN';
        }

        return 'RECORDED';
    }

    private static function document_type_label(string $documentType): string
    {
        return self::normalize_document_type($documentType) === 'BILL' ? 'Vendor bill' : 'Expense';
    }
}
