<?php

namespace KBS\Auth;

use KBS\Core\SystemLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class RegisterController
{
    private const OTP_MAX_AGE_SECONDS = 10 * MINUTE_IN_SECONDS;

    public static function handle_register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $data     = $request->get_json_params();
        $email    = sanitize_email($data['email'] ?? '');
        $name     = sanitize_text_field($data['name'] ?? '');
        $company  = sanitize_text_field($data['company'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $otp      = sanitize_text_field($data['otp'] ?? '');

        // ---- Validate inputs
        if (!$email || !$name || !$company || !$password || !$otp) {
            return new WP_REST_Response(['message' => 'All fields are required.'], 400);
        }
        if (!is_email($email)) {
            return new WP_REST_Response(['message' => 'Invalid email address.'], 400);
        }
        if (strlen($password) < 8) {
            return new WP_REST_Response(['message' => 'Password must be at least 8 characters.'], 400);
        }

        // Already a WP user?
        if (email_exists($email)) {
            return new WP_REST_Response(['message' => 'Email already exists.'], 409);
        }

        $otp_table      = $wpdb->prefix . 'kbs_otp_attempts';
        $pending_table  = $wpdb->prefix . 'kbs_pending_users';

        // ---- Ensure an OTP was sent recently (freshness check via attempts table)
        $last_sent = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, created_at
                 FROM {$otp_table}
                 WHERE email = %s AND status = 'sent'
                 ORDER BY id DESC
                 LIMIT 1",
                $email
            )
        );
        if (!$last_sent) {
            return new WP_REST_Response(['message' => 'OTP not sent. Please request a new OTP.'], 403);
        }
        $age = time() - strtotime((string) $last_sent->created_at);
        if ($age > self::OTP_MAX_AGE_SECONDS) {
            return new WP_REST_Response(['message' => 'OTP expired. Please request a new OTP.'], 403);
        }

        // ---- Verify OTP against the transient hash (we do NOT store raw OTP in DB)
        $transient_key = 'otp_' . md5(strtolower(trim($email)));
        $stored_hash   = get_transient($transient_key);
        if (!$stored_hash || !is_string($stored_hash) || !password_verify($otp, $stored_hash)) {
            // mark failed (schema-aware insert)
            self::insert_attempt_row($email, 'failed', $request->get_param('context') ?: '');
            return new WP_REST_Response(['message' => 'Invalid OTP entered.'], 403);
        }

        // consume OTP
        delete_transient($transient_key);
        // mark last "sent" row as verified
        $wpdb->update(
            $otp_table,
            ['status' => 'verified', 'otp_code' => '******'],
            ['id' => (int) $last_sent->id],
            ['%s','%s'],
            ['%d']
        );

        // ---- Upsert into pending table (idempotent)
        $hashed_password = wp_hash_password($password);

        // If a pending row already exists, refresh it; else insert new
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$pending_table} WHERE email = %s ORDER BY id DESC LIMIT 1",
                $email
            )
        );

        if ($existing && $existing->status === 'pending') {
            $ok = $wpdb->update(
                $pending_table,
                [
                    'name'       => $name,
                    'company'    => $company,
                    'password'   => $hashed_password,
                    'applied_at' => current_time('mysql'),
                ],
                ['id' => (int) $existing->id],
                ['%s','%s','%s','%s'],
                ['%d']
            );
        } else {
            $ok = $wpdb->insert(
                $pending_table,
                [
                    'name'       => $name,
                    'email'      => $email,
                    'company'    => $company,
                    'password'   => $hashed_password,
                    'status'     => 'pending',
                    'applied_at' => current_time('mysql'),
                ],
                ['%s','%s','%s','%s','%s','%s']
            );
        }

        if (!$ok) {
            return new WP_REST_Response(['message' => 'Failed to save registration.'], 500);
        }

        if (class_exists(SystemLogger::class)) {
            SystemLogger::log_registration($email, 'User submitted registration and passed OTP.');
        }

        if (function_exists('kbs_send_email')) {
            $cta = home_url('/login');
            $body = "Thanks for registering {$company} with Vyavhar. Our team will review your application shortly. We'll notify you as soon as an admin approves your account.";
            \kbs_send_email(
                $email,
                'Registration received – waiting for approval',
                $body,
                [
                    'greeting'  => "Hi {$name},",
                    'cta_label' => 'Check status',
                    'cta_url'   => $cta,
                ]
            );
        }

        return new WP_REST_Response([
            'message' => 'Registration submitted successfully. Awaiting admin approval.'
        ], 200);
    }

    /**
     * Insert a row into kbs_otp_attempts in a schema-aware way (handles absence of ip/context).
     */
    private static function insert_attempt_row(string $email, string $status, string $context = ''): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kbs_otp_attempts';

        $insert  = [
            'email'      => $email,
            'otp_code'   => '******',
            'status'     => $status,
            'created_at' => current_time('mysql'),
        ];
        $formats = ['%s','%s','%s','%s'];

        // optional columns
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (is_array($cols)) {
            if (in_array('ip', $cols, true)) {
                $insert['ip'] = self::client_ip();
                $formats[] = '%s';
            }
            if (in_array('context', $cols, true)) {
                $insert['context'] = sanitize_text_field($context);
                $formats[] = '%s';
            }
        }

        $wpdb->insert($table, $insert, $formats);
    }

    private static function client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $v = explode(',', $_SERVER[$k])[0];
                return trim($v);
            }
        }
        return '0.0.0.0';
    }
}
