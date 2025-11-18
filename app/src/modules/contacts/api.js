import apiClient from "../../lib/apiClient";

export async function searchContacts({ type, q, page = 1, perPage = 10, status } = {}) {
    const params = new URLSearchParams();
    if (type) params.set("type", type);
    if (q) params.set("q", q);
    if (status) params.set("status", status);
    params.set("page", page);
    params.set("per_page", perPage);
    const query = params.toString();
    return apiClient.get(`/vy/v1/contacts?${query}`);
}

export async function createContact(payload) {
    return apiClient.post("/vy/v1/contacts", payload);
}
