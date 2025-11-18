<?php

namespace KBS\Helpers {

use WP_Error;

defined('ABSPATH') || exit;

class OrgHelper
{
    /**
     * Resolve the current organization ID from headers or user meta.
     */
    public static function current_org_id(): int|WP_Error
    {
        $headerKeys = ['HTTP_X_VY_ORG_ID', 'HTTP_X_KBS_ORG_ID'];
        foreach ($headerKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $org = absint($_SERVER[$key]);
                if ($org > 0) {
                    return $org;
                }
            }
        }

        $user_id = get_current_user_id();
        if ($user_id) {
            $candidates = [
                get_user_meta($user_id, 'vy_active_org_id', true),
                get_user_meta($user_id, 'org_id', true),
            ];
            foreach ($candidates as $meta) {
                if (!$meta) {
                    continue;
                }
                $org = (int) $meta;
                if ($org > 0) {
                    return $org;
                }
            }
        }

        /**
         * Allow other code paths (CLI, cron) to inject an org.
         */
        $from_filter = apply_filters('vy_current_org_id', null);
        if ($from_filter !== null) {
            $org = (int) $from_filter;
            if ($org > 0) {
                return $org;
            }
        }

        return new WP_Error('vy_no_org', 'Organization could not be determined for this request.', ['status' => 400]);
    }
}

}

namespace {
    if (!function_exists('vy_get_current_org_id')) {
        function vy_get_current_org_id()
        {
            return \KBS\Helpers\OrgHelper::current_org_id();
        }
    }
}
