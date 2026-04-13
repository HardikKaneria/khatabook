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

export async function getInvoicePreviewHtml(params = {}) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === "") {
            return;
        }
        search.set(key, String(value));
    });

    const path = `/vy/v1/invoices/preview?${search.toString()}`;

    try {
        const response = await apiClient.get(path, {
            responseType: "json",
        });

        if (typeof response === "string") {
            return response;
        }

        if (response && typeof response.html === "string") {
            return response.html;
        }
    } catch (error) {
        const fallback = await apiClient.get(path, {
            responseType: "text",
        });
        if (typeof fallback === "string") {
            return fallback;
        }
        throw error;
    }

    return "";
}
