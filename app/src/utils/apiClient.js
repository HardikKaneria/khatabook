export function makeDefaultApiFetch(rest, token) {
    return async (path, { method = "GET", body, headers } = {}) => {
        const base = rest?.root || "/wp-json/";
        const url = path.startsWith("http")
            ? new URL(path)
            : new URL(path.replace(/^\//, ""), base);

        if (typeof window !== "undefined" && window.location.protocol === "https:" && url.protocol === "http:") {
            throw new Error(`Blocked: App is HTTPS but API is HTTP (${url.toString()}). Use HTTPS for API.`);
        }

        const sameOrigin = typeof window !== "undefined" && window.location.origin === url.origin;
        const useTokenHeader = !!token;
        const useNonce = !!rest?.nonce && sameOrigin && !useTokenHeader;

        const hdrs = {
            "Content-Type": "application/json",
            ...(useTokenHeader ? { "X-KBS-Token": token } : {}),
            ...(useNonce ? { "X-WP-Nonce": rest.nonce } : {}),
            ...(headers || {}),
        };

        const credentials = sameOrigin ? "include" : "omit";

        const safeHeaders = {
            ...hdrs,
            ...(hdrs.Authorization ? { Authorization: "[redacted]" } : {}),
            ...(hdrs["X-KBS-Token"] ? { "X-KBS-Token": "[redacted]" } : {}),
            ...(hdrs["X-WP-Nonce"] ? { "X-WP-Nonce": "[redacted]" } : {}),
        };
        console.debug("[API] →", method, url.toString(), { headers: safeHeaders, body });

        const doFetch = async (finalHeaders) => {
            const res = await fetch(url.toString(), {
                method,
                credentials,
                headers: finalHeaders,
                ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
            });
            const ct = res.headers.get("content-type") || "";
            const text = await res.text().catch(() => "");

            console.debug("[API] ←", res.status, res.statusText, { contentType: ct, text: text?.slice(0, 400) });

            if (!res.ok) {
                if (text.includes("rest_cookie_invalid_nonce")) {
                    throw new Error("Nonce invalid: X-WP-Nonce or cookies are not valid for this origin.");
                }
                if (res.status === 401 || res.status === 403) {
                    throw new Error(text || "Unauthorized / Forbidden. Check auth headers.");
                }
                throw new Error(text || `${res.status} ${res.statusText}`);
            }
            return ct.includes("application/json") && text ? JSON.parse(text) : {};
        };

        try {
            return await doFetch(hdrs);
        } catch (e) {
            if (!useTokenHeader && useNonce && e.message?.includes("Nonce invalid") && token) {
                const retryHeaders = {
                    "Content-Type": "application/json",
                    "X-KBS-Token": token,
                    ...(headers || {}),
                };
                return await doFetch(retryHeaders);
            }
            throw e;
        }
    };
}
