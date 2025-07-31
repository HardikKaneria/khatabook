<?php
namespace KBS\Auth;

defined('ABSPATH') || exit;

class RegisterController {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('khatabook/v1', '/register', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_register'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_register(\WP_REST_Request $request) {
        $params   = $request->get_json_params();
        $email    = sanitize_email($params['email'] ?? '');
        $name     = sanitize_text_field($params['name'] ?? '');
        $password = sanitize_text_field($params['password'] ?? '');

        if (empty($email) || empty($name) || empty($password)) {
            return new \WP_REST_Response(['message' => 'All fields are required.'], 400);
        }

        if (email_exists($email)) {
            return new \WP_REST_Response(['message' => 'Email is already registered.'], 409);
        }

        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            return new \WP_REST_Response(['message' => 'User creation failed.'], 500);
        }

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name,
        ]);

        // Mark user as pending
        update_user_meta($user_id, 'khatabook_status', 'pending');

        return new \WP_REST_Response([
            'message' => 'Registration submitted. Awaiting admin approval.',
            'user_id' => $user_id,
        ], 200);
    }
}
