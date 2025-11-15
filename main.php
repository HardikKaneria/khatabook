<?php
/**
 * Plugin Name: Vyavhar SaaS
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

\KBS\Email\EmailManager::init();

/**
 * Build a simple HTML email shell with brand styling.
 */
function kbs_render_email_body(string $message, array $args = []): string {
    $greeting = $args['greeting'] ?? '';
    $ctaLabel = $args['cta_label'] ?? '';
    $ctaUrl   = $args['cta_url'] ?? '';
    $footer   = $args['footer'] ?? 'Thanks,<br>Team Vyavhar';
    $brand    = $args['brand'] ?? get_bloginfo('name', 'display');

    $body  = '<div style="background:#f3f4f6;padding:24px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;">';
    $body .= '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">';
    $body .= '<div style="font-size:22px;font-weight:700;color:#111827;margin-bottom:16px;">' . esc_html($brand) . '</div>';
    if ($greeting) {
        $body .= '<p style="margin-top:0;color:#111827;">' . esc_html($greeting) . '</p>';
    }
    $body .= wpautop(esc_html($message));
    if ($ctaLabel && $ctaUrl) {
        $body .= sprintf(
            '<p style="margin:24px 0;"><a href="%s" style="display:inline-block;background:#6c5ce7;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:999px;font-weight:600;">%s</a></p>',
            esc_url($ctaUrl),
            esc_html($ctaLabel)
        );
    }
    $body .= '<p style="color:#6b7280;font-size:13px;margin-top:32px;">' . wp_kses_post($footer) . '</p>';
    $body .= '</div></div>';
    return $body;
}

/**
 * Send a transactional email with minimal styling.
 */
function kbs_send_email(string $to, string $subject, string $message, array $args = []): bool {
    $html = kbs_render_email_body($message, $args);
    $headers = $args['headers'] ?? [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    return wp_mail($to, $subject, $html, $headers);
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
          $assets_dir = $dist_dir . 'assets/';
          $assets_url = plugin_dir_url(__FILE__) . 'app/dist/assets/';

          $manifest_path = $dist_dir . 'manifest.json';
          if (!file_exists($manifest_path)) {
              wp_die("Vite build not found. Did you run 'vite build'?");
          }
          $manifest = json_decode(file_get_contents($manifest_path), true);
          if (!is_array($manifest)) {
              wp_die("Invalid manifest.json");
          }

          // Find your JS entry ("src/main.jsx" by default)
          $entryKey = 'src/main.jsx';
          if (!isset($manifest[$entryKey])) {
              // fallback: first entry with isEntry=true
              foreach ($manifest as $k => $item) {
                  if (!empty($item['isEntry'])) { $entryKey = $k; break; }
              }
          }
          $entry = $manifest[$entryKey] ?? null;
          if (!$entry) wp_die("No entry in manifest.");

          // CSS files referenced by the entry
          if (!empty($entry['css']) && is_array($entry['css'])) {
              foreach ($entry['css'] as $css) {
                  echo '<link rel="stylesheet" href="' . esc_url($assets_url . $css) . '" />' . "\n";
              }
          }

          // JS file
          $js = $entry['file'] ?? '';
          if (!$js) wp_die("No JS file in manifest entry.");
          echo '<script type="module" src="' . esc_url($assets_url . $js) . '"></script>';
      }

      wp_footer();
      ?>
    </body>
    </html>
    <?php
    exit;
});
