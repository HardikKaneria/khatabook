import { useEffect, useMemo, useState } from "react";

export default function InvoiceTemplatePreview({
    selectedTemplateId,
    templateName,
    primaryColor,
    accentColor,
    logoUrl,
    invoiceId,
    fallbackMessage = "Create your first invoice to see a live preview here.",
}) {
    const previewUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (selectedTemplateId) params.set("template_id", selectedTemplateId);
        if (primaryColor) params.set("primary_color", primaryColor);
        if (accentColor) params.set("accent_color", accentColor);
        if (logoUrl) params.set("logo_url", logoUrl);
        if (invoiceId) params.set("invoice_id", invoiceId);
        return `/wp-json/vy/v1/invoices/preview?${params.toString()}`;
    }, [selectedTemplateId, primaryColor, accentColor, logoUrl, invoiceId]);

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
                    <h3>Live Preview</h3>
                    <p>Updates instantly as you customize the design.</p>
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
