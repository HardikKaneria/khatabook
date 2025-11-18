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

export const getInvoices = (params = {}) =>
    apiClient.get(`${BASE}/invoices${buildQuery(params)}`);

export const getInvoice = (id) =>
    apiClient.get(`${BASE}/invoices/${id}`);

const normalizeInvoicePayload = (payload = {}) => {
    const prepared = { ...payload };
    if (prepared.templateId !== undefined && prepared.template_id === undefined) {
        prepared.template_id = prepared.templateId;
        delete prepared.templateId;
    }
    if (prepared.template_id === "") {
        prepared.template_id = null;
    }
    return prepared;
};

export const createInvoice = (payload = {}) =>
    apiClient.post(`${BASE}/invoices`, normalizeInvoicePayload(payload));

export const updateInvoice = (id, payload = {}) =>
    apiClient.put(`${BASE}/invoices/${id}`, normalizeInvoicePayload(payload));

export const payInvoice = (id, payload) =>
    apiClient.post(`${BASE}/invoices/${id}/pay`, payload);

export const getInvoiceDescriptions = (params = {}) =>
    apiClient.get(`${BASE}/invoices/descriptions${buildQuery(params)}`);
