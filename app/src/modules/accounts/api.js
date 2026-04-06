import apiClient from "../../lib/apiClient";
import buildQuery from "../../utils/buildQuery";

const BASE = "/vy/v1";

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
