<?php

namespace KBS\Admin;

defined('ABSPATH') || exit;

class AdminPage
{
    public static function current_page_slug(): string
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    public static function request_text(string $key, string $fallback = ''): string
    {
        if (!isset($_GET[$key])) {
            return $fallback;
        }

        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    public static function request_email(string $key, string $fallback = ''): string
    {
        if (!isset($_GET[$key])) {
            return $fallback;
        }

        return sanitize_email(wp_unslash($_GET[$key]));
    }

    public static function request_key(string $key, string $fallback = ''): string
    {
        if (!isset($_GET[$key])) {
            return $fallback;
        }

        return sanitize_key(wp_unslash($_GET[$key]));
    }

    public static function request_int(string $key, int $fallback = 1, int $min = 1, ?int $max = null): int
    {
        if (!isset($_GET[$key])) {
            return $fallback;
        }

        $value = absint(wp_unslash($_GET[$key]));
        if ($value < $min) {
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value ?: $fallback;
    }

    public static function admin_url(array $overrides = [], array $remove = []): string
    {
        $args = [];

        foreach ($_GET as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $args[sanitize_key((string) $key)] = sanitize_text_field(wp_unslash((string) $value));
        }

        foreach ($remove as $key) {
            unset($args[$key]);
        }

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($args[$key]);
                continue;
            }

            $args[$key] = $value;
        }

        if (empty($args['page'])) {
            $page = self::current_page_slug();
            if ($page !== '') {
                $args['page'] = $page;
            }
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    public static function render_page_start(string $title, string $description = '', array $stats = [], array $args = []): void
    {
        $eyebrow = isset($args['eyebrow']) ? (string) $args['eyebrow'] : 'Vyavhar Admin';
        $note = isset($args['note']) ? (string) $args['note'] : '';

        echo '<div class="wrap kbs-admin-shell">';
        echo '<section class="kbs-admin-hero">';
        echo '<div class="kbs-admin-hero__copy">';
        echo '<span class="kbs-admin-hero__eyebrow">' . esc_html($eyebrow) . '</span>';
        echo '<h1 class="kbs-admin-hero__title">' . esc_html($title) . '</h1>';

        if ($description !== '') {
            echo '<p class="kbs-admin-hero__description">' . esc_html($description) . '</p>';
        }

        if ($note !== '') {
            echo '<p class="kbs-admin-hero__note">' . esc_html($note) . '</p>';
        }

        echo '</div>';

        if (!empty($stats)) {
            echo '<div class="kbs-stat-grid">';

            foreach ($stats as $stat) {
                $label = isset($stat['label']) ? (string) $stat['label'] : '';
                $value = isset($stat['value']) ? (string) $stat['value'] : '0';
                $helper = isset($stat['helper']) ? (string) $stat['helper'] : '';

                if ($label === '') {
                    continue;
                }

                echo '<article class="kbs-stat-card">';
                echo '<span class="kbs-stat-card__label">' . esc_html($label) . '</span>';
                echo '<strong class="kbs-stat-card__value">' . esc_html($value) . '</strong>';

                if ($helper !== '') {
                    echo '<span class="kbs-stat-card__helper">' . esc_html($helper) . '</span>';
                }

                echo '</article>';
            }

            echo '</div>';
        }

        echo '</section>';
    }

    public static function render_page_end(): void
    {
        echo '</div>';
    }

    public static function render_empty_state(string $title, string $description): void
    {
        echo '<div class="kbs-empty-state">';
        echo '<h3 class="kbs-empty-state__title">' . esc_html($title) . '</h3>';
        echo '<p class="kbs-empty-state__description">' . esc_html($description) . '</p>';
        echo '</div>';
    }

    public static function render_filter_form(array $fields, array $args = []): void
    {
        $page = isset($args['page']) ? sanitize_key((string) $args['page']) : self::current_page_slug();
        $submit_label = isset($args['submit_label']) ? (string) $args['submit_label'] : 'Apply filters';
        $reset_label = isset($args['reset_label']) ? (string) $args['reset_label'] : 'Reset';

        echo '<form method="get" class="kbs-filter-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page) . '">';

        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['name']) || empty($field['label'])) {
                continue;
            }

            $name = sanitize_key((string) $field['name']);
            $type = isset($field['type']) ? (string) $field['type'] : 'text';
            $label = (string) $field['label'];
            $value = isset($field['value']) ? (string) $field['value'] : '';
            $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';

            echo '<div class="kbs-filter-field">';
            echo '<label for="kbs-filter-' . esc_attr($name) . '">' . esc_html($label) . '</label>';

            if ($type === 'select') {
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                echo '<select id="kbs-filter-' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="kbs-select">';
                foreach ($options as $option_value => $option_label) {
                    echo '<option value="' . esc_attr((string) $option_value) . '" ' . selected($value, (string) $option_value, false) . '>' . esc_html((string) $option_label) . '</option>';
                }
                echo '</select>';
            } else {
                $input_type = in_array($type, ['search', 'email', 'number', 'date', 'text'], true) ? $type : 'text';
                echo '<input id="kbs-filter-' . esc_attr($name) . '" class="kbs-input" type="' . esc_attr($input_type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
            }

            echo '</div>';
        }

        echo '<div class="kbs-filter-actions">';
        echo '<button type="submit" class="button kbs-button kbs-button--filter">' . esc_html($submit_label) . '</button>';
        echo '<a class="button kbs-button kbs-button--secondary" href="' . esc_url(self::admin_url([], array_merge(['paged'], array_map(static fn($field) => sanitize_key((string) ($field['name'] ?? '')), $fields)))) . '">' . esc_html($reset_label) . '</a>';
        echo '</div>';
        echo '</form>';
    }

