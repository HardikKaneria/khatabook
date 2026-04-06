import apiClient from "../../lib/apiClient";
import buildQuery from "../../utils/buildQuery";

const BASE = "/vy/v1";

export const getPayments = (params = {}) =>
    apiClient.get(`${BASE}/payments${buildQuery(params)}`);

export const getPromises = (params = {}) =>
    apiClient.get(`${BASE}/promises${buildQuery(params)}`);
