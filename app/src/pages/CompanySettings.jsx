import React, { useEffect, useMemo, useState, useCallback } from "react";
import {
    Anchor,
    Button,
    Card,
    Col,
    ConfigProvider,
    Divider,
    Form,
    Grid,
    Input,
    InputNumber,
    Row,
    Select,
    Space,
    Switch,
    Typography,
} from "antd";
import { getAuth } from "../utils/authStorage";
import { useToast } from "../components/ToastProvider";
import { makeDefaultApiFetch } from "../utils/apiClient";

// ---------------------------------------------------------------------------
const { Title, Text } = Typography;
const { useBreakpoint } = Grid;

// --------------------------- Defaults --------------------------------------
const DEFAULTS = {
    company: {
        business_name: "",
        display_name: "",
        logo_url: "",
        gst_registered: false,
        gstin: "",
        state: "",
        fy_start: "2025-04-01",
        base_currency: "INR",
        multi_currency: false,
        numbering_prefix: "",
        numbering_suffix: "",
    },
    sales: {
        invoice_prefix: "INV/2025/",
        invoice_suffix: "",
        padding: 4,
        reset_cycle: "yearly",
        default_terms: "Net 7",
        round_off: 1,
    },
    tax: {
        gst_type: "regular",
        enable_einvoice: false,
        enable_eway: false,
        tds_enabled: false,
    },
    payments: {
        upi_id: "",
        bank_name: "",
        account_number: "",
        ifsc: "",
        gateways: { razorpay: false, stripe: false },
    },
    inventory: {
        enabled: true,
        valuation: "FIFO",
        low_stock_alert: true,
        batch_expiry: false,
    },
    docs: {
        paper_size: "A4",
        header_html: "",
        footer_html: "",
        terms_invoice: "",
        signature_url: "",
    },
    notify: {
        channels: { email: true, sms: false, whatsapp: true },
        triggers: {
            invoice_created: true,
            payment_received: true,
            low_stock: true,
            approval_request: true,
        },
        integrations: { whatsapp_business: false, tally_export: true, webhooks: false },
    },
    security: {
        export_import_enabled: true,
        period_lock: false,
        period_lock_upto: "",
        two_factor: false,
        session_timeout_minutes: 60,
        audit_log_enabled: true,
    },
};

// ------------------------ Helpers ------------------------------------------
const isObj = (v) => v && typeof v === "object" && !Array.isArray(v);

function deepMerge(base, patch) {
    if (!isObj(base)) return patch;
    const out = { ...base };
    if (isObj(patch)) {
        Object.keys(patch).forEach((k) => {
            out[k] = isObj(patch[k]) && isObj(base[k]) ? deepMerge(base[k], patch[k]) : patch[k];
        });
    }
    return out;
}

function buildData(fetchedSettings) {
    const result = {};
    Object.keys(DEFAULTS).forEach((cat) => {
        result[cat] = deepMerge(DEFAULTS[cat], isObj(fetchedSettings?.[cat]) ? fetchedSettings[cat] : {});
    });
    return result;
}
const tryParse = (maybeJson) => {
    if (typeof maybeJson !== "string") return maybeJson;
    try {
        const first = JSON.parse(maybeJson);
        return typeof first === "string" ? JSON.parse(first) : first;
    } catch {
        return null;
    }
};

const normalizeAuth = (raw) => {
    if (!raw) return {};
    if (typeof raw === "object" && raw.user) return raw;
    if (typeof raw === "object" && typeof raw._plain === "string") return tryParse(raw._plain) || {};
    if (typeof raw === "string") return tryParse(raw) || {};
    return raw || {};
};

const getOrgIdFromAuth = (auth) => {
    const user = auth?.user || {};
    return (
        user?.org_id ??
        user?.orgId ??
        auth?.org_id ??
        auth?.orgId ??
        (Array.isArray(user?.orgs) &&
            (user.orgs.find((o) => o.is_primary)?.org_id ?? user.orgs[0]?.org_id))
    );
};

