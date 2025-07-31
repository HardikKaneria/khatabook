<?php

namespace KBS\Core;

class AdminAccess
{
    public static function restrict_dashboard(): void
    {
        // If user is logged in and not master_admin, block access to dashboard
        if (
            is_admin() &&
            !defined('DOING_AJAX') &&
            current_user_can('read') &&
            !current_user_can('manage_options') // Only master_admin can manage options
        ) {
            $screen = get_current_screen();

            // Only block if trying to access the dashboard
            if ($screen && $screen->base !== 'profile') {
                wp_redirect(home_url('/client-dashboard')); // Replace with your frontend route
                exit;
            }
        }
    }
}

