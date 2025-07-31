<?php
/**
 * Plugin Name: Khatabook SaaS
 * Description: Headless accounting plugin with mPDF, OTP, CPTs
 * Version: 1.0
 */

defined('ABSPATH') || exit;

define('KHATABOOK_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

$plugin = new \KBS\Core\Plugin(KHATABOOK_PLUGIN_FILE);
$plugin->run();

add_action('wp_enqueue_scripts', function () {
    if (defined('WP_ENV') && WP_ENV === 'development') {
        // Development mode (use Vite dev server)
        wp_enqueue_script(
            'vite-client',
            'http://localhost:5173/@vite/client',
            [],
            null,
            true
        );
        wp_enqueue_script(
            'vite-react-app',
            'http://localhost:5173/src/main.jsx',
            [],
            null,
            true
        );
    } else {
        // Production mode
        $dist = plugin_dir_url(__FILE__) . 'app/dist/assets/';
        wp_enqueue_style('kbs-style', $dist . 'index.css', [], null);
        wp_enqueue_script('kbs-app', $dist . 'index.js', [], null, true);
    }
});
