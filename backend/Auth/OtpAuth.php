<?php

namespace KBS\Auth;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

class OtpAuth
{
    /** Config */
    private const OTP_CONTEXT_LOGIN  = 'login';
    private const OTP_CONTEXT_REGISTER = 'register';
    private const OTP_REDACTED       = '******';
    private const OTP_TTL_SECONDS    = 5 * MINUTE_IN_SECONDS;   // 5 minutes
    private const RESEND_COOLDOWN    = 60;                      // 1 minute
    private const WINDOW_SECONDS     = 10 * MINUTE_IN_SECONDS;  // attempt window
    private const MAX_ATTEMPTS_EMAIL = 8;                       // per email per window
    private const MAX_ATTEMPTS_IP    = 20;                      // per IP per window
    private const OTP_MIN            = 100000;                  // 6 digits
    private const OTP_MAX            = 999999;

    /** ---------------- SEND OTP ---------------- */
    public static function send_otp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email   = sanitize_email($request->get_param('email'));
        $context = self::normalize_context($request->get_param('context'));
        $ip      = self::client_ip();

        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address.', ['status' => 400]);
        }
        if (!$context) {
            return new WP_Error('invalid_context', 'Invalid context provided.', ['status' => 400]);
        }

        if ($context === self::OTP_CONTEXT_LOGIN) {
            $user = get_user_by('email', $email);
            $loginState = vy_login_access_state(
                (bool) $user,
                $user ? !class_exists('\KBS\User\UserManager') || \KBS\User\UserManager::is_user_approved((int) $user->ID) : false
            );

            if ($loginState === 'not_registered') {
                return new WP_REST_Response([
                    'status'  => 'not_registered',
                    'message' => 'This email is not registered. Please register first.',
                ], 400);
            }

            if ($loginState === 'not_approved') {
                return new WP_Error('not_approved', 'Your account is not yet approved by admin.', ['status' => 403]);
            }
        }

        if ($context === self::OTP_CONTEXT_REGISTER && email_exists($email)) {
            return new WP_REST_Response([
                'status'  => 'email_exists',
                'message' => 'This email is already registered. Please sign in instead.',
            ], 409);
        }

        // --- Throttle / cooldown ---
        if (self::recent_sent_within_cooldown($email, $context)) {
            return new WP_Error('otp_cooldown', 'Please wait a minute before requesting another OTP.', ['status' => 429]);
        }
        if (!self::under_limits($email, $ip)) {
            return new WP_Error('rate_limited', 'Too many OTP requests. Please try again later.', ['status' => 429]);
        }

        // Generate + hash for transient verification. Audit rows stay redacted.
        $otp      = (string) random_int(self::OTP_MIN, self::OTP_MAX);
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);

        $transient_key = self::transient_key($email, $context);
        set_transient($transient_key, $otp_hash, self::OTP_TTL_SECONDS);
        self::insert_attempt_row($email, 'sent', $context, $ip);

        // Send email
        $subject = 'Your Vyavhar OTP Code';
        $message = "Use this one-time passcode to continue your " . ucfirst($context) . "This code expires in 5 minutes. If you didn’t request it, you can safely ignore this email.";
        if (function_exists('kbs_send_email')) {
            $sent = \kbs_send_email($email, $subject, $message, [
                'variant'      => 'otp',
                'eyebrow'      => $context === self::OTP_CONTEXT_LOGIN ? 'Secure sign in' : 'Registration verification',
                'greeting'     => 'Hello,',
                'otp_code'     => $otp,
                'helper_lines' => [
                    'This code expires in 5 minutes.',
                    'Enter it exactly as shown in the Vyavhar screen.',
                    'If you did not request this code, you can safely ignore this email.',
                ],
                'cta_label'    => $context === self::OTP_CONTEXT_LOGIN ? 'Open login' : 'Open registration',
                'cta_url'      => $context === self::OTP_CONTEXT_LOGIN ? home_url('/login') : home_url('/register'),
            ]);
        } else {
            $sent = wp_mail($email, $subject, $message);
        }

        if (!$sent) {
            return new WP_Error('email_failed', 'Failed to send OTP email.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'status'        => 'sent',
            'message'       => 'OTP sent successfully.',
            'ttl_seconds'   => self::OTP_TTL_SECONDS,
            'cooldown_secs' => self::RESEND_COOLDOWN,
        ], 200);
    }

    public static function has_active_otp(string $email, string $context): bool
    {
        $normalized_context = self::normalize_context($context);
        if (!$normalized_context) {
            return false;
        }

        $stored_hash = get_transient(self::transient_key($email, $normalized_context));
        return is_string($stored_hash) && $stored_hash !== '';
    }

    public static function consume_otp(string $email, string $otp_input, string $context): bool|WP_Error
    {
        $normalized_context = self::normalize_context($context);
        if (!$normalized_context) {
            return new WP_Error('invalid_context', 'Invalid OTP context.', ['status' => 400]);
        }

        $stored_hash = get_transient(self::transient_key($email, $normalized_context));
        if (!$stored_hash || !is_string($stored_hash) || !password_verify($otp_input, $stored_hash)) {
            self::insert_attempt_row($email, 'failed', $normalized_context, self::client_ip());
            return new WP_Error('invalid_otp', 'Invalid or expired OTP.', ['status' => 401]);
        }

        delete_transient(self::transient_key($email, $normalized_context));
        self::mark_latest_sent_attempt($email, $normalized_context, 'verified');

        return true;
    }

    public static function create_auth_payload(int $user_id, ?int $forced_org_id = null): array|WP_Error
    {
        $token  = bin2hex(random_bytes(32));
        $expiry = time() + 2 * HOUR_IN_SECONDS;

        update_user_meta($user_id, 'auth_token', $token);
        update_user_meta($user_id, 'auth_token_expires', $expiry);

        self::destroy_all_sessions_for_user($user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return self::build_auth_payload($user_id, $token, $expiry, $forced_org_id);
    }

    public static function hydrate_existing_auth_payload(int $user_id, ?int $forced_org_id = null): array|WP_Error
    {
        $token = (string) get_user_meta($user_id, 'auth_token', true);
        $expiry = (int) get_user_meta($user_id, 'auth_token_expires', true);

        if ($token === '' || $expiry <= 0 || time() >= $expiry) {
            return new WP_Error('unauthorized', 'Session expired. Please login again.', ['status' => 401]);
        }

        return self::build_auth_payload($user_id, $token, $expiry, $forced_org_id);
    }

    private static function build_auth_payload(int $user_id, string $token, int $expiry, ?int $forced_org_id = null): array|WP_Error
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
        }

        $primaryOrg = self::get_primary_org_for_user($user_id);
        $allOrgs    = self::get_all_orgs_for_user($user_id);
        $org_id     = null;

        if ($forced_org_id && self::org_id_in_memberships($forced_org_id, $allOrgs)) {
            $org_id = $forced_org_id;
        }

        if (!$org_id) {
            $org_id = self::preferred_org_id_for_user($user_id, $allOrgs);
        }
        if (!$org_id && $primaryOrg) {
            $org_id = (int) $primaryOrg['org_id'];
        }

        if ($org_id) {
            update_user_meta($user_id, 'org_id', $org_id);
            update_user_meta($user_id, 'vy_active_org_id', $org_id);
        }

        $rest_nonce = wp_create_nonce('wp_rest');
        $orgs_payload = array_map(static function ($row) {
            return [
                'org_id'     => (int) $row['org_id'],
                'org_name'   => isset($row['org_name']) ? sanitize_text_field((string) $row['org_name']) : null,
                'is_primary' => (bool) $row['is_primary'],
                'role'       => isset($row['role']) ? sanitize_key((string) $row['role']) : null,
            ];
        }, $allOrgs);

        $active_org_role = null;
        $active_org_name = null;
        foreach ($orgs_payload as $org_row) {
            if ((int) ($org_row['org_id'] ?? 0) === (int) $org_id) {
                $active_org_role = $org_row['role'] ?: null;
                $active_org_name = $org_row['org_name'] ?: null;
                break;
            }
        }

        $wp_role = $user->roles[0] ?? null;
        if (!$active_org_role && user_can($user_id, 'manage_options')) {
            $active_org_role = 'administrator';
        }

        return [
            'status'     => 'authenticated',
            'token'      => $token,
            'expires_at' => $expiry,
            'user'       => [
                'id'           => $user_id,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'role'         => $active_org_role ?: $wp_role,
                'wp_role'      => $wp_role,
                'org_id'       => $org_id,
                'org_name'     => $active_org_name,
                'orgs'         => $orgs_payload,
            ],
            'rest' => [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => $rest_nonce,
            ],
        ];
    }

    /** ---------------- VERIFY OTP ---------------- */
    public static function verify_otp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email     = sanitize_email($request->get_param('email'));
        $otp_input = trim((string) $request->get_param('otp'));
        $context   = self::normalize_context($request->get_param('context'), self::OTP_CONTEXT_LOGIN);

        if (!$email || !$otp_input) {
            return new WP_Error('missing_fields', 'Email and OTP are required.', ['status' => 400]);
        }
        if ($context !== self::OTP_CONTEXT_LOGIN) {
            return new WP_Error('invalid_context', 'Login OTP verification requires the login context.', ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        $loginState = vy_login_access_state(
            (bool) $user,
            $user ? !class_exists('\KBS\User\UserManager') || \KBS\User\UserManager::is_user_approved((int) $user->ID) : false
        );

        if ($loginState === 'not_registered') {
            return new WP_REST_Response([
                'status'  => 'not_registered',
                'message' => 'This email is not registered. Please register first.',
            ], 400);
        }
        $user_id = (int) $user->ID;

        if ($loginState === 'not_approved') {
            return new WP_Error('not_approved', 'Your account is not yet approved by admin.', ['status' => 403]);
        }

        $otp_result = self::consume_otp($email, $otp_input, $context);
        if (is_wp_error($otp_result)) {
            return $otp_result;
        }

        $payload = self::create_auth_payload($user_id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        return new WP_REST_Response($payload, 200);
    }


    /** ---------------- LOGOUT ---------------- */
    public static function handle_logout(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = self::resolve_authenticated_user_id($request);
        if ($user_id) {
            delete_user_meta($user_id, 'auth_token');
            delete_user_meta($user_id, 'auth_token_expires');
            self::destroy_all_sessions_for_user($user_id);
        }

        if (is_user_logged_in()) {
            wp_destroy_current_session();
        }

        wp_clear_auth_cookie();
        wp_set_current_user(0);

        return new WP_REST_Response([
            'status'  => 'logged_out',
            'message' => 'You have been logged out successfully.',
        ], 200);
    }

    /** ================= helpers ================= */

    private static function normalize_context($context, ?string $default = null): ?string
    {
        $normalized = sanitize_text_field((string) ($context ?? ''));
        if ($normalized === '' && $default !== null) {
            $normalized = $default;
        }

        return in_array($normalized, [self::OTP_CONTEXT_LOGIN, self::OTP_CONTEXT_REGISTER], true)
            ? $normalized
            : null;
    }

    private static function transient_key(string $email, string $context): string
    {
        return 'otp_' . md5(strtolower(trim($context . ':' . $email)));
    }

    private static function client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $v = explode(',', $_SERVER[$k])[0];
                return trim($v);
            }
        }
        return '0.0.0.0';
    }

    private static function recent_sent_within_cooldown(string $email, string $context): bool
    {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - self::RESEND_COOLDOWN);
        $sql = "SELECT id FROM {$wpdb->prefix}kbs_otp_attempts
             WHERE email=%s AND status='sent' AND created_at >= %s";
        $params = [$email, $since];
        if (self::has_col('context')) {
            $sql .= " AND context=%s";
            $params[] = $context;
        }
        $sql .= " ORDER BY id DESC LIMIT 1";
        $row = $wpdb->get_var($wpdb->prepare($sql, ...$params));
        return !empty($row);
    }

    private static function insert_attempt_row(string $email, string $status, string $context, string $ip): void
    {
        global $wpdb;

        $insert  = [
            'email'      => $email,
            'otp_code'   => self::OTP_REDACTED,
            'status'     => $status,
            'created_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s', '%s', '%s'];

        if (self::has_col('ip')) {
            $insert['ip'] = $ip;
            $formats[] = '%s';
        }
        if (self::has_col('context')) {
            $insert['context'] = $context;
            $formats[] = '%s';
        }

        $wpdb->insert($wpdb->prefix . 'kbs_otp_attempts', $insert, $formats);
    }

    private static function mark_latest_sent_attempt(string $email, string $context, string $status): void
    {
        global $wpdb;

        $sql = "UPDATE {$wpdb->prefix}kbs_otp_attempts
             SET status = %s, otp_code = %s
             WHERE email = %s AND status = %s";
        $params = [$status, self::OTP_REDACTED, $email, 'sent'];
        if (self::has_col('context')) {
            $sql .= " AND context = %s";
            $params[] = $context;
        }
        $sql .= " ORDER BY id DESC LIMIT 1";

        $wpdb->query($wpdb->prepare($sql, ...$params));
    }

    private static function request_token(WP_REST_Request $request): string
    {
        return (string) ($request->get_header('X-KBS-Token') ?: $request->get_header('x-kbs-token') ?: '');
    }

    private static function resolve_authenticated_user_id(WP_REST_Request $request): int
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return $user_id;
        }

        $token = self::request_token($request);
        if ($token === '') {
            return 0;
        }

        global $wpdb;
        $resolved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'auth_token' AND meta_value = %s LIMIT 1",
            $token
        ));

        if ($resolved <= 0) {
            return 0;
        }

        $storedToken = (string) get_user_meta($resolved, 'auth_token', true);
        $expires = (int) get_user_meta($resolved, 'auth_token_expires', true);
        if (!vy_auth_token_is_active($token, $storedToken, $expires)) {
            return 0;
        }

        return $resolved;
    }

    private static function under_limits(string $email, string $ip): bool
    {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $table = $wpdb->prefix . 'kbs_otp_attempts';

        // per email
        $countEmail = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email=%s AND created_at >= %s",
            $email,
            $since
        ));
        if ($countEmail >= self::MAX_ATTEMPTS_EMAIL) return false;

        // per IP (only if column exists)
        if (self::has_col('ip')) {
            $countIp = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ip=%s AND created_at >= %s",
                $ip,
                $since
            ));
            if ($countIp >= self::MAX_ATTEMPTS_IP) return false;
        }

        return true;
    }

    /** ---- schema awareness ---- */

    /** Return list of columns for kbs_otp_attempts (cached) */
    private static function otp_cols(): array
    {
        static $cols = null;
        if ($cols !== null) return $cols;

        global $wpdb;
        $table = $wpdb->prefix . 'kbs_otp_attempts';
        $cols = [];
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!empty($r['Field'])) $cols[] = $r['Field'];
            }
        }
        return $cols;
    }

    private static function has_col(string $name): bool
    {
        return in_array($name, self::otp_cols(), true);
    }

    private static function preferred_org_id_for_user(int $user_id, array $all_orgs): ?int
    {
        $accessible_org_ids = array_map(
            static fn(array $row): int => (int) ($row['org_id'] ?? 0),
            $all_orgs
        );

        foreach (['vy_active_org_id', 'org_id'] as $meta_key) {
            $candidate = (int) get_user_meta($user_id, $meta_key, true);
            if ($candidate > 0 && in_array($candidate, $accessible_org_ids, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function org_id_in_memberships(int $org_id, array $all_orgs): bool
    {
        return vy_org_membership_allows($all_orgs, $org_id);
    }

    /** Return the user's primary org row (or the most recent) */
    private static function get_primary_org_for_user(int $user_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        // Guard if table not present
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table
        ));
        if (!$exists) return null;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT org_id, is_primary, role
             FROM {$table}
             WHERE user_id = %d
             ORDER BY is_primary DESC, id DESC
             LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Return all orgs for the user (handy for org switching UI) */
    private static function get_all_orgs_for_user(int $user_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_user_org_roles';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table
        ));
        if (!$exists) return [];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.org_id, r.is_primary, r.role, o.org_name
                 FROM {$table} r
                 LEFT JOIN {$wpdb->prefix}kbs_organizations o ON o.org_id = r.org_id
                 WHERE r.user_id = %d
                 ORDER BY r.is_primary DESC, r.id DESC",
                $user_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    private static function destroy_all_sessions_for_user(int $user_id): void
    {
        if ($user_id <= 0 || !class_exists('\WP_Session_Tokens')) {
            return;
        }

        \WP_Session_Tokens::get_instance($user_id)->destroy_all();
    }
}
