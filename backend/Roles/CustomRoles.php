<?php

namespace KBS\Roles;

class CustomRoles
{
    /**
     * Add custom user roles
     */
    public static function add_roles(): void
    {
        // Master Admin - full access to WP Dashboard
        add_role('master_admin', 'Master Admin', [
            'read' => true,
            'edit_posts' => true,
            'edit_pages' => true,
            'edit_users' => true,
            'manage_options' => true,
            'delete_posts' => true,
            'delete_pages' => true,
            'publish_posts' => true,
            'upload_files' => true,
        ]);

        // Company Admin - no dashboard access
        add_role('company_admin', 'Company Admin', [
            'read' => true,
            'upload_files' => true,
        ]);

        // Client Manager - limited access
        add_role('c_manager', 'Client Manager', [
            'read' => true,
            'upload_files' => true,
        ]);

        // Client Employee - minimal access
        add_role('c_employee', 'Client Employee', [
            'read' => true,
        ]);
    }

    /**
     * Remove custom user roles
     */
    public static function remove_roles(): void
    {
        remove_role('master_admin');
        remove_role('company_admin');
        remove_role('c_manager');
        remove_role('c_employee');
    }
}
