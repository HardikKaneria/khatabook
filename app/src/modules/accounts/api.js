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

export const getAccounts = (params = {}) =>
    apiClient.get(`${BASE}/accounts${buildQuery(params)}`);

export const getAccount = (id) =>
    apiClient.get(`${BASE}/accounts/${id}`);

export const getAccountStatement = (id, params = {}) =>
    apiClient.get(`${BASE}/accounts/${id}/statement${buildQuery(params)}`);

export const createAccount = (payload) =>
    apiClient.post(`${BASE}/accounts`, payload);

export const createReceipt = (payload) =>
    apiClient.post(`${BASE}/transactions/receipt`, payload);

export const createPayment = (payload) =>
    apiClient.post(`${BASE}/transactions/payment`, payload);

export const createTransfer = (payload) =>
    apiClient.post(`${BASE}/transactions/transfer`, payload);
