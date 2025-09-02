<?php
namespace KBS\Core;

defined('ABSPATH') || exit;

class Helpers
{
    /**
     * Generate a secure random alphanumeric string (uppercase).
     *
     * @param int $length
     * @return string
     */
    public static function generate_alphanumeric_id($length = 8): string {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $id;
    }

    /**
     * Generate a unique ID for a given table + column
     *
     * @param string $prefix   e.g. 'ED'
     * @param string $table    full table name (with prefix)
     * @param string $column   column to ensure uniqueness
     * @param int $length
     * @return string
     */
    public static function generate_unique_id($prefix, $table, $column, $length = 6): string {
        global $wpdb;

        do {
            $candidate = $prefix . self::generate_alphanumeric_id($length);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE $column = %s",
                $candidate
            ));
        } while ($exists > 0);

        return $candidate;
    }

    /**
     * Get current datetime in MySQL format
     *
     * @return string
     */
    public static function now(): string {
        return current_time('mysql');
    }
}
