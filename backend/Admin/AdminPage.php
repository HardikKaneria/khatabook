<?php

namespace KBS\Admin;

defined('ABSPATH') || exit;

class AdminPage
{
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
