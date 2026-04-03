<?php

namespace KBS\Email;

defined('ABSPATH') || exit;

class EmailManager
{
    private const CONFIG_ENV_KEYS = [
        'host'       => ['KBS_SMTP_HOST', 'BREVO_SMTP_HOST'],
        'port'       => ['KBS_SMTP_PORT', 'BREVO_SMTP_PORT'],
        'user'       => ['KBS_SMTP_USER', 'BREVO_SMTP_USER'],
        'pass'       => ['KBS_SMTP_PASS', 'BREVO_SMTP_PASS'],
        'from_email' => ['KBS_SMTP_FROM_EMAIL', 'BREVO_FROM_EMAIL'],
        'from_name'  => ['KBS_SMTP_FROM_NAME', 'BREVO_FROM_NAME'],
        'secure'     => ['KBS_SMTP_SECURE', 'BREVO_SMTP_SECURE'],
    ];

    private const CONFIG_CONST_KEYS = [
        'host'       => ['KBS_SMTP_HOST', 'BREVO_SMTP_HOST'],
        'port'       => ['KBS_SMTP_PORT', 'BREVO_SMTP_PORT'],
        'user'       => ['KBS_SMTP_USER', 'BREVO_SMTP_USER'],
        'pass'       => ['KBS_SMTP_PASS', 'BREVO_SMTP_PASS'],
        'from_email' => ['KBS_SMTP_FROM_EMAIL', 'BREVO_FROM_EMAIL'],
        'from_name'  => ['KBS_SMTP_FROM_NAME', 'BREVO_FROM_NAME'],
        'secure'     => ['KBS_SMTP_SECURE', 'BREVO_SMTP_SECURE'],
    ];

    private const REQUIRED_FIELDS = ['host', 'port', 'user', 'pass'];

    public static function init(): void
    {
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer']);
        add_action('wp_mail_failed', [__CLASS__, 'handle_mail_failure']);
    }

