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

export const getMonthlyTrends = (params = {}) =>
    apiClient.get(`${BASE}/monthly-trends${buildQuery(params)}`);
