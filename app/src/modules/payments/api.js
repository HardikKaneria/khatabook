import apiClient from "../../lib/apiClient";

const BASE = "/vy/v1";

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

export const getPayments = (params = {}) =>
    apiClient.get(`${BASE}/payments${buildQuery(params)}`);
