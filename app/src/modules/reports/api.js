import apiClient from "../../lib/apiClient";
import buildQuery from "../../utils/buildQuery";

const BASE = "/vy/v1/reports";

export const getProfitSummary = (params = {}) =>
    apiClient.get(`${BASE}/profit-summary${buildQuery(params)}`);

export const getGstSummary = (params = {}) =>
    apiClient.get(`${BASE}/gst-summary${buildQuery(params)}`);

export const getTaxEstimate = (params = {}) =>
    apiClient.get(`${BASE}/tax-estimate${buildQuery(params)}`);

export const getReceivablesSummary = (params = {}) =>
    apiClient.get(`${BASE}/receivables-summary${buildQuery(params)}`);

export const getPayablesSummary = (params = {}) =>
    apiClient.get(`${BASE}/payables-summary${buildQuery(params)}`);

export const getMonthlyTrends = (params = {}) =>
    apiClient.get(`${BASE}/monthly-trends${buildQuery(params)}`);

export const getBillingHealth = (params = {}) =>
    apiClient.get(`${BASE}/billing-health${buildQuery(params)}`);

export const getOwnerDailyBrief = (params = {}) =>
    apiClient.get(`${BASE}/owner-daily-brief${buildQuery(params)}`);

export const getRevenueLeaks = (params = {}) =>
    apiClient.get(`${BASE}/revenue-leaks${buildQuery(params)}`);
