<?php

namespace KBS\Core;

use KBS\Roles\CustomRoles;
use KBS\Core\AdminAccess;
use KBS\Db\TableManager;
use KBS\Endpoint\EndpointManager;
use KBS\Admin\AdminMenu;
use KBS\Admin\PendingUserController;

defined('ABSPATH') || exit;

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
        register_activation_hook($this->plugin_file, [TableManager::class, 'create_all_tables']);
        add_action('init', [AdminMenu::class, 'init']);
        add_action('admin_init', [AdminAccess::class, 'restrict_dashboard']);
        add_action('rest_api_init', [EndpointManager::class, 'register_endpoints']);
        add_action('admin_enqueue_scripts', function($hook) {
            if (strpos($hook, 'kbs-admin') !== false) {
                wp_enqueue_style('kbs-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
            }
        });
        add_action('admin_init', [PendingUserController::class, 'handle_actions']);
        add_action('after_setup_theme', [AdminAccess::class, 'hide_admin_bar']);
        add_filter('login_redirect', [AdminAccess::class, 'redirect_after_login'], 10, 3);

        TableManager::maybe_upgrade();
        
    }
}
