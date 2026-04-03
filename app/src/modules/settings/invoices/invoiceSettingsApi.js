import apiClient from "../../../lib/apiClient";

export async function getInvoiceSettings() {
    const response = await apiClient.get("/vy/v1/invoice-settings");
    return response;
}

export async function saveInvoiceSettings(payload) {
    const response = await apiClient.post("/vy/v1/invoice-settings", payload);
    return response;
}

export async function uploadInvoiceLogo(file) {
    const body = new FormData();
    body.append("logo", file);
    const response = await apiClient.request("/vy/v1/invoice-settings/logo", {
        method: "POST",
        body,
    });
    return response;
}

export async function removeInvoiceLogo() {
    const response = await apiClient.request("/vy/v1/invoice-settings/logo", {
        method: "DELETE",
    });
    return response;
}
