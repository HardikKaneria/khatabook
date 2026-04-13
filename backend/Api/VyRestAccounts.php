<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use KBS\Accounting\VyJournalEngine;
use KBS\Helpers\OrgHelper;

defined('ABSPATH') || exit;

class VyRestAccounts
{
    public const NS = 'vy/v1';

    public static function register_routes(): void
    {
        register_rest_route(self::NS, '/accounts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_accounts'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/accounts', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_account'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/accounts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_account'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);
        register_rest_route(self::NS, '/accounts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'update_account'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);
        register_rest_route(self::NS, '/accounts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_account'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/accounts/(?P<id>\d+)/statement', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'account_statement'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/transactions/receipt', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_receipt'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/transactions/payment', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_payment'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);

        register_rest_route(self::NS, '/transactions/transfer', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_transfer'],
            'permission_callback' => [__CLASS__, 'require_auth'],
        ]);
    }

    public static function list_accounts(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vy_accounts';

        $where  = ['org_id = %d'];
        $params = [$org];

        $includeArchived = (int) $request->get_param('include_archived') === 1 || $request->get_param('include_archived') === '1';

        if ($type = $request->get_param('type')) {
            $where[] = 'type = %s';
            $params[] = sanitize_text_field($type);
        }
        if ($sub = $request->get_param('sub_type')) {
            $where[] = 'sub_type = %s';
            $params[] = sanitize_text_field($sub);
        }
        if (!$includeArchived) {
            $where[] = "status = %s";
            $params[] = 'ACTIVE';
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        $accounts = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        $payload = array_map(function ($row) use ($org) {
            $balance = VyJournalEngine::get_account_balance($org, (int) $row->id, (array) $row);
            return [
                'id'         => (int) $row->id,
                'name'       => $row->name,
                'code'       => $row->code,
                'type'       => $row->type,
                'sub_type'   => $row->sub_type,
                'currency'   => $row->currency,
                'is_system'  => (bool) $row->is_system,
                'status'     => $row->status,
                'balance'    => $balance,
                'updated_at' => $row->updated_at,
            ];
        }, $accounts ?: []);

        return new WP_REST_Response($payload, 200);
    }

    public static function create_account(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        global $wpdb;
        $body = $request->get_json_params();

        $name  = sanitize_text_field($body['name'] ?? '');
        $type  = strtoupper(sanitize_text_field($body['type'] ?? ''));
        $sub   = $body['sub_type'] ? sanitize_text_field($body['sub_type']) : null;
        $code  = $body['code'] ? sanitize_text_field($body['code']) : null;
        $currency = strtoupper($body['currency'] ?? 'INR');
        $opening = isset($body['opening_balance']) ? (float) $body['opening_balance'] : 0.0;
        $openingType = isset($body['opening_balance_type']) && strtoupper($body['opening_balance_type']) === 'CREDIT'
            ? 'CREDIT'
            : 'DEBIT';

        if (!$name || !in_array($type, ['ASSET','LIABILITY','EQUITY','INCOME','EXPENSE'], true)) {
            return new WP_Error('vy_bad_account', 'Account name and valid type are required.', ['status' => 400]);
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vy_accounts',
            [
                'org_id'               => $org,
                'code'                 => $code,
                'name'                 => $name,
                'type'                 => $type,
                'sub_type'             => $sub,
                'currency'             => $currency,
                'opening_balance'      => $opening,
                'opening_balance_type' => $openingType,
                'created_at'           => current_time('mysql', true),
                'updated_at'           => current_time('mysql', true),
            ],
            ['%d','%s','%s','%s','%s','%s','%f','%s','%s','%s']
        );

        if ($inserted === false) {
            return new WP_Error('vy_account_insert_failed', 'Failed to create account.', ['status' => 500]);
        }

        $account_id = (int) $wpdb->insert_id;

        if ($sub === 'BANK' && !empty($body['bank_meta']) && is_array($body['bank_meta'])) {
            $meta = $body['bank_meta'];
            $wpdb->insert(
                $wpdb->prefix . 'vy_bank_accounts',
                [
                    'org_id'                => $org,
                    'account_id'            => $account_id,
                    'bank_name'             => sanitize_text_field($meta['bank_name'] ?? ''),
                    'branch'                => sanitize_text_field($meta['branch'] ?? ''),
                    'account_number_masked' => sanitize_text_field($meta['account_number_masked'] ?? ''),
                    'ifsc'                  => sanitize_text_field($meta['ifsc'] ?? ''),
                    'integration_meta'      => isset($meta['integration_meta']) ? wp_json_encode($meta['integration_meta']) : null,
                    'created_at'            => current_time('mysql', true),
                    'updated_at'            => current_time('mysql', true),
                ],
                ['%d','%d','%s','%s','%s','%s','%s','%s']
            );
        }

        return new WP_REST_Response(['id' => $account_id], 201);
    }

    public static function get_account(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $account = self::fetch_account((int) $org, (int) $request['id']);
        if (!$account) {
            return new WP_Error('vy_not_found', 'Account not found.', ['status' => 404]);
        }

        $account['balance'] = VyJournalEngine::get_account_balance((int) $org, (int) $account['id'], $account);
        $hasUsage = self::account_has_journal_usage((int) $org, (int) $account['id']);
        $account['lifecycle'] = self::build_lifecycle_payload($account, $hasUsage);

        if ($account['sub_type'] === 'BANK') {
            global $wpdb;
            $meta = $wpdb->get_row($wpdb->prepare(
                "SELECT bank_name, branch, account_number_masked, ifsc, integration_meta
                 FROM {$wpdb->prefix}vy_bank_accounts
                 WHERE org_id = %d AND account_id = %d",
                $org,
                $account['id']
            ), ARRAY_A);
            $account['bank_meta'] = $meta ?: null;
        }

        return new WP_REST_Response($account, 200);
    }

    public static function update_account(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $accountId = (int) $request['id'];
        $account = self::fetch_account((int) $org, $accountId);
        if (!$account) {
            return new WP_Error('vy_not_found', 'Account not found.', ['status' => 404]);
        }

        if (!empty($account['is_system'])) {
            return new WP_Error('vy_system_account_locked', 'System accounts cannot be edited.', ['status' => 400]);
        }

        $body = (array) $request->get_json_params();
        $hasUsage = self::account_has_journal_usage((int) $org, $accountId);
        $normalized = self::normalize_account_payload($body, $account);

        if ($normalized['name'] === '' || !in_array($normalized['type'], ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'], true)) {
            return new WP_Error('vy_bad_account', 'Account name and valid type are required.', ['status' => 400]);
        }
        if (!in_array($normalized['status'], ['ACTIVE', 'ARCHIVED'], true)) {
            return new WP_Error('vy_bad_account_status', 'Account status must be ACTIVE or ARCHIVED.', ['status' => 400]);
        }

        $lockedFields = self::locked_account_fields($account, $normalized);
        if ($hasUsage && $lockedFields) {
            return new WP_Error(
                'vy_account_locked_fields',
                'Accounts with historical journal activity can only update name, code, and active status.',
                [
                    'status' => 400,
                    'fields' => $lockedFields,
                ]
            );
        }

        global $wpdb;
        $timestamp = current_time('mysql', true);
        $updateData = [
            'name'       => $normalized['name'],
            'code'       => $normalized['code'],
            'status'     => $normalized['status'],
            'updated_at' => $timestamp,
        ];
        $formats = ['%s', '%s', '%s', '%s'];

        if (!$hasUsage) {
            $updateData['type'] = $normalized['type'];
            $updateData['sub_type'] = $normalized['sub_type'];
            $updateData['currency'] = $normalized['currency'];
            $updateData['opening_balance'] = $normalized['opening_balance'];
            $updateData['opening_balance_type'] = $normalized['opening_balance_type'];
            $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s'];
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'vy_accounts',
            $updateData,
            ['org_id' => $org, 'id' => $accountId],
            $formats,
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('vy_account_update_failed', 'Failed to update account.', ['status' => 500]);
        }

        if (!$hasUsage) {
            self::sync_bank_account_meta((int) $org, $accountId, $normalized['sub_type'], $body['bank_meta'] ?? null);
        }

        $fresh = self::fetch_account((int) $org, $accountId);
        if ($fresh) {
            $fresh['balance'] = VyJournalEngine::get_account_balance((int) $org, (int) $fresh['id'], $fresh);
            $fresh['lifecycle'] = self::build_lifecycle_payload($fresh, $hasUsage);
        }

        return new WP_REST_Response([
            'success' => true,
            'account' => $fresh,
        ], 200);
    }

    /**
     * Frontend note: call DELETE /vy/v1/accounts/{id}. Response action = deleted|archived.
     */
    public static function delete_account(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $accountId = (int) $request['id'];
        $account = self::fetch_account((int) $org, $accountId);
        if (!$account) {
            return new WP_Error('vy_not_found', 'Account not found.', ['status' => 404]);
        }

        if (!empty($account['is_system'])) {
            return new WP_Error('cannot_delete_system_account', 'System accounts cannot be deleted.', ['status' => 400]);
        }

        global $wpdb;
        $hasUsage = self::account_has_journal_usage((int) $org, $accountId);

        if (!$hasUsage) {
            $wpdb->delete($wpdb->prefix . 'vy_bank_accounts', ['org_id' => $org, 'account_id' => $accountId], ['%d','%d']);
            $wpdb->delete($wpdb->prefix . 'vy_accounts', ['org_id' => $org, 'id' => $accountId], ['%d','%d']);
            return new WP_REST_Response(['success' => true, 'action' => 'deleted'], 200);
        }

        $wpdb->update(
            $wpdb->prefix . 'vy_accounts',
            ['status' => 'ARCHIVED', 'updated_at' => current_time('mysql', true)],
            ['org_id' => $org, 'id' => $accountId],
            ['%s','%s'],
            ['%d','%d']
        );

        return new WP_REST_Response(['success' => true, 'action' => 'archived'], 200);
    }

    public static function account_statement(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $accountId = (int) $request['id'];
        if (!self::fetch_account((int) $org, $accountId)) {
            return new WP_Error('vy_not_found', 'Account not found.', ['status' => 404]);
        }

        $dateFrom = $request->get_param('from') ?: gmdate('Y-m-01');
        $dateTo   = $request->get_param('to') ?: gmdate('Y-m-d');
        $page     = (int) ($request->get_param('page') ?? 1);
        $perPage  = (int) ($request->get_param('per_page') ?? 50);

        $data = VyJournalEngine::get_account_statement((int) $org, $accountId, $dateFrom, $dateTo, $page, $perPage);
        return new WP_REST_Response($data, 200);
    }

    public static function create_receipt(WP_REST_Request $request)
    {
        return self::handle_money_flow($request, [
            'type'          => 'RECEIPT',
            'source_module' => 'manual_receipt',
            'debit_key'     => 'to_account_id',
            'credit_key'    => 'from_account_id',
        ]);
    }

    public static function create_payment(WP_REST_Request $request)
    {
        return self::handle_money_flow($request, [
            'type'          => 'PAYMENT',
            'source_module' => 'manual_payment',
            'debit_key'     => 'to_account_id',
            'credit_key'    => 'from_account_id',
        ]);
    }

    public static function create_transfer(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $body  = $request->get_json_params();
        $from  = (int) ($body['from_account_id'] ?? 0);
        $to    = (int) ($body['to_account_id'] ?? 0);
        $amount = (float) ($body['amount'] ?? 0);

        if ($from <= 0 || $to <= 0 || $from === $to) {
            return new WP_Error('vy_bad_accounts', 'Source and target accounts are required and must differ.', ['status' => 400]);
        }
        if ($amount <= 0) {
            return new WP_Error('vy_bad_amount', 'Amount must be greater than zero.', ['status' => 400]);
        }

        $fromAccount = self::fetch_account((int) $org, $from);
        $toAccount   = self::fetch_account((int) $org, $to);
        if (!$fromAccount || !$toAccount) {
            return new WP_Error('vy_not_found', 'Account not found for transfer.', ['status' => 404]);
        }
        if (($archivedError = self::ensure_account_is_active($fromAccount, 'vy_account_archived', 'Archived accounts cannot be used for new transfers.')) instanceof WP_Error) {
            return $archivedError;
        }
        if (($archivedError = self::ensure_account_is_active($toAccount, 'vy_account_archived', 'Archived accounts cannot be used for new transfers.')) instanceof WP_Error) {
            return $archivedError;
        }

        $result = VyJournalEngine::create_journal_entry([
            'org_id'        => (int) $org,
            'date'          => $body['date'] ?? gmdate('Y-m-d'),
            'type'          => 'TRANSFER',
            'description'   => sanitize_text_field($body['description'] ?? 'Internal transfer'),
            'source_module' => 'manual_transfer',
            'reference'     => $body['reference'] ?? null,
            'lines'         => [
                [
                    'account_id' => $to,
                    'debit'      => $amount,
                    'credit'     => 0,
                    'line_memo'  => $body['description'] ?? null,
                ],
                [
                    'account_id' => $from,
                    'debit'      => 0,
                    'credit'     => $amount,
                    'line_memo'  => $body['description'] ?? null,
                ],
            ],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['journal_id' => $result], 201);
    }

    private static function handle_money_flow(WP_REST_Request $request, array $config)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $body = $request->get_json_params();
        $amount = (float) ($body['amount'] ?? 0);
        if ($amount <= 0) {
            return new WP_Error('vy_bad_amount', 'Amount must be greater than zero.', ['status' => 400]);
        }

        $debitAccountId  = (int) ($body[$config['debit_key']] ?? 0);
        $creditAccountId = (int) ($body[$config['credit_key']] ?? 0);
        if ($debitAccountId <= 0 || $creditAccountId <= 0) {
            return new WP_Error('vy_bad_accounts', 'Both source and target accounts are required.', ['status' => 400]);
        }

        $debitAccount  = self::fetch_account((int) $org, $debitAccountId);
        $creditAccount = self::fetch_account((int) $org, $creditAccountId);
        if (!$debitAccount || !$creditAccount) {
            return new WP_Error('vy_not_found', 'Account not found.', ['status' => 404]);
        }
        if (($archivedError = self::ensure_account_is_active($debitAccount, 'vy_account_archived', 'Archived accounts cannot be used for new transactions.')) instanceof WP_Error) {
            return $archivedError;
        }
        if (($archivedError = self::ensure_account_is_active($creditAccount, 'vy_account_archived', 'Archived accounts cannot be used for new transactions.')) instanceof WP_Error) {
            return $archivedError;
        }

        $result = VyJournalEngine::create_journal_entry([
            'org_id'        => (int) $org,
            'date'          => $body['date'] ?? gmdate('Y-m-d'),
            'type'          => $config['type'],
            'description'   => sanitize_text_field($body['description'] ?? ''),
            'reference'     => $body['reference'] ?? null,
            'source_module' => $config['source_module'],
            'lines'         => [
                [
                    'account_id' => $debitAccountId,
                    'debit'      => $amount,
                    'credit'     => 0,
                    'line_memo'  => $body['description'] ?? null,
                ],
                [
                    'account_id' => $creditAccountId,
                    'debit'      => 0,
                    'credit'     => $amount,
                    'line_memo'  => $body['description'] ?? null,
                ],
            ],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['journal_id' => $result], 201);
    }

    public static function require_auth(WP_REST_Request $request): bool|WP_Error
    {
        $user_id = 0;

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        } else {
            $token = $request->get_header('X-KBS-Token') ?: $request->get_header('x-kbs-token');
            if (!$token) {
                return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
            }

            global $wpdb;
            $user_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'auth_token' AND meta_value = %s LIMIT 1",
                $token
            ));
            if (!$user_id) {
                return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
            }

            $storedToken = (string) get_user_meta($user_id, 'auth_token', true);
            $expires = (int) get_user_meta($user_id, 'auth_token_expires', true);
            if (!vy_auth_token_is_active((string) $token, $storedToken, $expires)) {
                return new WP_Error('unauthorized', 'Session expired. Please login again.', ['status' => 401]);
            }

            wp_set_current_user($user_id); // ensure capability checks work downstream
        }

        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
        }

        $org_id = OrgHelper::resolve_request_org_id($request, $user_id);
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        return true;
    }

    private static function fetch_account(int $org_id, int $account_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_accounts WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function account_has_journal_usage(int $org_id, int $account_id): bool
    {
        global $wpdb;
        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vy_journal_lines WHERE org_id = %d AND account_id = %d LIMIT 1",
            $org_id,
            $account_id
        ), ARRAY_A);
        return !empty($match);
    }

