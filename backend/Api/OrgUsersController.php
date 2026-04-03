<?php
namespace KBS\Api;

use KBS\Auth\OtpAuth;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class OrgUsersController
{
    /** Keep org role choices aligned with your frontend */
    private const ALLOWED_ROLES = ['company_admin', 'c_manager', 'c_employee'];

    /** Cache role table existence checks */
    private static $rolesTableExists = null;

    /** ---------- Auth helpers (cookie OR X-KBS-Token) ---------- */

    private static function current_user_id_from_request(WP_REST_Request $request): ?int {
        if (is_user_logged_in()) return get_current_user_id();

        // Token path
        $token = $request->get_header('X-KBS-Token') ?: $request->get_header('x-kbs-token');
        if (!$token) return null;

        global $wpdb;
        $uid = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'auth_token' AND meta_value = %s LIMIT 1",
            $token
        ));
        if (!$uid) return null;

        $storedToken = (string) get_user_meta($uid, 'auth_token', true);
        $exp = (int) get_user_meta($uid, 'auth_token_expires', true);
        if (!vy_auth_token_is_active((string) $token, $storedToken, $exp)) return null;

        // establish current user context for caps checks
        wp_set_current_user($uid);
        return $uid;
    }

    private static function require_login(WP_REST_Request $request): bool|WP_Error {
        $uid = self::current_user_id_from_request($request);
        if ($uid) return true;
        return new WP_Error('unauthorized', 'You must be logged in.', ['status' => 401]);
    }

    private static function roles_table_exists(): bool {
        if (self::$rolesTableExists !== null) {
            return self::$rolesTableExists;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        self::$rolesTableExists = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table
        ));

        return self::$rolesTableExists;
    }

    private static function org_role_for_user(int $org_id, int $user_id): ?string {
        global $wpdb;
        if (!self::roles_table_exists()) {
            return null;
        }
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        $role = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$table} WHERE org_id = %d AND user_id = %d LIMIT 1",
            $org_id,
            $user_id
        ));
        return $role ?: null;
    }

    private static function actor_role_for_org(int $org_id): ?string {
        $uid = get_current_user_id();
        if (!$uid) {
            return null;
        }
        if (user_can($uid, 'manage_options')) {
            return 'administrator';
        }
        return self::org_role_for_user($org_id, $uid);
    }

    private static function assignable_roles_for_actor(string $actorRole): array {
        if ($actorRole === 'administrator') {
            return self::ALLOWED_ROLES;
        }
        if ($actorRole === 'company_admin') {
            return ['c_manager', 'c_employee'];
        }
        if ($actorRole === 'c_manager') {
            return ['c_employee'];
        }
        return [];
    }

    private static function actor_can_manage_role(?string $actorRole, string $targetRole): bool {
        $actorRole = $actorRole ?: '';
        if ($actorRole === 'administrator') {
            return true;
        }
        if ($actorRole === 'company_admin') {
            return in_array($targetRole, ['c_manager', 'c_employee'], true);
        }
        if ($actorRole === 'c_manager') {
            return $targetRole === 'c_employee';
        }
        return false;
    }

    /** Must be admin in WP (manage_options) OR company_admin/c_manager of that org */
    public static function can_manage_org(WP_REST_Request $request): bool|WP_Error {
        $ok = self::require_login($request);
        if ($ok instanceof WP_Error) return $ok;

        $org_id = absint($request->get_param('org_id') ?? 0);
        if (!$org_id) {
            return new WP_Error('bad_request', 'Missing org_id.', ['status' => 400]);
        }

        $actorRole = self::actor_role_for_org($org_id);
        if (in_array($actorRole, ['administrator', 'company_admin', 'c_manager'], true)) {
            return true;
        }

        return new WP_Error('forbidden', 'Insufficient permissions for this organization.', ['status' => 403]);
    }

    /** ---------- Data helpers ---------- */

    private static function user_row_to_payload($row): array {
        // Normalize to what your table expects:
        return [
            'id'           => (int) $row->ID,
            'email'        => $row->user_email,
            'display_name' => $row->display_name,
        ];
    }

    private static function get_org_members(int $org_id): array {
        global $wpdb;

        $map = [];
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        $users_table = $wpdb->users;

        // Join users to org roles
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_email, u.display_name, r.role
             FROM {$users_table} u
             INNER JOIN {$table} r ON r.user_id = u.ID
             WHERE r.org_id = %d",
            $org_id
        ));

        foreach ($rows as $row) {
            $payload = self::user_row_to_payload($row);
            $payload['role']   = self::normalize_role($row->role ?? null);
            $payload['status'] = 'active';

            $map[$payload['id']] = $payload;
        }
        return array_values($map);
    }

    private static function get_org_invites(int $org_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_org_invites';
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table
        ));
        if (!$exists) return [];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, role, status, created_at FROM {$table}
             WHERE org_id = %d AND status = 'invited'
             ORDER BY id DESC",
            $org_id
        ));

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                // string id so frontend rowKey remains unique; your table uses rowKey="id"
                'id'           => 'invite:' . (int) $r->id,
                'email'        => $r->email,
                'display_name' => null,
                'role'         => self::normalize_role($r->role ?? null),
                'status'       => 'invited',
                'created_at'   => $r->created_at,
            ];
        }
        return $out;
    }

    private static function ensure_membership(int $org_id, int $user_id, string $role): void {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        $role  = self::normalize_role($role);

        // upsert
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE org_id = %d AND user_id = %d",
            $org_id, $user_id
        ));

        if ($exists) {
            $wpdb->update($table, ['role' => $role], ['org_id' => $org_id, 'user_id' => $user_id], ['%s'], ['%d','%d']);
        } else {
            $wpdb->insert($table, ['org_id' => $org_id, 'user_id' => $user_id, 'role' => $role, 'is_primary' => 0], ['%d','%d','%s','%d']);
        }
    }

    private static function set_wp_role(int $user_id, string $role): void {
        $role = self::normalize_role($role);
        $user = new \WP_User($user_id);
        // Keep the WP role aligned for legacy capability checks outside org-scoped APIs.
        $user->set_role($role);
    }

    private static function get_org_name(int $org_id): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_organizations';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT org_name FROM {$table} WHERE org_id = %d LIMIT 1",
            $org_id
        )) ?: null;
    }

    private static function normalize_role(?string $role): string {
        return in_array($role, self::ALLOWED_ROLES, true) ? $role : 'c_employee';
    }

    private static function normalize_invite_email($email): string {
        $value = trim((string) $email);
        for ($i = 0; $i < 2; $i++) {
            $decoded = rawurldecode($value);
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        return sanitize_email($value);
    }

    private static function build_accept_url(string $token, string $email): string {
        return add_query_arg(
            [
                'invite' => $token,
                'email'  => $email,
            ],
            home_url('/accept-invite')
        );
    }

    private static function find_invite(string $token, string $email): ?object {
        global $wpdb;

        $table      = $wpdb->prefix . 'kbs_org_invites';
        $orgs_table = $wpdb->prefix . 'kbs_organizations';

        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, o.org_name
             FROM {$table} i
             LEFT JOIN {$orgs_table} o ON o.org_id = i.org_id
             WHERE i.token = %s AND i.email = %s
             LIMIT 1",
            $token,
            $email
        ));

        return $invite ?: null;
    }

    private static function invite_is_expired(object $invite): bool {
        if (empty($invite->expires_at)) {
            return false;
        }

        $expires = strtotime((string) $invite->expires_at);
        if (!$expires) {
            return false;
        }

        return $expires < time();
    }

    private static function invite_payload(object $invite): array {
        return [
            'id'         => (int) $invite->id,
            'org_id'     => (int) $invite->org_id,
            'org_name'   => $invite->org_name ?: sprintf('Organization #%d', (int) $invite->org_id),
            'email'      => $invite->email,
            'role'       => self::normalize_role($invite->role ?? null),
            'status'     => sanitize_text_field((string) ($invite->status ?? 'invited')),
            'created_at' => $invite->created_at ?: null,
            'expires_at' => $invite->expires_at ?: null,
        ];
    }

    private static function generate_user_login_from_email(string $email): string {
        $parts = explode('@', $email);
        $base  = sanitize_user((string) ($parts[0] ?? ''), true);
        if ($base === '') {
            $base = 'user';
        }

        $user_login = $base;
        $suffix = 1;
        while (username_exists($user_login)) {
            $user_login = "{$base}_{$suffix}";
            $suffix++;
        }

        return $user_login;
    }

    private static function create_user_for_invite(string $email, string $role): int|WP_Error {
        $user_login = self::generate_user_login_from_email($email);
        $user_id = wp_insert_user([
            'user_login'   => $user_login,
            'user_pass'    => wp_generate_password(20, true, true),
            'user_email'   => $email,
            'display_name' => $user_login,
            'role'         => self::normalize_role($role),
        ]);

        if (is_wp_error($user_id)) {
            return new WP_Error('invite_user_create_failed', 'Failed to create invited user: ' . $user_id->get_error_message(), ['status' => 500]);
        }

        return (int) $user_id;
    }

    /** Claim invites after a user exists */
    public static function claim_invites_for_user(int $user_id, string $email): void {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_org_invites';
        $invites = $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_id, role FROM {$table}
             WHERE email = %s AND status = 'invited'",
            $email
        ));
        if (!$invites) return;

        foreach ($invites as $i) {
            $role = self::normalize_role($i->role ?? null);
            self::ensure_membership((int)$i->org_id, $user_id, $role);
            $wpdb->update($table, ['status' => 'accepted'], ['id' => (int)$i->id], ['%s'], ['%d']);
        }
    }

    /** GET /kbs/v1/invite?invite=token&email=... */
    public static function get_invite(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $token = sanitize_text_field((string) $request->get_param('invite'));
        $email = self::normalize_invite_email($request->get_param('email'));

        if ($token === '' || $email === '') {
            return new WP_Error('bad_request', 'Invite token and email are required.', ['status' => 400]);
        }

        $invite = self::find_invite($token, $email);
        if (!$invite) {
            return new WP_REST_Response([
                'status'  => 'invalid',
                'message' => 'This invitation link is invalid or no longer available.',
            ], 404);
        }

        $current_user_id = self::current_user_id_from_request($request);
        $current_user    = $current_user_id ? get_userdata($current_user_id) : null;
        $existing_user   = get_user_by('email', $invite->email);
        $email_matches_current_user = $current_user
            && strcasecmp((string) $current_user->user_email, (string) $invite->email) === 0;

        if (($invite->status ?? '') === 'accepted') {
            return new WP_REST_Response([
                'status'  => 'already_accepted',
                'message' => 'This invitation has already been accepted.',
                'invite'  => self::invite_payload($invite),
                'meta'    => [
                    'user_exists'                => (bool) $existing_user,
                    'email_matches_current_user' => (bool) $email_matches_current_user,
                ],
            ], 200);
        }

        if (self::invite_is_expired($invite)) {
            return new WP_REST_Response([
                'status'  => 'expired',
                'message' => 'This invitation link has expired. Ask your admin to send a new one.',
                'invite'  => self::invite_payload($invite),
                'meta'    => [
                    'user_exists'                => (bool) $existing_user,
                    'email_matches_current_user' => (bool) $email_matches_current_user,
                ],
            ], 410);
        }

        return new WP_REST_Response([
            'status'  => 'valid',
            'message' => 'Invitation is valid.',
            'invite'  => self::invite_payload($invite),
            'meta'    => [
                'user_exists'                => (bool) $existing_user,
                'email_matches_current_user' => (bool) $email_matches_current_user,
            ],
        ], 200);
    }

    /** POST /kbs/v1/accept-invite { invite, email } */
    public static function accept_invite(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;

        $token = sanitize_text_field((string) $request->get_param('invite'));
        $email = self::normalize_invite_email($request->get_param('email'));

        if ($token === '' || $email === '') {
            return new WP_Error('bad_request', 'Invite token and email are required.', ['status' => 400]);
        }

        $invite = self::find_invite($token, $email);
        if (!$invite) {
            return new WP_Error('invite_not_found', 'Invite not found.', ['status' => 404]);
        }

        if (($invite->status ?? '') === 'accepted') {
            return new WP_REST_Response([
                'status'  => 'already_accepted',
                'message' => 'This invitation has already been used.',
                'invite'  => self::invite_payload($invite),
            ], 409);
        }

        if (self::invite_is_expired($invite)) {
            return new WP_Error('invite_expired', 'This invitation has expired. Ask your admin to resend it.', ['status' => 410]);
        }

        $current_user_id = self::current_user_id_from_request($request);
        if ($current_user_id) {
            $current_user = get_userdata($current_user_id);
            if (!$current_user || strcasecmp((string) $current_user->user_email, (string) $invite->email) !== 0) {
                return new WP_Error('invite_email_mismatch', 'This invitation belongs to a different email address. Sign out first and retry from the invited email.', ['status' => 403]);
            }
        }

        $role = self::normalize_role($invite->role ?? null);
        $user = get_user_by('email', $invite->email);
        $user_id = $user ? (int) $user->ID : self::create_user_for_invite($invite->email, $role);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $table = $wpdb->prefix . 'kbs_org_invites';
        $wpdb->query('START TRANSACTION');

        try {
            self::ensure_membership((int) $invite->org_id, (int) $user_id, $role);
            self::set_wp_role((int) $user_id, $role);

            $updated = $wpdb->update(
                $table,
                ['status' => 'accepted'],
                ['id' => (int) $invite->id],
                ['%s'],
                ['%d']
            );
            if (false === $updated) {
                throw new \RuntimeException('Failed to update invite status.');
            }

            update_user_meta((int) $user_id, 'kbs_account_status', 'approved');
            update_user_meta((int) $user_id, 'org_id', (int) $invite->org_id);
            update_user_meta((int) $user_id, 'vy_active_org_id', (int) $invite->org_id);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invite_accept_failed', 'Failed to accept invite: ' . $e->getMessage(), ['status' => 500]);
        }

        $payload = OtpAuth::create_auth_payload((int) $user_id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['invite']  = self::invite_payload($invite);
        $payload['message'] = 'Invitation accepted successfully.';

        return new WP_REST_Response($payload, 200);
    }

    /** ---------- Endpoints ---------- */

    /** GET /kbs/v1/org-users?org_id=... */
    public static function get_org_users(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $perm = self::can_manage_org($request);
        if ($perm instanceof WP_Error) return $perm;

        $org_id = absint($request->get_param('org_id'));
        $users  = self::get_org_members($org_id);
        $inv    = self::get_org_invites($org_id);

        // merge + sort (invited last)
        $all = array_merge($users, $inv);
        usort($all, function($a,$b){
            return strcmp(($a['status'] ?? 'active'), ($b['status'] ?? 'active'));
        });

        return new WP_REST_Response(['users' => $all], 200);
    }

    /** POST /kbs/v1/invite-user { org_id, email, role } */
    public static function invite_user(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $perm = self::can_manage_org($request);
        if ($perm instanceof WP_Error) return $perm;

        $org_id = absint($request->get_param('org_id'));
        $email  = sanitize_email($request->get_param('email'));
        $role   = sanitize_text_field($request->get_param('role') ?: '');
        if (!$org_id || !$email) {
            return new WP_Error('bad_request', 'org_id and email are required.', ['status' => 400]);
        }
        if (!in_array($role, self::ALLOWED_ROLES, true)) $role = 'c_employee';

        $actorRole = self::actor_role_for_org($org_id);
        if (!$actorRole) {
            return new WP_Error('forbidden', 'Unable to determine permissions for this action.', ['status' => 403]);
        }
        $assignable = self::assignable_roles_for_actor($actorRole);
        if (!in_array($role, $assignable, true)) {
            return new WP_Error('forbidden', 'You cannot invite users with that role.', ['status' => 403]);
        }
        $org_name = self::get_org_name($org_id) ?: sprintf('Organization #%d', $org_id);

        // If user exists, add membership + set WP role; no "invited" state.
        $user = get_user_by('email', $email);
        if ($user) {
            $user_id = (int) $user->ID;
            self::ensure_membership($org_id, $user_id, $role);
            self::set_wp_role($user_id, $role);

            $body = sprintf(
                "You've been granted access to %s as %s.\nSign in with your email to start collaborating.",
                $org_name,
                $role
            );
            $subject = sprintf('[%s] Access granted', get_bloginfo('name'));
            $login_url = home_url('/login');
            if (function_exists('kbs_send_email')) {
                \kbs_send_email(
                    $email,
                    $subject,
                    $body,
                    [
                        'greeting'  => $user->display_name ? "Hi {$user->display_name}," : 'Hello,',
                        'cta_label' => 'Open Vyavhar',
                        'cta_url'   => $login_url,
                    ]
                );
            } else {
                wp_mail($email, $subject, $body . "\n\n" . $login_url);
            }

            return new WP_REST_Response(['status' => 'active', 'user_id' => $user_id], 200);
        }

        // Else create an invite
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_org_invites';
        $token = bin2hex(random_bytes(16));
        $expires_at = gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS);

        // upsert by (org_id,email)
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND email = %s LIMIT 1",
            $org_id, $email
        ));
        if ($existing_id) {
            $wpdb->update($table,
                ['role' => $role, 'token' => $token, 'status' => 'invited', 'expires_at' => $expires_at],
                ['id'   => $existing_id],
                ['%s','%s','%s','%s'],
                ['%d']
            );
            $invite_id = $existing_id;
        } else {
            $wpdb->insert($table, [
                'org_id'     => $org_id,
                'email'      => $email,
                'role'       => $role,
                'token'      => $token,
                'status'     => 'invited',
                'invited_by' => get_current_user_id(),
                'created_at' => current_time('mysql', true),
                'expires_at' => $expires_at,
            ], ['%d','%s','%s','%s','%s','%d','%s','%s']);
            $invite_id = (int) $wpdb->insert_id;
        }

        // Send invitation
        $subject = sprintf('[%s] You have been invited', get_bloginfo('name'));
        $accept_url = self::build_accept_url($token, $email);
        $body = sprintf(
            "You've been invited to join %s with the role %s.\nUse the button below to accept the invitation. The link expires in 7 days.",
            $org_name,
            $role
        );
        if (function_exists('kbs_send_email')) {
            \kbs_send_email(
                $email,
                $subject,
                $body,
                [
                    'greeting'  => 'Hello,',
                    'cta_label' => 'Accept invitation',
                    'cta_url'   => $accept_url,
                ]
            );
        } else {
            wp_mail($email, $subject, $body . "\n\n" . $accept_url);
        }

        return new WP_REST_Response(['status' => 'invited', 'invite_id' => $invite_id], 200);
    }

    /** PUT /kbs/v1/user-role { org_id, user_id, role } */
    public static function update_user_role(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $perm = self::can_manage_org($request);
        if ($perm instanceof WP_Error) return $perm;

        $org_id  = absint($request->get_param('org_id'));
        $user_id = absint($request->get_param('user_id'));
        $role    = sanitize_text_field($request->get_param('role') ?: '');

        if (!$org_id || !$user_id || !in_array($role, self::ALLOWED_ROLES, true)) {
            return new WP_Error('bad_request', 'org_id, user_id and a valid role are required.', ['status' => 400]);
        }

        $actorRole = self::actor_role_for_org($org_id);
        if (!$actorRole) {
            return new WP_Error('forbidden', 'Unable to determine permissions for this action.', ['status' => 403]);
        }

        // Prevent self-demotion
        if ($user_id === get_current_user_id()) {
            return new WP_Error('forbidden', 'You cannot change your own role.', ['status' => 403]);
        }

        $currentRole = self::org_role_for_user($org_id, $user_id);
        if (!$currentRole) {
            return new WP_Error('not_found', 'User is not part of this organization.', ['status' => 404]);
        }
        if (!self::actor_can_manage_role($actorRole, $currentRole)) {
            return new WP_Error('forbidden', 'You cannot manage that user role.', ['status' => 403]);
        }

        $assignable = self::assignable_roles_for_actor($actorRole);
        if (!in_array($role, $assignable, true)) {
            return new WP_Error('forbidden', 'You cannot assign that role.', ['status' => 403]);
        }

        // Ensure membership exists and sync role
        self::ensure_membership($org_id, $user_id, $role);
        self::set_wp_role($user_id, $role);

        return new WP_REST_Response(['ok' => true], 200);
    }

    /** POST /kbs/v1/resend-invite { org_id, user_id? , email? } */
    public static function resend_invite(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $perm = self::can_manage_org($request);
        if ($perm instanceof WP_Error) return $perm;

        $org_id  = absint($request->get_param('org_id'));
        $user_id = $request->get_param('user_id');
        $actorRole = self::actor_role_for_org($org_id);
        if (!$actorRole) {
            return new WP_Error('forbidden', 'Unable to determine permissions for this action.', ['status' => 403]);
        }
        $email   = sanitize_email($request->get_param('email'));
        $org_name = self::get_org_name($org_id) ?: sprintf('Organization #%d', $org_id);

        // If user_id is like "invite:123", use that; otherwise try email param
        $invite_id = null;
        if (is_string($user_id) && str_starts_with($user_id, 'invite:')) {
            $invite_id = absint(substr($user_id, 7));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_org_invites';

        if ($invite_id) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, role, token FROM {$table} WHERE id = %d AND org_id = %d AND status = 'invited'",
                $invite_id, $org_id
            ));
            if (!$row) return new WP_Error('not_found', 'Invite not found.', ['status' => 404]);
            if (!self::actor_can_manage_role($actorRole, $row->role ?: 'c_employee')) {
                return new WP_Error('forbidden', 'You cannot manage that invite.', ['status' => 403]);
            }

            $accept_url = self::build_accept_url((string) $row->token, (string) $row->email);
            if (function_exists('kbs_send_email')) {
                \kbs_send_email(
                    $row->email,
                    '['.get_bloginfo('name').'] Invitation reminder',
                    sprintf('Here is your reminder to join %s.', $org_name),
                    [
                        'greeting'  => 'Hello,',
                        'cta_label' => 'Accept invitation',
                        'cta_url'   => $accept_url,
                    ]
                );
            } else {
                wp_mail($row->email, '['.get_bloginfo('name').'] Invitation reminder', "Accept invite: {$accept_url}");
            }
            return new WP_REST_Response(['resent' => true], 200);
        }

        if ($email) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, role, token FROM {$table} WHERE org_id = %d AND email = %s AND status = 'invited' LIMIT 1",
                $org_id, $email
            ));
            if (!$row) return new WP_Error('not_found', 'Invite not found.', ['status' => 404]);
            if (!self::actor_can_manage_role($actorRole, $row->role ?: 'c_employee')) {
                return new WP_Error('forbidden', 'You cannot manage that invite.', ['status' => 403]);
            }

            $accept_url = self::build_accept_url((string) $row->token, (string) $row->email);
            if (function_exists('kbs_send_email')) {
                \kbs_send_email(
                    $row->email,
                    '['.get_bloginfo('name').'] Invitation reminder',
                    sprintf('Here is your reminder to join %s.', $org_name),
                    [
                        'greeting'  => 'Hello,',
                        'cta_label' => 'Accept invitation',
                        'cta_url'   => $accept_url,
                    ]
                );
            } else {
                wp_mail($row->email, '['.get_bloginfo('name').'] Invitation reminder', "Accept invite: {$accept_url}");
            }
            return new WP_REST_Response(['resent' => true], 200);
        }

        return new WP_Error('bad_request', 'Provide invite id or email.', ['status' => 400]);
    }

    /** DELETE /kbs/v1/user { org_id, user_id } — removes membership or cancels invite */
    public static function remove_user(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $perm = self::can_manage_org($request);
        if ($perm instanceof WP_Error) return $perm;

        $org_id  = absint($request->get_param('org_id'));
        $user_id = $request->get_param('user_id');
        $actorRole = self::actor_role_for_org($org_id);
        if (!$actorRole) {
            return new WP_Error('forbidden', 'Unable to determine permissions for this action.', ['status' => 403]);
        }

        // invited row?
        if (is_string($user_id) && str_starts_with($user_id, 'invite:')) {
            $invite_id = absint(substr($user_id, 7));
            global $wpdb;
            $table = $wpdb->prefix . 'kbs_org_invites';
            $invite = $wpdb->get_row($wpdb->prepare(
                "SELECT id, role FROM {$table} WHERE id = %d AND org_id = %d",
                $invite_id,
                $org_id
            ));
            if (!$invite) {
                return new WP_Error('not_found', 'Invite not found.', ['status' => 404]);
            }
            if (!self::actor_can_manage_role($actorRole, $invite->role ?: 'c_employee')) {
                return new WP_Error('forbidden', 'You cannot remove that invite.', ['status' => 403]);
            }
            $wpdb->delete($table, ['id' => $invite_id, 'org_id' => $org_id], ['%d','%d']);
            return new WP_REST_Response(['deleted' => true], 200);
        }

        $user_id = absint($user_id);
        if (!$user_id) {
            return new WP_Error('bad_request', 'user_id is required.', ['status' => 400]);
        }

        // Prevent removing yourself
        if ($user_id === get_current_user_id()) {
            return new WP_Error('forbidden', 'You cannot remove yourself.', ['status' => 403]);
        }

        $currentRole = self::org_role_for_user($org_id, $user_id);
        if (!$currentRole) {
            return new WP_Error('not_found', 'User is not part of this organization.', ['status' => 404]);
        }
        if (!self::actor_can_manage_role($actorRole, $currentRole)) {
            return new WP_Error('forbidden', 'You cannot remove that user.', ['status' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        $wpdb->delete($table, ['org_id' => $org_id, 'user_id' => $user_id], ['%d','%d']);

        return new WP_REST_Response(['deleted' => true], 200);
    }
}
