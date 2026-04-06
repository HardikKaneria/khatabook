<?php
/**
 * PendingUserController
 *
 * Approve/Decline actions for KBS Pending Users (admin only).
 */

namespace KBS\Admin;

use KBS\Core\SystemLogger;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class PendingUserController {
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 50;

    private static function get_pending_user(int $pending_id): ?object {
        global $wpdb;

        if ($pending_id <= 0) {
            return null;
        }

        $table_pending = $wpdb->prefix . 'kbs_pending_users';
        $pending_user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_pending} WHERE id = %d", $pending_id)
        );

        return $pending_user ?: null;
    }

    private static function normalize_pending_user(object $pending_user): array {
        return [
            'id'      => (int) ($pending_user->id ?? 0),
            'status'  => sanitize_text_field((string) ($pending_user->status ?? 'pending')),
            'email'   => sanitize_email((string) ($pending_user->email ?? '')),
            'name'    => sanitize_text_field((string) ($pending_user->name ?? '')),
            'company' => sanitize_text_field((string) ($pending_user->company ?? '')),
        ];
    }

    private static function process_pending_user(object $pending_user, string $status): array|WP_Error {
        $decision = strtolower(sanitize_text_field($status));
        if (!in_array($decision, ['approved', 'declined'], true)) {
            return new WP_Error('invalid_status', 'Invalid pending user action.', ['status' => 400]);
        }

        $normalized = self::normalize_pending_user($pending_user);
        if (!$normalized['id'] || !$normalized['email']) {
            return new WP_Error('invalid_pending_user', 'Pending user record is incomplete.', ['status' => 400]);
        }

        if ($normalized['status'] === $decision) {
            return [
                'id'          => $normalized['id'],
                'status'      => $decision,
                'email'       => $normalized['email'],
                'message'     => sprintf('User %s already %s.', $normalized['email'], $decision),
                'notice_type' => $decision === 'approved' ? 'success' : 'warning',
            ];
        }

        if ($normalized['status'] !== 'pending') {
            return new WP_Error('pending_user_processed', 'This request has already been processed.', ['status' => 409]);
        }

        if ($decision === 'declined') {
            return self::decline_pending_user($pending_user, $normalized);
        }

        return self::approve_pending_user($pending_user, $normalized);
    }

    private static function decline_pending_user(object $pending_user, array $normalized): array|WP_Error {
        global $wpdb;

        $table_pending = $wpdb->prefix . 'kbs_pending_users';
        $pending_id    = (int) $pending_user->id;

        $updated = $wpdb->update(
            $table_pending,
            [ 'status' => 'declined' ],
            [ 'id' => $pending_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if (false === $updated) {
            return new WP_Error('pending_user_decline_failed', 'Failed to update pending user status.', ['status' => 500]);
        }

        if (class_exists(SystemLogger::class)) {
            SystemLogger::log_registration($normalized['email'], 'User declined');
        }

        if (function_exists('kbs_send_email')) {
            \kbs_send_email(
                $normalized['email'],
                'Your Vyavhar registration status',
                'We reviewed your application and unfortunately had to decline it at this time. If you believe this was a mistake, please reply to this email with additional details.',
                [
                    'greeting' => $normalized['name'] ? "Hi {$normalized['name']}," : 'Hello,',
                ]
            );
        }

        return [
            'id'          => $normalized['id'],
            'status'      => 'declined',
            'email'       => $normalized['email'],
            'message'     => sprintf('User %s declined.', $normalized['email']),
            'notice_type' => 'warning',
        ];
    }

    private static function approve_pending_user(object $pending_user, array $normalized): array|WP_Error {
        global $wpdb;

        $pending_id    = (int) $pending_user->id;
        $table_pending = $wpdb->prefix . 'kbs_pending_users';
        $table_orgs    = $wpdb->prefix . 'kbs_organizations';
        $table_roles   = $wpdb->prefix . 'kbs_user_org_roles';
        $org_id        = 0;

        $existing = get_user_by('email', $normalized['email']);
        if ($existing) {
            $new_user_id = (int) $existing->ID;

            if ($normalized['name'] && $existing->display_name !== $normalized['name']) {
                wp_update_user([ 'ID' => $new_user_id, 'display_name' => $normalized['name'] ]);
            }
        } else {
            $email_parts = explode('@', $normalized['email']);
            $base_login = sanitize_user((string) ($email_parts[0] ?? ''), true);
            if ('' === $base_login) {
                $base_login = 'user';
            }

            $user_login = $base_login;
            $suffix     = 1;
            while (username_exists($user_login)) {
                $user_login = "{$base_login}_{$suffix}";
                $suffix++;
            }

            $password    = wp_generate_password(20, true, true);
            $new_user_id = wp_insert_user([
                'user_login'   => $user_login,
                'user_pass'    => $password,
                'user_email'   => $normalized['email'],
                'display_name' => $normalized['name'] ?: $user_login,
                'role'         => 'company_admin',
            ]);

            if (is_wp_error($new_user_id)) {
                return new WP_Error('pending_user_create_failed', 'Failed to create user: ' . $new_user_id->get_error_message(), ['status' => 500]);
            }
        }

        $user = get_userdata($new_user_id);
        if ($user) {
            $user->set_role('company_admin');
        }

        $wpdb->query('START TRANSACTION');

        try {
            if ($normalized['company'] !== '') {
                $org_id = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT org_id FROM {$table_orgs} WHERE org_name = %s LIMIT 1", $normalized['company'])
                );

                if (!$org_id) {
                    $org_created = $wpdb->insert(
                        $table_orgs,
                        [ 'org_name' => $normalized['company'] ],
                        [ '%s' ]
                    );

                    if (!$org_created) {
                        throw new Exception("Failed to create organization for: {$normalized['company']}");
                    }

                    $org_id = (int) $wpdb->insert_id;
                }

                $existing_role_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$table_roles} WHERE user_id = %d AND org_id = %d LIMIT 1",
                        $new_user_id,
                        $org_id
                    )
                );

                if ($existing_role_row) {
                    $role_updated = $wpdb->update(
                        $table_roles,
                        [
                            'role'       => 'company_admin',
                            'is_primary' => 1,
                        ],
                        [ 'id' => (int) $existing_role_row->id ],
                        [ '%s', '%d' ],
                        [ '%d' ]
                    );

                    if (false === $role_updated) {
                        throw new Exception('Failed to update organization role mapping');
                    }
                } else {
                    $role_inserted = $wpdb->insert(
                        $table_roles,
                        [
                            'user_id'    => $new_user_id,
                            'org_id'     => $org_id,
                            'role'       => 'company_admin',
                            'is_primary' => 1,
                        ],
                        [ '%d', '%d', '%s', '%d' ]
                    );

                    if (!$role_inserted) {
                        throw new Exception('Failed to assign user to organization role mapping');
                    }
                }
            }

            $pending_updated = $wpdb->update(
                $table_pending,
                [ 'status' => 'approved' ],
                [ 'id' => $pending_id ],
                [ '%s' ],
                [ '%d' ]
            );

            if (false === $pending_updated) {
                throw new Exception('Failed to update pending user status.');
            }

            update_user_meta($new_user_id, 'kbs_account_status', 'approved');
            if ($org_id > 0) {
                update_user_meta($new_user_id, 'org_id', $org_id);
                update_user_meta($new_user_id, 'vy_active_org_id', $org_id);
            }

            $wpdb->query('COMMIT');

            if (class_exists(SystemLogger::class)) {
                SystemLogger::log_registration($normalized['email'], 'User approved and account created');
            }

            if (function_exists('kbs_send_email')) {
                \kbs_send_email(
                    $normalized['email'],
                    'Your Vyavhar account is ready',
                    'Great news! Your organization has been approved and your Vyavhar account is live. Sign in with your registered email to start using the dashboard.',
                    [
                        'greeting'  => $normalized['name'] ? "Hi {$normalized['name']}," : 'Hello,',
                        'cta_label' => 'Open Vyavhar',
                        'cta_url'   => home_url('/login'),
                    ]
                );
            }

            return [
                'id'          => $normalized['id'],
                'status'      => 'approved',
                'email'       => $normalized['email'],
                'user_id'     => (int) $new_user_id,
                'org_id'      => $org_id ?: null,
                'message'     => sprintf('User %s approved successfully.', $normalized['email']),
                'notice_type' => 'success',
            ];
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            SystemLogger::log_event(
                'pending_user_approval_failed',
                'Pending user approval failed.',
                [
                    'pending_user_id' => $normalized['id'],
                    'email' => $normalized['email'],
                    'error_message' => $e->getMessage(),
                ],
                get_current_user_id(),
                'backend/Admin/PendingUserController.php'
            );
            return new WP_Error('pending_user_approve_failed', 'Approval failed: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    public static function get_all_pending(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $table_pending = $wpdb->prefix . 'kbs_pending_users';
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = absint($request->get_param('per_page') ?: self::DEFAULT_PER_PAGE);
        $per_page = max(1, min(self::MAX_PER_PAGE, $per_page));
        $offset = ($page - 1) * $per_page;

        $where_parts = ["status = 'pending'"];
        $params = [];

        $email = sanitize_email((string) $request->get_param('email'));
        if ($email !== '') {
            $where_parts[] = 'email = %s';
            $params[] = $email;
        }

        $company = sanitize_text_field((string) $request->get_param('company'));
        if ($company !== '') {
            $where_parts[] = 'company LIKE %s';
            $params[] = '%' . $wpdb->esc_like($company) . '%';
        }

        $where = ' WHERE ' . implode(' AND ', $where_parts);
        $count_sql = "SELECT COUNT(*) FROM {$table_pending}{$where}";
        $total = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params))
            : (int) $wpdb->get_var($count_sql);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, email, company, status, applied_at
                 FROM {$table_pending}{$where}
                 ORDER BY applied_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                ...array_merge($params, [$per_page, $offset])
            )
        );

        $users = array_map(
            static function ($row): array {
                return [
                    'id'         => (int) ($row->id ?? 0),
                    'name'       => sanitize_text_field((string) ($row->name ?? '')),
                    'email'      => sanitize_email((string) ($row->email ?? '')),
                    'company'    => sanitize_text_field((string) ($row->company ?? '')),
                    'status'     => sanitize_text_field((string) ($row->status ?? 'pending')),
                    'applied_at' => sanitize_text_field((string) ($row->applied_at ?? '')),
                ];
            },
            $rows ?: []
        );

        $distinct_companies_sql = "SELECT COUNT(DISTINCT company) FROM {$table_pending}{$where}";
        $company_count = $params
            ? (int) $wpdb->get_var($wpdb->prepare($distinct_companies_sql, ...$params))
            : (int) $wpdb->get_var($distinct_companies_sql);

        $response = new WP_REST_Response([
            'users' => $users,
            'total' => $total,
            'summary' => [
                'pending_count' => $total,
                'company_count' => $company_count,
                'latest_request' => $users[0]['applied_at'] ?? '',
            ],
        ], 200);

        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) max(1, (int) ceil($total / max(1, $per_page))));
        $response->header('X-KBS-Page', (string) $page);
        $response->header('X-KBS-Per-Page', (string) $per_page);

        return $response;
    }

    public static function update_status(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $pending_id = absint($request->get_param('id'));
        $status     = strtolower(sanitize_text_field((string) $request->get_param('status')));

        if (!$pending_id) {
            return new WP_Error('missing_pending_id', 'Pending user id is required.', ['status' => 400]);
        }

        $status = match ($status) {
            'approve', 'approved' => 'approved',
            'decline', 'declined' => 'declined',
            default               => '',
        };

        if ($status === '') {
            return new WP_Error('invalid_status', 'Status must be approved or declined.', ['status' => 400]);
        }

        $pending_user = self::get_pending_user($pending_id);
        if (!$pending_user) {
            return new WP_Error('pending_user_not_found', 'Pending user not found.', ['status' => 404]);
        }

        $result = self::process_pending_user($pending_user, $status);
        if ($result instanceof WP_Error) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Handle approve/decline actions on pending users.
     */
    public static function handle_actions(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        // Nonce (form must include: wp_nonce_field('kbs_pending_user_action','kbs_pending_user_nonce'))
        $nonce = isset( $_POST['kbs_pending_user_nonce'] ) ? wp_unslash( $_POST['kbs_pending_user_nonce'] ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'kbs_pending_user_action' ) ) {
            self::admin_notice( 'Security check failed.', 'error' );
            return;
        }

        $pending_id = isset( $_POST['user_id'] ) ? (int) wp_unslash( $_POST['user_id'] ) : 0;
        if ( ! $pending_id ) {
            return;
        }

        $pending_user = self::get_pending_user($pending_id);
        if ( ! $pending_user ) {
            self::admin_notice( 'Pending user not found.', 'error' );
            return;
        }

        $decision = null;
        if ( isset( $_POST['kbs_decline_user'] ) ) {
            $decision = 'declined';
        } elseif ( isset( $_POST['kbs_approve_user'] ) ) {
            $decision = 'approved';
        }

        if ( ! $decision ) {
            return;
        }

        $result = self::process_pending_user($pending_user, $decision);
        if ( $result instanceof WP_Error ) {
            self::admin_notice( $result->get_error_message(), 'error' );
            return;
        }

        self::admin_notice( (string) ( $result['message'] ?? 'Pending user updated.' ), (string) ( $result['notice_type'] ?? 'success' ) );
    }

    /**
     * Helper to enqueue an admin notice message.
     */
    private static function admin_notice( string $message, string $type = 'success' ): void {
        add_action(
            'admin_notices',
            static function () use ( $message, $type ) {
                echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            }
        );
    }
}
