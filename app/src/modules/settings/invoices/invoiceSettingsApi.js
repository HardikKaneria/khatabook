import apiClient from "../../../lib/apiClient";

export async function getInvoiceSettings() {
    const response = await apiClient.get("/vy/v1/invoice-settings");
    return response;
}

export async function saveInvoiceSettings(payload) {
    const response = await apiClient.post("/vy/v1/invoice-settings", payload);
    return response;
}
