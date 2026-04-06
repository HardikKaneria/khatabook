<?php
/**
 * Plugin Name: Vyavhar SaaS
 * Description: Headless accounting plugin with mPDF, OTP, CPTs
 * Version: 1.0
 */

defined('ABSPATH') || exit;

define('KHATABOOK_PLUGIN_FILE', __FILE__);

// Load Composer Autoloader + non-autoloaded helpers
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/Helpers/OrgHelper.php';
require_once __DIR__ . '/backend/Helpers/OrgMembershipHelper.php';
require_once __DIR__ . '/backend/Helpers/ReportHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceTemplateHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceRenderHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceTemplateRenderHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceFinancialHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceEditHelper.php';
require_once __DIR__ . '/backend/Helpers/ExpenseEditHelper.php';
require_once __DIR__ . '/backend/Auth/AuthSessionHelper.php';
require_once __DIR__ . '/backend/Helpers/InvoiceEmailHelper.php';
require_once __DIR__ . '/backend/Invoices/VyInvoicePdf.php';

// Initialize the plugin
$plugin = new \KBS\Core\Plugin(KHATABOOK_PLUGIN_FILE);
$plugin->run();

\KBS\Email\EmailManager::init();

/**
 * Build a simple HTML email shell with brand styling.
 */
if (!function_exists('kbs_render_email_body')) {
    function kbs_render_email_body(string $message, array $args = []): string {
        return \KBS\Email\EmailManager::render($message, $args);
    }
}

/**
 * Send a transactional email with minimal styling.
 */
if (!function_exists('kbs_send_email')) {
    function kbs_send_email(string $to, string $subject, string $message, array $args = []): bool {
        return \KBS\Email\EmailManager::send($to, $subject, $message, $args);
    }
}

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

if (!function_exists('kbs_render_frontend_boot_error')) {
    function kbs_render_frontend_boot_error(string $admin_message, string $log_message = ''): void {
        if ($log_message !== '') {
            error_log('[Vyavhar Frontend] ' . $log_message);
        }

        status_header(503);
        nocache_headers();

        $message = current_user_can('manage_options')
            ? $admin_message
            : 'Application assets are unavailable right now. Please contact the site administrator.';

        echo '<!DOCTYPE html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vyavhar App Unavailable</title></head><body style="margin:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;background:#f8fafc;color:#0f172a;"><main style="max-width:760px;margin:72px auto;padding:32px;background:#ffffff;border:1px solid rgba(15,23,42,0.08);border-radius:20px;box-shadow:0 20px 45px rgba(15,23,42,0.08);"><h1 style="margin:0 0 16px;font-size:28px;">Vyavhar App Unavailable</h1><p style="margin:0;font-size:16px;line-height:1.6;">' . esc_html($message) . '</p></main></body></html>';
        exit;
    }
}

if (!function_exists('kbs_load_vite_manifest')) {
    function kbs_load_vite_manifest(string $manifest_path): array {
        if (!is_readable($manifest_path)) {
            kbs_render_frontend_boot_error(
                'Frontend build files are missing. Run `npm install` and `npm run build` inside `plugins/khatabook/app`, then deploy the generated `app/dist` directory.',
                'Manifest not found at ' . $manifest_path
            );
        }

        $contents = file_get_contents($manifest_path);
        if ($contents === false) {
            kbs_render_frontend_boot_error(
                'Frontend build files could not be read. Confirm `plugins/khatabook/app/dist/manifest.json` is present and readable.',
                'Failed to read manifest at ' . $manifest_path
            );
        }

        $manifest = json_decode($contents, true);
        if (!is_array($manifest)) {
            kbs_render_frontend_boot_error(
                'Frontend build manifest is invalid. Rebuild the app from `plugins/khatabook/app` before deploying.',
                'Invalid manifest JSON at ' . $manifest_path
            );
        }

        return $manifest;
    }
}

if (!function_exists('kbs_find_vite_manifest_path')) {
    function kbs_find_vite_manifest_path(string $dist_dir): string {
        foreach ([$dist_dir . 'manifest.json', $dist_dir . '.vite/manifest.json'] as $manifest_path) {
            if (is_readable($manifest_path)) {
                return $manifest_path;
            }
        }

        return $dist_dir . 'manifest.json';
    }
}

