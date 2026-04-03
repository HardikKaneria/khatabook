<?php

namespace KBS\Auth;

use KBS\Core\SystemLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class RegisterController
{
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

        $pending_table  = $wpdb->prefix . 'kbs_pending_users';

        if (!OtpAuth::has_active_otp($email, 'register')) {
            return new WP_REST_Response(['message' => 'OTP not sent. Please request a new OTP.'], 403);
        }

        $otp_result = OtpAuth::consume_otp($email, $otp, 'register');
        if (is_wp_error($otp_result)) {
            return new WP_REST_Response(['message' => $otp_result->get_error_message()], 403);
        }

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
}
