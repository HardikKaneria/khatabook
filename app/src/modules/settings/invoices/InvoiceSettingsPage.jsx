import { useEffect, useMemo, useState } from "react";
import { Input, Select, Switch } from "antd";
import { useToast } from "../../../components/ToastProvider";
import { getInvoiceSettings, saveInvoiceSettings } from "./invoiceSettingsApi";
import { subscribeAuthUpdated } from "../../../utils/authEvents.js";
import InvoiceLogoUploader from "./InvoiceLogoUploader.jsx";
import InvoiceTemplatePreview from "./InvoiceTemplatePreview.jsx";

const PLACEHOLDER_HINT =
    "{{org_name}}, {{invoice_number}}, {{invoice_total}}, {{invoice_date}}, {{customer_name}}";

export default function InvoiceSettingsPage() {
    const toast = useToast();
    const [settings, setSettings] = useState(null);
    const [templates, setTemplates] = useState([]);
    const [fontOptions, setFontOptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [logoBusy, setLogoBusy] = useState(false);
    const [authRevision, setAuthRevision] = useState(0);

    useEffect(() => {
        return subscribeAuthUpdated(() => {
            setAuthRevision((value) => value + 1);
        });
    }, []);

    useEffect(() => {
        let alive = true;
        (async () => {
            setLoading(true);
            try {
                const response = await getInvoiceSettings();
                if (!alive) return;
                setSettings(response?.settings || {});
                setTemplates(response?.templates || []);
                setFontOptions(response?.font_options || []);
            } catch (error) {
                if (!alive) return;
                toast.error(error?.message || "Failed to load invoice settings");
            } finally {
                if (alive) setLoading(false);
            }
        })();
        return () => {
            alive = false;
        };
    }, [authRevision, toast]);

    const selectedTemplate = useMemo(
        () => templates.find((tpl) => tpl.id === settings?.default_template_id) || null,
        [templates, settings?.default_template_id]
    );

    const selectedFontOption = useMemo(
        () => fontOptions.find((option) => option.value === settings?.font_family) || null,
        [fontOptions, settings?.font_family]
    );

    const updateField = (key, value) => {
        setSettings((prev) => ({
            ...(prev || {}),
            [key]: value,
        }));
    };

    const handleInputChange = (key) => (event) => {
        updateField(key, event.target.value);
    };

    const handleSwitchChange = (key) => (checked) => {
        updateField(key, checked ? 1 : 0);
    };

    const applySettingsResponse = (response, fallbackSettings = settings) => {
        setSettings(response?.settings || fallbackSettings || {});
        if (Array.isArray(response?.templates)) {
            setTemplates(response.templates);
        }
        if (Array.isArray(response?.font_options)) {
            setFontOptions(response.font_options);
        }
    };

    const handleSave = async () => {
        if (!settings) return;
        setSaving(true);
        try {
            const response = await saveInvoiceSettings(settings);
            applySettingsResponse(response, settings);
            toast.success("Invoice settings updated");
        } catch (error) {
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
        <div className="invoice-settings-page">
            <header className="invoice-settings-header">
                <div>
                    <p className="invoice-settings-eyebrow">Brand &amp; Email</p>
                    <h1>Invoice Template &amp; Email</h1>
                    <p className="invoice-settings-subtitle">
                        Brand your invoices, manage the org-wide template, and control outgoing email copy.
                    </p>
                </div>
                <button className="invoice-settings-save" onClick={handleSave} disabled={saving || logoBusy}>
                    {saving ? "Saving…" : logoBusy ? "Uploading Logo…" : "Save Changes"}
                </button>
            </header>

            <div className="invoice-settings-grid">
                <div className="invoice-settings-form">
                    <section className="invoice-settings-card">
                        <SectionTitle title="Template Catalog" description="Choose the one org-wide template used for preview, PDF export, and emailed invoices." />
                        <div className="template-option-grid" role="list" aria-label="Invoice templates">
                            {templates.map((tpl) => {
                                const active = settings.default_template_id === tpl.id;
                                return (
                                    <button
                                        key={tpl.id}
                                        type="button"
                                        className={`template-option ${active ? "is-active" : ""}`}
                                        onClick={() => updateField("default_template_id", tpl.id)}
                                    >
                                        <div className="template-option__top">
                                            <span className="template-option__name">{tpl.name}</span>
                                            <span className="template-option__state">{active ? "Active" : "Select"}</span>
                                        </div>
                                        <code>{tpl.id}</code>
                                        <p>{tpl.description}</p>
                                    </button>
                                );
                            })}
                        </div>
                        <p className="field-hint">
                            Template selection is controlled only here. Invoice create and edit screens follow this active org template.
                        </p>

                        <InvoiceLogoUploader
                            logoUrl={settings.logo_url || ""}
                            disabled={saving || logoBusy}
                            onBusyChange={setLogoBusy}
                            onSettingsChange={(nextSettings) =>
                                setSettings((prev) => ({
                                    ...(prev || {}),
                                    ...(nextSettings || {}),
                                }))
                            }
                            onError={(message) => toast.error(message)}
                        />

                        <div className="field-row">
                        <div className="field">
                            <label htmlFor="primary-color">Primary Color</label>
                            <Input
                                id="primary-color"
                                placeholder="#4f46e5"
                                maxLength={7}
                                value={settings.primary_color || ""}
                                onChange={handleInputChange("primary_color")}
                            />
                            <p className="field-hint">Used for the main template accent such as bars, totals, and key highlights. Leave blank to keep the template default.</p>
                        </div>
                        <div className="field">
                            <label htmlFor="accent-color">Accent Color</label>
                            <Input
                                id="accent-color"
                                placeholder="#1e3a8a"
                                maxLength={7}
                                value={settings.accent_color || ""}
                                onChange={handleInputChange("accent_color")}
                            />
                            <p className="field-hint">Used for secondary bands, dividers, and supporting accents where the active template provides them.</p>
                        </div>
                        <div className="field">
                            <label htmlFor="font-family">Font Family</label>
                            <Select
                                id="font-family"
                                value={settings.font_family || undefined}
                                onChange={(value) => updateField("font_family", value)}
                                options={fontOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                }))}
                                placeholder="Select invoice font"
                            />
                            {selectedFontOption?.description ? (
                                <p className="field-hint">{selectedFontOption.description}</p>
                            ) : (
                                <p className="field-hint">Only curated PDF-safe font stacks are available here.</p>
                            )}
                        </div>
                        </div>
                    </section>

                    <section className="invoice-settings-card">
                        <SectionTitle title="Document Blocks" description="Content that appears on every invoice." />
                        <div className="field">
                            <label>Footer Text</label>
                            <Input.TextArea
                                rows={4}
                                value={settings.footer_text || ""}
                                onChange={handleInputChange("footer_text")}
                                placeholder="Thanks for your business."
                            />
                        </div>
                        <div className="field">
                            <label>Terms & Conditions</label>
                            <Input.TextArea
                                rows={4}
                                value={settings.terms_and_conditions || ""}
                                onChange={handleInputChange("terms_and_conditions")}
                                placeholder="Payment is due within 7 days."
                            />
                        </div>
                        <div className="field">
                            <label>Bank Details</label>
                            <Input.TextArea
                                rows={4}
                                value={settings.bank_details || ""}
                                onChange={handleInputChange("bank_details")}
                                placeholder="Account Name, Number, IFSC…"
                            />
                        </div>
                    </section>

                    <section className="invoice-settings-card">
                        <SectionTitle title="Display Options" description="Toggle optional sections on your invoice." />
                        <ToggleField
                            id="show-tax"
                            label="Show tax breakup on invoice"
                            checked={!!settings.show_tax_breakup}
                            onChange={handleSwitchChange("show_tax_breakup")}
                        />
                        <ToggleField
                            id="show-qr"
                            label="Show payment QR code"
                            checked={!!settings.show_qr_code}
                            onChange={handleSwitchChange("show_qr_code")}
                        />
                        <ToggleField
                            id="auto-email"
                            label="Auto-email invoice after creation"
                            checked={!!settings.auto_email_on_create}
                            onChange={handleSwitchChange("auto_email_on_create")}
                        />
                    </section>

                    <section className="invoice-settings-card">
                        <SectionTitle title="Email Template" description="Customize the email sent with invoices." />
                        <div className="field">
                            <label htmlFor="email-subject">Subject</label>
                            <Input
                                id="email-subject"
                                placeholder="Invoice {{invoice_number}} from {{org_name}}"
                                value={settings.email_subject_template || ""}
                                onChange={handleInputChange("email_subject_template")}
                            />
                        </div>
                        <div className="field">
                            <label htmlFor="email-body">Body</label>
                            <Input.TextArea
                                id="email-body"
                                rows={4}
                                value={settings.email_body_template || ""}
                                onChange={handleInputChange("email_body_template")}
                                placeholder="Dear {{customer_name}}, ..."
                            />
                            <p className="field-hint">Supported placeholders: {PLACEHOLDER_HINT}</p>
                        </div>
                    </section>

                    <div className="form-footer">
                        <button type="button" className="ghost-btn" onClick={() => window.history.back()}>
                            Cancel
                        </button>
                        <button type="button" className="invoice-settings-save" onClick={handleSave} disabled={saving || logoBusy}>
                            {saving ? "Saving…" : logoBusy ? "Uploading Logo…" : "Save Changes"}
                        </button>
                    </div>
                </div>

                <InvoiceTemplatePreview
                    selectedTemplateId={settings.default_template_id}
                    templateName={selectedTemplate?.name}
                    primaryColor={settings.primary_color}
                    accentColor={settings.accent_color}
                    logoUrl={settings.logo_url}
                    fontFamily={settings.font_family}
                    footerText={settings.footer_text}
                    termsAndConditions={settings.terms_and_conditions}
                    bankDetails={settings.bank_details}
                    showTaxBreakup={!!settings.show_tax_breakup}
                    showQrCode={!!settings.show_qr_code}
                />
            </div>
        </div>
    );
}

function SectionTitle({ title, description }) {
    return (
        <div className="section-title">
            <h2>{title}</h2>
            {description ? <p>{description}</p> : null}
        </div>
    );
}

function ToggleField({ id, label, checked, onChange }) {
    return (
        <label className="toggle-field" htmlFor={id}>
            <Switch id={id} checked={checked} onChange={onChange} />
            <span>{label}</span>
        </label>
    );
}
