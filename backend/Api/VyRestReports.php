<?php

namespace KBS\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

class VyRestReports
{
    public static function register_routes(): void
    {
        register_rest_route(VyRestAccounts::NS, '/reports/profit-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'profit_summary'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/gst-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'gst_summary'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/tax-estimate', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'tax_estimate'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/receivables-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'receivables_summary'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/payables-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'payables_summary'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/monthly-trends', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'monthly_trends'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/billing-health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'billing_health'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/owner-daily-brief', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'owner_daily_brief'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);

        register_rest_route(VyRestAccounts::NS, '/reports/revenue-leaks', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'revenue_leaks'],
            'permission_callback' => [VyRestAccounts::class, 'require_auth'],
        ]);
    }

    public static function profit_summary(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_profit_summary((int) $org, $from, $to);

        return new WP_REST_Response([
            'from'    => $from,
            'to'      => $to,
            'income'  => [
                'total'       => $summary['income_total'],
                'by_account'  => $summary['income_by_account'],
            ],
            'expense' => [
                'total'       => $summary['expense_total'],
                'by_account'  => $summary['expense_by_account'],
            ],
            'profit'  => $summary['profit'],
        ], 200);
    }

    public static function gst_summary(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_gst_summary((int) $org, $from, $to);

        return new WP_REST_Response([
            'from'              => $from,
            'to'                => $to,
            'output_tax'        => $summary['output_tax'],
            'input_tax'         => $summary['input_tax'],
            'net_gst_payable'   => $summary['net_gst_payable'],
            'is_gst_registered' => $summary['is_gst_registered'] ?? true,
            'gst_type'          => $summary['gst_type'] ?? 'regular',
        ], 200);
    }

    public static function tax_estimate(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) return $org;

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $estimate = vy_get_tax_estimate((int) $org, $from, $to);

        return new WP_REST_Response([
            'from'                   => $from,
            'to'                     => $to,
            'profit'                 => $estimate['profit'],
            'income_tax_rate'        => $estimate['income_tax_rate'],
            'estimated_income_tax'   => $estimate['estimated_income_tax'],
        ], 200);
    }

    public static function receivables_summary(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_receivables_summary((int) $org, $from, $to, $request->get_param('as_of'));

        return new WP_REST_Response($summary, 200);
    }

    public static function payables_summary(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_payables_summary((int) $org, $from, $to, $request->get_param('as_of'));

        return new WP_REST_Response($summary, 200);
    }

    public static function monthly_trends(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_monthly_document_trends((int) $org, $from, $to);

        return new WP_REST_Response($summary, 200);
    }

    public static function billing_health(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        [$from, $to] = vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = vy_get_billing_health_score((int) $org, $from, $to, $request->get_param('as_of'));

        return new WP_REST_Response($summary, 200);
    }

    public static function owner_daily_brief(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        $summary = vy_get_owner_daily_brief((int) $org, $request->get_param('as_of'));

        return new WP_REST_Response($summary, 200);
    }

    public static function revenue_leaks(WP_REST_Request $request)
    {
        $org = \vy_get_current_org_id();
        if (is_wp_error($org)) {
            return $org;
        }

        [$from, $to] = \vy_get_date_range_defaults($request->get_param('from'), $request->get_param('to'));
        $summary = \vy_get_revenue_leak_detector((int) $org, $from, $to, $request->get_param('as_of'));

        return new WP_REST_Response($summary, 200);
    }
}
