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

export const getExpenses = (params = {}) =>
    apiClient.get(`${BASE}/expenses${buildQuery(params)}`);

export const getExpense = (id) =>
    apiClient.get(`${BASE}/expenses/${id}`);

export const createExpense = (payload) =>
    apiClient.post(`${BASE}/expenses`, payload);

export const updateExpense = (id, payload) =>
    apiClient.put(`${BASE}/expenses/${id}`, payload);

export const archiveExpense = (id) =>
    apiClient.post(`${BASE}/expenses/${id}/archive`, {});
