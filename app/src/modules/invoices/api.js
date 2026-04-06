import apiClient from "../../lib/apiClient";
import buildQuery from "../../utils/buildQuery";

const BASE = "/vy/v1";

export const getInvoices = (params = {}) =>
    apiClient.get(`${BASE}/invoices${buildQuery(params)}`);

export const getInvoice = (id) =>
    apiClient.get(`${BASE}/invoices/${id}`);

export const createInvoice = (payload = {}) =>
    apiClient.post(`${BASE}/invoices`, payload);

export const updateInvoice = (id, payload = {}) =>
    apiClient.put(`${BASE}/invoices/${id}`, payload);

export const payInvoice = (id, payload) =>
    apiClient.post(`${BASE}/invoices/${id}/pay`, payload);

export const getInvoiceDescriptions = (params = {}) =>
    apiClient.get(`${BASE}/invoices/descriptions${buildQuery(params)}`);

export const getRecurringProfiles = (params = {}) =>
    apiClient.get(`${BASE}/recurring-invoices${buildQuery(params)}`);

export const createRecurringProfile = (invoiceId, payload = {}) =>
    apiClient.post(`${BASE}/invoices/${invoiceId}/recurring`, payload);

export const updateRecurringProfile = (profileId, payload = {}) =>
    apiClient.put(`${BASE}/recurring-invoices/${profileId}`, payload);

export const generateRecurringProfile = (profileId, payload = {}) =>
    apiClient.post(`${BASE}/recurring-invoices/${profileId}/generate`, payload);

export const createInvoiceNote = (invoiceId, payload = {}) =>
    apiClient.post(`${BASE}/invoices/${invoiceId}/notes`, payload);

export const createInvoicePromise = (invoiceId, payload = {}) =>
    apiClient.post(`${BASE}/invoices/${invoiceId}/promises`, payload);

export const updateInvoicePromise = (promiseId, payload = {}) =>
    apiClient.put(`${BASE}/promises/${promiseId}`, payload);