    private static function normalize_account_payload(array $body, array $account): array
    {
        $subType = array_key_exists('sub_type', $body)
            ? trim((string) sanitize_text_field((string) ($body['sub_type'] ?? '')))
            : (string) ($account['sub_type'] ?? '');

        return [
            'name' => sanitize_text_field((string) ($body['name'] ?? ($account['name'] ?? ''))),
            'code' => self::normalize_nullable_string($body['code'] ?? ($account['code'] ?? null)),
            'type' => strtoupper(sanitize_text_field((string) ($body['type'] ?? ($account['type'] ?? '')))),
            'sub_type' => $subType !== '' ? strtoupper($subType) : null,
            'currency' => strtoupper(sanitize_text_field((string) ($body['currency'] ?? ($account['currency'] ?? 'INR')))),
            'opening_balance' => array_key_exists('opening_balance', $body)
                ? (float) $body['opening_balance']
                : (float) ($account['opening_balance'] ?? 0),
            'opening_balance_type' => strtoupper(sanitize_text_field((string) ($body['opening_balance_type'] ?? ($account['opening_balance_type'] ?? 'DEBIT')))) === 'CREDIT'
                ? 'CREDIT'
                : 'DEBIT',
            'status' => strtoupper(sanitize_text_field((string) ($body['status'] ?? ($account['status'] ?? 'ACTIVE')))),
        ];
    }