// --------------------------- Small Layout bits -------------------------------
function SectionCard({ id, title, extra, children, loading }) {
    return (
        <div id={id} style={{ scrollMarginTop: 96 }}>
            <Card title={title} extra={extra} className="kbs-card" loading={loading}>
                {children}
            </Card>
        </div>
    );
}

function SaveBar({ onSave, saving, disabled }) {
    return (
        <Space>
            <Button type="primary" onClick={onSave} loading={saving} disabled={disabled}>
                Save
            </Button>
        </Space>
    );
}

// ------------------------------- Page ----------------------------------------
export default function SettingsAntD() {
    const message = useToast();

    const [auth, setAuth] = useState(null);
    useEffect(() => {
        let alive = true;
        (async () => {
            try {
                const raw = await getAuth();         // await!
                if (!alive) return;
                setAuth(raw || {});
            } catch (e) {
                if (!alive) return;
                console.error("[AUTH] load failed", e);
                message.error("Failed to load auth");
                setAuth({});
            }
        })();
        return () => { alive = false; };
    }, [message]);

    const orgId = useMemo(() => {
        const u = auth?.user || {};
        return u.org_id ?? u.orgId ?? (u.orgs?.find(o => o.is_primary)?.org_id ?? u.orgs?.[0]?.org_id) ?? null;
    }, [auth]);

    const apiFetch = useMemo(
        () => makeDefaultApiFetch(auth?.rest, auth?.token),
        [auth?.rest, auth?.token]
    );

    // ---- keep the rest of your state as-is
    const [data, setData] = useState(buildData({}));
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState({});
    const [versions, setVersions] = useState({});
    const [savingAll, setSavingAll] = useState(false);
    const [lastError, setLastError] = useState("");

    const withDefaults = useCallback((cat) => data?.[cat] ?? DEFAULTS[cat], [data]);

    // Normalize various server shapes to { settings, versions }
    const normalizeSettingsResponse = useCallback((raw) => {
        const out = { settings: {}, versions: {} };
        if (!raw) return out;

        if (raw.settings && typeof raw.settings === "object") {
            out.settings = raw.settings || {};
            out.versions = raw.versions || {};
            return out;
        }
        if (raw.data && typeof raw.data === "object") {
            Object.entries(raw.data).forEach(([key, val]) => {
                if (val && typeof val === "object") {
                    out.settings[key] = "settings" in val ? val.settings || {} : val;
                    if ("version" in val) out.versions[key] = val.version;
                }
            });
            return out;
        }
        if (typeof raw === "object") out.settings = raw;
        return out;
    }, []);

    const fetchSettings = useCallback(
        async (orgIdArg, category) => {
            if (!orgIdArg) throw new Error("Missing org_id");
            const params = new URLSearchParams({ org_id: String(orgIdArg) });
            if (category) params.set("category", category);
            const raw = await apiFetch(`/kbs/v1/settings?${params.toString()}`, { method: "GET" });
            return normalizeSettingsResponse(raw);
        },
        [apiFetch, normalizeSettingsResponse]
    );

    const saveCategory = useCallback(
        async (orgIdArg, category, settings) => {
            if (!orgIdArg) throw new Error("Missing org_id");
            if (!category) throw new Error("Missing category");
            return apiFetch(`/kbs/v1/settings`, {
                method: "PUT",
                body: { org_id: orgIdArg, category, settings },
            });
        },
        [apiFetch]
    );

    const saveAll = useCallback(
        async (orgIdArg, batch, versionsMap = {}) => {
            if (!orgIdArg) throw new Error("Missing org_id");
            const body = { org_id: orgIdArg, batch };
            if (versionsMap && Object.keys(versionsMap).length) body.versions = versionsMap;
            return apiFetch(`/kbs/v1/settings`, { method: "PUT", body });
        },
        [apiFetch]
    );

    // Initial load
    useEffect(() => {
        if (!auth) return;                 // wait until auth is resolved
        if (!orgId) {
            console.error("[AUTH] No orgId. Raw auth:", auth);
            message.error("Your account is missing an organization. Please re-login or contact support.");
            setLoading(false);
            return;
        }
        let alive = true;
        (async () => {
            setLoading(true);
            try {
                const { settings, versions: v } = await fetchSettings(orgId);
                if (!alive) return;
                setData(buildData(settings));
                setVersions(v || {});
                setLastError("");
            } catch (e) {
                if (!alive) return;
                console.error(e);
                const msg = e?.message || "Failed to load settings";
                setLastError(msg);
                message.error(msg);
                setData(buildData({}));
            } finally {
                if (alive) setLoading(false);
            }
        })();
        return () => { alive = false; };
    }, [auth, orgId, fetchSettings, message]);

    // Per-category save
    const onSave = async (category) => {
        setSaving((s) => ({ ...s, [category]: true }));
        try {
            const payload = withDefaults(category);
            const resp = await saveCategory(orgId, category, payload);
            const nextVersion =
                (resp && (resp.version ?? resp.next_version)) != null ? resp.version ?? resp.next_version : undefined;
            if (nextVersion != null) {
                setVersions((v) => ({ ...v, [category]: nextVersion }));
            }
            message.success(`${category} saved`);
        } catch (e) {
            console.error(e);
            message.error(e.message || `Failed to save ${category}`);
        } finally {
            setSaving((s) => ({ ...s, [category]: false }));
        }
    };

    // Save ALL
    const onSaveAll = async () => {
        if (!data) return;
        setSavingAll(true);
        try {
            const batch = {};
            Object.keys(DEFAULTS).forEach((cat) => {
                batch[cat] = withDefaults(cat);
            });
            const resp = await saveAll(orgId, batch, versions);
            if (resp && resp.versions) setVersions(resp.versions);
            message.success("All settings saved");
        } catch (e) {
            console.error(e);
            message.error(e.message || "Failed to save all settings");
        } finally {
            setSavingAll(false);
        }
    };

    const anchorItems = useMemo(
        () => [
            { key: "company", href: "#company", title: "Company & Organization" },
            { key: "sales", href: "#sales", title: "Sales & Purchases" },
            { key: "tax", href: "#tax", title: "Taxes & Compliance" },
            { key: "payments", href: "#payments", title: "Payments & Banking" },
            { key: "inventory", href: "#inventory", title: "Inventory" },
            { key: "docs", href: "#docs", title: "Documents & Branding" },
            { key: "notify", href: "#notify", title: "Notifications & Integrations" },
            { key: "security", href: "#security", title: "Data & Security" },
        ],
        []
    );

    return (
        <ConfigProvider
            theme={{
                token: { borderRadius: 14 },
                components: { Card: { paddingLG: 20 }, Anchor: { paddingBlock: 8 } },
            }}
        >
            <Row gutter={[16, 16]} style={{ padding: 16, gap: 20 }}>
                {/* Main content */}
                <Col
                    xs={24}
                    lg={15}
                    xl={16}
                    xxl={17}
                    className="shadow-xl bg-white"
                    style={{ padding: 20, borderRadius: 14 }}
                >
                    <Space direction="vertical" size={16} style={{ display: "flex" }}>
                        {/* Header + Save all button */}
                        <Row align="middle" justify="space-between" style={{ width: "100%" }}>
                            <Col>
                                <Title level={3} style={{ margin: 0 }}>
                                    Settings
                                </Title>
                                <Text type="secondary">
                                    One page. Everything you need. Expand a section, edit, and save independently.
                                </Text>
                                {lastError ? (
                                    <Text type="danger" style={{ display: "block", marginTop: 8 }}>
                                        {lastError}
                                    </Text>
                                ) : null}
                            </Col>
                            <Col>
                                <Button type="primary" onClick={onSaveAll} loading={savingAll} disabled={!data || loading}>
                                    Save all
                                </Button>
                            </Col>
                        </Row>

                        {/* Sections */}
                        <CompanySection
                            loading={loading}
                            value={withDefaults("company")}
                            onChange={(v) => setData((d) => ({ ...d, company: v }))}
                            onSave={() => onSave("company")}
                            saving={!!saving.company}
                            disabled={!data || loading}
                        />
                        <SalesSection
                            loading={loading}
                            value={withDefaults("sales")}
                            onChange={(v) => setData((d) => ({ ...d, sales: v }))}
                            onSave={() => onSave("sales")}
                            saving={!!saving.sales}
                            disabled={!data || loading}
                        />
                        <TaxSection
                            loading={loading}
                            value={withDefaults("tax")}
                            onChange={(v) => setData((d) => ({ ...d, tax: v }))}
                            onSave={() => onSave("tax")}
                            saving={!!saving.tax}
                            disabled={!data || loading}
                        />
                        <PaymentsSection
                            loading={loading}
                            value={withDefaults("payments")}
                            onChange={(v) => setData((d) => ({ ...d, payments: v }))}
                            onSave={() => onSave("payments")}
                            saving={!!saving.payments}
                            disabled={!data || loading}
                        />
                        <InventorySection
                            loading={loading}
                            value={withDefaults("inventory")}
                            onChange={(v) => setData((d) => ({ ...d, inventory: v }))}
                            onSave={() => onSave("inventory")}
                            saving={!!saving.inventory}
                            disabled={!data || loading}
                        />
                        <DocsSection
                            loading={loading}
                            value={withDefaults("docs")}
                            onChange={(v) => setData((d) => ({ ...d, docs: v }))}
                            onSave={() => onSave("docs")}
                            saving={!!saving.docs}
                            disabled={!data || loading}
                        />
                        <NotifySection
                            loading={loading}
                            value={withDefaults("notify")}
                            onChange={(v) => setData((d) => ({ ...d, notify: v }))}
                            onSave={() => onSave("notify")}
                            saving={!!saving.notify}
                            disabled={!data || loading}
                        />
                        <SecuritySection
                            loading={loading}
                            value={withDefaults("security")}
                            onChange={(v) => setData((d) => ({ ...d, security: v }))}
                            onSave={() => onSave("security")}
                            saving={!!saving.security}
                            disabled={!data || loading}
                        />
                    </Space>
                </Col>

                {/* Sticky nav */}
                <Col
                    xs={24}
                    lg={8}
                    xl={7}
                    xxl={6}
                    className="shadow-xl bg-white"
                    style={{ padding: 20, borderRadius: 14 }}
                >
                    <Card
                        className="kbs-sticky-card"
                        style={{ position: "sticky", top: 80, alignSelf: "flex-start", border: "none" }}
                        styles={{
                            body: {
                                maxHeight: "calc(100vh - 120px)",
                                overflow: "auto",
                                background: "#fff",
                                border: "none",
                            },
                        }}
                    >
                        <Anchor items={anchorItems} affix={false} />
                    </Card>
                </Col>
            </Row>

            <style>{`
        .kbs-card { box-shadow: 0 1px 2px rgba(16,24,40,0.04), 0 1px 3px rgba(16,24,40,0.06); }
      `}</style>
        </ConfigProvider>
    );
}

