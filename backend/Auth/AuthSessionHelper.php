<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_auth_token_is_active')) {
    function vy_auth_token_is_active(?string $providedToken, ?string $storedToken, int $expiresAt, ?int $now = null): bool
    {
        $providedToken = trim((string) $providedToken);
        $storedToken = trim((string) $storedToken);
        $now = $now ?? time();

        if ($providedToken === '' || $storedToken === '') {
            return false;
        }

        if (!hash_equals($storedToken, $providedToken)) {
            return false;
        }

        return $expiresAt > 0 && $now < $expiresAt;
    }
}

if (!function_exists('vy_login_access_state')) {
    function vy_login_access_state(bool $userExists, bool $isApproved): ?string
    {
        if (!$userExists) {
            return 'not_registered';
        }

        if (!$isApproved) {
            return 'not_approved';
        }

        return null;
    }
}
