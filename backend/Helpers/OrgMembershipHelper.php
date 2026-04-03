<?php

defined('ABSPATH') || exit;

if (!function_exists('vy_org_membership_allows')) {
    function vy_org_membership_allows(array $memberships, int $orgId): bool
    {
        if ($orgId <= 0) {
            return false;
        }

        foreach ($memberships as $membership) {
            if ((int) (($membership['org_id'] ?? 0)) === $orgId) {
                return true;
            }
        }

        return false;
    }
}
