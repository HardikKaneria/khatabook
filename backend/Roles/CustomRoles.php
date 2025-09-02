<?php

namespace KBS\Roles;

class CustomRoles
{
    /**
     * Add custom user roles
     */
    public static function add_roles(): void
    {
        $customer = get_role('customer');
        $caps = $customer ? $customer->capabilities : ['read' => true];

        add_role('company_admin', 'Company Admin', $caps);
        add_role('c_manager', 'Company Manager', $caps);
        add_role('c_employee', 'Company Employee', $caps);
        
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
