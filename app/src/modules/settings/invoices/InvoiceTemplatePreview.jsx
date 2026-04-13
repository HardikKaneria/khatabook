import { useEffect, useMemo, useState } from "react";
import { getInvoicePreviewHtml } from "./invoiceSettingsApi";

export default function InvoiceTemplatePreview({
    selectedTemplateId,
    templateName,
    primaryColor,
    accentColor,
    logoUrl,
    fontFamily,
    footerText,
    termsAndConditions,
    bankDetails,
    showTaxBreakup,
    showQrCode,
    invoiceId,
    fallbackMessage = "The preview is unavailable right now.",
}) {
    const previewParams = useMemo(() => ({
        template_id: selectedTemplateId || "",
        primary_color: primaryColor || "",
        accent_color: accentColor || "",
        logo_url: logoUrl || "",
        font_family: fontFamily || "",
        footer_text: footerText || "",
        terms_and_conditions: termsAndConditions || "",
        bank_details: bankDetails || "",
        show_tax_breakup: showTaxBreakup ? "1" : "0",
        show_qr_code: showQrCode ? "1" : "0",
        invoice_id: invoiceId || "",
    }), [
        selectedTemplateId,
        primaryColor,
        accentColor,
        logoUrl,
        fontFamily,
        footerText,
        termsAndConditions,
        bankDetails,
        showTaxBreakup,
        showQrCode,
        invoiceId,
    ]);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [html, setHtml] = useState("");

    useEffect(() => {
        let alive = true;
        setLoading(true);
        setError(null);
        setHtml("");

        (async () => {
            try {
                const nextHtml = await getInvoicePreviewHtml(previewParams);
                if (!alive) {
                    return;
                }
                if (!String(nextHtml || "").trim()) {
                    throw new Error("Preview returned no HTML.");
                }
                setHtml(nextHtml || "");
            } catch (nextError) {
                if (!alive) {
                    return;
                }
                setError(normalizePreviewError(nextError));
            } finally {
                if (alive) {
                    setLoading(false);
                }
            }
        })();

        return () => {
            alive = false;
        };
    }, [previewParams]);

    return (
        <aside className="invoice-settings-preview">
            <div className="preview-header">
                <div>
                    <h3>Template Preview</h3>
                    <p>Uses your latest invoice when available, or a synthetic sample invoice when your org has not created one yet.</p>
                </div>
                {templateName || selectedTemplateId ? (
                    <div className="template-chip">
                        <span>{templateName || "Custom Template"}</span>
                        {selectedTemplateId ? <code>{selectedTemplateId}</code> : null}
                    </div>
                ) : null}
            </div>
            <div className="preview-frame">
                {loading && !error ? <div className="preview-skeleton">Loading preview…</div> : null}
                {error ? (
                    <div className="preview-error">{error || fallbackMessage}</div>
                ) : (
                    <iframe
                        title="Invoice preview"
                        srcDoc={html}
                        style={{
                            width: "100%",
                            height: "100%",
                            border: "none",
                        }}
                        sandbox="allow-same-origin"
                    />
                )}
            </div>
        </aside>
    );
}

function normalizePreviewError(error) {
    const raw = error?.message || "";
    if (!raw) {
        return "The preview is unavailable right now.";
    }

    try {
        const parsed = JSON.parse(raw);
        if (parsed?.message) {
            return parsed.message;
        }
    } catch (parseError) {
        // no-op: plain text errors are valid here
    }

    return raw;
}
