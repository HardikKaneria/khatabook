<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use KBS\Accounting\VyJournalEngine;

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

        $payload = array_map(function ($row) use ($contactMap) {
            $contactId = $row->contact_id ? (int) $row->contact_id : null;
            return [
                'id'          => (int) $row->id,
                'contact_id'  => $contactId,
                'contact'     => self::format_contact_summary($contactId ? ($contactMap[$contactId] ?? null) : null),
                'expense_date'=> $row->expense_date,
                'category'    => $row->category,
                'payee'       => $row->payee,
                'description' => $row->description,
                'amount'      => (float) $row->amount,
                'currency'    => $row->currency,
                'gst_rate'    => isset($row->gst_rate) ? (float) $row->gst_rate : 0.0,
                'gst_amount'  => isset($row->gst_amount) ? (float) $row->gst_amount : 0.0,
                'gst_type'    => $row->gst_type ?? 'GST',
                'is_gst_input_eligible' => isset($row->is_gst_input_eligible) ? (bool) $row->is_gst_input_eligible : true,
                'status'      => $row->status,
                'journal_id'  => $row->payment_journal_id ? (int) $row->payment_journal_id : null,
            ];
        }, $rows ?: []);

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

        return new WP_REST_Response(['id' => $expense_id, 'payment_journal_id' => $journalId], 201);
    }

    public static function get_expense(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_expenses WHERE org_id = %d AND id = %d LIMIT 1",
            $org,
            (int) $request['id']
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('vy_not_found', 'Expense not found.', ['status' => 404]);
        }

        $row['amount'] = (float) $row['amount'];
        $row['payment_journal_id'] = $row['payment_journal_id'] ? (int) $row['payment_journal_id'] : null;
        $row['gst_rate'] = isset($row['gst_rate']) ? (float) $row['gst_rate'] : 0.0;
        $row['gst_amount'] = isset($row['gst_amount']) ? (float) $row['gst_amount'] : 0.0;
        $row['is_gst_input_eligible'] = isset($row['is_gst_input_eligible']) ? (bool) $row['is_gst_input_eligible'] : true;
        $row['contact_id'] = $row['contact_id'] ? (int) $row['contact_id'] : null;
        if ($row['contact_id']) {
            $row['contact'] = self::format_contact_summary(self::fetch_contact((int) $org, $row['contact_id']));
        }
        return new WP_REST_Response($row, 200);
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
}
