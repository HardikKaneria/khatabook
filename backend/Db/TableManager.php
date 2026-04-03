<?php
namespace KBS\Db;

defined('ABSPATH') || exit;

class TableManager
{
    private const SCHEMA_VERSION = 6;

    public static function maybe_upgrade(): void
    {
        $current = (int) \get_option('kbs_db_schema_version', 0);
        if ($current < self::SCHEMA_VERSION) {
            self::create_all_tables();
            return;
        }
        self::run_column_patches();
    }

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

        /*
         * 7-11) Legacy business tables retained for backward compatibility.
         * Active invoice/accounting flows in the current app use the vy_* tables below.
         */
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

        /* 14) Vy Accounts */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(64) NULL,
            name VARCHAR(191) NOT NULL,
            type VARCHAR(50) NOT NULL,
            sub_type VARCHAR(50) NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            opening_balance_type ENUM('DEBIT','CREDIT') NOT NULL DEFAULT 'DEBIT',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_org_code (org_id, code),
            KEY idx_org (org_id),
            KEY idx_type (type),
            KEY idx_status (status)
        ) {$charset};";

        /* 15) Vy Contacts */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            type ENUM('CUSTOMER','VENDOR','BOTH') NOT NULL DEFAULT 'CUSTOMER',
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NULL,
            phone VARCHAR(32) NULL,
            gstin VARCHAR(32) NULL,
            billing_address TEXT NULL,
            shipping_address TEXT NULL,
            notes TEXT NULL,
            status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org (org_id),
            KEY idx_org_type_name (org_id, type, name)
        ) {$charset};";

        /* 16) Vy Bank Accounts */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_bank_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            bank_name VARCHAR(150) NULL,
            branch VARCHAR(150) NULL,
            account_number_masked VARCHAR(64) NULL,
            ifsc VARCHAR(20) NULL,
            integration_meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_org_account (org_id, account_id),
            KEY idx_org (org_id),
            KEY idx_account (account_id)
        ) {$charset};";

        /* 17) Vy Invoices */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NULL,
            invoice_number VARCHAR(64) NOT NULL,
            customer_name VARCHAR(191) NULL,
            customer_email VARCHAR(191) NULL,
            customer_phone VARCHAR(50) NULL,
            date DATE NULL,
            due_date DATE NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
            tax_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            total DECIMAL(18,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'SENT',
            template_id VARCHAR(32) NULL,
            pdf_url TEXT NULL,
            email_sent_at DATETIME NULL,
            email_sent_to TEXT NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_org_invoice (org_id, invoice_number),
            KEY idx_org (org_id),
            KEY idx_status (status),
            KEY idx_date (date)
        ) {$charset};";

        /* 18) Vy Invoice Items */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            description TEXT NULL,
            quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,
            tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(18,4) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_invoice (invoice_id),
            KEY idx_org (org_id)
        ) {$charset};";

        /* 19) Vy Invoice Payments */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_invoice_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            journal_id BIGINT UNSIGNED NULL,
            amount DECIMAL(18,2) NOT NULL,
            date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_invoice (invoice_id),
            KEY idx_org (org_id)
        ) {$charset};";

        /* 20) Vy Record History */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_record_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            record_type VARCHAR(32) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            related_record_type VARCHAR(32) NULL,
            related_record_id BIGINT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL,
            summary VARCHAR(255) NOT NULL,
            details_json LONGTEXT NULL,
            actor_user_id BIGINT UNSIGNED NULL,
            actor_label VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org_record (org_id, record_type, record_id),
            KEY idx_org_related (org_id, related_record_type, related_record_id),
            KEY idx_created_at (created_at)
        ) {$charset};";

        /* 21) Vy Expenses */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_expenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NULL,
            expense_date DATE NOT NULL,
            category VARCHAR(100) NOT NULL,
            payee VARCHAR(191) NULL,
            description LONGTEXT NULL,
            amount DECIMAL(18,2) NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
            payment_journal_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org (org_id),
            KEY idx_date (expense_date),
            KEY idx_category (category)
        ) {$charset};";

        /* 22) Vy Journal Entries */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_journal_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            entry_date DATE NOT NULL,
            type VARCHAR(50) NOT NULL,
            description TEXT NULL,
            reference VARCHAR(100) NULL,
            source_module VARCHAR(100) NULL,
            source_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org_date (org_id, entry_date),
            KEY idx_source (source_module, source_id)
        ) {$charset};";

        /* 23) Vy Journal Lines */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_journal_lines (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            journal_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            line_memo VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_journal (journal_id),
            KEY idx_account (account_id),
            KEY idx_org_account (org_id, account_id)
        ) {$charset};";

        /* 24) Invoice Template Settings */
        $sql[] = "CREATE TABLE {$wpdb->prefix}vy_invoice_template_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL,
            default_template_id VARCHAR(32) NOT NULL DEFAULT 'minimal-clean',
            logo_url TEXT NULL,
            primary_color VARCHAR(16) NULL,
            accent_color VARCHAR(16) NULL,
            font_family VARCHAR(64) NULL,
            footer_text TEXT NULL,
            terms_and_conditions TEXT NULL,
            bank_details TEXT NULL,
            show_tax_breakup TINYINT(1) NOT NULL DEFAULT 1,
            show_qr_code TINYINT(1) NOT NULL DEFAULT 0,
            auto_email_on_create TINYINT(1) NOT NULL DEFAULT 0,
            email_subject_template TEXT NULL,
            email_body_template TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_org (org_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $q) {
            dbDelta($q);
        }

        self::run_column_patches();
        \update_option('kbs_db_schema_version', self::SCHEMA_VERSION);
    }

    private static function run_column_patches(): void
    {
        global $wpdb;

        self::ensure_column($wpdb->prefix . 'vy_invoice_items', 'tax_amount', "ADD COLUMN tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER tax_rate");
        self::ensure_column($wpdb->prefix . 'vy_invoice_items', 'tax_type', "ADD COLUMN tax_type VARCHAR(32) NULL DEFAULT 'GST' AFTER tax_amount");

        self::ensure_column($wpdb->prefix . 'vy_invoices', 'template_id', "ADD COLUMN template_id VARCHAR(32) NULL AFTER status");
        self::ensure_column($wpdb->prefix . 'vy_invoices', 'pdf_url', "ADD COLUMN pdf_url TEXT NULL AFTER template_id");
        self::ensure_column($wpdb->prefix . 'vy_invoices', 'email_sent_at', "ADD COLUMN email_sent_at DATETIME NULL AFTER pdf_url");
        self::ensure_column($wpdb->prefix . 'vy_invoices', 'email_sent_to', "ADD COLUMN email_sent_to TEXT NULL AFTER email_sent_at");

        self::ensure_column($wpdb->prefix . 'vy_expenses', 'gst_rate', "ADD COLUMN gst_rate DECIMAL(6,2) NULL DEFAULT 0 AFTER amount");
        self::ensure_column($wpdb->prefix . 'vy_expenses', 'gst_amount', "ADD COLUMN gst_amount DECIMAL(18,2) NULL DEFAULT 0 AFTER gst_rate");
        self::ensure_column($wpdb->prefix . 'vy_expenses', 'gst_type', "ADD COLUMN gst_type VARCHAR(32) NULL DEFAULT 'GST' AFTER gst_amount");
        self::ensure_column($wpdb->prefix . 'vy_expenses', 'is_gst_input_eligible', "ADD COLUMN is_gst_input_eligible TINYINT(1) NOT NULL DEFAULT 1 AFTER gst_type");

        self::ensure_column($wpdb->prefix . 'kbs_organizations', 'default_income_tax_rate', "ADD COLUMN default_income_tax_rate DECIMAL(5,2) NULL DEFAULT 25.00 AFTER industry");
        self::ensure_column($wpdb->prefix . 'kbs_organizations', 'is_gst_registered', "ADD COLUMN is_gst_registered TINYINT(1) NOT NULL DEFAULT 1 AFTER default_income_tax_rate");
        self::redact_legacy_otp_values();
    }

    private static function ensure_column(string $table, string $column, string $ddl): void
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        if (!$exists) {
            $wpdb->query("ALTER TABLE {$table} {$ddl}");
        }
    }

    private static function redact_legacy_otp_values(): void
    {
        global $wpdb;

        if (\get_option('kbs_otp_values_redacted', false)) {
            return;
        }

        $table = $wpdb->prefix . 'kbs_otp_attempts';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table
        ));
        if (!$exists) {
            \update_option('kbs_otp_values_redacted', 1, false);
            return;
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET otp_code = %s
             WHERE otp_code IS NOT NULL
               AND otp_code <> %s
               AND otp_code <> ''",
            '******',
            '******'
        ));

        \update_option('kbs_otp_values_redacted', 1, false);
    }
}
