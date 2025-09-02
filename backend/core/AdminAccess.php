<?php

namespace KBS\Core;

class AdminAccess
{
    public static function restrict_dashboard(): void
    {
        if (
            is_admin() &&
            !defined('DOING_AJAX') &&
            !current_user_can('edit_posts') &&
            !(defined('DOING_CRON') && DOING_CRON)
        ) {
            wp_redirect(home_url('/my-account')); // or your custom dashboard
            exit;
        }
    }

    public static function hide_admin_bar(): void
    {
        if (!current_user_can('edit_posts')) {
            show_admin_bar(false);
        }
    }

    public static function redirect_after_login($redirect_to, $request, $user)
    {
        if (!is_wp_error($user) && !user_can($user, 'edit_posts')) {
            return home_url(); // or your React/Frontend dashboard
        }

        return $redirect_to;
    }
}
