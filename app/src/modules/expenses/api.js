import apiClient from "../../lib/apiClient";
import buildQuery from "../../utils/buildQuery";

const BASE = "/vy/v1";

export const getExpenses = (params = {}) =>
    apiClient.get(`${BASE}/expenses${buildQuery(params)}`);

export const getExpenseSummary = (params = {}) =>
    apiClient.get(`${BASE}/expenses/summary${buildQuery(params)}`);

export const getExpense = (id) =>
    apiClient.get(`${BASE}/expenses/${id}`);

export const createExpense = (payload) =>
    apiClient.post(`${BASE}/expenses`, payload);

export const updateExpense = (id, payload) =>
    apiClient.put(`${BASE}/expenses/${id}`, payload);

export const archiveExpense = (id) =>
    apiClient.post(`${BASE}/expenses/${id}/archive`, {});

export const settleExpense = (id, payload) =>
    apiClient.post(`${BASE}/expenses/${id}/settle`, payload);
