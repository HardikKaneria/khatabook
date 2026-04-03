import apiClient from "../../lib/apiClient";

const BASE = "/vy/v1/contacts";

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

export async function listContacts({ type, q, page = 1, perPage = 10, status } = {}) {
    return apiClient.get(
        `${BASE}${buildQuery({
            type,
            q,
            status,
            page,
            per_page: perPage,
        })}`
    );
}

export const searchContacts = listContacts;

export async function createContact(payload) {
    return apiClient.post(BASE, payload);
}

export async function getContact(id) {
    return apiClient.get(`${BASE}/${id}`);
}

export async function updateContact(id, payload) {
    return apiClient.put(`${BASE}/${id}`, payload);
}

export async function archiveContact(id) {
    return apiClient.post(`${BASE}/${id}/archive`, {});
}