    private static function locked_account_fields(array $account, array $normalized): array
    {
        $locked = [];

        if (strtoupper((string) ($account['type'] ?? '')) !== $normalized['type']) {
            $locked[] = 'type';
        }
        if (strtoupper((string) ($account['sub_type'] ?? '')) !== strtoupper((string) ($normalized['sub_type'] ?? ''))) {
            $locked[] = 'sub_type';
        }
        if (strtoupper((string) ($account['currency'] ?? 'INR')) !== $normalized['currency']) {
            $locked[] = 'currency';
        }
        if ((float) ($account['opening_balance'] ?? 0) !== (float) $normalized['opening_balance']) {
            $locked[] = 'opening_balance';
        }
        if (strtoupper((string) ($account['opening_balance_type'] ?? 'DEBIT')) !== $normalized['opening_balance_type']) {
            $locked[] = 'opening_balance_type';
        }

        return $locked;
    }

    private static function build_lifecycle_payload(array $account, bool $hasUsage): array
    {
        $isSystem = !empty($account['is_system']);
        $status = strtoupper((string) ($account['status'] ?? 'ACTIVE'));

        return [
            'has_journal_usage' => $hasUsage,
            'can_edit' => !$isSystem,
            'can_edit_structure' => !$isSystem && !$hasUsage,
            'can_change_status' => !$isSystem,
            'can_delete_permanently' => !$isSystem && !$hasUsage,
            'status' => $status,
            'status_note' => $status === 'ARCHIVED'
                ? 'Archived accounts stay in history but cannot be used for new transactions.'
                : ($hasUsage
                    ? 'This account has journal history. Only display fields and active status can change.'
                    : 'This account can still be updated or deleted because it has no journal history.'),
        ];
    }

