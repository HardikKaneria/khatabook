<?php

namespace KBS\Auth;
use KBS\Core\UserManager; // Corrected namespace

class OtpAuth
{
    public static function register_endpoints(): void
    {
        register_rest_route('kbs/v1', '/send-otp', [
            'methods' => 'POST',
            'callback' => [self::class, 'send_otp'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kbs/v1', '/verify-otp', [
            'methods' => 'POST',
            'callback' => [self::class, 'verify_otp'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function send_otp(\WP_REST_Request $request)
    {
        $email = sanitize_email($request->get_param('email'));

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
        }

        $otp = rand(100000, 999999);
        set_transient('otp_' . md5($email), $otp, 5 * MINUTE_IN_SECONDS);

        $subject = 'Your OTP Code';
        $message = "Your OTP for login is: $otp";
        wp_mail($email, $subject, $message);

        return ['status' => 'sent'];
    }

    public static function verify_otp(\WP_REST_Request $request)
    {
        $email = sanitize_email($request->get_param('email'));
        $otp_input = sanitize_text_field($request->get_param('otp'));

        $stored_otp = get_transient('otp_' . md5($email));
        if (!$stored_otp || $otp_input !== (string) $stored_otp) {
            return new \WP_Error('invalid_otp', 'Invalid or expired OTP', ['status' => 401]);
        }

        delete_transient('otp_' . md5($email)); // Clean up

        $user = get_user_by('email', $email);
        if (!$user) {
            $random_password = wp_generate_password();
            $user_id = wp_create_user($email, $random_password, $email);
            if (is_wp_error($user_id)) {
                return new \WP_Error('user_create_failed', 'Failed to create user');
            }
            $user = get_user_by('id', $user_id);
            $user->set_role('c_employee');
            UserManager::mark_user_pending($user_id); // Set as pending
        }

        // Block login if not approved
        if (!UserManager::is_user_approved($user->ID)) {
            return new \WP_Error('not_approved', 'Your account is not yet approved by admin', ['status' => 403]);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        return [
            'status' => 'authenticated',
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'role' => $user->roles[0] ?? null,
        ];
    }
}