if (!function_exists('kbs_find_vite_manifest_entry')) {
    function kbs_find_vite_manifest_entry(array $manifest): ?array {
        foreach (['src/main.jsx', 'main.jsx', './src/main.jsx'] as $entry_key) {
            if (isset($manifest[$entry_key]) && is_array($manifest[$entry_key])) {
                return $manifest[$entry_key];
            }
        }

        foreach ($manifest as $entry) {
            if (is_array($entry) && !empty($entry['isEntry'])) {
                return $entry;
            }
        }

        return null;
    }
}

if (!function_exists('kbs_vite_asset_url')) {
    function kbs_vite_asset_url(string $relative_path): string {
        return plugin_dir_url(__FILE__) . 'app/dist/' . ltrim($relative_path, '/');
    }
}

add_action('template_redirect', function () {
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return;

    // 1) Don't hijack REST requests
    if (function_exists('rest_get_url_prefix')) {
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        if (strpos($_SERVER['REQUEST_URI'] ?? '', $rest_prefix) !== false) {
            return; // let REST return JSON
        }
    }

    status_header(200);
    nocache_headers();

    // 2) Helpful boot data for the app
    $current_user = wp_get_current_user();
    $has_user = $current_user && $current_user->ID;
    $rest_root = get_rest_url(null, '/');             // e.g. https://site/wp-json/
    $rest_nonce = wp_create_nonce('wp_rest');         // WP REST nonce

    // Optional: expose a tiny "you are logged in" object
    $boot_user = $has_user ? [
        'id'    => $current_user->ID,
        'name'  => $current_user->display_name,
        'email' => $current_user->user_email,
    ] : null;

    $plugin_url = plugin_dir_url(__FILE__);
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php bloginfo('name'); ?> | Vyavhar App</title>
        <link rel="icon" type="image/png" href="<?php echo esc_url($plugin_url . 'assets/image/fav_icon.png'); ?>" />
        <?php wp_head(); ?>
    </head>
    <body>
      <div id="root"></div>

      <script>
        // Boot globals your frontend can read
        window.kbsApi = {
          root: <?php echo json_encode($rest_root); ?>,
          nonce: <?php echo json_encode($rest_nonce); ?>,
        };
        <?php if ($boot_user): ?>
        window.kbsBootUser = <?php echo json_encode($boot_user); ?>;
        <?php else: ?>
        window.kbsBootUser = null;
        <?php endif; ?>
      </script>

      <?php
      // 3) Dev vs Prod assets
      $is_dev = defined('WP_ENV') && WP_ENV === 'development';

      if ($is_dev) {
          // --- DEV: load Vite client, preamble, then your entry ---
          ?>
          <!-- Vite client -->
          <script type="module" src="http://localhost:5173/@vite/client"></script>

          <!-- React Refresh preamble (required for @vitejs/plugin-react) -->
          <script type="module">
            import RefreshRuntime from "http://localhost:5173/@react-refresh";
            RefreshRuntime.injectIntoGlobalHook(window);
            window.$RefreshReg$ = () => {};
            window.$RefreshSig$ = () => (type) => type;
            window.__vite_plugin_react_preamble_installed__ = true;
          </script>

          <!-- Your app entry (note: /src/main.jsx is the default) -->
          <script type="module" src="http://localhost:5173/main.jsx"></script>
          <?php
      } else {
          // --- PROD: load built assets from manifest (handles hashed filenames & CSS) ---
          $dist_dir  = plugin_dir_path(__FILE__) . 'app/dist/';
          $manifest_path = kbs_find_vite_manifest_path($dist_dir);
          $manifest = kbs_load_vite_manifest($manifest_path);
          $entry = kbs_find_vite_manifest_entry($manifest);
          if (!$entry) {
              kbs_render_frontend_boot_error(
                  'No frontend entrypoint was found in `plugins/khatabook/app/dist/manifest.json`. Rebuild the app before deploying.',
                  'No Vite entrypoint found in manifest at ' . $manifest_path
              );
          }

          // CSS files referenced by the entry
          if (!empty($entry['css']) && is_array($entry['css'])) {
              foreach ($entry['css'] as $css) {
                  echo '<link rel="stylesheet" href="' . esc_url(kbs_vite_asset_url((string) $css)) . '" />' . "\n";
              }
          }

          // JS file
          $js = $entry['file'] ?? '';
          if (!$js) {
              kbs_render_frontend_boot_error(
                  'The frontend manifest entry is missing its JavaScript file. Rebuild the app before deploying.',
                  'Manifest entry missing JS file at ' . $manifest_path
              );
          }
          echo '<script type="module" src="' . esc_url(kbs_vite_asset_url((string) $js)) . '"></script>';
      }

      wp_footer();
      ?>
    </body>
    </html>
    <?php
    exit;
});