    private static function sync_bank_account_meta(int $org_id, int $account_id, ?string $sub_type, $bankMeta): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vy_bank_accounts';

        if ($sub_type !== 'BANK') {
            $wpdb->delete($table, ['org_id' => $org_id, 'account_id' => $account_id], ['%d', '%d']);
            return;
        }

        if (!is_array($bankMeta)) {
            return;
        }

        $data = [
            'org_id' => $org_id,
            'account_id' => $account_id,
            'bank_name' => sanitize_text_field((string) ($bankMeta['bank_name'] ?? '')),
            'branch' => sanitize_text_field((string) ($bankMeta['branch'] ?? '')),
            'account_number_masked' => sanitize_text_field((string) ($bankMeta['account_number_masked'] ?? '')),
            'ifsc' => sanitize_text_field((string) ($bankMeta['ifsc'] ?? '')),
            'integration_meta' => isset($bankMeta['integration_meta']) ? wp_json_encode($bankMeta['integration_meta']) : null,
            'updated_at' => current_time('mysql', true),
        ];

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND account_id = %d LIMIT 1",
            $org_id,
            $account_id
        ));

        if ($existingId > 0) {
            $wpdb->update(
                $table,
                array_diff_key($data, ['org_id' => true, 'account_id' => true]),
                ['id' => $existingId],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            return;
        }

        $data['created_at'] = current_time('mysql', true);
        $wpdb->insert(
            $table,
            $data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private static function ensure_account_is_active(array $account, string $code, string $message): ?WP_Error
    {
        $status = strtoupper((string) ($account['status'] ?? 'ACTIVE'));
        if ($status === 'ARCHIVED') {
            return new WP_Error($code, $message, ['status' => 400]);
        }

        return null;
    }

    private static function normalize_nullable_string($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) sanitize_text_field((string) $value));
        return $normalized === '' ? null : $normalized;
    }

}
