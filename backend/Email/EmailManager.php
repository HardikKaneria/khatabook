<?php

namespace KBS\Email;

defined('ABSPATH') || exit;

class EmailManager {
    public static function init(): void {
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer']);
        add_filter('kbs_brevo_smtp_creds', [__CLASS__, 'default_brevo_creds'], 5, 1);
        add_action('wp_mail_failed', [__CLASS__, 'handle_mail_failure']);
    }

    public static function configure_phpmailer($phpmailer): void {
        $creds = self::get_brevo_creds();
        if (!$creds) {
            error_log('[Vyavhar Email] SMTP credentials not available. Emails will fall back to default wp_mail transport.');
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $creds['host'];
        $phpmailer->Port       = (int) $creds['port'];
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $creds['user'];
        $phpmailer->Password   = $creds['pass'];
        $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        if (!empty($creds['from_email'])) {
            $phpmailer->From = $creds['from_email'];
        }
        if (!empty($creds['from_name'])) {
            $phpmailer->FromName = $creds['from_name'];
        }
    }

    public static function default_brevo_creds($existing): ?array {
        return [
            'host'       => 'smtp-relay.brevo.com',
            'port'       => 587,
            'user'       => '8f5d82001@smtp-brevo.com',
            'pass'       => 'xsmtpsib-3aced2449be7cf6fbd2f759a77a0e291409514981b5a07cf1bcc9bdf5e2b72d3-Ru9T2tVHii3f0gCh',
            'from_email' => 'support@hkrafted.com',
            'from_name'  => 'Vyavhar Support',
        ];
    }

    public static function render(string $message, array $args = []): string {
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

    public static function send(string $to, string $subject, string $message, array $args = []): bool {
        $html = self::render($message, $args);
        $headers = $args['headers'] ?? [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $sent = wp_mail($to, $subject, $html, $headers);
        if (!$sent) {
            error_log(sprintf('[Vyavhar Email] wp_mail returned false. To: %s Subject: %s', $to, $subject));
        }
        return $sent;
    }

    public static function handle_mail_failure(\WP_Error $wp_error): void {
        error_log('[Vyavhar Email] wp_mail_failed: ' . $wp_error->get_error_message());
        $data = $wp_error->get_error_data();
        if ($data) {
            error_log('[Vyavhar Email] Failure data: ' . wp_json_encode($data));
        }
    }

    private static function get_brevo_creds(): ?array {
        $from_filter = apply_filters('kbs_brevo_smtp_creds', null);
        if (is_array($from_filter) && !empty($from_filter['host']) && !empty($from_filter['pass'])) {
            return $from_filter;
        }

        $env = [
            'host'       => getenv('BREVO_SMTP_HOST') ?: null,
            'port'       => getenv('BREVO_SMTP_PORT') ?: null,
            'user'       => getenv('BREVO_SMTP_USER') ?: null,
            'pass'       => getenv('BREVO_SMTP_PASS') ?: null,
            'from_email' => getenv('BREVO_FROM_EMAIL') ?: null,
            'from_name'  => getenv('BREVO_FROM_NAME') ?: null,
        ];
        if (!empty($env['host']) && !empty($env['pass']) && !empty($env['user']) && !empty($env['port'])) {
            return $env;
        }

        $const = [
            'host'       => defined('BREVO_SMTP_HOST') ? constant('BREVO_SMTP_HOST') : null,
            'port'       => defined('BREVO_SMTP_PORT') ? constant('BREVO_SMTP_PORT') : null,
            'user'       => defined('BREVO_SMTP_USER') ? constant('BREVO_SMTP_USER') : null,
            'pass'       => defined('BREVO_SMTP_PASS') ? constant('BREVO_SMTP_PASS') : null,
            'from_email' => defined('BREVO_FROM_EMAIL') ? constant('BREVO_FROM_EMAIL') : null,
            'from_name'  => defined('BREVO_FROM_NAME') ? constant('BREVO_FROM_NAME') : null,
        ];
        if (!empty($const['host']) && !empty($const['pass']) && !empty($const['user']) && !empty($const['port'])) {
            return $const;
        }

        $opt = [
            'host'       => get_option('kbs_brevo_smtp_host'),
            'port'       => get_option('kbs_brevo_smtp_port'),
            'user'       => get_option('kbs_brevo_smtp_user'),
            'pass'       => get_option('kbs_brevo_smtp_pass'),
            'from_email' => get_option('kbs_brevo_from_email'),
            'from_name'  => get_option('kbs_brevo_from_name'),
        ];
        if (!empty($opt['host']) && !empty($opt['pass']) && !empty($opt['user']) && !empty($opt['port'])) {
            return $opt;
        }

        return null;
    }
}

if (!function_exists('kbs_send_email')) {
    function kbs_send_email(string $to, string $subject, string $message, array $args = []): bool {
        return EmailManager::send($to, $subject, $message, $args);
    }
}

if (!function_exists('kbs_render_email_body')) {
    function kbs_render_email_body(string $message, array $args = []): string {
        return EmailManager::render($message, $args);
    }
}
