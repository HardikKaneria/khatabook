<?php
namespace KBS\Db;

defined('ABSPATH') || exit;

class TableManager
{
    public static function create_all_tables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        /* 1) Pending users */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_pending_users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NULL,
            email VARCHAR(191) NOT NULL,
            company VARCHAR(150) NULL,
            password TEXT NULL,
            status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_email (email),
            KEY idx_status (status),
            KEY idx_applied_at (applied_at)
        ) {$charset};";

        /* 2) Registration logs */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_registration_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(191) NULL,
            event TEXT NULL,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_logged_at (logged_at)
        ) {$charset};";

        /* 3) OTP attempts */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_otp_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(191) NOT NULL,
            otp_code VARCHAR(12) NOT NULL,
            status ENUM('sent','verified','failed') NOT NULL DEFAULT 'sent',
            ip VARCHAR(45) NULL,
            context VARCHAR(20) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_time (email, created_at),
            KEY idx_ip_time (ip, created_at),
            KEY idx_status_time (status, created_at)
        ) {$charset};";

        /* 4) System logs (add PK) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_system_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            file_name VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) {$charset};";

        /* 5) Organizations */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_organizations (
            org_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_name VARCHAR(150) NOT NULL,
            industry VARCHAR(100) NULL,
            logo_url TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id),
            UNIQUE KEY uniq_org_name (org_name),
            KEY idx_created_at (created_at)
        ) {$charset};";

        /* 6) User–Org roles (plus useful indexes) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_user_org_roles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_org (user_id, org_id),
            KEY idx_org (org_id),
            KEY idx_user (user_id),
            KEY idx_role (role)
        ) {$charset};";

        /* 7) Invoices (unique number per org + indexes) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_invoices (
            invoice_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            client_name VARCHAR(150) NULL,
            invoice_number VARCHAR(50) NULL,
            status ENUM('draft','sent','paid','cancelled') NOT NULL DEFAULT 'draft',
            issue_date DATE NULL,
            due_date DATE NULL,
            subtotal DECIMAL(12,2) NULL,
            tax_total DECIMAL(12,2) NULL,
            total DECIMAL(12,2) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (invoice_id),
            UNIQUE KEY uniq_org_number (org_id, invoice_number),
            KEY idx_org (org_id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_issue_date (issue_date),
            KEY idx_due_date (due_date)
        ) {$charset};";

        /* 8) Invoice items (index FKs) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            item_name VARCHAR(150) NULL,
            description TEXT NULL,
            quantity DECIMAL(12,2) NULL,
            unit_price DECIMAL(12,2) NULL,
            tax_percent DECIMAL(5,2) NULL,
            total DECIMAL(12,2) NULL,
            PRIMARY KEY (id),
            KEY idx_invoice (invoice_id),
            KEY idx_org (org_id)
        ) {$charset};";

        /* 9) Quotations (unique number per org + indexes) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_quotations (
            quotation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            client_name VARCHAR(150) NULL,
            quotation_number VARCHAR(50) NULL,
            status ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
            issue_date DATE NULL,
            expiry_date DATE NULL,
            subtotal DECIMAL(12,2) NULL,
            tax_total DECIMAL(12,2) NULL,
            total DECIMAL(12,2) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (quotation_id),
            UNIQUE KEY uniq_org_quote (org_id, quotation_number),
            KEY idx_org (org_id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_issue_date (issue_date),
            KEY idx_expiry_date (expiry_date)
        ) {$charset};";

        /* 10) Quotation items */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_quotation_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quotation_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            item_name VARCHAR(150) NULL,
            description TEXT NULL,
            quantity DECIMAL(12,2) NULL,
            unit_price DECIMAL(12,2) NULL,
            tax_percent DECIMAL(5,2) NULL,
            total DECIMAL(12,2) NULL,
            PRIMARY KEY (id),
            KEY idx_quotation (quotation_id),
            KEY idx_org (org_id)
        ) {$charset};";

        /* 11) Accounting entries (query-friendly indexes) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_accounting_entries (
            entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            entry_type ENUM('income','expense','adjustment') NOT NULL,
            reference_type ENUM('invoice','quotation','manual') NULL,
            reference_id BIGINT UNSIGNED NULL,
            amount DECIMAL(14,2) NOT NULL,
            description TEXT NULL,
            entry_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (entry_id),
            KEY idx_org_date (org_id, entry_date),
            KEY idx_ref (reference_type, reference_id),
            KEY idx_type (entry_type)
        ) {$charset};";

        /* 12) Settings (as you had, plus category index) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(64) NOT NULL,
            settings_json LONGTEXT NOT NULL,
            version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY org_cat (org_id, category),
            KEY org_idx (org_id),
            KEY cat_idx (category)
        ) {$charset};";

        /* 13) Org invites (needed by UsersAdmin endpoints) */
        $sql[] = "CREATE TABLE {$wpdb->prefix}kbs_org_invites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(191) NOT NULL,
            role VARCHAR(50) NOT NULL,
            token VARCHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'invited',
            invited_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_org_email (org_id, email),
            KEY idx_token (token),
            KEY idx_org_status (org_id, status),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $q) {
            dbDelta($q);
        }
    }
}
