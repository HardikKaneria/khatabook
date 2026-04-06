<?php

namespace KBS\Notifications;

use KBS\Core\SystemLogger;

defined('ABSPATH') || exit;

class InternalDocumentNotifier
{
    private const RECIPIENT_ROLES = ['company_admin', 'c_manager'];

    public static function notify_invoice_created(int $org_id, int $invoice_id): void
    {
        global $wpdb;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_invoices WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $invoice_id
        ));
        if (!$invoice) {
            return;
        }

        self::send_notification(
            $org_id,
            'Invoice',
            [
                'title'           => $invoice->invoice_number ?: sprintf('Invoice #%d', $invoice_id),
                'amount'          => trim(($invoice->currency ?: 'INR') . ' ' . number_format((float) $invoice->total, 2)),
                'date'            => $invoice->date ?: gmdate('Y-m-d'),
                'counterparty'    => $invoice->customer_name ?: null,
                'direct_app_link' => add_query_arg('org_id', $org_id, home_url('/invoices/' . $invoice_id)),
            ]
        );
    }

    public static function notify_expense_created(int $org_id, int $expense_id): void
    {
        global $wpdb;

        $expense = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vy_expenses WHERE org_id = %d AND id = %d LIMIT 1",
            $org_id,
            $expense_id
        ));
        if (!$expense) {
            return;
        }

        $reference = $expense->category ?: sprintf('Expense #%d', $expense_id);
        if (!empty($expense->payee)) {
            $reference .= ' · ' . $expense->payee;
        }

        self::send_notification(
            $org_id,
            'Expense',
            [
                'title'           => $reference,
                'amount'          => trim(($expense->currency ?: 'INR') . ' ' . number_format((float) $expense->amount, 2)),
                'date'            => $expense->expense_date ?: gmdate('Y-m-d'),
                'counterparty'    => $expense->payee ?: null,
                'direct_app_link' => add_query_arg('org_id', $org_id, home_url('/expenses/' . $expense_id)),
            ]
        );
    }

    public static function normalize_recipient_emails(array $emails): array
    {
        $normalized = [];
        foreach ($emails as $email) {
            $sanitized = sanitize_email((string) $email);
            if ($sanitized && is_email($sanitized)) {
                $normalized[$sanitized] = $sanitized;
            }
        }

        return array_values($normalized);
    }

    private static function send_notification(int $org_id, string $document_type, array $details): void
    {
        $recipients = self::resolve_recipient_emails($org_id);
        if (!$recipients) {
            return;
        }

        $org_name = self::get_org_name($org_id) ?: sprintf('Organization #%d', $org_id);
        $actor = wp_get_current_user();
        $created_by = $actor && $actor->ID
            ? ($actor->display_name ?: $actor->user_email)
            : 'A team member';

        $subject = sprintf('%s: new %s created', $org_name, strtolower($document_type));
        $lines = [
            sprintf('A new %s was created in %s.', strtolower($document_type), $org_name),
            '',
            'Created by: ' . $created_by,
            $document_type . ': ' . ($details['title'] ?? '-'),
            'Amount: ' . ($details['amount'] ?? '-'),
            'Date: ' . ($details['date'] ?? '-'),
        ];

        if (!empty($details['counterparty'])) {
            $label = $document_type === 'Expense' ? 'Vendor/Payee' : 'Customer';
            $lines[] = $label . ': ' . $details['counterparty'];
        }

        if (!empty($details['direct_app_link'])) {
            $lines[] = '';
            $lines[] = 'Open in Vyavhar: ' . $details['direct_app_link'];
        }

        $message = implode("\n", $lines);

        foreach ($recipients as $recipient) {
            $sent = function_exists('kbs_send_email')
                ? kbs_send_email($recipient, $subject, $message, [
                    'greeting'  => 'Hello,',
                    'cta_label' => 'Open Vyavhar',
                    'cta_url'   => $details['direct_app_link'] ?? home_url('/home'),
                    'footer'    => 'This is an internal notification from Vyavhar.',
                ])
                : wp_mail($recipient, $subject, kbs_render_email_body($message), ['Content-Type: text/html; charset=UTF-8']);

            if (!$sent) {
                SystemLogger::log_event(
                    'internal_' . sanitize_key(strtolower($document_type)) . '_notification_failed',
                    sprintf('Internal %s notification email was not sent.', strtolower($document_type)),
                    [
                        'org_id' => $org_id,
                        'document_type' => strtolower($document_type),
                    ],
                    0,
                    'backend/Notifications/InternalDocumentNotifier.php'
                );
            }
        }
    }

    private static function resolve_recipient_emails(int $org_id): array
    {
        global $wpdb;

        $roles_sql = implode(',', array_fill(0, count(self::RECIPIENT_ROLES), '%s'));
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT u.user_email
             FROM {$wpdb->prefix}kbs_user_org_roles r
             INNER JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.org_id = %d
               AND r.role IN ({$roles_sql})
               AND u.user_email <> ''",
            array_merge([$org_id], self::RECIPIENT_ROLES)
        ));

        return self::normalize_recipient_emails($rows ?: []);
    }

    private static function get_org_name(int $org_id): ?string
    {
        global $wpdb;

        $org_name = $wpdb->get_var($wpdb->prepare(
            "SELECT org_name FROM {$wpdb->prefix}kbs_organizations WHERE org_id = %d LIMIT 1",
            $org_id
        ));

        return is_string($org_name) && $org_name !== '' ? $org_name : null;
    }
}
