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

class VyRestInvoices
{
    private const PAYMENT_SUBMISSION_TTL = 45;
    private const PAYMENT_SUBMISSION_TTL_EXPLICIT = 3600;

    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/invoices', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_invoices'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/pay', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'pay_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/refund', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'refund_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/email', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'email_invoice'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/generate-pdf', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'generate_invoice_pdf'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/next-number', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'next_invoice_number'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/descriptions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'description_suggestions'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/payments', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_payments'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/recurring', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_recurring_profile'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/recurring-invoices', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_recurring_profiles'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/recurring-invoices/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_recurring_profile'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/recurring-invoices/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_recurring_profile'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/recurring-invoices/(?P<id>\d+)/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'generate_recurring_profile'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/notes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_invoice_notes'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/notes', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_invoice_note'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/promises', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_promises'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/promises', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_invoice_promises'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/promises', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_invoice_promise'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/promises/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_invoice_promise'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function generate_invoice_pdf(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoiceId = (int) $request->get_param('id');
        $result = \Vy_Invoice_Pdf::generate((int) $org, $invoiceId);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['pdf_url' => $result], 200);
    }

    public static function email_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        $body = $request->get_json_params() ?: [];
        $recipients = [];
        if (!empty($body['recipients']) && is_array($body['recipients'])) {
            foreach ($body['recipients'] as $email) {
                $sanitized = sanitize_email($email);
                if ($sanitized) {
                    $recipients[] = $sanitized;
                }
            }
        }

        $result = vy_send_invoice_email((int) $invoice['id'], $recipients);
        if (is_wp_error($result)) {
            return $result;
        }

        self::log_invoice_email_audit((int) $org, (int) $invoice['id'], (string) ($invoice['invoice_number'] ?? ''), $result);

        return new WP_REST_Response([
            'success'       => true,
            'email_sent_to' => $result['recipients'],
            'email_sent_at' => $result['sent_at'],
            'pdf_url'       => $result['pdf_url'],
        ], 200);
    }

    public static function list_invoices(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $table = $wpdb->prefix . 'vy_invoices';

        $where = ['org_id = %d'];
        $params = [$org];

        if ($status = $request->get_param('status')) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($status);
        }
        if ($customer = $request->get_param('customer_name')) {
            $where[] = '(customer_name LIKE %s OR invoice_number LIKE %s)';
            $customerLike = '%' . $wpdb->esc_like((string) $customer) . '%';
            $params[] = $customerLike;
            $params[] = $customerLike;
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY date DESC, id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        $invoices = [];
        $contactMap = self::prime_contacts((int) $org, $rows ?: []);
        $noteTotalsMap = vy_invoice_note_totals_map((int) $org);

        foreach ($rows as $row) {
            $paid = self::get_paid_amount((int) $row->id);
            $financials = vy_invoice_apply_adjustments(
                [
                    'id' => (int) $row->id,
                    'total' => (float) $row->total,
                ],
                $paid,
                $noteTotalsMap[(int) $row->id] ?? null
            );
            $refunded = self::get_refunded_amount((int) $org, (int) $row->id);
            $balanceDue = strtoupper((string) ($row->status ?? 'SENT')) === 'VOID' && $refunded > 0
                ? 0.0
                : (float) ($financials['balance_due'] ?? 0);
            $invoices[] = [
                'id'             => (int) $row->id,
                'contact_id'     => $row->contact_id ? (int) $row->contact_id : null,
                'contact'        => self::format_contact_summary($row->contact_id ? ($contactMap[$row->contact_id] ?? null) : null),
                'invoice_number' => $row->invoice_number,
                'customer_name'  => $row->customer_name,
                'date'           => $row->date,
                'due_date'       => $row->due_date,
                'subtotal'       => (float) $row->subtotal,
                'tax_total'      => (float) $row->tax_total,
                'total'          => (float) $row->total,
                'adjusted_total' => (float) ($financials['adjusted_total'] ?? (float) $row->total),
                'status'         => $row->status,
                'paid_amount'    => $paid,
                'refunded_amount'=> $refunded,
                'net_paid_amount'=> round(max(0, $paid - $refunded), 2),
                'credit_total'   => (float) ($financials['credit_total'] ?? 0),
                'debit_total'    => (float) ($financials['debit_total'] ?? 0),
                'balance_due'    => $balanceDue,
            ];
        }

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE " . implode(' AND ', $where),
            ...array_slice($params, 0, -2)
        ));

        return new WP_REST_Response([
            'data' => $invoices,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $count,
            ],
        ], 200);
    }

    public static function create_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $created = self::create_invoice_record((int) $org, $request->get_json_params() ?: []);
        if (is_wp_error($created)) {
            return $created;
        }

        return new WP_REST_Response($created, 201);
    }

    public static function get_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        $items = self::fetch_invoice_items((int) $org, (int) $invoice['id']);
        $payments = self::get_invoice_payments((int) $org, $invoice['id']);

        $invoice = self::apply_invoice_financials((int) $org, $invoice);
        $invoice['items'] = $items ?: [];
        $invoice['payments'] = $payments;
        $invoice['refunds'] = self::get_invoice_refunds((int) $org, (int) $invoice['id']);
        $invoice['adjustments'] = self::fetch_invoice_notes((int) $org, (int) $invoice['id']);
        $invoice['promises'] = self::fetch_invoice_promises((int) $org, ['invoice_id' => (int) $invoice['id']]);
        $editState = vy_invoice_edit_state($invoice, $payments);
        $invoice['can_edit'] = $editState['can_edit'];
        $invoice['edit_block_reason'] = $editState['reason'];
        $refundState = self::invoice_refund_state($invoice);
        $invoice['can_refund'] = $refundState['can_refund'];
        $invoice['refund_block_reason'] = $refundState['reason'];
        $invoice['refundable_amount'] = $refundState['amount'];
        $invoice['risk_summary'] = vy_get_invoice_risk_summary(
            (int) $org,
            $invoice,
            $invoice['items'],
            $invoice['payments'],
            $invoice['adjustments'],
            $invoice['promises']
        );
        $invoice['contact_id'] = $invoice['contact_id'] ? (int) $invoice['contact_id'] : null;
        if ($invoice['contact_id']) {
            $invoice['contact'] = self::format_contact_summary(self::fetch_contact((int) $org, $invoice['contact_id']));
        }
        $sourceRecurringProfile = self::find_recurring_profile_by_source((int) $org, (int) $invoice['id']);
        $invoice['source_recurring_profile'] = $sourceRecurringProfile
            ? self::format_recurring_profile_row((int) $org, $sourceRecurringProfile, false)
            : null;
        $recurringProfile = !empty($invoice['recurring_profile_id'])
            ? self::fetch_recurring_profile((int) $org, (int) $invoice['recurring_profile_id'])
            : null;
        $invoice['recurring_profile'] = $recurringProfile
            ? self::format_recurring_profile_row((int) $org, $recurringProfile, false)
            : null;
        $invoice['history'] = RecordAuditLogger::list_for_record((int) $org, 'invoice', (int) $invoice['id']);

        return new WP_REST_Response($invoice, 200);
    }

    public static function update_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        $payments = self::get_invoice_payments((int) $org, (int) $invoice['id']);
        $invoice = self::apply_invoice_financials((int) $org, $invoice);
        $editState = vy_invoice_edit_state($invoice, $payments);
        if (!$editState['can_edit']) {
            return new WP_Error('vy_invoice_locked', $editState['reason'] ?: 'This invoice can no longer be edited.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $items = $body['items'] ?? [];
        if (!$items || !is_array($items)) {
            return new WP_Error('vy_no_items', 'At least one item is required.', ['status' => 400]);
        }

        $contactData = self::resolve_customer_contact((int) $org, $body);
        if (is_wp_error($contactData)) {
            return $contactData;
        }

        $date = sanitize_text_field($body['date'] ?? ($invoice['date'] ?? gmdate('Y-m-d')));
        $due = self::resolve_due_date(
            (int) $org,
            $date,
            array_key_exists('due_date', $body)
                ? sanitize_text_field((string) $body['due_date'])
                : (string) ($invoice['due_date'] ?? '')
        );
        $status = strtoupper((string) ($body['status'] ?? ($invoice['status'] ?? 'SENT')));
        if (!in_array($status, ['DRAFT', 'SENT'], true)) {
            $status = (string) ($invoice['status'] ?? 'SENT');
        }

        $templateId = self::resolve_template_id((int) $org);
        $currency = strtoupper(sanitize_text_field((string) ($body['currency'] ?? ($invoice['currency'] ?? 'INR'))));
        if ($currency === '') {
            $currency = 'INR';
        }

        $totals = self::calculate_totals($items);
        $previousInvoice = $invoice;
        $invoicesTable = $wpdb->prefix . 'vy_invoices';
        $itemsTable = $wpdb->prefix . 'vy_invoice_items';
        $timestamp = current_time('mysql', true);

        $wpdb->query('START TRANSACTION');

        $updated = $wpdb->update(
            $invoicesTable,
            [
                'contact_id'     => $contactData['contact_id'],
                'customer_name'  => $contactData['name'],
                'customer_email' => $contactData['email'],
                'customer_phone' => $contactData['phone'],
                'date'           => $date,
                'due_date'       => $due,
                'currency'       => $currency,
                'subtotal'       => $totals['subtotal'],
                'tax_total'      => $totals['tax_total'],
                'total'          => $totals['total'],
                'status'         => $status,
                'template_id'    => $templateId,
                'notes'          => wp_kses_post($body['notes'] ?? ($invoice['notes'] ?? '')),
                'pdf_url'        => null,
                'updated_at'     => $timestamp,
            ],
            [
                'org_id' => (int) $org,
                'id'     => (int) $invoice['id'],
            ],
            ['%d','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s','%s'],
            ['%d','%d']
        );

        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_invoice_update_failed', 'Failed to update invoice.', ['status' => 500]);
        }

        $deleted = $wpdb->delete(
            $itemsTable,
            [
                'org_id'     => (int) $org,
                'invoice_id' => (int) $invoice['id'],
            ],
            ['%d', '%d']
        );

        if ($deleted === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_invoice_items_delete_failed', 'Failed to update invoice items.', ['status' => 500]);
        }

        foreach ($totals['lines'] as $line) {
            $inserted = $wpdb->insert(
                $itemsTable,
                [
                    'org_id'     => (int) $org,
                    'invoice_id' => (int) $invoice['id'],
                    'description'=> $line['description'],
                    'quantity'   => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate'   => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'tax_type'   => $line['tax_type'],
                    'line_total' => $line['line_total'],
                    'created_at' => $timestamp,
                ],
                ['%d','%d','%s','%f','%f','%f','%f','%s','%s']
            );

            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('vy_invoice_items_insert_failed', 'Failed to save invoice items.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        $updatedInvoice = array_merge($previousInvoice, [
            'customer_name'  => $contactData['name'],
            'customer_email' => $contactData['email'],
            'customer_phone' => $contactData['phone'],
            'date'           => $date,
            'due_date'       => $due,
            'currency'       => $currency,
            'subtotal'       => $totals['subtotal'],
            'tax_total'      => $totals['tax_total'],
            'total'          => $totals['total'],
            'status'         => $status,
            'notes'          => wp_kses_post($body['notes'] ?? ($invoice['notes'] ?? '')),
        ]);
        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) $invoice['id'],
            'updated',
            sprintf('Updated invoice %s', (string) ($invoice['invoice_number'] ?? '')),
            [
                'lines' => self::build_invoice_update_audit_lines($previousInvoice, $updatedInvoice, count($totals['lines'])),
            ]
        );

        return new WP_REST_Response([
            'id'         => (int) $invoice['id'],
            'success'    => true,
            'pdf_url'    => null,
            'can_edit'   => true,
            'updated_at' => $timestamp,
        ], 200);
    }

    public static function pay_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        $body = $request->get_json_params();
        $amount = (float) ($body['amount'] ?? 0);
        if ($amount <= 0) {
            return new WP_Error('vy_bad_amount', 'Amount must be greater than zero.', ['status' => 400]);
        }

        if ($invoice['status'] === 'VOID') {
            return new WP_Error('vy_invoice_void', 'Cannot record payment for a void invoice.', ['status' => 400]);
        }

        $toAccountId     = (int) ($body['to_account_id'] ?? 0);
        $incomeAccountId = (int) ($body['income_account_id'] ?? 0);
        if ($toAccountId <= 0 || $incomeAccountId <= 0) {
            return new WP_Error('vy_bad_accounts', 'Money-in and income accounts are required.', ['status' => 400]);
        }

        $toAccount = self::fetch_account_row((int) $org, $toAccountId);
        if (!$toAccount || !in_array(strtoupper($toAccount['sub_type'] ?? ''), ['BANK', 'CASH', 'WALLET'], true)) {
            return new WP_Error('vy_invalid_bank', 'The receiving account must be a bank/cash account.', ['status' => 400]);
        }
        if (($activeError = self::ensure_account_row_is_active($toAccount, 'vy_account_archived', 'The receiving account is archived and cannot accept new payments.')) instanceof WP_Error) {
            return $activeError;
        }
        $incomeAccount = self::fetch_account_row((int) $org, $incomeAccountId);
        if (!$incomeAccount || strtoupper($incomeAccount['type'] ?? '') !== 'INCOME') {
            return new WP_Error('vy_invalid_income', 'The income account must be of type INCOME.', ['status' => 400]);
        }
        if (($activeError = self::ensure_account_row_is_active($incomeAccount, 'vy_account_archived', 'The income account is archived and cannot receive new payment postings.')) instanceof WP_Error) {
            return $activeError;
        }

        $financials = self::apply_invoice_financials((int) $org, $invoice);
        $alreadyPaid = (float) ($financials['paid_amount'] ?? 0);
        $outstanding = (float) ($financials['balance_due'] ?? 0);
        if ($outstanding <= 0) {
            return new WP_Error('vy_invoice_paid', 'Invoice is already fully paid.', ['status' => 400]);
        }
        if ($amount > $outstanding + 0.01) {
            return new WP_Error('vy_amount_exceeds', 'Payment exceeds outstanding balance.', ['status' => 400]);
        }

        $submissionGuard = self::begin_payment_submission_guard((int) $invoice['id'], (array) $body);
        if (is_wp_error($submissionGuard)) {
            return $submissionGuard;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $journalResult = VyJournalEngine::create_journal_entry([
            'org_id'        => (int) $org,
            'date'          => $body['date'] ?? gmdate('Y-m-d'),
            'type'          => 'RECEIPT',
            'description'   => sanitize_text_field($body['description'] ?? 'Invoice payment'),
            'reference'     => $body['reference'] ?? null,
            'source_module' => 'invoice_payment',
            'source_id'     => $invoice['id'],
            'lines'         => [
                [
                    'account_id' => $toAccountId,
                    'debit'      => $amount,
                    'credit'     => 0,
                    'line_memo'  => 'Invoice payment',
                ],
                [
                    'account_id' => $incomeAccountId,
                    'debit'      => 0,
                    'credit'     => $amount,
                    'line_memo'  => 'Invoice payment',
                ],
            ],
        ]);

        if (is_wp_error($journalResult)) {
            $wpdb->query('ROLLBACK');
            self::clear_payment_submission_guard($submissionGuard);
            return $journalResult;
        }

        $paymentInserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoice_payments',
            [
                'org_id'     => $org,
                'invoice_id' => $invoice['id'],
                'journal_id' => $journalResult,
                'amount'     => $amount,
                'date'       => $body['date'] ?? gmdate('Y-m-d'),
                'created_at' => current_time('mysql', true),
            ],
            ['%d','%d','%d','%f','%s','%s']
        );
        if ($paymentInserted === false) {
            $wpdb->query('ROLLBACK');
            self::clear_payment_submission_guard($submissionGuard);
            return new WP_Error('vy_payment_insert_failed', 'Failed to record invoice payment.', ['status' => 500]);
        }
        $paymentId = (int) $wpdb->insert_id;

        $updatedInvoiceForBalance = self::apply_invoice_financials((int) $org, $invoice);
        $paidTotal = self::get_paid_amount($invoice['id']);
        $status = self::determine_invoice_status((string) ($invoice['status'] ?? 'SENT'), (float) ($updatedInvoiceForBalance['adjusted_total'] ?? $invoice['total']), $paidTotal);
        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_invoices',
            ['status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $invoice['id']],
            ['%s','%s'],
            ['%d']
        );

        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            self::clear_payment_submission_guard($submissionGuard);
            return new WP_Error('vy_invoice_status_update_failed', 'Failed to update invoice status after payment.', ['status' => 500]);
        }

        $wpdb->query('COMMIT');

        $openPromises = self::fetch_open_invoice_promises((int) $org, (int) $invoice['id']);
        foreach ($openPromises as $promise) {
            $shouldKeep = $amount + 0.01 >= (float) ($promise['promised_amount'] ?? 0)
                || max(0, (float) (($updatedInvoiceForBalance['adjusted_total'] ?? $invoice['total']) - $paidTotal)) <= 0;
            if (!$shouldKeep) {
                continue;
            }

            $wpdb->update(
                $wpdb->prefix . 'vy_invoice_promises',
                [
                    'status' => 'KEPT',
                    'resolved_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                ],
                ['org_id' => (int) $org, 'id' => (int) ($promise['id'] ?? 0)],
                ['%s', '%s', '%s'],
                ['%d', '%d']
            );
        }

        if ($paymentId > 0) {
            RecordAuditLogger::log(
                (int) $org,
                'payment',
                $paymentId,
                'recorded',
                sprintf('Recorded payment of %s', RecordAuditLogger::money($amount, (string) ($invoice['currency'] ?? 'INR'))),
                [
                    'lines' => [
                        'Invoice: ' . (string) ($invoice['invoice_number'] ?? '—'),
                        'Payment date: ' . (string) ($body['date'] ?? gmdate('Y-m-d')),
                        'Journal: #' . (int) $journalResult,
                        'Balance due: ' . RecordAuditLogger::money(max(0, (float) (($updatedInvoiceForBalance['adjusted_total'] ?? $invoice['total']) - $paidTotal)), (string) ($invoice['currency'] ?? 'INR')),
                    ],
                ],
                'invoice',
                (int) $invoice['id']
            );
        }

        $responseData = [
            'success'        => true,
            'invoice_id'     => $invoice['id'],
            'payment_id'     => $paymentId > 0 ? $paymentId : null,
            'journal_id'     => $journalResult,
            'paid_amount'    => $paidTotal,
            'balance_due'    => max(0, (float) (($updatedInvoiceForBalance['adjusted_total'] ?? $invoice['total']) - $paidTotal)),
            'invoice_status' => $status,
        ];

        self::complete_payment_submission_guard($submissionGuard);

        return new WP_REST_Response($responseData, 201);
    }

    public static function refund_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        $invoice = self::apply_invoice_financials((int) $org, $invoice);
        $refundState = self::invoice_refund_state($invoice);
        if (!$refundState['can_refund']) {
            return new WP_Error('vy_invoice_refund_blocked', $refundState['reason'] ?: 'This invoice cannot be refunded.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $refundAmount = round((float) ($refundState['amount'] ?? 0), 2);
        if ($refundAmount <= 0) {
            return new WP_Error('vy_invoice_refund_amount', 'This invoice has no refundable amount.', ['status' => 400]);
        }

        $payoutAccountId = (int) ($body['payout_account_id'] ?? 0);
        $incomeAccountId = (int) ($body['income_account_id'] ?? 0);
        if ($payoutAccountId <= 0 || $incomeAccountId <= 0) {
            return new WP_Error('vy_bad_accounts', 'Refund payout and income accounts are required.', ['status' => 400]);
        }

        $payoutAccount = self::fetch_account_row((int) $org, $payoutAccountId);
        if (!$payoutAccount || !in_array(strtoupper((string) ($payoutAccount['sub_type'] ?? '')), ['BANK', 'CASH', 'WALLET'], true)) {
            return new WP_Error('vy_invalid_refund_account', 'The refund payout account must be a bank, cash, or wallet account.', ['status' => 400]);
        }
        if (($activeError = self::ensure_account_row_is_active($payoutAccount, 'vy_account_archived', 'The refund payout account is archived and cannot post refunds.')) instanceof WP_Error) {
            return $activeError;
        }

        $incomeAccount = self::fetch_account_row((int) $org, $incomeAccountId);
        if (!$incomeAccount || strtoupper((string) ($incomeAccount['type'] ?? '')) !== 'INCOME') {
            return new WP_Error('vy_invalid_income', 'The refund income account must be of type INCOME.', ['status' => 400]);
        }
        if (($activeError = self::ensure_account_row_is_active($incomeAccount, 'vy_account_archived', 'The refund income account is archived and cannot post refunds.')) instanceof WP_Error) {
            return $activeError;
        }

        $refundDate = self::normalize_date_input($body['date'] ?? null, gmdate('Y-m-d'));
        $reason = sanitize_text_field((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            return new WP_Error('vy_refund_reason_required', 'Refund reason is required.', ['status' => 400]);
        }

        global $wpdb;
        $timestamp = current_time('mysql', true);
        $wpdb->query('START TRANSACTION');

        $journalId = VyJournalEngine::create_journal_entry([
            'org_id'        => (int) $org,
            'date'          => $refundDate,
            'type'          => 'REFUND',
            'description'   => sprintf('Invoice refund for %s', (string) ($invoice['invoice_number'] ?? ('#' . $invoice['id']))),
            'reference'     => sanitize_text_field((string) ($body['reference'] ?? '')),
            'source_module' => 'invoice_refund',
            'source_id'     => (int) $invoice['id'],
            'lines'         => [
                [
                    'account_id' => $incomeAccountId,
                    'debit'      => $refundAmount,
                    'credit'     => 0,
                    'line_memo'  => 'Invoice refund reversal',
                ],
                [
                    'account_id' => $payoutAccountId,
                    'debit'      => 0,
                    'credit'     => $refundAmount,
                    'line_memo'  => 'Customer refund payout',
                ],
            ],
        ]);

        if (is_wp_error($journalId)) {
            $wpdb->query('ROLLBACK');
            return $journalId;
        }

        $refundInserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoice_refunds',
            [
                'org_id'            => (int) $org,
                'invoice_id'        => (int) $invoice['id'],
                'journal_id'        => (int) $journalId,
                'payout_account_id' => $payoutAccountId,
                'income_account_id' => $incomeAccountId,
                'amount'            => $refundAmount,
                'date'              => $refundDate,
                'reason'            => $reason,
                'created_at'        => $timestamp,
            ],
            ['%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s']
        );

        if ($refundInserted === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_refund_insert_failed', 'Failed to record invoice refund.', ['status' => 500]);
        }
        $refundId = (int) $wpdb->insert_id;

        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_invoices',
            [
                'status'     => 'VOID',
                'pdf_url'    => null,
                'updated_at' => $timestamp,
            ],
            [
                'org_id' => (int) $org,
                'id'     => (int) $invoice['id'],
            ],
            ['%s', '%s', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_invoice_refund_update_failed', 'Failed to update invoice status after refund.', ['status' => 500]);
        }

        $wpdb->query('COMMIT');

        RecordAuditLogger::log(
            (int) $org,
            'invoice_refund',
            $refundId,
            'refunded',
            sprintf('Refunded and voided invoice %s', (string) ($invoice['invoice_number'] ?? ('#' . $invoice['id']))),
            [
                'lines' => [
                    'Refund amount: ' . RecordAuditLogger::money($refundAmount, (string) ($invoice['currency'] ?? 'INR')),
                    'Refund date: ' . $refundDate,
                    'Payout account: ' . (string) ($payoutAccount['name'] ?? '—'),
                    'Income account: ' . (string) ($incomeAccount['name'] ?? '—'),
                    'Reason: ' . $reason,
                    'Journal: #' . (int) $journalId,
                ],
            ],
            'invoice',
            (int) $invoice['id']
        );

        $refundedInvoice = self::apply_invoice_financials((int) $org, array_merge($invoice, [
            'status' => 'VOID',
            'pdf_url' => null,
            'updated_at' => $timestamp,
        ]));

        return new WP_REST_Response([
            'success'         => true,
            'invoice_id'      => (int) $invoice['id'],
            'refund_id'       => $refundId,
            'journal_id'      => (int) $journalId,
            'invoice_status'  => 'VOID',
            'refunded_amount' => (float) ($refundedInvoice['refunded_amount'] ?? $refundAmount),
            'net_paid_amount' => (float) ($refundedInvoice['net_paid_amount'] ?? 0),
            'balance_due'     => (float) ($refundedInvoice['balance_due'] ?? 0),
        ], 201);
    }

    public static function next_invoice_number(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $date = $request->get_param('date');
        if ($date) {
            $date = sanitize_text_field($date);
        }
        $number = self::generate_invoice_number((int) $org, $date ?: null);
        return new WP_REST_Response(['invoice_number' => $number], 200);
    }

    public static function description_suggestions(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $table = $wpdb->prefix . 'vy_invoice_items';
        $q = sanitize_text_field($request->get_param('q') ?? '');
        $limit = min(20, max(1, (int) ($request->get_param('limit') ?? 10)));

        $sql = "SELECT DISTINCT description FROM {$table} WHERE org_id = %d AND description <> ''";
        $params = [$org];
        if ($q !== '') {
            $sql .= " AND description LIKE %s";
            $params[] = '%' . $wpdb->esc_like($q) . '%';
        }
        $sql .= " ORDER BY description ASC LIMIT {$limit}";

        $rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));
        return new WP_REST_Response(['data' => $rows ?: []], 200);
    }

    public static function list_payments(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vy_invoice_payments';
        $where = ['org_id = %d'];
        $params = [(int) $org];

        $from = sanitize_text_field((string) ($request->get_param('from') ?? ''));
        if ($from !== '') {
            $where[] = 'date >= %s';
            $params[] = $from;
        }

        $to = sanitize_text_field((string) ($request->get_param('to') ?? ''));
        if ($to !== '') {
            $where[] = 'date <= %s';
            $params[] = $to;
        }

        $invoiceId = (int) ($request->get_param('invoice_id') ?? 0);
        if ($invoiceId > 0) {
            $where[] = 'invoice_id = %d';
            $params[] = $invoiceId;
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        $whereSql = implode(' AND ', $where);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_id, journal_id, amount, date, created_at
             FROM {$table}
             WHERE {$whereSql}
             ORDER BY date DESC, id DESC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        ), ARRAY_A);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE {$whereSql}",
            ...$params
        ));

        $data = array_map(function (array $row) use ($org): array {
            $invoice = self::fetch_invoice((int) $org, (int) ($row['invoice_id'] ?? 0));
            $account = self::fetch_payment_account_summary((int) $org, !empty($row['journal_id']) ? (int) $row['journal_id'] : 0);

            $financials = $invoice ? self::apply_invoice_financials((int) $org, $invoice) : null;

            return [
                'id'         => (int) ($row['id'] ?? 0),
                'amount'     => (float) ($row['amount'] ?? 0),
                'date'       => (string) ($row['date'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'journal_id' => !empty($row['journal_id']) ? (int) $row['journal_id'] : null,
                'currency'   => (string) ($invoice['currency'] ?? 'INR'),
                'invoice'    => $invoice ? [
                    'id'             => (int) ($invoice['id'] ?? 0),
                    'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                    'customer_name'  => (string) ($invoice['customer_name'] ?? ''),
                    'status'         => (string) ($invoice['status'] ?? ''),
                    'balance_due'    => (float) ($financials['balance_due'] ?? 0),
                ] : null,
                'account'    => $account,
            ];
        }, $rows ?: []);

        return new WP_REST_Response([
            'data' => $data,
            'pagination' => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
        ], 200);
    }

    public static function ensure_recurring_runner(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (!wp_next_scheduled('kbs_process_recurring_invoices')) {
            wp_schedule_event(time() + 300, 'hourly', 'kbs_process_recurring_invoices');
        }
    }

    public static function clear_recurring_runner(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('kbs_process_recurring_invoices');
        }
    }

    public static function process_due_recurring_profiles(): void
    {
        self::run_due_recurring_profiles();
    }

    public static function list_recurring_profiles(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vy_invoice_recurring_profiles';
        $where = ['org_id = %d'];
        $params = [(int) $org];

        $status = strtoupper(sanitize_text_field((string) ($request->get_param('status') ?? '')));
        if (in_array($status, ['ACTIVE', 'PAUSED', 'ENDED'], true)) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $sourceInvoiceId = (int) ($request->get_param('source_invoice_id') ?? 0);
        if ($sourceInvoiceId > 0) {
            $where[] = 'source_invoice_id = %d';
            $params[] = $sourceInvoiceId;
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(50, max(1, (int) ($request->get_param('per_page') ?? 10)));
        $offset = ($page - 1) * $perPage;
        $whereSql = implode(' AND ', $where);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE {$whereSql}
             ORDER BY status ASC, next_run_date ASC, id DESC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$perPage, $offset])
        ), ARRAY_A);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE {$whereSql}",
            ...$params
        ));

        $data = array_map(static function (array $row) use ($org): array {
            return self::format_recurring_profile_row((int) $org, $row, false);
        }, $rows ?: []);

        return new WP_REST_Response([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ], 200);
    }

    public static function get_recurring_profile(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $profile = self::fetch_recurring_profile((int) $org, (int) $request['id']);
        if (!$profile) {
            return new WP_Error('vy_not_found', 'Recurring profile not found.', ['status' => 404]);
        }

        return new WP_REST_Response(self::format_recurring_profile_row((int) $org, $profile, true), 200);
    }

    public static function create_recurring_profile(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $sourceInvoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$sourceInvoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        if (strtoupper((string) ($sourceInvoice['status'] ?? 'SENT')) === 'VOID') {
            return new WP_Error('vy_invoice_void', 'Void invoices cannot be used for recurring billing.', ['status' => 400]);
        }

        $existingProfile = self::find_recurring_profile_by_source((int) $org, (int) $sourceInvoice['id']);
        if ($existingProfile && in_array(strtoupper((string) ($existingProfile['status'] ?? 'ACTIVE')), ['ACTIVE', 'PAUSED'], true)) {
            return new WP_Error('vy_recurring_exists', 'This invoice already has an active recurring plan.', ['status' => 400]);
        }

        $sourceItems = self::fetch_invoice_items((int) $org, (int) $sourceInvoice['id']);
        if (!$sourceItems) {
            return new WP_Error('vy_no_items', 'Recurring billing requires at least one invoice item.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $payload = self::sanitize_recurring_profile_payload((int) $org, $sourceInvoice, $body);
        $recurringContactId = !empty($sourceInvoice['contact_id']) ? (int) $sourceInvoice['contact_id'] : null;
        if (!$recurringContactId && !empty($sourceInvoice['customer_name'])) {
            $resolvedContact = self::resolve_customer_contact((int) $org, [
                'customer_name' => (string) ($sourceInvoice['customer_name'] ?? ''),
                'customer_email' => (string) ($sourceInvoice['customer_email'] ?? ''),
                'customer_phone' => (string) ($sourceInvoice['customer_phone'] ?? ''),
            ]);
            if (is_wp_error($resolvedContact)) {
                return $resolvedContact;
            }
            $recurringContactId = !empty($resolvedContact['contact_id']) ? (int) $resolvedContact['contact_id'] : null;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoice_recurring_profiles',
            [
                'org_id' => (int) $org,
                'source_invoice_id' => (int) $sourceInvoice['id'],
                'contact_id' => $recurringContactId,
                'profile_name' => $payload['profile_name'],
                'customer_name' => (string) ($sourceInvoice['customer_name'] ?? ''),
                'customer_email' => (string) ($sourceInvoice['customer_email'] ?? ''),
                'customer_phone' => (string) ($sourceInvoice['customer_phone'] ?? ''),
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'frequency' => $payload['frequency'],
                'interval_count' => $payload['interval_count'],
                'due_days' => $payload['due_days'],
                'currency' => $payload['currency'],
                'invoice_status' => $payload['invoice_status'],
                'notes' => $payload['notes'],
                'status' => $payload['status'],
                'next_run_date' => $payload['next_run_date'],
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_recurring_insert_failed', 'Failed to create recurring billing profile.', ['status' => 500]);
        }

        $profileId = (int) $wpdb->insert_id;
        foreach ($sourceItems as $item) {
            $itemInserted = $wpdb->insert(
                $wpdb->prefix . 'vy_invoice_recurring_items',
                [
                    'org_id' => (int) $org,
                    'profile_id' => $profileId,
                    'description' => sanitize_text_field((string) ($item['description'] ?? '')),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                    'tax_type' => sanitize_text_field((string) ($item['tax_type'] ?? 'GST')),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                    'created_at' => current_time('mysql', true),
                ],
                ['%d', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%s']
            );

            if ($itemInserted === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('vy_recurring_item_insert_failed', 'Failed to save recurring invoice items.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) $sourceInvoice['id'],
            'recurring_enabled',
            sprintf('Created recurring plan for invoice %s', (string) ($sourceInvoice['invoice_number'] ?? '')),
            [
                'lines' => [
                    'Plan: ' . $payload['profile_name'],
                    'Frequency: ' . $payload['interval_count'] . ' x ' . strtolower($payload['frequency']),
                    'First run: ' . $payload['next_run_date'],
                    'Generated invoices default to: ' . $payload['invoice_status'],
                ],
            ],
            'recurring_profile',
            $profileId
        );

        $profile = self::fetch_recurring_profile((int) $org, $profileId);
        return new WP_REST_Response(self::format_recurring_profile_row((int) $org, $profile ?: [], true), 201);
    }

    public static function update_recurring_profile(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $profile = self::fetch_recurring_profile((int) $org, (int) $request['id']);
        if (!$profile) {
            return new WP_Error('vy_not_found', 'Recurring profile not found.', ['status' => 404]);
        }

        $payload = self::sanitize_recurring_profile_update_payload($profile, $request->get_json_params() ?: []);
        if (!$payload) {
            return new WP_Error('vy_recurring_no_fields', 'No recurring fields were provided.', ['status' => 400]);
        }

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_invoice_recurring_profiles',
            array_merge($payload, ['updated_at' => current_time('mysql', true)]),
            [
                'org_id' => (int) $org,
                'id' => (int) $profile['id'],
            ],
            null,
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('vy_recurring_update_failed', 'Failed to update recurring profile.', ['status' => 500]);
        }

        $refreshed = self::fetch_recurring_profile((int) $org, (int) $profile['id']);
        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) ($profile['source_invoice_id'] ?? 0),
            'recurring_updated',
            sprintf('Updated recurring plan %s', (string) ($profile['profile_name'] ?? ('#' . $profile['id']))),
            [
                'lines' => self::build_recurring_update_audit_lines($profile, $refreshed ?: $profile),
            ],
            'recurring_profile',
            (int) $profile['id']
        );

        return new WP_REST_Response(self::format_recurring_profile_row((int) $org, $refreshed ?: [], true), 200);
    }

    public static function generate_recurring_profile(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $profile = self::fetch_recurring_profile((int) $org, (int) $request['id']);
        if (!$profile) {
            return new WP_Error('vy_not_found', 'Recurring profile not found.', ['status' => 404]);
        }

        $asOfDate = self::normalize_date_input($request->get_param('as_of'), gmdate('Y-m-d'));
        $generated = self::run_due_recurring_profiles((int) $org, (int) $profile['id'], $asOfDate);
        if (is_wp_error($generated)) {
            return $generated;
        }

        $refreshed = self::fetch_recurring_profile((int) $org, (int) $profile['id']);
        return new WP_REST_Response([
            'success' => true,
            'generated' => $generated,
            'profile' => $refreshed ? self::format_recurring_profile_row((int) $org, $refreshed, true) : null,
        ], 200);
    }

    public static function list_invoice_notes(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        return new WP_REST_Response([
            'data' => self::fetch_invoice_notes((int) $org, (int) $invoice['id']),
        ], 200);
    }

    public static function create_invoice_note(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }
        if (strtoupper((string) ($invoice['status'] ?? 'SENT')) === 'VOID') {
            return new WP_Error('vy_invoice_void', 'Invoice adjustments are not allowed for void invoices.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $noteType = strtoupper(sanitize_text_field((string) ($body['note_type'] ?? '')));
        if (!in_array($noteType, ['CREDIT', 'DEBIT'], true)) {
            return new WP_Error('vy_bad_note_type', 'Note type must be CREDIT or DEBIT.', ['status' => 400]);
        }

        $amount = round((float) ($body['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return new WP_Error('vy_bad_amount', 'Adjustment amount must be greater than zero.', ['status' => 400]);
        }

        $noteDate = self::normalize_date_input($body['note_date'] ?? null, gmdate('Y-m-d'));
        $reason = wp_kses_post((string) ($body['reason'] ?? ''));

        $financials = self::apply_invoice_financials((int) $org, $invoice);
        if ($noteType === 'CREDIT' && $amount > (float) ($financials['balance_due'] ?? 0)) {
            return new WP_Error('vy_credit_too_large', 'Credit notes cannot exceed the current balance due.', ['status' => 400]);
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoice_notes',
            [
                'org_id' => (int) $org,
                'invoice_id' => (int) $invoice['id'],
                'note_number' => self::generate_invoice_note_number((int) $org, $noteType, $noteDate),
                'note_type' => $noteType,
                'note_date' => $noteDate,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'POSTED',
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_note_insert_failed', 'Failed to save invoice note.', ['status' => 500]);
        }

        $noteId = (int) $wpdb->insert_id;
        $notes = self::fetch_invoice_notes((int) $org, (int) $invoice['id']);
        $createdNote = null;
        foreach ($notes as $note) {
            if ((int) ($note['id'] ?? 0) === $noteId) {
                $createdNote = $note;
                break;
            }
        }

        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) $invoice['id'],
            strtolower($noteType) . '_note_created',
            sprintf('Added %s note %s', strtolower($noteType), (string) ($createdNote['note_number'] ?? ('#' . $noteId))),
            [
                'lines' => [
                    'Amount: ' . RecordAuditLogger::money($amount, (string) ($invoice['currency'] ?? 'INR')),
                    'Date: ' . $noteDate,
                    $reason !== '' ? 'Reason: ' . wp_strip_all_tags($reason) : '',
                ],
            ]
        );

        return new WP_REST_Response([
            'note' => $createdNote,
            'financials' => self::apply_invoice_financials((int) $org, $invoice),
        ], 201);
    }

    public static function list_promises(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $filters = [
            'status' => $request->get_param('status'),
            'invoice_id' => $request->get_param('invoice_id'),
            'contact_id' => $request->get_param('contact_id'),
            'view' => $request->get_param('view'),
        ];

        return new WP_REST_Response([
            'data' => self::fetch_invoice_promises((int) $org, $filters),
        ], 200);
    }

    public static function list_invoice_promises(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        return new WP_REST_Response([
            'data' => self::fetch_invoice_promises((int) $org, ['invoice_id' => (int) $invoice['id']]),
        ], 200);
    }

    public static function create_invoice_promise(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }
        if (strtoupper((string) ($invoice['status'] ?? 'SENT')) === 'VOID') {
            return new WP_Error('vy_invoice_void', 'Promises are not allowed for void invoices.', ['status' => 400]);
        }

        $financials = self::apply_invoice_financials((int) $org, $invoice);
        if ((float) ($financials['balance_due'] ?? 0) <= 0) {
            return new WP_Error('vy_invoice_paid', 'Promises can only be recorded for invoices with an outstanding balance.', ['status' => 400]);
        }

        $body = $request->get_json_params() ?: [];
        $promisedDate = self::normalize_date_input($body['promised_date'] ?? null, null);
        if (!$promisedDate) {
            return new WP_Error('vy_bad_promised_date', 'A promised payment date is required.', ['status' => 400]);
        }

        $promisedAmount = round((float) ($body['promised_amount'] ?? 0), 2);
        if ($promisedAmount <= 0 || $promisedAmount > (float) ($financials['balance_due'] ?? 0)) {
            return new WP_Error('vy_bad_promised_amount', 'Promised amount must be greater than zero and within the current balance due.', ['status' => 400]);
        }

        $notes = wp_kses_post((string) ($body['notes'] ?? ''));

        global $wpdb;
        $existingOpen = self::fetch_open_invoice_promises((int) $org, (int) $invoice['id']);
        foreach ($existingOpen as $openPromise) {
            $wpdb->update(
                $wpdb->prefix . 'vy_invoice_promises',
                [
                    'status' => 'SUPERSEDED',
                    'resolved_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                ],
                ['org_id' => (int) $org, 'id' => (int) ($openPromise['id'] ?? 0)],
                ['%s', '%s', '%s'],
                ['%d', '%d']
            );
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoice_promises',
            [
                'org_id' => (int) $org,
                'invoice_id' => (int) $invoice['id'],
                'contact_id' => !empty($invoice['contact_id']) ? (int) $invoice['contact_id'] : null,
                'promised_date' => $promisedDate,
                'promised_amount' => $promisedAmount,
                'notes' => $notes,
                'status' => 'OPEN',
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_promise_insert_failed', 'Failed to save the payment promise.', ['status' => 500]);
        }

        $promiseId = (int) $wpdb->insert_id;
        $promise = self::fetch_promise((int) $org, $promiseId);

        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) $invoice['id'],
            'promise_recorded',
            'Recorded promise to pay',
            [
                'lines' => [
                    'Promised date: ' . $promisedDate,
                    'Promised amount: ' . RecordAuditLogger::money($promisedAmount, (string) ($invoice['currency'] ?? 'INR')),
                    $notes !== '' ? 'Notes: ' . wp_strip_all_tags($notes) : '',
                ],
            ]
        );

        return new WP_REST_Response([
            'promise' => $promise ? self::format_promise_row((int) $org, $promise) : null,
        ], 201);
    }

    public static function update_invoice_promise(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $promise = self::fetch_promise((int) $org, (int) $request['id']);
        if (!$promise) {
            return new WP_Error('vy_not_found', 'Payment promise not found.', ['status' => 404]);
        }

        $body = $request->get_json_params() ?: [];
        $payload = [];

        if (array_key_exists('promised_date', $body)) {
            $promisedDate = self::normalize_date_input($body['promised_date'], (string) ($promise['promised_date'] ?? ''));
            if ($promisedDate) {
                $payload['promised_date'] = $promisedDate;
            }
        }

        if (array_key_exists('promised_amount', $body)) {
            $invoice = self::fetch_invoice((int) $org, (int) ($promise['invoice_id'] ?? 0));
            $financials = $invoice ? self::apply_invoice_financials((int) $org, $invoice) : null;
            $promisedAmount = round((float) $body['promised_amount'], 2);
            if ($promisedAmount <= 0 || ($financials && $promisedAmount > (float) ($financials['balance_due'] ?? 0))) {
                return new WP_Error('vy_bad_promised_amount', 'Promised amount must be within the current balance due.', ['status' => 400]);
            }
            $payload['promised_amount'] = $promisedAmount;
        }

        if (array_key_exists('notes', $body)) {
            $payload['notes'] = wp_kses_post((string) $body['notes']);
        }

        if (array_key_exists('resolution_note', $body)) {
            $payload['resolution_note'] = sanitize_text_field((string) $body['resolution_note']);
        }

        if (array_key_exists('status', $body)) {
            $status = strtoupper(sanitize_text_field((string) $body['status']));
            if (!in_array($status, ['OPEN', 'KEPT', 'BROKEN', 'CANCELLED', 'SUPERSEDED'], true)) {
                return new WP_Error('vy_bad_promise_status', 'Invalid promise status.', ['status' => 400]);
            }
            $payload['status'] = $status;
            $payload['resolved_at'] = $status === 'OPEN' ? null : current_time('mysql', true);
        }

        if (!$payload) {
            return new WP_Error('vy_promise_no_fields', 'No promise changes were provided.', ['status' => 400]);
        }

        $payload['updated_at'] = current_time('mysql', true);

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_invoice_promises',
            $payload,
            ['org_id' => (int) $org, 'id' => (int) $promise['id']],
            null,
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('vy_promise_update_failed', 'Failed to update the payment promise.', ['status' => 500]);
        }

        $refreshed = self::fetch_promise((int) $org, (int) $promise['id']);
        RecordAuditLogger::log(
            (int) $org,
            'invoice',
            (int) ($promise['invoice_id'] ?? 0),
            'promise_updated',
            'Updated promise to pay',
            [
                'lines' => self::build_promise_update_audit_lines($promise, $refreshed ?: $promise),
            ]
        );

        return new WP_REST_Response([
            'promise' => $refreshed ? self::format_promise_row((int) $org, $refreshed) : null,
        ], 200);
    }

    private static function create_invoice_record(int $org, array $body, array $options = [])
    {
        global $wpdb;

        $items = $body['items'] ?? [];
        if (!$items || !is_array($items)) {
            return new WP_Error('vy_no_items', 'At least one item is required.', ['status' => 400]);
        }

        $wpdb->query('START TRANSACTION');

        $contactData = self::resolve_customer_contact($org, $body);
        if (is_wp_error($contactData)) {
            $wpdb->query('ROLLBACK');
            return $contactData;
        }

        $invoiceNumber = sanitize_text_field((string) ($options['force_invoice_number'] ?? ($body['invoice_number'] ?? '')));
        $date = self::normalize_date_input($body['date'] ?? null, gmdate('Y-m-d'));
        $dueInput = array_key_exists('due_date', $body)
            ? sanitize_text_field((string) $body['due_date'])
            : null;
        $status = strtoupper((string) ($body['status'] ?? 'SENT'));
        if (!$invoiceNumber) {
            $invoiceNumber = self::generate_invoice_number($org, $date);
        }
        $due = self::resolve_due_date($org, $date, $dueInput);
        $templateId = self::resolve_template_id($org);
        $currency = strtoupper(sanitize_text_field((string) ($body['currency'] ?? 'INR')));
        if ($currency === '') {
            $currency = 'INR';
        }

        $totals = self::calculate_totals($items);
        $timestamp = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoices',
            [
                'org_id' => $org,
                'contact_id' => $contactData['contact_id'],
                'invoice_number' => $invoiceNumber,
                'customer_name' => $contactData['name'],
                'customer_email' => $contactData['email'],
                'customer_phone' => $contactData['phone'],
                'date' => $date,
                'due_date' => $due,
                'currency' => $currency,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'status' => in_array($status, ['DRAFT', 'SENT', 'PARTIAL', 'PAID', 'VOID'], true) ? $status : 'SENT',
                'template_id' => $templateId,
                'notes' => wp_kses_post($body['notes'] ?? ''),
                'recurring_profile_id' => !empty($options['recurring_profile_id']) ? (int) $options['recurring_profile_id'] : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            ['%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%d','%s','%s']
        );

        if ($inserted === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('vy_invoice_insert_failed', 'Failed to create invoice.', ['status' => 500]);
        }
        $invoiceId = (int) $wpdb->insert_id;

        foreach ($totals['lines'] as $line) {
            $insertedItem = $wpdb->insert(
                $wpdb->prefix . 'vy_invoice_items',
                [
                    'org_id' => $org,
                    'invoice_id' => $invoiceId,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'tax_type' => $line['tax_type'],
                    'line_total' => $line['line_total'],
                    'created_at' => $timestamp,
                ],
                ['%d','%d','%s','%f','%f','%f','%f','%s','%f','%s']
            );

            if ($insertedItem === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('vy_invoice_items_insert_failed', 'Failed to save invoice items.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        $responsePayload = ['id' => $invoiceId];
        $auditLines = [
            'Customer: ' . ($contactData['name'] ?: 'Walk-in customer'),
            'Status: ' . (in_array($status, ['DRAFT', 'SENT', 'PARTIAL', 'PAID', 'VOID'], true) ? $status : 'SENT'),
            'Items: ' . count($totals['lines']),
            'Total: ' . RecordAuditLogger::money((float) $totals['total'], $currency),
        ];
        foreach ((array) ($options['audit_lines'] ?? []) as $extraLine) {
            $extraLine = trim((string) $extraLine);
            if ($extraLine !== '') {
                $auditLines[] = $extraLine;
            }
        }

        RecordAuditLogger::log(
            $org,
            'invoice',
            $invoiceId,
            'created',
            sprintf('Created invoice %s', $invoiceNumber),
            ['lines' => $auditLines],
            !empty($options['related_record_type']) ? (string) $options['related_record_type'] : null,
            !empty($options['related_record_id']) ? (int) $options['related_record_id'] : null
        );

        if (empty($options['suppress_auto_email'])) {
            $settings = vy_fetch_invoice_template_settings($org);
            if (!empty($settings['auto_email_on_create'])) {
                $emailResult = vy_send_invoice_email($invoiceId);
                if (is_wp_error($emailResult)) {
                    SystemLogger::log_event(
                        'invoice_auto_email_failed',
                        'Auto email failed after invoice creation.',
                        [
                            'org_id' => $org,
                            'invoice_id' => $invoiceId,
                            'error_message' => $emailResult->get_error_message(),
                        ],
                        get_current_user_id(),
                        'backend/Api/VyRestInvoices.php'
                    );
                    $responsePayload['email_error'] = $emailResult->get_error_message();
                } else {
                    $responsePayload['email_sent_to'] = $emailResult['recipients'];
                    $responsePayload['email_sent_at'] = $emailResult['sent_at'];
                    self::log_invoice_email_audit($org, $invoiceId, $invoiceNumber, $emailResult);
                }
            }
        }

        if (empty($options['suppress_internal_notification'])) {
            try {
                InternalDocumentNotifier::notify_invoice_created($org, $invoiceId);
            } catch (\Throwable $throwable) {
                SystemLogger::log_event(
                    'invoice_internal_notification_failed',
                    'Internal invoice creation notification failed.',
                    [
                        'org_id' => $org,
                        'invoice_id' => $invoiceId,
                        'error_message' => $throwable->getMessage(),
                    ],
                    get_current_user_id(),
                    'backend/Api/VyRestInvoices.php'
                );
            }
        }

        $responsePayload['invoice_number'] = $invoiceNumber;
        $responsePayload['created_at'] = $timestamp;

        return $responsePayload;
    }

    private static function fetch_recurring_profile(int $org_id, int $profile_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_recurring_profiles
             WHERE org_id = %d AND id = %d
             LIMIT 1",
            $org_id,
            $profile_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function find_recurring_profile_by_source(int $org_id, int $source_invoice_id): ?array
    {
        if ($source_invoice_id <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_recurring_profiles
             WHERE org_id = %d AND source_invoice_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $org_id,
            $source_invoice_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function fetch_recurring_profile_items(int $org_id, int $profile_id): array
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

    private static function sanitize_recurring_profile_payload(int $org_id, array $sourceInvoice, array $body): array
    {
        $startDate = self::normalize_date_input($body['start_date'] ?? ($sourceInvoice['date'] ?? null), gmdate('Y-m-d'));
        $endDate = !empty($body['end_date']) ? self::normalize_date_input($body['end_date'], null) : null;
        $frequency = self::normalize_recurring_frequency($body['frequency'] ?? 'MONTHLY');
        $interval = min(12, max(1, (int) ($body['interval_count'] ?? 1)));
        $dueDays = max(0, (int) ($body['due_days'] ?? self::calculate_invoice_due_days($org_id, $sourceInvoice)));
        $profileName = sanitize_text_field((string) ($body['profile_name'] ?? ('Recurring ' . ($sourceInvoice['invoice_number'] ?? 'Invoice'))));
        if ($profileName === '') {
            $profileName = 'Recurring Invoice';
        }
        $invoiceStatus = strtoupper((string) ($body['invoice_status'] ?? ($sourceInvoice['status'] ?? 'DRAFT')));
        if (!in_array($invoiceStatus, ['DRAFT', 'SENT'], true)) {
            $invoiceStatus = 'DRAFT';
        }

        $status = strtoupper((string) ($body['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'PAUSED', 'ENDED'], true)) {
            $status = 'ACTIVE';
        }

        $nextRunDate = $status === 'ENDED' ? null : $startDate;
        if ($endDate && $nextRunDate && $nextRunDate > $endDate) {
            $nextRunDate = null;
            $status = 'ENDED';
        }

        return [
            'profile_name' => $profileName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'frequency' => $frequency,
            'interval_count' => $interval,
            'due_days' => $dueDays,
            'currency' => strtoupper((string) ($sourceInvoice['currency'] ?? 'INR')) ?: 'INR',
            'invoice_status' => $invoiceStatus,
            'notes' => wp_kses_post((string) ($body['notes'] ?? ($sourceInvoice['notes'] ?? ''))),
            'status' => $status,
            'next_run_date' => $nextRunDate,
        ];
    }

    private static function sanitize_recurring_profile_update_payload(array $profile, array $body): array
    {
        $payload = [];

        if (array_key_exists('profile_name', $body)) {
            $profileName = sanitize_text_field((string) $body['profile_name']);
            if ($profileName !== '') {
                $payload['profile_name'] = $profileName;
            }
        }

        $frequency = strtoupper((string) ($profile['frequency'] ?? 'MONTHLY'));
        if (array_key_exists('frequency', $body)) {
            $frequency = self::normalize_recurring_frequency($body['frequency']);
            $payload['frequency'] = $frequency;
        }

        $interval = (int) ($profile['interval_count'] ?? 1);
        if (array_key_exists('interval_count', $body)) {
            $interval = min(12, max(1, (int) $body['interval_count']));
            $payload['interval_count'] = $interval;
        }

        $endDate = array_key_exists('end_date', $body)
            ? (!empty($body['end_date']) ? self::normalize_date_input($body['end_date'], null) : null)
            : (!empty($profile['end_date']) ? (string) $profile['end_date'] : null);
        if (array_key_exists('end_date', $body)) {
            $payload['end_date'] = $endDate;
        }

        if (array_key_exists('due_days', $body)) {
            $payload['due_days'] = max(0, (int) $body['due_days']);
        }

        if (array_key_exists('invoice_status', $body)) {
            $invoiceStatus = strtoupper((string) $body['invoice_status']);
            if (in_array($invoiceStatus, ['DRAFT', 'SENT'], true)) {
                $payload['invoice_status'] = $invoiceStatus;
            }
        }

        if (array_key_exists('notes', $body)) {
            $payload['notes'] = wp_kses_post((string) $body['notes']);
        }

        if (array_key_exists('start_date', $body)) {
            $payload['start_date'] = self::normalize_date_input($body['start_date'], (string) ($profile['start_date'] ?? gmdate('Y-m-d')));
        }

        $status = strtoupper((string) ($profile['status'] ?? 'ACTIVE'));
        if (array_key_exists('status', $body)) {
            $status = strtoupper((string) $body['status']);
            if (in_array($status, ['ACTIVE', 'PAUSED', 'ENDED'], true)) {
                $payload['status'] = $status;
            }
        }

        $lastRunDate = !empty($profile['last_run_date']) ? (string) $profile['last_run_date'] : null;
        if (array_key_exists('start_date', $body) || array_key_exists('frequency', $body) || array_key_exists('interval_count', $body) || array_key_exists('end_date', $body) || array_key_exists('status', $body)) {
            if ($status === 'ENDED') {
                $payload['next_run_date'] = null;
            } elseif ($lastRunDate) {
                $payload['next_run_date'] = self::calculate_next_recurring_date($lastRunDate, $frequency, $interval, $endDate);
            } else {
                $payload['next_run_date'] = $payload['start_date'] ?? (string) ($profile['start_date'] ?? gmdate('Y-m-d'));
                if ($endDate && $payload['next_run_date'] > $endDate) {
                    $payload['next_run_date'] = null;
                    $payload['status'] = 'ENDED';
                }
            }
        }

        return $payload;
    }

    private static function format_recurring_profile_row(int $org_id, array $profile, bool $includeItems): array
    {
        $lastInvoice = !empty($profile['last_invoice_id'])
            ? self::fetch_invoice($org_id, (int) $profile['last_invoice_id'])
            : null;
        $sourceInvoice = !empty($profile['source_invoice_id'])
            ? self::fetch_invoice($org_id, (int) $profile['source_invoice_id'])
            : null;

        $row = [
            'id' => (int) ($profile['id'] ?? 0),
            'profile_name' => (string) ($profile['profile_name'] ?? ''),
            'source_invoice_id' => !empty($profile['source_invoice_id']) ? (int) $profile['source_invoice_id'] : null,
            'contact_id' => !empty($profile['contact_id']) ? (int) $profile['contact_id'] : null,
            'customer_name' => (string) ($profile['customer_name'] ?? ''),
            'customer_email' => (string) ($profile['customer_email'] ?? ''),
            'customer_phone' => (string) ($profile['customer_phone'] ?? ''),
            'start_date' => (string) ($profile['start_date'] ?? ''),
            'end_date' => !empty($profile['end_date']) ? (string) $profile['end_date'] : null,
            'frequency' => (string) ($profile['frequency'] ?? 'MONTHLY'),
            'interval_count' => (int) ($profile['interval_count'] ?? 1),
            'due_days' => (int) ($profile['due_days'] ?? 0),
            'currency' => (string) ($profile['currency'] ?? 'INR'),
            'invoice_status' => (string) ($profile['invoice_status'] ?? 'DRAFT'),
            'notes' => (string) ($profile['notes'] ?? ''),
            'status' => (string) ($profile['status'] ?? 'ACTIVE'),
            'next_run_date' => !empty($profile['next_run_date']) ? (string) $profile['next_run_date'] : null,
            'last_run_date' => !empty($profile['last_run_date']) ? (string) $profile['last_run_date'] : null,
            'generated_count' => (int) ($profile['generated_count'] ?? 0),
            'can_generate_now' => !empty($profile['next_run_date']) && (string) $profile['next_run_date'] <= gmdate('Y-m-d') && strtoupper((string) ($profile['status'] ?? 'ACTIVE')) === 'ACTIVE',
            'last_invoice' => $lastInvoice ? [
                'id' => (int) ($lastInvoice['id'] ?? 0),
                'invoice_number' => (string) ($lastInvoice['invoice_number'] ?? ''),
                'status' => (string) ($lastInvoice['status'] ?? ''),
                'date' => (string) ($lastInvoice['date'] ?? ''),
            ] : null,
            'source_invoice' => $sourceInvoice ? [
                'id' => (int) ($sourceInvoice['id'] ?? 0),
                'invoice_number' => (string) ($sourceInvoice['invoice_number'] ?? ''),
            ] : null,
        ];

        if ($includeItems) {
            $row['items'] = self::fetch_recurring_profile_items($org_id, (int) ($profile['id'] ?? 0));
        }

        return $row;
    }

    private static function run_due_recurring_profiles(?int $org_id = null, ?int $profile_id = null, ?string $asOfDate = null)
    {
        global $wpdb;

        $asOfDate = self::normalize_date_input($asOfDate, gmdate('Y-m-d'));
        $where = ["status = 'ACTIVE'", 'next_run_date IS NOT NULL', 'next_run_date <= %s'];
        $params = [$asOfDate];

        if ($org_id !== null && $org_id > 0) {
            $where[] = 'org_id = %d';
            $params[] = $org_id;
        }

        if ($profile_id !== null && $profile_id > 0) {
            $where[] = 'id = %d';
            $params[] = $profile_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_recurring_profiles
             WHERE " . implode(' AND ', $where) . "
             ORDER BY next_run_date ASC, id ASC
             LIMIT 25",
            ...$params
        ), ARRAY_A);

        if ($profile_id !== null && !$rows) {
            return new WP_Error('vy_recurring_not_due', 'This recurring plan is not due for generation yet.', ['status' => 400]);
        }

        $generated = [];
        foreach ($rows ?: [] as $profile) {
            $result = self::process_recurring_profile_generations((int) ($profile['org_id'] ?? 0), $profile, $asOfDate);
            if (is_wp_error($result)) {
                if ($profile_id !== null) {
                    return $result;
                }

                SystemLogger::log_event(
                    'recurring_generation_failed',
                    'Recurring invoice generation failed.',
                    [
                        'org_id' => (int) ($profile['org_id'] ?? 0),
                        'profile_id' => (int) ($profile['id'] ?? 0),
                        'error_message' => $result->get_error_message(),
                    ],
                    get_current_user_id(),
                    'backend/Api/VyRestInvoices.php'
                );
                continue;
            }

            $generated = array_merge($generated, $result);
        }

        return $generated;
    }

    private static function process_recurring_profile_generations(int $org_id, array $profile, string $asOfDate)
    {
        if ($org_id <= 0 || empty($profile['id'])) {
            return new WP_Error('vy_recurring_invalid_profile', 'Recurring profile could not be resolved.', ['status' => 400]);
        }

        $items = self::fetch_recurring_profile_items($org_id, (int) $profile['id']);
        if (!$items) {
            return new WP_Error('vy_no_items', 'Recurring billing profile has no invoice items.', ['status' => 400]);
        }

        $generated = [];
        $currentProfile = $profile;
        $runs = 0;

        while (!empty($currentProfile['next_run_date']) && (string) $currentProfile['next_run_date'] <= $asOfDate && $runs < 12) {
            $invoiceDate = (string) $currentProfile['next_run_date'];
            $body = [
                'contact_id' => !empty($currentProfile['contact_id']) ? (int) $currentProfile['contact_id'] : null,
                'customer_name' => (string) ($currentProfile['customer_name'] ?? ''),
                'customer_email' => (string) ($currentProfile['customer_email'] ?? ''),
                'customer_phone' => (string) ($currentProfile['customer_phone'] ?? ''),
                'date' => $invoiceDate,
                'due_date' => self::resolve_due_date_from_days($invoiceDate, (int) ($currentProfile['due_days'] ?? 0)),
                'currency' => (string) ($currentProfile['currency'] ?? 'INR'),
                'status' => (string) ($currentProfile['invoice_status'] ?? 'DRAFT'),
                'notes' => (string) ($currentProfile['notes'] ?? ''),
                'items' => array_map(static function (array $item): array {
                    return [
                        'description' => (string) ($item['description'] ?? ''),
                        'quantity' => (float) ($item['quantity'] ?? 0),
                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                        'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    ];
                }, $items),
            ];

            $created = self::create_invoice_record($org_id, $body, [
                'recurring_profile_id' => (int) $currentProfile['id'],
                'suppress_auto_email' => true,
                'audit_lines' => [
                    'Generated from recurring plan: ' . (string) ($currentProfile['profile_name'] ?? ('#' . $currentProfile['id'])),
                    'Scheduled run date: ' . $invoiceDate,
                ],
                'related_record_type' => 'recurring_profile',
                'related_record_id' => (int) $currentProfile['id'],
            ]);
            if (is_wp_error($created)) {
                return $created;
            }

            $generated[] = [
                'profile_id' => (int) $currentProfile['id'],
                'invoice_id' => (int) ($created['id'] ?? 0),
                'invoice_number' => (string) ($created['invoice_number'] ?? ''),
                'date' => $invoiceDate,
            ];

            $nextRunDate = self::calculate_next_recurring_date(
                $invoiceDate,
                (string) ($currentProfile['frequency'] ?? 'MONTHLY'),
                (int) ($currentProfile['interval_count'] ?? 1),
                !empty($currentProfile['end_date']) ? (string) $currentProfile['end_date'] : null
            );
            $nextStatus = $nextRunDate ? 'ACTIVE' : 'ENDED';

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'vy_invoice_recurring_profiles',
                [
                    'last_run_date' => $invoiceDate,
                    'last_invoice_id' => (int) ($created['id'] ?? 0),
                    'generated_count' => (int) ($currentProfile['generated_count'] ?? 0) + 1,
                    'next_run_date' => $nextRunDate,
                    'status' => $nextStatus,
                    'updated_at' => current_time('mysql', true),
                ],
                ['org_id' => $org_id, 'id' => (int) $currentProfile['id']],
                ['%s', '%d', '%d', '%s', '%s', '%s'],
                ['%d', '%d']
            );

            RecordAuditLogger::log(
                $org_id,
                'invoice',
                (int) ($created['id'] ?? 0),
                'recurring_generated',
                sprintf('Generated invoice %s from recurring plan', (string) ($created['invoice_number'] ?? '')),
                [
                    'lines' => [
                        'Recurring plan: ' . (string) ($currentProfile['profile_name'] ?? ('#' . $currentProfile['id'])),
                        'Run date: ' . $invoiceDate,
                    ],
                ],
                'recurring_profile',
                (int) $currentProfile['id']
            );

            $currentProfile['last_run_date'] = $invoiceDate;
            $currentProfile['last_invoice_id'] = (int) ($created['id'] ?? 0);
            $currentProfile['generated_count'] = (int) ($currentProfile['generated_count'] ?? 0) + 1;
            $currentProfile['next_run_date'] = $nextRunDate;
            $currentProfile['status'] = $nextStatus;
            $runs++;
        }

        return $generated;
    }

    private static function calculate_invoice_due_days(int $org_id, array $invoice): int
    {
        $date = !empty($invoice['date']) ? (string) $invoice['date'] : gmdate('Y-m-d');
        $dueDate = !empty($invoice['due_date']) ? (string) $invoice['due_date'] : self::resolve_due_date($org_id, $date, null);

        try {
            $start = new \DateTimeImmutable($date);
            $end = new \DateTimeImmutable($dueDate);
            $days = (int) $start->diff($end)->format('%r%a');
            return max(0, $days);
        } catch (\Throwable $throwable) {
            return 0;
        }
    }

    private static function normalize_recurring_frequency($value): string
    {
        $frequency = strtoupper(sanitize_text_field((string) $value));
        if (!in_array($frequency, ['WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY'], true)) {
            $frequency = 'MONTHLY';
        }

        return $frequency;
    }

    private static function calculate_next_recurring_date(string $anchorDate, string $frequency, int $interval, ?string $endDate): ?string
    {
        try {
            $date = new \DateTimeImmutable($anchorDate, new \DateTimeZone('UTC'));
        } catch (\Throwable $throwable) {
            return null;
        }

        $interval = max(1, $interval);
        $modifier = match (self::normalize_recurring_frequency($frequency)) {
            'WEEKLY' => sprintf('+%d week', $interval),
            'QUARTERLY' => sprintf('+%d month', $interval * 3),
            'YEARLY' => sprintf('+%d year', $interval),
            default => sprintf('+%d month', $interval),
        };

        $next = $date->modify($modifier);
        if (!$next) {
            return null;
        }

        $nextDate = $next->format('Y-m-d');
        if ($endDate && $nextDate > $endDate) {
            return null;
        }

        return $nextDate;
    }

    private static function resolve_due_date_from_days(string $invoiceDate, int $dueDays): string
    {
        if ($dueDays <= 0) {
            return $invoiceDate;
        }

        try {
            $date = new \DateTimeImmutable($invoiceDate, new \DateTimeZone('UTC'));
            return $date->modify(sprintf('+%d days', $dueDays))->format('Y-m-d');
        } catch (\Throwable $throwable) {
            return $invoiceDate;
        }
    }

    private static function normalize_date_input($value, ?string $fallback): ?string
    {
        $candidate = is_string($value) ? trim($value) : '';
        if ($candidate !== '' && strtotime($candidate)) {
            return gmdate('Y-m-d', strtotime($candidate));
        }

        if ($fallback !== null && strtotime($fallback)) {
            return gmdate('Y-m-d', strtotime($fallback));
        }

        return $fallback;
    }

    private static function build_recurring_update_audit_lines(array $before, array $after): array
    {
        $lines = [];
        self::append_change_line($lines, 'Plan', (string) ($before['profile_name'] ?? ''), (string) ($after['profile_name'] ?? ''));
        self::append_change_line($lines, 'Frequency', (string) (($before['interval_count'] ?? 1) . ' x ' . ($before['frequency'] ?? '')), (string) (($after['interval_count'] ?? 1) . ' x ' . ($after['frequency'] ?? '')));
        self::append_change_line($lines, 'Next run', (string) ($before['next_run_date'] ?? ''), (string) ($after['next_run_date'] ?? ''));
        self::append_change_line($lines, 'Status', (string) ($before['status'] ?? ''), (string) ($after['status'] ?? ''));
        return $lines ?: ['Recurring plan settings saved.'];
    }

    private static function fetch_invoice_notes(int $org_id, int $invoice_id): array
    {
        if ($invoice_id <= 0) {
            return [];
        }

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_id, note_number, note_type, note_date, amount, reason, status, created_at
             FROM {$wpdb->prefix}vy_invoice_notes
             WHERE org_id = %d AND invoice_id = %d
             ORDER BY note_date DESC, id DESC",
            $org_id,
            $invoice_id
        ), ARRAY_A);

        return array_map([__CLASS__, 'format_note_row'], $rows ?: []);
    }

    private static function format_note_row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'invoice_id' => (int) ($row['invoice_id'] ?? 0),
            'note_number' => (string) ($row['note_number'] ?? ''),
            'note_type' => (string) ($row['note_type'] ?? ''),
            'note_date' => (string) ($row['note_date'] ?? ''),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'reason' => (string) ($row['reason'] ?? ''),
            'status' => (string) ($row['status'] ?? 'POSTED'),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private static function generate_invoice_note_number(int $org_id, string $note_type, ?string $note_date = null): string
    {
        global $wpdb;

        $prefix = $note_type === 'DEBIT' ? 'DBN/' : 'CRN/';
        $year = gmdate('Y', strtotime($note_date ?: gmdate('Y-m-d')));
        $fullPrefix = $prefix . $year . '/';
        $likePattern = $wpdb->esc_like($fullPrefix) . '%';

        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT note_number
             FROM {$wpdb->prefix}vy_invoice_notes
             WHERE org_id = %d AND note_number LIKE %s
             ORDER BY id DESC
             LIMIT 1",
            $org_id,
            $likePattern
        ));

        $next = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return $fullPrefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private static function apply_invoice_financials(int $org_id, array $invoice): array
    {
        $paidAmount = self::get_paid_amount((int) ($invoice['id'] ?? 0));
        $noteTotals = vy_invoice_note_totals_for_invoice($org_id, (int) ($invoice['id'] ?? 0));
        $refundedAmount = self::get_refunded_amount($org_id, (int) ($invoice['id'] ?? 0));
        $invoice = vy_invoice_apply_adjustments($invoice, $paidAmount, $noteTotals);
        $invoice['refunded_amount'] = $refundedAmount;
        $invoice['net_paid_amount'] = round(max(0, (float) ($invoice['paid_amount'] ?? 0) - $refundedAmount), 2);

        if (strtoupper((string) ($invoice['status'] ?? 'SENT')) === 'VOID' && $refundedAmount > 0) {
            $invoice['balance_due'] = 0.0;
        }

        return $invoice;
    }

    private static function fetch_invoice_promises(int $org_id, array $filters = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'vy_invoice_promises';
        $where = ['org_id = %d'];
        $params = [$org_id];

        $invoiceId = (int) ($filters['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $where[] = 'invoice_id = %d';
            $params[] = $invoiceId;
        }

        $contactId = (int) ($filters['contact_id'] ?? 0);
        if ($contactId > 0) {
            $where[] = 'contact_id = %d';
            $params[] = $contactId;
        }

        $status = strtoupper(sanitize_text_field((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE " . implode(' AND ', $where) . "
             ORDER BY promised_date ASC, id DESC",
            ...$params
        ), ARRAY_A);

        $data = array_map(function (array $row) use ($org_id): array {
            return self::format_promise_row($org_id, $row);
        }, $rows ?: []);

        $view = strtolower(sanitize_text_field((string) ($filters['view'] ?? '')));
        if ($view === 'overdue') {
            $today = gmdate('Y-m-d');
            $data = array_values(array_filter($data, static function (array $row) use ($today): bool {
                return strtoupper((string) ($row['status'] ?? 'OPEN')) === 'OPEN'
                    && !empty($row['promised_date'])
                    && (string) $row['promised_date'] < $today;
            }));
        }

        return $data;
    }

    private static function fetch_open_invoice_promises(int $org_id, int $invoice_id): array
    {
        return array_values(array_filter(
            self::fetch_invoice_promises($org_id, ['invoice_id' => $invoice_id]),
            static function (array $promise): bool {
                return strtoupper((string) ($promise['status'] ?? 'OPEN')) === 'OPEN';
            }
        ));
    }

    private static function fetch_promise(int $org_id, int $promise_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}vy_invoice_promises
             WHERE org_id = %d AND id = %d
             LIMIT 1",
            $org_id,
            $promise_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function get_invoice_refunds(int $org_id, int $invoice_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, journal_id, payout_account_id, income_account_id, amount, date, reason, created_at
             FROM {$wpdb->prefix}vy_invoice_refunds
             WHERE org_id = %d AND invoice_id = %d
             ORDER BY date DESC, id DESC",
            $org_id,
            $invoice_id
        ), ARRAY_A);

        if (!$rows) {
            return [];
        }

        return array_map(function (array $row) use ($org_id): array {
            $payoutAccount = !empty($row['payout_account_id']) ? self::fetch_account_row($org_id, (int) $row['payout_account_id']) : null;
            $incomeAccount = !empty($row['income_account_id']) ? self::fetch_account_row($org_id, (int) $row['income_account_id']) : null;

            return [
                'id'             => (int) ($row['id'] ?? 0),
                'journal_id'     => !empty($row['journal_id']) ? (int) $row['journal_id'] : null,
                'amount'         => round((float) ($row['amount'] ?? 0), 2),
                'date'           => (string) ($row['date'] ?? ''),
                'reason'         => (string) ($row['reason'] ?? ''),
                'created_at'     => (string) ($row['created_at'] ?? ''),
                'payout_account' => $payoutAccount ? [
                    'id'   => (int) ($payoutAccount['id'] ?? 0),
                    'name' => (string) ($payoutAccount['name'] ?? ''),
                ] : null,
                'income_account' => $incomeAccount ? [
                    'id'   => (int) ($incomeAccount['id'] ?? 0),
                    'name' => (string) ($incomeAccount['name'] ?? ''),
                ] : null,
            ];
        }, $rows);
    }

    private static function get_refunded_amount(int $org_id, int $invoice_id): float
    {
        if ($invoice_id <= 0) {
            return 0.0;
        }

        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}vy_invoice_refunds WHERE invoice_id = %d",
            $invoice_id
        ));

        return round((float) ($sum ?: 0), 2);
    }

    private static function invoice_refund_state(array $invoice): array
    {
        $status = strtoupper((string) ($invoice['status'] ?? 'SENT'));
        $paidAmount = round((float) ($invoice['paid_amount'] ?? 0), 2);
        $refundedAmount = round((float) ($invoice['refunded_amount'] ?? 0), 2);
        $netPaidAmount = round(max(0, (float) ($invoice['net_paid_amount'] ?? ($paidAmount - $refundedAmount))), 2);
        $balanceDue = round((float) ($invoice['balance_due'] ?? 0), 2);

        if ($status === 'VOID' && $refundedAmount > 0) {
            return [
                'can_refund' => false,
                'reason'     => 'This invoice has already been refunded and voided.',
                'amount'     => 0.0,
            ];
        }

        if ($status === 'VOID') {
            return [
                'can_refund' => false,
                'reason'     => 'Void invoices cannot be refunded.',
                'amount'     => 0.0,
            ];
        }

        if ($status !== 'PAID') {
            return [
                'can_refund' => false,
                'reason'     => 'Only fully paid invoices can be cancelled and refunded.',
                'amount'     => 0.0,
            ];
        }

        if ($paidAmount <= 0 || $netPaidAmount <= 0) {
            return [
                'can_refund' => false,
                'reason'     => 'This invoice has no refundable payment balance.',
                'amount'     => 0.0,
            ];
        }

        if ($balanceDue > 0.01) {
            return [
                'can_refund' => false,
                'reason'     => 'Only fully settled invoices can be refunded.',
                'amount'     => 0.0,
            ];
        }

        return [
            'can_refund' => true,
            'reason'     => null,
            'amount'     => $netPaidAmount,
        ];
    }

    private static function format_promise_row(int $org_id, array $row): array
    {
        $invoice = !empty($row['invoice_id']) ? self::fetch_invoice($org_id, (int) $row['invoice_id']) : null;
        $financials = $invoice ? self::apply_invoice_financials($org_id, $invoice) : null;
        $today = gmdate('Y-m-d');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'invoice_id' => !empty($row['invoice_id']) ? (int) $row['invoice_id'] : null,
            'contact_id' => !empty($row['contact_id']) ? (int) $row['contact_id'] : null,
            'promised_date' => (string) ($row['promised_date'] ?? ''),
            'promised_amount' => round((float) ($row['promised_amount'] ?? 0), 2),
            'notes' => (string) ($row['notes'] ?? ''),
            'status' => (string) ($row['status'] ?? 'OPEN'),
            'resolution_note' => (string) ($row['resolution_note'] ?? ''),
            'resolved_at' => !empty($row['resolved_at']) ? (string) $row['resolved_at'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_overdue' => strtoupper((string) ($row['status'] ?? 'OPEN')) === 'OPEN'
                && !empty($row['promised_date'])
                && (string) $row['promised_date'] < $today,
            'invoice' => $invoice ? [
                'id' => (int) ($invoice['id'] ?? 0),
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'customer_name' => (string) ($invoice['customer_name'] ?? ''),
                'status' => (string) ($invoice['status'] ?? ''),
                'balance_due' => (float) ($financials['balance_due'] ?? 0),
            ] : null,
        ];
    }

    private static function build_promise_update_audit_lines(array $before, array $after): array
    {
        $lines = [];
        self::append_change_line($lines, 'Promised date', (string) ($before['promised_date'] ?? ''), (string) ($after['promised_date'] ?? ''));
        self::append_change_line($lines, 'Promised amount', (string) ($before['promised_amount'] ?? ''), (string) ($after['promised_amount'] ?? ''));
        self::append_change_line($lines, 'Status', (string) ($before['status'] ?? ''), (string) ($after['status'] ?? ''));

        $notesBefore = trim((string) ($before['notes'] ?? ''));
        $notesAfter = trim((string) ($after['notes'] ?? ''));
        if ($notesBefore !== $notesAfter) {
            $lines[] = $notesAfter === '' ? 'Promise notes cleared.' : 'Promise notes updated.';
        }

        return $lines ?: ['Promise details saved.'];
    }

    private static function calculate_totals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        $lines = [];

        foreach ($items as $item) {
            $desc = sanitize_text_field($item['description'] ?? '');
            $qty  = max(0, (float) ($item['quantity'] ?? 1));
            $unit = max(0, (float) ($item['unit_price'] ?? 0));
            $taxRate = max(0, (float) ($item['tax_rate'] ?? 0));
            $lineTotal = $qty * $unit;
            $tax = $lineTotal * $taxRate / 100;

            $lines[] = [
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $unit,
                'tax_rate'    => $taxRate,
                'tax_amount'  => round($tax, 2),
                'tax_type'    => 'GST',
                'line_total'  => round($lineTotal + $tax, 2),
            ];

            $subtotal += $lineTotal;
            $taxTotal += $tax;
        }

        $total = $subtotal + $taxTotal;

        return [
            'lines'    => $lines,
            'subtotal' => round($subtotal, 2),
            'tax_total'=> round($taxTotal, 2),
            'total'    => round($total, 2),
        ];
    }

    private static function get_paid_amount(int $invoice_id): float
    {
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}vy_invoice_payments WHERE invoice_id = %d",
            $invoice_id
        ));
        return $sum ? round((float) $sum, 2) : 0.0;
    }

    private static function generate_invoice_number(int $org_id, ?string $invoiceDate = null): string
    {
        global $wpdb;
        $config = self::get_invoice_numbering_config($org_id);
        $prefix = $config['prefix'];
        $suffix = $config['suffix'];
        $padding = $config['padding'];
        $resetCycle = $config['reset_cycle'];

        $likePattern = $wpdb->esc_like($prefix) . '%' . $wpdb->esc_like($suffix);
        $conditions = ['org_id = %d', 'invoice_number LIKE %s'];
        $params = [$org_id, $likePattern];

        $now = $invoiceDate ?: current_time('mysql');
        if ($resetCycle === 'yearly') {
            $conditions[] = 'YEAR(`date`) = YEAR(%s)';
            $params[] = $now;
        } elseif ($resetCycle === 'monthly') {
            $conditions[] = "DATE_FORMAT(`date`, '%Y-%m') = DATE_FORMAT(%s, '%Y-%m')";
            $params[] = $now;
        }

        $sql = sprintf(
            "SELECT invoice_number FROM {$wpdb->prefix}vy_invoices WHERE %s ORDER BY id DESC LIMIT 1",
            implode(' AND ', $conditions)
        );
        $last = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        $nextNumber = 1;
        if ($last) {
            $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
            if (preg_match($pattern, $last, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        $numberSegment = str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT);
        return $prefix . $numberSegment . $suffix;
    }

    private static function get_invoice_numbering_config(int $org_id): array
    {
        $settings = self::get_sales_settings($org_id);

        $prefix = isset($settings['invoice_prefix']) && is_string($settings['invoice_prefix'])
            ? $settings['invoice_prefix']
            : 'INV/' . date('Y') . '/';
        $suffix = isset($settings['invoice_suffix']) && is_string($settings['invoice_suffix'])
            ? $settings['invoice_suffix']
            : '';
        $padding = isset($settings['padding']) && is_numeric($settings['padding'])
            ? max(1, (int) $settings['padding'])
            : 4;
        $resetCycle = isset($settings['reset_cycle']) ? strtolower((string) $settings['reset_cycle']) : 'yearly';
        if (!in_array($resetCycle, ['yearly', 'monthly', 'never'], true)) {
            $resetCycle = 'yearly';
        }

        return [
            'prefix' => $prefix,
            'suffix' => $suffix,
            'padding' => $padding,
            'reset_cycle' => $resetCycle,
        ];
    }

    private static function get_sales_settings(int $org_id): array
    {
        static $cache = [];
        if (isset($cache[$org_id])) {
            return $cache[$org_id];
        }

        $defaults = [
            'invoice_prefix' => 'INV/' . date('Y') . '/',
            'invoice_suffix' => '',
            'padding' => 4,
            'reset_cycle' => 'yearly',
            'default_terms' => 'Net 7',
            'default_due_days' => 7,
        ];

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT settings_json FROM {$wpdb->prefix}kbs_settings WHERE org_id = %d AND category = %s LIMIT 1",
            $org_id,
            'sales'
        ));

        $settings = [];
        if ($row && !empty($row->settings_json)) {
            $decoded = json_decode($row->settings_json, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $cache[$org_id] = array_merge($defaults, $settings);
        return $cache[$org_id];
    }

    private static function resolve_due_date(int $org_id, string $invoiceDate, ?string $explicit): string
    {
        $baseDate = $invoiceDate ?: gmdate('Y-m-d');
        if (!empty($explicit)) {
            return $explicit;
        }

        $settings = self::get_sales_settings($org_id);
        $days = null;
        if (isset($settings['default_due_days']) && is_numeric($settings['default_due_days'])) {
            $days = (int) $settings['default_due_days'];
        }
        if ($days === null || $days <= 0) {
            $days = self::parse_terms_days($settings['default_terms'] ?? null);
        }
        if ($days <= 0) {
            return $baseDate;
        }

        $timezone = new \DateTimeZone('UTC');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $baseDate, $timezone);
        if (!$dt) {
            $dt = new \DateTimeImmutable('now', $timezone);
        }
        $due = $dt->modify(sprintf('+%d days', $days));
        return $due->format('Y-m-d');
    }

    private static function parse_terms_days($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/-?\d+/', $value, $matches)) {
            return (int) $matches[0];
        }
        return 0;
    }

    private static function fetch_invoice(int $org_id, int $invoice_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $invoice_id
        ), ARRAY_A);
        if (!$row) return null;
        $row['subtotal'] = (float) $row['subtotal'];
        $row['tax_total'] = (float) $row['tax_total'];
        $row['total'] = (float) $row['total'];
        return $row;
    }

    private static function fetch_invoice_items(int $org_id, int $invoice_id): array
    {
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT description, quantity, unit_price, tax_rate, tax_amount, tax_type, line_total
             FROM {$wpdb->prefix}vy_invoice_items
             WHERE org_id = %d AND invoice_id = %d",
            $org_id,
            $invoice_id
        ), ARRAY_A);

        return $items ?: [];
    }

    private static function fetch_account_row(int $org_id, int $account_id): ?array
    {
        if ($account_id <= 0) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, type, sub_type, status FROM {$wpdb->prefix}vy_accounts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function ensure_account_row_is_active(array $account, string $code, string $message): ?WP_Error
    {
        if (strtoupper((string) ($account['status'] ?? 'ACTIVE')) === 'ARCHIVED') {
            return new WP_Error($code, $message, ['status' => 400]);
        }

        return null;
    }

    private static function begin_payment_submission_guard(int $invoiceId, array $body): array|WP_Error
    {
        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            return [
                'user_id' => 0,
                'meta_key' => '',
            ];
        }

        $explicitRequestId = trim((string) sanitize_key((string) ($body['client_request_id'] ?? '')));
        $signature = $explicitRequestId !== ''
            ? $explicitRequestId
            : md5(wp_json_encode([
                'invoice_id' => $invoiceId,
                'date' => (string) ($body['date'] ?? ''),
                'amount' => round((float) ($body['amount'] ?? 0), 2),
                'to_account_id' => (int) ($body['to_account_id'] ?? 0),
                'income_account_id' => (int) ($body['income_account_id'] ?? 0),
                'description' => sanitize_text_field((string) ($body['description'] ?? '')),
                'reference' => sanitize_text_field((string) ($body['reference'] ?? '')),
            ]));
        $ttl = $explicitRequestId !== '' ? self::PAYMENT_SUBMISSION_TTL_EXPLICIT : self::PAYMENT_SUBMISSION_TTL;
        $metaKey = 'vy_payment_guard_' . $invoiceId . '_' . substr(md5($signature), 0, 24);
        $existing = get_user_meta($userId, $metaKey, true);
        $now = time();

        if (is_array($existing)) {
            $existingStatus = (string) ($existing['status'] ?? '');
            $existingTimestamp = (int) ($existing['timestamp'] ?? 0);
            if ($existingStatus !== '' && ($now - $existingTimestamp) < $ttl) {
                return new WP_Error(
                    'vy_duplicate_payment',
                    $existingStatus === 'processing'
                        ? 'This payment is already being processed. Please wait or refresh the invoice.'
                        : 'This payment request was already submitted. Refresh the invoice before trying again.',
                    ['status' => 409]
                );
            }
        }

        update_user_meta($userId, $metaKey, [
            'status' => 'processing',
            'timestamp' => $now,
        ]);

        return [
            'user_id' => $userId,
            'meta_key' => $metaKey,
        ];
    }

    private static function complete_payment_submission_guard(array $guard): void
    {
        $userId = (int) ($guard['user_id'] ?? 0);
        $metaKey = (string) ($guard['meta_key'] ?? '');
        if ($userId <= 0 || $metaKey === '') {
            return;
        }

        update_user_meta($userId, $metaKey, [
            'status' => 'completed',
            'timestamp' => time(),
        ]);
    }

    private static function clear_payment_submission_guard(array $guard): void
    {
        $userId = (int) ($guard['user_id'] ?? 0);
        $metaKey = (string) ($guard['meta_key'] ?? '');
        if ($userId <= 0 || $metaKey === '') {
            return;
        }

        delete_user_meta($userId, $metaKey);
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
             WHERE org_id = %d AND journal_id = %d AND debit > 0
             ORDER BY id ASC
             LIMIT 1",
            $org_id,
            $journal_id
        ), ARRAY_A);
        if (!$row || empty($row['account_id'])) {
            return null;
        }

        $account = self::fetch_account_row($org_id, (int) $row['account_id']);
        if (!$account) {
            return null;
        }

        return [
            'id'       => (int) ($account['id'] ?? 0),
            'name'     => (string) ($account['name'] ?? ''),
            'type'     => (string) ($account['type'] ?? ''),
            'sub_type' => (string) ($account['sub_type'] ?? ''),
        ];
    }

    private static function determine_invoice_status(string $currentStatus, float $total, float $paid): string
    {
        if ($total <= 0) {
            return 'PAID';
        }
        if ($paid <= 0) {
            return $currentStatus === 'DRAFT' ? 'DRAFT' : 'SENT';
        }
        if ($paid + 0.01 >= $total) {
            return 'PAID';
        }
        return 'PARTIAL';
    }

    private static function resolve_customer_contact(int $org_id, array $body)
    {
        $contactId = isset($body['contact_id']) ? (int) $body['contact_id'] : 0;
        $name = sanitize_text_field($body['customer_name'] ?? '');
        $email = sanitize_email($body['customer_email'] ?? '');
        $phoneProvided = array_key_exists('customer_phone', $body);
        $phone = $phoneProvided
            ? self::sanitize_customer_phone((string) ($body['customer_phone'] ?? ''), $contactId <= 0)
            : '';

        if (is_wp_error($phone)) {
            return $phone;
        }

        if ($contactId > 0) {
            $contact = self::fetch_contact($org_id, $contactId);
            if (!$contact || !in_array($contact['type'], ['CUSTOMER', 'BOTH'], true)) {
                return new WP_Error('vy_bad_contact', 'Invalid customer contact.', ['status' => 400]);
            }
            if ($name === '') {
                $name = $contact['name'] ?? '';
            }
            if ($email === '' && !empty($contact['email'])) {
                $email = $contact['email'];
            }
            if ($phone === '' && !empty($contact['phone'])) {
                $phone = $contact['phone'];
            }
            return [
                'contact_id' => $contactId,
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
            ];
        }

        if ($name === '') {
            return new WP_Error('vy_invoice_customer_required', 'Customer name is required.', ['status' => 400]);
        }

        $newId = self::insert_contact($org_id, [
            'type'  => 'CUSTOMER',
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        if (is_wp_error($newId)) {
            return $newId;
        }

        return [
            'contact_id' => $newId,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
        ];
    }

    private static function sanitize_customer_phone(string $rawPhone, bool $strictWhenPresent = true)
    {
        $rawPhone = trim($rawPhone);
        if ($rawPhone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if ($digits === '') {
            if ($strictWhenPresent) {
                return new WP_Error('vy_invalid_customer_phone', 'Customer phone must be a valid 10-digit number.', ['status' => 400]);
            }

            return '';
        }

        if (strlen($digits) !== 10) {
            return new WP_Error('vy_invalid_customer_phone', 'Customer phone must be a valid 10-digit number.', ['status' => 400]);
        }

        return $digits;
    }

    private static function insert_contact(int $org_id, array $data)
    {
        global $wpdb;
        $payload = [
            'org_id'            => $org_id,
            'type'              => $data['type'],
            'name'              => $data['name'],
            'email'             => $data['email'] ?: null,
            'phone'             => $data['phone'] ?: null,
            'gstin'             => $data['gstin'] ?? null,
            'billing_address'   => $data['billing_address'] ?? null,
            'shipping_address'  => $data['shipping_address'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'status'            => 'ACTIVE',
            'created_at'        => current_time('mysql', true),
            'updated_at'        => current_time('mysql', true),
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
            'type'  => $contact['type'] ?? null,
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

    private static function get_invoice_payments(int $org_id, int $invoice_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, amount, date, journal_id
             FROM {$wpdb->prefix}vy_invoice_payments
             WHERE org_id = %d AND invoice_id = %d
             ORDER BY date ASC, id ASC",
            $org_id,
            $invoice_id
        ), ARRAY_A);
        if (!$rows) {
            return [];
        }
        return array_map(fn($row) => [
            'id'        => (int) $row['id'],
            'amount'    => (float) $row['amount'],
            'date'      => $row['date'],
            'journal_id'=> $row['journal_id'] ? (int) $row['journal_id'] : null,
        ], $rows);
    }

    private static function build_invoice_update_audit_lines(array $before, array $after, int $itemCount): array
    {
        $lines = [];

        self::append_change_line($lines, 'Customer', (string) ($before['customer_name'] ?? ''), (string) ($after['customer_name'] ?? ''));
        self::append_change_line($lines, 'Invoice date', (string) ($before['date'] ?? ''), (string) ($after['date'] ?? ''));
        self::append_change_line($lines, 'Due date', (string) ($before['due_date'] ?? ''), (string) ($after['due_date'] ?? ''));
        self::append_change_line($lines, 'Status', (string) ($before['status'] ?? ''), (string) ($after['status'] ?? ''));
        self::append_change_line(
            $lines,
            'Total',
            RecordAuditLogger::money((float) ($before['total'] ?? 0), (string) ($before['currency'] ?? 'INR')),
            RecordAuditLogger::money((float) ($after['total'] ?? 0), (string) ($after['currency'] ?? 'INR'))
        );

        $notesBefore = trim((string) ($before['notes'] ?? ''));
        $notesAfter = trim((string) ($after['notes'] ?? ''));
        if ($notesBefore !== $notesAfter) {
            $lines[] = $notesAfter === '' ? 'Notes cleared.' : 'Notes updated.';
        }

        $lines[] = 'Items saved: ' . $itemCount;

        return $lines;
    }

    private static function log_invoice_email_audit(int $org_id, int $invoice_id, string $invoiceNumber, array $emailResult): void
    {
        $recipients = array_filter(array_map(static fn($email): string => sanitize_email((string) $email), (array) ($emailResult['recipients'] ?? [])));
        $lines = [
            'Invoice: ' . ($invoiceNumber !== '' ? $invoiceNumber : ('#' . $invoice_id)),
            'Sent at: ' . (string) ($emailResult['sent_at'] ?? current_time('mysql', true)),
        ];
        if ($recipients) {
            $lines[] = 'Recipients: ' . implode(', ', $recipients);
        }

        RecordAuditLogger::log(
            $org_id,
            'invoice',
            $invoice_id,
            'emailed',
            sprintf('Emailed invoice %s', $invoiceNumber !== '' ? $invoiceNumber : ('#' . $invoice_id)),
            ['lines' => $lines]
        );
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

    private static function resolve_template_id(int $org_id): string
    {
        $settings = vy_fetch_invoice_template_settings($org_id);
        return \vy_resolve_invoice_template_id($settings);
    }
}
