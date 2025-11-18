import apiClient from "../../lib/apiClient";

const BASE = "/vy/v1/reports";

const buildQuery = (params = {}) => {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== "") {
            search.append(key, value);
        }
    });
    const qs = search.toString();
    return qs ? `?${qs}` : "";
};

export const getProfitSummary = (params = {}) =>
    apiClient.get(`${BASE}/profit-summary${buildQuery(params)}`);

export const getGstSummary = (params = {}) =>
    apiClient.get(`${BASE}/gst-summary${buildQuery(params)}`);

export const getTaxEstimate = (params = {}) =>
    apiClient.get(`${BASE}/tax-estimate${buildQuery(params)}`);
