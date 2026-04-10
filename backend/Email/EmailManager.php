<?php

namespace KBS\Email;

use KBS\Core\SystemLogger;

defined('ABSPATH') || exit;

class EmailManager
{
    private const DEFAULT_BRAND_NAME = 'Vyavhar';
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
            SystemLogger::log_event(
                'email_smtp_missing_config',
                'SMTP configuration not found. Falling back to default wp_mail transport.',
                ['source' => 'wp_mail'],
                0,
                'backend/Email/EmailManager.php'
            );
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
        $variant  = sanitize_key((string) ($args['variant'] ?? 'transactional'));
        $greeting = $args['greeting'] ?? '';
        $ctaLabel = $args['cta_label'] ?? '';
        $ctaUrl   = $args['cta_url'] ?? '';
        $footer   = $args['footer'] ?? 'Thanks,<br>Team Vyavhar';
        $brand    = self::brand_name(isset($args['brand']) ? (string) $args['brand'] : null);
        $eyebrow  = isset($args['eyebrow']) ? sanitize_text_field((string) $args['eyebrow']) : '';
        $logo_url = self::resolve_logo_url($args['logo_url'] ?? '');
        $summary_rows = self::normalize_summary_rows($args['summary_rows'] ?? []);
        $site_url = home_url('/');

        if ($variant === 'otp') {
            return self::render_otp_template($message, [
                'greeting'     => $greeting,
                'cta_label'    => $ctaLabel,
                'cta_url'      => $ctaUrl,
                'footer'       => $footer,
                'brand'        => $brand,
                'eyebrow'      => $eyebrow,
                'logo_url'     => $logo_url,
                'site_url'     => $site_url,
                'otp_code'     => isset($args['otp_code']) ? trim((string) $args['otp_code']) : '',
                'helper_lines' => is_array($args['helper_lines'] ?? null) ? $args['helper_lines'] : [],
            ]);
        }

        $body  = '<div style="background:#f8fafc;padding:32px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;">';
        $body .= '<div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;padding:40px 48px;box-shadow:0 25px 65px rgba(15,23,42,0.15);border:1px solid rgba(15,23,42,0.08);">';
        $body .= '<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">';
        $body .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($brand) . '" style="height:40px;width:40px;border-radius:12px;object-fit:cover;background:#eef2ff;padding:6px" />';
        $body .= '<span style="font-size:22px;font-weight:700;color:#111827;">' . esc_html($brand) . '</span>';
        $body .= '</div>';

        if ($eyebrow !== '') {
            $body .= '<p style="margin:0 0 8px;color:#4C2CE9;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;font-weight:700;">' . esc_html($eyebrow) . '</p>';
        }

        if ($greeting) {
            $body .= '<p style="margin:0 0 12px;color:#0f172a;font-size:16px;">' . esc_html($greeting) . '</p>';
        }

        $body .= '<div style="color:#1f2937;font-size:15px;line-height:1.6;">' . wp_kses_post(nl2br(esc_html($message))) . '</div>';

        if ($summary_rows) {
            $body .= self::render_summary_rows_block($summary_rows);
        }

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

