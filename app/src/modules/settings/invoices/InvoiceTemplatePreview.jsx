import { useEffect, useMemo, useState } from "react";

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
    const previewUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (selectedTemplateId) params.set("template_id", selectedTemplateId);
        if (primaryColor) params.set("primary_color", primaryColor);
        if (accentColor) params.set("accent_color", accentColor);
        if (logoUrl) params.set("logo_url", logoUrl);
        if (fontFamily) params.set("font_family", fontFamily);
        if (footerText) params.set("footer_text", footerText);
        if (termsAndConditions) params.set("terms_and_conditions", termsAndConditions);
        if (bankDetails) params.set("bank_details", bankDetails);
        params.set("show_tax_breakup", showTaxBreakup ? "1" : "0");
        params.set("show_qr_code", showQrCode ? "1" : "0");
        if (invoiceId) params.set("invoice_id", invoiceId);
        return `/wp-json/vy/v1/invoices/preview?${params.toString()}`;
    }, [
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

    useEffect(() => {
        setLoading(true);
        setError(null);
    }, [previewUrl]);

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
                    <div className="preview-error">{fallbackMessage}</div>
                ) : (
                    <iframe
                        title="Invoice preview"
                        src={previewUrl}
                        onLoad={() => setLoading(false)}
                        onError={() => {
                            setLoading(false);
                            setError("error");
                        }}
                        style={{
                            width: "100%",
                            height: "100%",
                            border: "none",
                        }}
                        sandbox="allow-same-origin allow-scripts"
                    />
                )}
            </div>
        </aside>
    );
}
