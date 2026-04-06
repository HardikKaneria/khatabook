<?php

define('ABSPATH', dirname(__DIR__) . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('KHATABOOK_PLUGIN_FILE', dirname(__DIR__) . '/main.php');

require __DIR__ . '/TestWpEnvironment.php';

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value)
    {
        return filter_var((string) $value, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('is_email')) {
    function is_email($value)
    {
        return (bool) filter_var((string) $value, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value)
    {
        return (string) $value;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($value)
    {
        return (string) $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value)
    {
        return (string) $value;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($value)
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($value)
    {
        $value = strtolower((string) $value);
        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value)
    {
        return rtrim((string) $value, "/\\") . '/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return trailingslashit(dirname((string) $file));
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        $base = 'https://example.test';
        $path = (string) $path;

        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url)
    {
        $parts = parse_url((string) $url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query[(string) $key] = $value;

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return $scheme . $host . $port . $path . '?' . http_build_query($query);
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/backend/Helpers/InvoiceTemplateHelper.php';
require dirname(__DIR__) . '/backend/Helpers/InvoiceRenderHelper.php';
require dirname(__DIR__) . '/backend/Helpers/InvoiceTemplateRenderHelper.php';
require dirname(__DIR__) . '/backend/Helpers/InvoiceFinancialHelper.php';
require dirname(__DIR__) . '/backend/Helpers/InvoiceEditHelper.php';
require dirname(__DIR__) . '/backend/Helpers/ExpenseEditHelper.php';
require dirname(__DIR__) . '/backend/Helpers/ReportHelper.php';
require dirname(__DIR__) . '/backend/Helpers/OrgHelper.php';
require dirname(__DIR__) . '/backend/Auth/AuthSessionHelper.php';
require dirname(__DIR__) . '/backend/Helpers/OrgMembershipHelper.php';

$GLOBALS['kbs_tests'] = [];

function kbs_test(string $name, callable $callback): void
{
    $GLOBALS['kbs_tests'][] = ['name' => $name, 'callback' => $callback];
}

function kbs_assert_true($condition, string $message = 'Expected condition to be true.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function kbs_assert_false($condition, string $message = 'Expected condition to be false.'): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function kbs_assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message ?: sprintf("Expected %s, got %s.", var_export($expected, true), var_export($actual, true)));
    }
}

function kbs_assert_starts_with(string $prefix, string $actual, string $message = ''): void
{
    if (strpos($actual, $prefix) !== 0) {
        throw new RuntimeException($message ?: sprintf("Expected '%s' to start with '%s'.", $actual, $prefix));
    }
}

function kbs_assert_count(int $expected, array $actual, string $message = ''): void
{
    if (count($actual) !== $expected) {
        throw new RuntimeException($message ?: sprintf('Expected count %d, got %d.', $expected, count($actual)));
    }
}

function kbs_assert_wp_error($value, ?string $code = null, ?int $status = null): WP_Error
{
    if (!is_wp_error($value)) {
        throw new RuntimeException('Expected a WP_Error result.');
    }

    if ($code !== null && $value->get_error_code() !== $code) {
        throw new RuntimeException(sprintf("Expected error code '%s', got '%s'.", $code, $value->get_error_code()));
    }

    if ($status !== null) {
        $data = $value->get_error_data();
        $actualStatus = is_array($data) ? (int) ($data['status'] ?? 0) : 0;
        if ($actualStatus !== $status) {
            throw new RuntimeException(sprintf('Expected error status %d, got %d.', $status, $actualStatus));
        }
    }

    return $value;
}

function kbs_assert_response($value, int $status): WP_REST_Response
{
    if (!($value instanceof WP_REST_Response)) {
        throw new RuntimeException('Expected a WP_REST_Response result.');
    }

    if ($value->get_status() !== $status) {
        throw new RuntimeException(sprintf('Expected response status %d, got %d.', $status, $value->get_status()));
    }

    return $value;
}