    public static function configure_phpmailer($phpmailer): void
    {
        $config = self::get_mailer_config();
        if (!$config) {
            error_log('[Vyavhar Email] SMTP configuration not found. Save SMTP settings in Vyavhar Admin > SMTP Settings, or define KBS_SMTP_HOST, KBS_SMTP_PORT, KBS_SMTP_USER, and KBS_SMTP_PASS via environment variables or constants. Falling back to default wp_mail transport.');
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host     = $config['host'];
        $phpmailer->Port     = (int) $config['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $config['user'];
        $phpmailer->Password = $config['pass'];

        $secure = self::normalize_secure_mode($config['secure'] ?? null);
        if ($secure === 'ssl') {
            $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'none') {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        } else {
            $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if (!empty($config['from_email'])) {
            $phpmailer->From = $config['from_email'];
        }
        if (!empty($config['from_name'])) {
            $phpmailer->FromName = $config['from_name'];
        }
    }

    public static function render(string $message, array $args = []): string
    {
        $greeting = $args['greeting'] ?? '';
        $ctaLabel = $args['cta_label'] ?? '';
        $ctaUrl   = $args['cta_url'] ?? '';
        $footer   = $args['footer'] ?? 'Thanks,<br>Team Vyavhar';
        $brand    = $args['brand'] ?? get_bloginfo('name', 'display');
        $site_url = home_url('/');
        $logo_url = plugin_dir_url(KHATABOOK_PLUGIN_FILE) . 'assets/image/logo.svg';

        $body  = '<div style="background:#f8fafc;padding:32px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;">';
        $body .= '<div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;padding:40px 48px;box-shadow:0 25px 65px rgba(15,23,42,0.15);border:1px solid rgba(15,23,42,0.08);">';
        $body .= '<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">';
        $body .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($brand) . '" style="height:34px;width:auto" />';
        $body .= '<span style="font-size:22px;font-weight:700;color:#111827;">' . esc_html($brand) . '</span>';
        $body .= '</div>';

        if ($greeting) {
            $body .= '<p style="margin:0 0 12px;color:#0f172a;font-size:16px;">' . esc_html($greeting) . '</p>';
        }

        $body .= '<div style="color:#1f2937;font-size:15px;line-height:1.6;">' . wp_kses_post(nl2br(esc_html($message))) . '</div>';

        if ($ctaLabel && $ctaUrl) {
            $body .= sprintf(
                '<p style="margin:30px 0 10px;"><a href="%s" style="display:inline-block;background:#4C2CE9;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:600;box-shadow:0 12px 24px rgba(108,92,231,0.25);">%s</a></p>',
                esc_url($ctaUrl),
                esc_html($ctaLabel)
            );
        }

        $body .= '<hr style="margin:32px 0;border:none;border-top:1px solid rgba(15,23,42,0.08);" />';
        $body .= '<p style="color:#6b7280;font-size:13px;margin:0 0 6px;">' . wp_kses_post($footer) . '</p>';
        $body .= '<a href="' . esc_url($site_url) . '" style="color:#4C2CE9;font-size:13px;text-decoration:none;">' . esc_html(parse_url($site_url, PHP_URL_HOST) ?? $site_url) . '</a>';
        $body .= '</div></div>';

        return $body;
    }

    public static function send(string $to, string $subject, string $message, array $args = []): bool
    {
        $html = self::render($message, $args);
        $headers = $args['headers'] ?? [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $sent = wp_mail($to, $subject, $html, $headers);
        if (!$sent) {
            error_log(sprintf('[Vyavhar Email] wp_mail returned false. To: %s Subject: %s', $to, $subject));
        }
        return $sent;
    }

    public static function handle_mail_failure(\WP_Error $wp_error): void
    {
        error_log('[Vyavhar Email] wp_mail_failed: ' . $wp_error->get_error_message());
        $data = $wp_error->get_error_data();
        if ($data) {
            error_log('[Vyavhar Email] Failure data: ' . wp_json_encode($data));
        }
    }

    public static function get_config_diagnostics(): array
    {
        $resolved = self::resolve_mailer_config();
        $config = is_array($resolved['config']) ? $resolved['config'] : null;

        if (is_array($config)) {
            unset($config['pass']);
        }

        return [
            'configured'   => is_array($resolved['config']),
            'source'       => $resolved['source'],
            'source_label' => self::format_source_label($resolved['source']),
            'config'       => $config,
        ];
    }

    private static function get_mailer_config(): ?array
    {
        $resolved = self::resolve_mailer_config();
        return is_array($resolved['config']) ? $resolved['config'] : null;
    }

    private static function resolve_mailer_config(): array
    {
        $sources = [
            ['source' => 'filter', 'config' => self::normalize_config(apply_filters('kbs_smtp_config', null))],
            ['source' => 'legacy_filter', 'config' => self::normalize_config(apply_filters('kbs_brevo_smtp_creds', null))],
            ['source' => 'environment', 'config' => self::load_from_environment()],
            ['source' => 'constants', 'config' => self::load_from_constants()],
            ['source' => 'options', 'config' => self::load_from_options()],
        ];

        foreach ($sources as $source) {
            if ($source['config'] !== null) {
                return $source;
            }
        }

        return ['source' => 'none', 'config' => null];
    }

    private static function load_from_environment(): ?array
    {
        $config = [];
        foreach (self::CONFIG_ENV_KEYS as $field => $keys) {
            foreach ($keys as $key) {
                $value = getenv($key);
                if ($value !== false && $value !== '') {
                    $config[$field] = $value;
                    break;
                }
            }
        }

        return self::normalize_config($config);
    }

    private static function load_from_constants(): ?array
    {
        $config = [];
        foreach (self::CONFIG_CONST_KEYS as $field => $keys) {
            foreach ($keys as $key) {
                if (defined($key) && constant($key) !== '') {
                    $config[$field] = constant($key);
                    break;
                }
            }
        }

        return self::normalize_config($config);
    }

    private static function load_from_options(): ?array
    {
        $config = [
            'host'       => get_option('kbs_smtp_host') ?: get_option('kbs_brevo_smtp_host'),
            'port'       => get_option('kbs_smtp_port') ?: get_option('kbs_brevo_smtp_port'),
            'user'       => get_option('kbs_smtp_user') ?: get_option('kbs_brevo_smtp_user'),
            'pass'       => get_option('kbs_smtp_pass') ?: get_option('kbs_brevo_smtp_pass'),
            'from_email' => get_option('kbs_smtp_from_email') ?: get_option('kbs_brevo_from_email'),
            'from_name'  => get_option('kbs_smtp_from_name') ?: get_option('kbs_brevo_from_name'),
            'secure'     => get_option('kbs_smtp_secure') ?: get_option('kbs_brevo_smtp_secure'),
        ];

        return self::normalize_config($config);
    }

    private static function normalize_config($config): ?array
    {
        if (!is_array($config)) {
            return null;
        }

        $normalized = [
            'host'       => isset($config['host']) ? sanitize_text_field((string) $config['host']) : '',
            'port'       => isset($config['port']) ? (int) $config['port'] : 0,
            'user'       => isset($config['user']) ? sanitize_text_field((string) $config['user']) : '',
            'pass'       => isset($config['pass']) ? (string) $config['pass'] : '',
            'from_email' => isset($config['from_email']) ? sanitize_email((string) $config['from_email']) : '',
            'from_name'  => isset($config['from_name']) ? sanitize_text_field((string) $config['from_name']) : '',
            'secure'     => isset($config['secure']) ? sanitize_text_field((string) $config['secure']) : '',
        ];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($normalized[$field])) {
                return null;
            }
        }

        if ($normalized['port'] <= 0) {
            return null;
        }

        return $normalized;
    }

    private static function normalize_secure_mode(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['ssl', 'smtps'], true)) {
            return 'ssl';
        }
        if (in_array($normalized, ['none', 'off', 'false', '0'], true)) {
            return 'none';
        }
        return 'tls';
    }

    private static function format_source_label(string $source): string
    {
        switch ($source) {
            case 'filter':
                return 'Custom filter';
            case 'legacy_filter':
                return 'Legacy Brevo filter';
            case 'environment':
                return 'Environment variables';
            case 'constants':
                return 'WordPress constants';
            case 'options':
                return 'WordPress admin settings';
            default:
                return 'Not configured';
        }
    }
}