    public static function send(string|array $to, string $subject, string $message, array $args = []): bool
    {
        $html = self::render($message, $args);
        $headers = $args['headers'] ?? [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $attachments = is_array($args['attachments'] ?? null) ? $args['attachments'] : [];
        $sent = wp_mail($to, $subject, $html, $headers, $attachments);
        if (!$sent) {
            SystemLogger::log_event(
                'email_wp_mail_returned_false',
                'wp_mail returned false while sending a transactional email.',
                [
                    'subject_length' => strlen((string) $subject),
                    'has_custom_headers' => !empty($headers),
                    'has_attachments' => !empty($attachments),
                ],
                0,
                'backend/Email/EmailManager.php'
            );
        }
        return $sent;
    }

    public static function brand_name(?string $override = null): string
    {
        $brand = trim((string) ($override ?? ''));
        if ($brand !== '') {
            return sanitize_text_field($brand);
        }

        return self::DEFAULT_BRAND_NAME;
    }

    public static function handle_mail_failure(\WP_Error $wp_error): void
    {
        SystemLogger::log_event(
            'email_wp_mail_failed',
            'wp_mail_failed fired for a transactional email.',
            [
                'error_code' => $wp_error->get_error_code(),
                'error_message' => $wp_error->get_error_message(),
                'has_data' => (bool) $wp_error->get_error_data(),
            ],
            0,
            'backend/Email/EmailManager.php'
        );
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

    private static function resolve_logo_url($value): string
    {
        $logo = is_string($value) ? esc_url_raw($value) : '';
        if ($logo !== '') {
            return $logo;
        }

        return plugin_dir_url(KHATABOOK_PLUGIN_FILE) . 'assets/image/fav_icon.png';
    }

    private static function normalize_summary_rows($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            $value = wp_strip_all_tags((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $normalized;
    }

    private static function render_summary_rows_block(array $summary_rows): string
    {
        if (!$summary_rows) {
            return '';
        }

        $block = '<div style="margin-top:24px;border:1px solid rgba(15,23,42,0.08);border-radius:16px;background:#f8fafc;padding:18px 20px;">';
        $last_index = count($summary_rows) - 1;
        foreach ($summary_rows as $index => $row) {
            $border = $index === $last_index ? '' : 'border-bottom:1px solid rgba(15,23,42,0.08);';
            $block .= '<div style="display:flex;justify-content:space-between;gap:18px;padding:8px 0;' . $border . '">';
            $block .= '<span style="color:#6b7280;font-size:13px;">' . esc_html($row['label']) . '</span>';
            $block .= '<strong style="color:#111827;font-size:14px;text-align:right;">' . esc_html($row['value']) . '</strong>';
            $block .= '</div>';
        }
        $block .= '</div>';

        return $block;
    }

    private static function render_otp_template(string $message, array $args): string
    {
        $greeting = (string) ($args['greeting'] ?? '');
        $cta_label = (string) ($args['cta_label'] ?? '');
        $cta_url = (string) ($args['cta_url'] ?? '');
        $footer = (string) ($args['footer'] ?? 'Thanks,<br>Team Vyavhar');
        $brand = self::brand_name((string) ($args['brand'] ?? ''));
        $eyebrow = sanitize_text_field((string) ($args['eyebrow'] ?? 'Secure verification'));
        $logo_url = self::resolve_logo_url($args['logo_url'] ?? '');
        $site_url = (string) ($args['site_url'] ?? home_url('/'));
        $otp_code = preg_replace('/[^0-9A-Za-z]/', '', (string) ($args['otp_code'] ?? ''));
        $helper_lines = array_values(array_filter(array_map(static function ($line): string {
            return wp_strip_all_tags((string) $line);
        }, (array) ($args['helper_lines'] ?? []))));

        $body  = '<div style="background:#eef2ff;padding:32px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;">';
        $body .= '<div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:20px;padding:40px 48px;box-shadow:0 25px 65px rgba(15,23,42,0.14);border:1px solid rgba(15,23,42,0.08);">';
        $body .= '<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">';
        $body .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($brand) . '" style="height:40px;width:40px;border-radius:12px;object-fit:cover;background:#eef2ff;padding:6px" />';
        $body .= '<span style="font-size:22px;font-weight:700;color:#111827;">' . esc_html($brand) . '</span>';
        $body .= '</div>';
        $body .= '<p style="margin:0 0 8px;color:#4C2CE9;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;font-weight:700;">' . esc_html($eyebrow) . '</p>';
        if ($greeting !== '') {
            $body .= '<p style="margin:0 0 12px;color:#0f172a;font-size:16px;">' . esc_html($greeting) . '</p>';
        }
        $body .= '<h1 style="margin:0 0 12px;font-size:30px;line-height:1.2;color:#111827;">Your one-time passcode</h1>';
        $body .= '<div style="color:#1f2937;font-size:15px;line-height:1.6;">' . wp_kses_post(nl2br(esc_html($message))) . '</div>';
        if ($otp_code !== '') {
            $body .= '<div style="margin:28px 0 16px;padding:22px 18px;border-radius:18px;background:linear-gradient(135deg,#4C2CE9,#6f58ef);text-align:center;color:#ffffff;">';
            $body .= '<div style="font-size:12px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.82;margin-bottom:8px;">Copy this code</div>';
            $body .= '<div style="font-size:34px;font-weight:700;letter-spacing:0.34em;text-indent:0.34em;">' . esc_html($otp_code) . '</div>';
            $body .= '</div>';
        }
        if ($helper_lines) {
            $body .= '<div style="border:1px solid rgba(15,23,42,0.08);border-radius:16px;background:#f8fafc;padding:16px 18px;">';
            foreach ($helper_lines as $line) {
                $body .= '<p style="margin:6px 0;color:#4b5563;font-size:13px;">' . esc_html($line) . '</p>';
            }
            $body .= '</div>';
        }
        if ($cta_label !== '' && $cta_url !== '') {
            $body .= sprintf(
                '<p style="margin:24px 0 10px;"><a href="%s" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:13px 24px;border-radius:999px;font-weight:600;">%s</a></p>',
                esc_url($cta_url),
                esc_html($cta_label)
            );
        }
        $body .= '<hr style="margin:32px 0;border:none;border-top:1px solid rgba(15,23,42,0.08);" />';
        $body .= '<p style="color:#6b7280;font-size:13px;margin:0 0 6px;">' . wp_kses_post($footer) . '</p>';
        $body .= '<a href="' . esc_url($site_url) . '" style="color:#4C2CE9;font-size:13px;text-decoration:none;">' . esc_html(parse_url($site_url, PHP_URL_HOST) ?? $site_url) . '</a>';
        $body .= '</div></div>';

        return $body;
    }
}
