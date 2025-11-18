import { useEffect, useMemo, useState } from "react";
import { useToast } from "../../../components/ToastProvider";
import { getInvoiceSettings, saveInvoiceSettings } from "./invoiceSettingsApi";

const PLACEHOLDER_HINT =
    "{{org_name}}, {{invoice_number}}, {{invoice_total}}, {{invoice_date}}, {{customer_name}}";

export default function InvoiceSettingsPage() {
    const toast = useToast();
    const [settings, setSettings] = useState(null);
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        let alive = true;
        (async () => {
            setLoading(true);
            try {
                const response = await getInvoiceSettings();
                if (!alive) return;
                setSettings(response?.settings || {});
                setTemplates(response?.templates || []);
            } catch (error) {
                if (!alive) return;
                console.error("Invoice settings load failed", error);
                toast.error(error?.message || "Failed to load invoice settings");
            } finally {
                if (alive) setLoading(false);
            }
        })();
        return () => {
            alive = false;
        };
    }, [toast]);

    const selectedTemplate = useMemo(() => {
        return templates.find((tpl) => tpl.id === settings?.default_template_id) || null;
    }, [templates, settings?.default_template_id]);

    const updateField = (key, value) => {
        setSettings((prev) => ({
            ...(prev || {}),
            [key]: value,
        }));
    };

    const handleInputChange = (key) => (event) => {
        updateField(key, event.target.value);
    };

    const handleCheckboxChange = (key) => (event) => {
        updateField(key, event.target.checked ? 1 : 0);
    };

    const handleSave = async () => {
        if (!settings) return;
        setSaving(true);
        try {
            const response = await saveInvoiceSettings(settings);
            setSettings(response?.settings || settings);
            toast.success("Invoice settings updated");
        } catch (error) {
            console.error("Invoice settings save failed", error);
            toast.error(error?.message || "Failed to save settings");
        } finally {
            setSaving(false);
        }
    };

    if (loading && !settings) {
        return (
            <div className="kb-card" style={{ padding: 24 }}>
                <p>Loading invoice settings…</p>
            </div>
        );
    }

    if (!settings) {
        return (
            <div className="kb-card" style={{ padding: 24 }}>
                <p className="text-red-600">Unable to load invoice settings.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <header className="flex items-center justify-between" style={{ gap: 16 }}>
                <div>
                    <h1 className="kb-h2" style={{ marginBottom: 4 }}>
                        Invoice Template &amp; Email
                    </h1>
                    <p className="kb-muted" style={{ margin: 0 }}>
                        Brand your invoices and control outgoing email copy.
                    </p>
                </div>
                <button
                    className="kb-btn kb-btn--primary"
                    onClick={handleSave}
                    disabled={saving}
                >
                    {saving ? "Saving…" : "Save"}
                </button>
            </header>

            <div className="kb-card" style={{ padding: 24, display: "grid", gap: 24 }}>
                <section style={{ display: "grid", gap: 12 }}>
                    <h2 className="kb-h3" style={{ margin: 0 }}>
                        Template
                    </h2>
                    <div className="kb-field">
                        <label className="kb-label" htmlFor="template-select">
                            Default Template
                        </label>
                        <select
                            id="template-select"
                            className="kb-input"
                            value={settings.default_template_id || ""}
                            onChange={handleInputChange("default_template_id")}
                        >
                            {templates.map((tpl) => (
                                <option key={tpl.id} value={tpl.id}>
                                    {tpl.name}
                                </option>
                            ))}
                        </select>
                        {selectedTemplate?.description ? (
                            <p className="kb-muted" style={{ marginTop: 4 }}>
                                {selectedTemplate.description}
                            </p>
                        ) : null}
                    </div>

                    <div className="kb-field">
                        <label className="kb-label" htmlFor="logo-url">
                            Logo URL
                        </label>
                        <input
                            id="logo-url"
                            type="text"
                            className="kb-input"
                            placeholder="https://example.com/logo.png"
                            value={settings.logo_url || ""}
                            onChange={handleInputChange("logo_url")}
                        />
                    </div>

                    <div className="grid" style={{ gap: 16, gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
                        <div className="kb-field">
                            <label className="kb-label" htmlFor="primary-color">
                                Primary Color
                            </label>
                            <input
                                id="primary-color"
                                type="text"
                                className="kb-input"
                                placeholder="#1f2937"
                                value={settings.primary_color || ""}
                                onChange={handleInputChange("primary_color")}
                            />
                        </div>
                        <div className="kb-field">
                            <label className="kb-label" htmlFor="accent-color">
                                Accent Color
                            </label>
                            <input
                                id="accent-color"
                                type="text"
                                className="kb-input"
                                placeholder="#6c5ce7"
                                value={settings.accent_color || ""}
                                onChange={handleInputChange("accent_color")}
                            />
                        </div>
                        <div className="kb-field">
                            <label className="kb-label" htmlFor="font-family">
                                Font Family
                            </label>
                            <input
                                id="font-family"
                                type="text"
                                className="kb-input"
                                placeholder="Inter, Helvetica, sans-serif"
                                value={settings.font_family || ""}
                                onChange={handleInputChange("font_family")}
                            />
                        </div>
                    </div>
                </section>

                <section style={{ display: "grid", gap: 16 }}>
                    <h2 className="kb-h3" style={{ margin: 0 }}>
                        Document Blocks
                    </h2>
                    <TextareaField
                        label="Footer Text"
                        value={settings.footer_text || ""}
                        onChange={handleInputChange("footer_text")}
                        placeholder="Thanks for your business."
                    />
                    <TextareaField
                        label="Terms & Conditions"
                        value={settings.terms_and_conditions || ""}
                        onChange={handleInputChange("terms_and_conditions")}
                        placeholder="Payment is due within 7 days."
                    />
                    <TextareaField
                        label="Bank Details"
                        value={settings.bank_details || ""}
                        onChange={handleInputChange("bank_details")}
                        placeholder="Account Name, Number, IFSC…"
                    />
                </section>

                <section style={{ display: "grid", gap: 16 }}>
                    <h2 className="kb-h3" style={{ margin: 0 }}>
                        Display Options
                    </h2>
                    <ToggleField
                        id="show-tax"
                        label="Show tax breakup on invoice"
                        checked={!!settings.show_tax_breakup}
                        onChange={handleCheckboxChange("show_tax_breakup")}
                    />
                    <ToggleField
                        id="show-qr"
                        label="Show QR code placeholder"
                        checked={!!settings.show_qr_code}
                        onChange={handleCheckboxChange("show_qr_code")}
                    />
                    <ToggleField
                        id="auto-email"
                        label="Auto-email invoice after creation"
                        checked={!!settings.auto_email_on_create}
                        onChange={handleCheckboxChange("auto_email_on_create")}
                    />
                </section>

                <section style={{ display: "grid", gap: 16 }}>
                    <h2 className="kb-h3" style={{ margin: 0 }}>
                        Email Template
                    </h2>
                    <div className="kb-field">
                        <label className="kb-label" htmlFor="email-subject">
                            Subject
                        </label>
                        <input
                            id="email-subject"
                            type="text"
                            className="kb-input"
                            placeholder="Invoice {{invoice_number}} from {{org_name}}"
                            value={settings.email_subject_template || ""}
                            onChange={handleInputChange("email_subject_template")}
                        />
                    </div>
                    <TextareaField
                        label="Body"
                        id="email-body"
                        value={settings.email_body_template || ""}
                        onChange={handleInputChange("email_body_template")}
                        placeholder="Dear {{customer_name}}, ..."
                        helperText={`Placeholders: ${PLACEHOLDER_HINT}`}
                    />
                    <p className="kb-muted" style={{ margin: 0 }}>
                        Supported placeholders: {PLACEHOLDER_HINT}
                    </p>
                </section>

                <div className="flex items-center justify-end" style={{ gap: 12 }}>
                    <button
                        className="kb-btn kb-btn--secondary"
                        type="button"
                        onClick={() => window.history.back()}
                        style={{ minWidth: 120 }}
                    >
                        Cancel
                    </button>
                    <button
                        className="kb-btn kb-btn--primary"
                        type="button"
                        onClick={handleSave}
                        disabled={saving}
                        style={{ minWidth: 120 }}
                    >
                        {saving ? "Saving…" : "Save changes"}
                    </button>
                </div>
            </div>
        </div>
    );
}

function TextareaField({ label, value, onChange, placeholder, id, helperText }) {
    const textId = id || label?.toLowerCase().replace(/\s+/g, "-");
    return (
        <div className="kb-field">
            <label className="kb-label" htmlFor={textId}>
                {label}
            </label>
            <textarea
                id={textId}
                className="kb-textarea"
                rows={4}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
            />
            {helperText ? (
                <p className="kb-muted" style={{ marginTop: 4 }}>
                    {helperText}
                </p>
            ) : null}
        </div>
    );
}

function ToggleField({ id, label, checked, onChange }) {
    return (
        <label
            htmlFor={id}
            style={{
                display: "flex",
                alignItems: "center",
                gap: 12,
                cursor: "pointer",
            }}
        >
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={onChange}
                style={{ width: 18, height: 18 }}
            />
            <span>{label}</span>
        </label>
    );
}
