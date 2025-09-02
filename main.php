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
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        return isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false;
    }
}

add_action('template_redirect', function () {
    // Don't interfere with admin, ajax, or REST
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || is_rest_request()) {
        return;
    }

    status_header(200);
    nocache_headers();

    $current_user = wp_get_current_user();
    $has_user     = (bool) ($current_user && $current_user->ID);

    // REST boot info
    $rest_root  = esc_url_raw( rest_url() );
    // This nonce is most useful when a user is logged in (cookie auth).
    // It will still be created when logged out, but won’t pass validation.
    $rest_nonce = wp_create_nonce('wp_rest');

    // Payloads for the page script
    $user_payload = $has_user ? [
        'id'    => (int) $current_user->ID,
        'name'  => $current_user->display_name,
        'email' => $current_user->user_email,
        'role'  => $current_user->roles[0] ?? null,
    ] : null;
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

        <script>
          // Expose REST info for the SPA
          window.kbsApi = <?php echo wp_json_encode([
              'root'  => $rest_root,
              'nonce' => $rest_nonce,
          ], JSON_UNESCAPED_SLASHES); ?>;

          // Persist so it survives redirects/reloads
          try {
            sessionStorage.setItem('kbs_rest_root', window.kbsApi.root);
            sessionStorage.setItem('kbs_rest_nonce', window.kbsApi.nonce);
          } catch (e) {}

          // Optionally expose current user when already logged in
          window.currentUser = <?php echo wp_json_encode($user_payload); ?>;

          // Keep your existing localStorage bootstrap (only if not hitting explicit ?action=...)
          <?php if ($has_user && !isset($_GET['action'])) : ?>
            try {
              localStorage.setItem('auth_token', 'loggedin');
              localStorage.setItem('user_name', window.currentUser?.name || 'User');
              if (window.currentUser?.role) {
                localStorage.setItem('role', window.currentUser.role);
              }
            } catch (e) {}
          <?php endif; ?>
        </script>

        <?php
        // Vite dev mode (localhost)
        if (defined('WP_ENV') && WP_ENV === 'development') {
            echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
            echo '<script type="module" src="http://localhost:5173/main.jsx"></script>';
        } else {
            // Production build script
            $dist_url  = plugin_dir_url(__FILE__) . 'app/dist/assets/';
            $dist_path = plugin_dir_path(__FILE__) . 'app/dist/assets/';

            // main.*.js
            $files = glob($dist_path . 'main-*.js');
            if (!empty($files)) {
                $filename = basename($files[0]);
                echo '<script type="module" src="' . esc_url($dist_url . $filename) . '"></script>';
            } else {
                wp_die("No main JS bundle found in production build.");
            }
        }

        wp_footer();
        ?>
    </body>
    </html>
    <?php
    exit;
});
