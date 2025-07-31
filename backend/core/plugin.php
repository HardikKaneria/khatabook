<?php

namespace KBS\Core;

use KBS\Auth\OtpAuth;
use KBS\Roles\CustomRoles;
use KBS\PostTypes\CustomPostTypes;
use KBS\Core\AdminAccess;
use KBS\Auth\RegisterController;

class Plugin
{
    private string $plugin_file;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public function run(): void
    {
        register_activation_hook($this->plugin_file, [CustomRoles::class, 'add_roles']);
        register_deactivation_hook($this->plugin_file, [CustomRoles::class, 'remove_roles']);
        add_action('admin_init', [AdminAccess::class, 'restrict_dashboard']);
        add_action('init', [CustomPostTypes::class, 'register_all']);
        add_action('init', [RegisterController::class, 'init']);
        add_action('rest_api_init', [OtpAuth::class, 'register_endpoints']);
    }
}
