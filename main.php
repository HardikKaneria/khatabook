<?php
/**
 * Plugin Name: Khatabook SaaS
 * Description: Headless accounting plugin with mPDF, OTP, CPTs
 * Version: 1.0
 */

defined('ABSPATH') || exit;

define('KHATABOOK_PLUGIN_FILE', __FILE__);

// Load Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Initialize the plugin
$plugin = new \KBS\Core\Plugin(KHATABOOK_PLUGIN_FILE);
$plugin->run();

if (!function_exists('is_rest_request')) {
    function is_rest_request(): bool {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // REST URL pattern check (alternative fallback)
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        return strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false;
    }
}

// Enqueue scripts and styles for the headless React app
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || is_rest_request()) return;

    if (defined('WP_ENV') && WP_ENV === 'development') {
        wp_enqueue_script('vite-client', 'http://localhost:5173/@vite/client', [], null, true);
        echo '<script type="module" src="http://localhost:5173/main.jsx"></script>'; // ✅ Vite's default entry
    } else {
        $dist = plugin_dir_url(__FILE__) . 'app/dist/assets/';
        wp_enqueue_style('kbs-style', $dist . 'index.css', [], null);
        wp_enqueue_script('kbs-app', $dist . 'index.js', [], null, true);
    }
});


// Handle all requests to serve the React app
add_action('template_redirect', function () {
    if (is_admin() || is_rest_request()) return;

    status_header(200);
    nocache_headers();
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php bloginfo('name'); ?> | Khatabook App</title>
        <?php wp_head(); ?>
    </head>
    <body>
        <div id="root"></div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
});