    public static function render_pagination(int $page, int $per_page, int $total): void
    {
        $total_pages = max(1, (int) ceil($total / max(1, $per_page)));

        if ($total <= 0) {
            return;
        }

        $from = (($page - 1) * $per_page) + 1;
        $to = min($total, $page * $per_page);

        echo '<div class="kbs-pagination">';
        echo '<p class="kbs-pagination__summary">Showing ' . esc_html(number_format_i18n($from)) . '–' . esc_html(number_format_i18n($to)) . ' of ' . esc_html(number_format_i18n($total)) . '</p>';

        if ($total_pages > 1) {
            echo '<div class="kbs-pagination__actions">';

            if ($page > 1) {
                echo '<a class="button kbs-button kbs-button--secondary" href="' . esc_url(self::admin_url(['paged' => $page - 1])) . '">Previous</a>';
            }

            echo '<span class="kbs-pagination__current">Page ' . esc_html(number_format_i18n($page)) . ' of ' . esc_html(number_format_i18n($total_pages)) . '</span>';

            if ($page < $total_pages) {
                echo '<a class="button kbs-button kbs-button--secondary" href="' . esc_url(self::admin_url(['paged' => $page + 1])) . '">Next</a>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    public static function render_meta_grid(array $items): void
    {
        $items = array_values(array_filter($items, static function ($item): bool {
            return is_array($item) && !empty($item['label']);
        }));

        if (empty($items)) {
            return;
        }

        echo '<div class="kbs-meta-grid">';

        foreach ($items as $item) {
            $label = (string) $item['label'];
            $value = isset($item['value']) ? (string) $item['value'] : '—';
            $value = $value !== '' ? $value : '—';

            echo '<div class="kbs-meta">';
            echo '<span class="kbs-meta__label">' . esc_html($label) . '</span>';
            echo '<span class="kbs-meta__value">' . esc_html($value) . '</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    public static function badge(string $label, string $tone = 'neutral'): string
    {
        $allowed = ['neutral', 'info', 'success', 'warning', 'danger'];
        $tone = in_array($tone, $allowed, true) ? $tone : 'neutral';

        return sprintf(
            '<span class="kbs-badge kbs-badge--%1$s">%2$s</span>',
            esc_attr($tone),
            esc_html($label)
        );
    }

    public static function value($record, array $keys, string $fallback = '—'): string
    {
        foreach ($keys as $key) {
            if (is_array($record) && array_key_exists($key, $record)) {
                $value = $record[$key];
            } elseif (is_object($record) && isset($record->{$key})) {
                $value = $record->{$key};
            } else {
                continue;
            }

            if (is_scalar($value)) {
                $text = trim((string) $value);

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return $fallback;
    }

    public static function format_date(?string $value, bool $include_time = true): string
    {
        if (!$value) {
            return '—';
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        $format = get_option('date_format');
        if ($include_time) {
            $format .= ' ' . get_option('time_format');
        }

        return wp_date($format, $timestamp);
    }

    public static function relative_time(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }

        $now = current_time('timestamp');

        if ($timestamp <= $now) {
            return human_time_diff($timestamp, $now) . ' ago';
        }

        return 'in ' . human_time_diff($now, $timestamp);
    }

    public static function format_datetime(?string $value): string
    {
        $formatted = self::format_date($value, true);
        $relative = self::relative_time($value);

        if ($formatted === '—' || $relative === '') {
            return $formatted;
        }

        return $formatted . ' • ' . $relative;
    }
}
