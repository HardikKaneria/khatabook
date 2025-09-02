<?php

namespace KBS\Auth;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

class OtpAuth
{
    /** Config */
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
        global $wpdb;

        $email   = sanitize_email($request->get_param('email'));
        $context = sanitize_text_field($request->get_param('context')); // 'login' | 'register'
        $ip      = self::client_ip();

        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address.', ['status' => 400]);
        }
        if (!in_array($context, ['login', 'register'], true)) {
            return new WP_Error('invalid_context', 'Invalid context provided.', ['status' => 400]);
        }

        if ($context === 'login') {
            $user = get_user_by('email', $email);
            if (!$user) {
                return new WP_REST_Response([
                    'status'  => 'not_registered',
                    'message' => 'This email is not registered. Please register first.',
                ], 400);
            }
        }

        // --- Throttle / cooldown ---
        if (self::recent_sent_within_cooldown($email)) {
            return new WP_Error('otp_cooldown', 'Please wait a minute before requesting another OTP.', ['status' => 429]);
        }
        if (!self::under_limits($email, $ip)) {
            return new WP_Error('rate_limited', 'Too many OTP requests. Please try again later.', ['status' => 429]);
        }

        // Generate + hash (do not store raw OTP)
        $otp      = (string) random_int(self::OTP_MIN, self::OTP_MAX);
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);

        $transient_key = self::transient_key($email);
        set_transient($transient_key, $otp_hash, self::OTP_TTL_SECONDS);

        // Log attempt (mask the code in DB) — schema-aware
        $insert  = [
            'email'      => $email,
            'otp_code'   => $otp,
            'status'     => 'sent',
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

        // Send email
        $subject = 'Your OTP Code';
        $message = "Hello,\n\nYour OTP for " . ucfirst($context) . " is: {$otp}\nIt expires in 5 minutes.\n\nIf you didn’t request this, ignore this email.";
        $sent    = wp_mail($email, $subject, $message);

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

    /** ---------------- VERIFY OTP ---------------- */
    public static function verify_otp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $email     = sanitize_email($request->get_param('email'));
        $otp_input = trim((string) $request->get_param('otp'));

        if (!$email || !$otp_input) {
            return new WP_Error('missing_fields', 'Email and OTP are required.', ['status' => 400]);
        }

        $transient_key = self::transient_key($email);
        $stored_hash   = get_transient($transient_key);

        if (!$stored_hash || !is_string($stored_hash) || !password_verify($otp_input, $stored_hash)) {
            // Log failure
            $insert  = [
                'email'      => $email,
                'otp_code'   => '******',
                'status'     => 'failed',
                'created_at' => current_time('mysql'),
            ];
            $formats = ['%s', '%s', '%s', '%s'];

            if (self::has_col('ip')) {
                $insert['ip'] = self::client_ip();
                $formats[] = '%s';
            }
            if (self::has_col('context')) {
                $insert['context'] = sanitize_text_field($request->get_param('context') ?: '');
                $formats[] = '%s';
            }

            $wpdb->insert($wpdb->prefix . 'kbs_otp_attempts', $insert, $formats);
            return new WP_Error('invalid_otp', 'Invalid or expired OTP.', ['status' => 401]);
        }

        // OTP ok → consume it
        delete_transient($transient_key);

        // Mark last "sent" row as verified
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}kbs_otp_attempts
             SET status = %s, otp_code = %s
             WHERE email = %s AND status = %s
             ORDER BY id DESC LIMIT 1",
                'verified',
                '******',
                $email,
                'sent'
            )
        );

        // Ensure WP user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                return new WP_Error('user_create_failed', 'User creation failed.', ['status' => 500]);
            }
            $user = get_user_by('ID', $user_id);
        }
        $user_id = (int) $user->ID;

        // Approval gate (if you use it)
        if (class_exists('\KBS\User\UserManager') && !\KBS\User\UserManager::is_user_approved($user_id)) {
            return new WP_Error('not_approved', 'Your account is not yet approved by admin.', ['status' => 403]);
        }

        // Resolve org(s) — we only return org_id(s), role comes from WP role
        $primaryOrg = self::get_primary_org_for_user($user_id); // may be null
        $allOrgs    = self::get_all_orgs_for_user($user_id);    // list of {org_id,is_primary}

        $org_id = $primaryOrg ? (int) $primaryOrg['org_id'] : null;

        // (optional) store org_id in usermeta for quick lookup
        if ($org_id) {
            update_user_meta($user_id, 'org_id', $org_id);
        }

        // Token + cookies + REST nonce
        $token  = bin2hex(random_bytes(32));
        $expiry = time() + 2 * HOUR_IN_SECONDS;

        update_user_meta($user_id, 'auth_token', $token);
        update_user_meta($user_id, 'auth_token_expires', $expiry);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        $rest_nonce = wp_create_nonce('wp_rest');

        // Shape orgs array
        $orgs_payload = array_map(static function ($r) {
            return [
                'org_id'     => (int) $r['org_id'],
                'is_primary' => (bool) $r['is_primary'],
            ];
        }, $allOrgs);

        // WP role is the only role we expose
        $wp_role = $user->roles[0] ?? null;

        return new WP_REST_Response([
            'status'     => 'authenticated',
            'token'      => $token,
            'expires_at' => $expiry,
            'user'       => [
                'id'           => $user_id,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'role'         => $wp_role,   // <- single source of truth
                'org_id'       => $org_id,    // <- primary org id (or null)
                'orgs'         => $orgs_payload,
            ],
            'rest' => [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => $rest_nonce,
            ],
        ], 200);
    }


    /** ---------------- LOGOUT ---------------- */
    public static function handle_logout(WP_REST_Request $request): WP_REST_Response
    {
        wp_destroy_current_session();
        wp_clear_auth_cookie();

        $user_id = get_current_user_id();
        if ($user_id) {
            delete_user_meta($user_id, 'auth_token');
            delete_user_meta($user_id, 'auth_token_expires');
        }

        return new WP_REST_Response([
            'status'  => 'logged_out',
            'message' => 'You have been logged out successfully.',
        ], 200);
    }

    /** ================= helpers ================= */

    private static function transient_key(string $email): string
    {
        return 'otp_' . md5(strtolower(trim($email)));
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

    private static function recent_sent_within_cooldown(string $email): bool
    {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - self::RESEND_COOLDOWN);
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kbs_otp_attempts
             WHERE email=%s AND status='sent' AND created_at >= %s
             ORDER BY id DESC LIMIT 1",
            $email,
            $since
        ));
        return !empty($row);
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

        // We only care about org_id + is_primary. Ignore role (we use WP role).
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT org_id, is_primary
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
                "SELECT org_id, is_primary
             FROM {$table}
             WHERE user_id = %d
             ORDER BY is_primary DESC, id DESC",
                $user_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }
}
