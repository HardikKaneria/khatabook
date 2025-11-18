<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use KBS\Accounting\VyJournalEngine;

defined('ABSPATH') || exit;

class VyRestInvoices
{
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

        register_rest_route(VyRestAccounts::NS, '/invoices/(?P<id>\d+)/pay', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'pay_invoice'],
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
            $where[] = 'customer_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($customer) . '%';
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

        foreach ($rows as $row) {
            $paid = self::get_paid_amount((int) $row->id);
            $balanceDue = max(0, (float) $row->total - $paid);
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
                'status'         => $row->status,
                'paid_amount'    => $paid,
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

        global $wpdb;
        $body = $request->get_json_params();
        $items = $body['items'] ?? [];
        if (!$items || !is_array($items)) {
            return new WP_Error('vy_no_items', 'At least one item is required.', ['status' => 400]);
        }

        $contactData = self::resolve_customer_contact((int) $org, $body);
        if (is_wp_error($contactData)) {
            return $contactData;
        }

        $invoiceNumber = sanitize_text_field($body['invoice_number'] ?? '');
        $date = sanitize_text_field($body['date'] ?? gmdate('Y-m-d'));
        $dueInput = isset($body['due_date']) ? sanitize_text_field($body['due_date']) : null;
        $status = strtoupper($body['status'] ?? 'SENT');
        if (!$invoiceNumber) {
            $invoiceNumber = self::generate_invoice_number((int) $org, $date);
        }
        $due = self::resolve_due_date((int) $org, $date, $dueInput);

        $totals = self::calculate_totals($items);

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_invoices',
            [
                'org_id'        => $org,
                'contact_id'    => $contactData['contact_id'],
                'invoice_number'=> $invoiceNumber,
                'customer_name' => $contactData['name'],
                'customer_email'=> $contactData['email'],
                'customer_phone'=> $contactData['phone'],
                'date'          => $date,
                'due_date'      => $due,
                'currency'      => strtoupper($body['currency'] ?? 'INR'),
                'subtotal'      => $totals['subtotal'],
                'tax_total'     => $totals['tax_total'],
                'total'         => $totals['total'],
                'status'        => in_array($status, ['DRAFT','SENT','PARTIAL','PAID','VOID'], true) ? $status : 'SENT',
                'notes'         => wp_kses_post($body['notes'] ?? ''),
                'created_at'    => current_time('mysql', true),
                'updated_at'    => current_time('mysql', true),
            ],
            ['%d','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_invoice_insert_failed', 'Failed to create invoice.', ['status' => 500]);
        }
        $invoice_id = (int) $wpdb->insert_id;

        $items_table = $wpdb->prefix . 'vy_invoice_items';
        foreach ($totals['lines'] as $line) {
            $wpdb->insert(
                $items_table,
                [
                    'org_id'     => $org,
                    'invoice_id' => $invoice_id,
                    'description'=> $line['description'],
                    'quantity'   => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate'   => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'tax_type'   => $line['tax_type'],
                    'line_total' => $line['line_total'],
                    'created_at' => current_time('mysql', true),
                ],
                ['%d','%d','%s','%f','%f','%f','%f','%s','%s']
            );
        }

        $responsePayload = ['id' => $invoice_id];
        $settings = vy_fetch_invoice_template_settings((int) $org);
        if (!empty($settings['auto_email_on_create'])) {
            $emailResult = vy_send_invoice_email($invoice_id);
            if (is_wp_error($emailResult)) {
                error_log(sprintf('[Vyavhar] Auto email failed for invoice %d: %s', $invoice_id, $emailResult->get_error_message()));
                $responsePayload['email_error'] = $emailResult->get_error_message();
            } else {
                $responsePayload['email_sent_to'] = $emailResult['recipients'];
                $responsePayload['email_sent_at'] = $emailResult['sent_at'];
            }
        }

        return new WP_REST_Response($responsePayload, 201);
    }

    public static function get_invoice(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        $invoice = self::fetch_invoice((int) $org, (int) $request['id']);
        if (!$invoice) {
            return new WP_Error('vy_not_found', 'Invoice not found.', ['status' => 404]);
        }

        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT description, quantity, unit_price, tax_rate, tax_amount, tax_type, line_total
             FROM {$wpdb->prefix}vy_invoice_items WHERE org_id = %d AND invoice_id = %d",
            $org,
            $invoice['id']
        ), ARRAY_A);

        $payments = self::get_invoice_payments((int) $org, $invoice['id']);

        $paidTotal = self::get_paid_amount($invoice['id']);
        $invoice['paid_amount'] = $paidTotal;
        $invoice['balance_due'] = max(0, (float) $invoice['total'] - $paidTotal);
        $invoice['items'] = $items ?: [];
        $invoice['payments'] = $payments;
        $invoice['contact_id'] = $invoice['contact_id'] ? (int) $invoice['contact_id'] : null;
        if ($invoice['contact_id']) {
            $invoice['contact'] = self::format_contact_summary(self::fetch_contact((int) $org, $invoice['contact_id']));
        }

        return new WP_REST_Response($invoice, 200);
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
        $incomeAccount = self::fetch_account_row((int) $org, $incomeAccountId);
        if (!$incomeAccount || strtoupper($incomeAccount['type'] ?? '') !== 'INCOME') {
            return new WP_Error('vy_invalid_income', 'The income account must be of type INCOME.', ['status' => 400]);
        }

        $alreadyPaid = self::get_paid_amount($invoice['id']);
        $outstanding = max(0, (float) $invoice['total'] - $alreadyPaid);
        if ($outstanding <= 0) {
            return new WP_Error('vy_invoice_paid', 'Invoice is already fully paid.', ['status' => 400]);
        }
        if ($amount > $outstanding + 0.01) {
            return new WP_Error('vy_amount_exceeds', 'Payment exceeds outstanding balance.', ['status' => 400]);
        }

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
            return $journalResult;
        }

        global $wpdb;
        $wpdb->insert(
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

        $paidTotal = self::get_paid_amount($invoice['id']);
        $status = self::determine_invoice_status($invoice['status'], (float) $invoice['total'], $paidTotal);
        $wpdb->update(
            $wpdb->prefix . 'vy_invoices',
            ['status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $invoice['id']],
            ['%s','%s'],
            ['%d']
        );

        return new WP_REST_Response([
            'success'        => true,
            'invoice_id'     => $invoice['id'],
            'journal_id'     => $journalResult,
            'paid_amount'    => $paidTotal,
            'balance_due'    => max(0, (float) $invoice['total'] - $paidTotal),
            'invoice_status' => $status,
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

    private static function fetch_account_row(int $org_id, int $account_id): ?array
    {
        if ($account_id <= 0) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, type, sub_type FROM {$wpdb->prefix}vy_accounts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);
        return $row ?: null;
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
        $phone = sanitize_text_field($body['customer_phone'] ?? '');

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
}
