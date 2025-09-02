<?php

namespace KBS\Api;

class AdminData {

    public static function get_registration_logs() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kbs_registration_logs", ARRAY_A);
    }

    public static function get_otp_attempts() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kbs_otp_attempts", ARRAY_A);
    }

    public static function get_system_logs() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kbs_system_logs", ARRAY_A);
    }
}