// --------------------------- Sections ----------------------------------------
function CompanySection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    const onValuesChange = (_, all) => onChange(all);
    return (
        <SectionCard
            id="company"
            title="Company & Organization"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={onValuesChange}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="Business Name" name="business_name" rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Display Name" name="display_name">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="GST Registered" name="gst_registered" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        {value?.gst_registered && (
                            <Col xs={24} md={12}>
                                <Form.Item label="GSTIN" name="gstin" rules={[{ len: 15, message: "15 characters" }]}>
                                    <Input onChange={(e) => onChange({ ...value, gstin: e.target.value.toUpperCase() })} />
                                </Form.Item>
                            </Col>
                        )}
                        <Col xs={24} md={12}>
                            <Form.Item label="State" name="state">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="FY Start" name="fy_start">
                                <Input type="date" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Base Currency" name="base_currency">
                                <Select options={[{ value: "INR" }, { value: "USD" }, { value: "EUR" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Multi Currency" name="multi_currency" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Numbering Prefix" name="numbering_prefix">
                                <Input placeholder="INV/2025/" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Numbering Suffix" name="numbering_suffix">
                                <Input placeholder="-SE" />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function SalesSection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="sales"
            title="Sales & Purchases"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="Invoice Prefix" name="invoice_prefix">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Invoice Suffix" name="invoice_suffix">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Padding (digits)" name="padding">
                                <InputNumber min={0} style={{ width: "100%" }} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Reset Cycle" name="reset_cycle">
                                <Select options={[{ value: "yearly" }, { value: "monthly" }, { value: "never" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Default Terms" name="default_terms">
                                <Select options={[{ value: "Net 7" }, { value: "Net 15" }, { value: "Net 30" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Round Off" name="round_off">
                                <Select
                                    options={[
                                        { value: 1, label: "Nearest ₹1" },
                                        { value: 0.5, label: "Nearest ₹0.50" },
                                        { value: 0.01, label: "Nearest ₹0.01" },
                                    ]}
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function TaxSection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="tax"
            title="Taxes & Compliance"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="GST Type" name="gst_type">
                                <Select options={[{ value: "regular" }, { value: "composition" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="e-Invoice Enabled" name="enable_einvoice" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="e-Way Bill Enabled" name="enable_eway" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="TDS Enabled" name="tds_enabled" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function PaymentsSection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="payments"
            title="Payments & Banking"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="UPI ID" name="upi_id">
                                <Input placeholder="name@bank" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Bank Name" name="bank_name">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Account Number" name="account_number">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="IFSC" name="ifsc">
                                <Input onChange={(e) => onChange({ ...value, ifsc: e.target.value.toUpperCase() })} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Razorpay" name={["gateways", "razorpay"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Stripe" name={["gateways", "stripe"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function InventorySection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="inventory"
            title="Inventory"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="Enable Inventory" name="enabled" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Valuation Method" name="valuation">
                                <Select options={[{ value: "FIFO" }, { value: "WAC" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Low Stock Alerts" name="low_stock_alert" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Batch/Expiry Tracking" name="batch_expiry" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function DocsSection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="docs"
            title="Documents & Branding"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="Paper Size" name="paper_size">
                                <Select options={[{ value: "A4" }, { value: "A5" }, { value: "80mm" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Signature/Seal URL" name="signature_url">
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24}>
                            <Form.Item label="Header (HTML)" name="header_html">
                                <Input.TextArea rows={4} />
                            </Form.Item>
                        </Col>
                        <Col xs={24}>
                            <Form.Item label="Footer (HTML)" name="footer_html">
                                <Input.TextArea rows={4} />
                            </Form.Item>
                        </Col>
                        <Col xs={24}>
                            <Form.Item label="Invoice Terms" name="terms_invoice">
                                <Input.TextArea rows={4} />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function NotifySection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="notify"
            title="Notifications & Integrations"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={8}>
                            <Form.Item label="Email" name={["channels", "email"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="SMS" name={["channels", "sms"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="WhatsApp" name={["channels", "whatsapp"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>

                        <Col span={24}>
                            <Divider>Triggers</Divider>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Invoice Created" name={["triggers", "invoice_created"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Payment Received" name={["triggers", "payment_received"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Low Stock" name={["triggers", "low_stock"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Approval Request" name={["triggers", "approval_request"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>

                        <Col span={24}>
                            <Divider>Integrations</Divider>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="WhatsApp Business API" name={["integrations", "whatsapp_business"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="Tally CSV Export" name={["integrations", "tally_export"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="Webhooks" name={["integrations", "webhooks"]} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}

function SecuritySection({ value, onChange, onSave, saving, loading, disabled }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (!loading) form.setFieldsValue(value);
    }, [value, loading, form]);
    return (
        <SectionCard
            id="security"
            title="Data & Security"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="Enable Export/Import" name="export_import_enabled" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Lock Periods" name="period_lock" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        {value?.period_lock && (
                            <Col xs={24} md={12}>
                                <Form.Item label="Lock Up To (YYYY-MM)" name="period_lock_upto">
                                    <Input placeholder="2025-06" />
                                </Form.Item>
                            </Col>
                        )}
                        <Col xs={24} md={12}>
                            <Form.Item label="Two Factor Auth" name="two_factor" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Session Timeout (min)" name="session_timeout_minutes">
                                <InputNumber min={5} style={{ width: "100%" }} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="Audit Log" name="audit_log_enabled" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}
