<?php

kbs_test('auth token remains valid only for the current token before expiry', function (): void {
    kbs_assert_true(
        vy_auth_token_is_active('current-token', 'current-token', time() + 300, time()),
        'The current active token should validate before expiry.'
    );
});

kbs_test('single-session auth invalidates the previous token after a new login', function (): void {
    kbs_assert_false(
        vy_auth_token_is_active('old-token', 'new-token', time() + 300, time()),
        'The previous token should be invalid after a replacement login issues a new token.'
    );
});

kbs_test('login access state rejects missing and unapproved users', function (): void {
    kbs_assert_same('not_registered', vy_login_access_state(false, false));
    kbs_assert_same('not_approved', vy_login_access_state(true, false));
    kbs_assert_same(null, vy_login_access_state(true, true));
});
