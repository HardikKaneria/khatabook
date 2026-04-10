<?php

use KBS\Auth\OtpAuth;

kbs_test('otp verify rejects non-login contexts for public login verification', function (): void {
    $request = kbs_test_make_request('POST', '/kbs/v1/verify-otp', [
        'email' => 'user@example.com',
        'otp' => '123456',
        'context' => 'register',
    ]);

    $result = OtpAuth::verify_otp($request);
    kbs_assert_wp_error($result, 'invalid_context', 400);
});

kbs_test('otp verify returns not_registered for unknown login email', function (): void {
    $request = kbs_test_make_request('POST', '/kbs/v1/verify-otp', [
        'email' => 'missing@example.com',
        'otp' => '123456',
        'context' => 'login',
    ]);

    $result = OtpAuth::verify_otp($request);
    $response = kbs_assert_response($result, 400);
    $data = $response->get_data();

    kbs_assert_same('not_registered', $data['status'] ?? null);
});

kbs_test('otp verify blocks unapproved users before issuing auth', function (): void {
    kbs_test_add_user([
        'ID' => 101,
        'user_email' => 'pending@example.com',
        'display_name' => 'Pending User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_user_meta(101, 'kbs_account_status', 'pending');

    $request = kbs_test_make_request('POST', '/kbs/v1/verify-otp', [
        'email' => 'pending@example.com',
        'otp' => '123456',
        'context' => 'login',
    ]);

    $result = OtpAuth::verify_otp($request);
    kbs_assert_wp_error($result, 'not_approved', 403);
});

kbs_test('otp verify authenticates approved users and consumes the active login otp', function (): void {
    kbs_test_add_user([
        'ID' => 102,
        'user_email' => 'approved@example.com',
        'display_name' => 'Approved User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_user_meta(102, 'kbs_account_status', 'approved');
    kbs_test_set_user_meta(102, 'vy_active_org_id', 11);
    kbs_test_seed_org_membership(102, 11, 'company_admin', true, 'Alpha Traders');

    $email = 'approved@example.com';
    $otp = '123456';
    $transientKey = 'otp_' . md5(strtolower(trim('login:' . $email)));
    set_transient($transientKey, password_hash($otp, PASSWORD_DEFAULT), 300);
    kbs_test_seed_table($GLOBALS['wpdb']->prefix . 'kbs_otp_attempts', [[
        'id' => 1,
        'email' => $email,
        'otp_code' => '******',
        'status' => 'sent',
        'created_at' => current_time('mysql'),
        'ip' => '127.0.0.1',
        'context' => 'login',
    ]]);

    $request = kbs_test_make_request('POST', '/kbs/v1/verify-otp', [
        'email' => $email,
        'otp' => $otp,
        'context' => 'login',
    ]);

    $result = OtpAuth::verify_otp($request);
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same('authenticated', $data['status'] ?? null);
    kbs_assert_same(102, $data['user']['id'] ?? null);
    kbs_assert_same(11, $data['user']['org_id'] ?? null);
    kbs_assert_same('company_admin', $data['user']['role'] ?? null);
    kbs_assert_true(($data['token'] ?? '') !== '', 'An auth token should be issued on successful login.');
    kbs_assert_same(false, get_transient($transientKey), 'The OTP transient should be consumed after successful verification.');
    kbs_assert_true((string) get_user_meta(102, 'auth_token', true) !== '', 'The issued auth token should be stored for the user.');
});

kbs_test('send otp uses the dedicated otp email template with a prominent passcode block', function (): void {
    kbs_test_add_user([
        'ID' => 103,
        'user_email' => 'otp-template@example.com',
        'display_name' => 'OTP Template User',
        'roles' => ['c_employee'],
    ]);
    kbs_test_set_user_meta(103, 'kbs_account_status', 'approved');

    $request = kbs_test_make_request('POST', '/kbs/v1/send-otp', [
        'email' => 'otp-template@example.com',
        'context' => 'login',
    ]);

    $result = OtpAuth::send_otp($request);
    $response = kbs_assert_response($result, 200);
    $data = $response->get_data();

    kbs_assert_same('sent', $data['status'] ?? null);

    $mail = kbs_test_last_mail();
    kbs_assert_same('Your Vyavhar OTP Code', $mail['subject'] ?? null);
    kbs_assert_true(str_contains((string) ($mail['message'] ?? ''), 'Your one-time passcode'), 'OTP mail should use the dedicated OTP heading.');
    kbs_assert_true(str_contains((string) ($mail['message'] ?? ''), 'Copy this code'), 'OTP mail should include the copy-friendly code block label.');
    kbs_assert_true((bool) preg_match('/\b\d{6}\b/', (string) ($mail['message'] ?? '')), 'OTP mail should render a visible six-digit code.');
});
