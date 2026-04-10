import React, { useEffect, useMemo, useState, useCallback } from "react";
import {
    Alert,
    Anchor,
    Button,
    Card,
    Col,
    ConfigProvider,
    Form,
    Input,
    InputNumber,
    Row,
    Select,
    Space,
    Switch,
} from "antd";
import { getAuth } from "../utils/authStorage";
import { useToast } from "../components/ToastProvider";
import { makeDefaultApiFetch } from "../utils/apiClient";
import PageContainer from "../components/ui/PageContainer.jsx";
import PageHeader from "../components/ui/PageHeader.jsx";
import PrimaryButton from "../components/ui/PrimaryButton.jsx";
import CompanyLogoUploader from "../components/settings/CompanyLogoUploader.jsx";
import useAsyncResource from "../hooks/useAsyncResource";

// ---------------------------------------------------------------------------
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
        default_due_days: 7,
        round_off: 1,
    },
    tax: {
        gst_type: "regular",
        income_tax_rate: 25,
    },
};

const ACTIVE_CATEGORIES = ["company", "sales", "tax"];

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
    const { data: auth = null, loading: authLoading, error: authError } = useAsyncResource(
        () => getAuth().then((raw) => raw || {}),
        []
    );

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
    const [saving, setSaving] = useState({});
    const [versions, setVersions] = useState({});
    const [savingAll, setSavingAll] = useState(false);
    const [lastError, setLastError] = useState("");
    const [companyLogoBusy, setCompanyLogoBusy] = useState(false);

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
    const authReady = !authLoading && auth !== null;
    const {
        data: loadedSettings,
        loading: settingsLoading,
        error: settingsError,
    } = useAsyncResource(
        async () => {
            if (!authReady) {
                return null;
            }
            if (!orgId) {
                throw new Error("Your account is missing an organization. Please re-login or contact support.");
            }
            return fetchSettings(orgId);
        },
        [authReady, orgId, auth?.rest?.root, auth?.token, fetchSettings]
    );

    const loading = authLoading || (authReady && settingsLoading);

    useEffect(() => {
        if (!loadedSettings) {
            return;
        }
        setData(buildData(loadedSettings.settings));
        setVersions(loadedSettings.versions || {});
        setLastError("");
    }, [loadedSettings]);

    useEffect(() => {
        if (!authError) {
            return;
        }
        setLastError("Failed to load auth.");
        message.error("Failed to load auth.");
    }, [authError, message]);

    useEffect(() => {
        if (!settingsError) {
            return;
        }
        const msg = settingsError?.message || "Failed to load settings";
        setLastError(msg);
        setData(buildData({}));
        setVersions({});
        message.error(msg);
    }, [settingsError, message]);

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
            setLastError("");
            message.success(`${category} saved`);
        } catch (e) {
            const msg = e?.message || `Failed to save ${category}`;
            setLastError(msg);
            message.error(msg);
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
            ACTIVE_CATEGORIES.forEach((cat) => {
                batch[cat] = withDefaults(cat);
            });
            const resp = await saveAll(orgId, batch, versions);
            if (resp && resp.versions) setVersions(resp.versions);
            setLastError("");
            message.success("All settings saved");
        } catch (e) {
            const msg = e?.message || "Failed to save all settings";
            setLastError(msg);
            message.error(msg);
        } finally {
            setSavingAll(false);
        }
    };

    const anchorItems = useMemo(
        () => [
            { key: "company", href: "#company", title: "Company & Organization" },
            { key: "sales", href: "#sales", title: "Invoice Defaults" },
            { key: "tax", href: "#tax", title: "Taxes & Compliance" },
        ],
        []
    );

    return (
        <PageContainer>
            <ConfigProvider
                theme={{
                    token: { borderRadius: 14 },
                    components: { Card: { paddingLG: 20 }, Anchor: { paddingBlock: 8 } },
                }}
            >
                <PageHeader
                    eyebrow="Organization"
                    title="Company Settings"
                    subtitle="One page. Everything you need. Expand a section, edit, and save independently."
                    actions={
                        <PrimaryButton onClick={onSaveAll} disabled={!data || loading || companyLogoBusy} style={{ minWidth: 140 }}>
                            {savingAll ? "Saving…" : "Save All"}
                        </PrimaryButton>
                    }
                />
                {lastError ? (
                    <p className="text-red-600" style={{ marginTop: -16, marginBottom: 12 }}>
                        {lastError}
                    </p>
                ) : null}

                <Row gutter={[16, 16]} style={{ gap: 20 }}>
                    {/* Main content */}
                    <Col xs={24} lg={15} xl={16} xxl={17}>
                        <Space direction="vertical" size={20} style={{ display: "flex" }}>
                            <Alert
                                type="info"
                                showIcon
                                message="This page only shows company, invoice-default, and tax settings that the current app actively uses."
                                description="Invoice templates, logo upload, footer text, bank details, QR, and other document presentation settings live in Invoice Settings. Placeholder areas for banking, inventory, notifications, and security are intentionally hidden until they are wired to live modules."
                            />
                            {/* Sections */}
                        <CompanySection
                            orgId={orgId}
                            apiFetch={apiFetch}
                            loading={loading}
                            value={withDefaults("company")}
                            onChange={(v) => setData((d) => ({ ...d, company: v }))}
                            onSave={() => onSave("company")}
                            saving={!!saving.company}
                            disabled={!data || loading || companyLogoBusy}
                            onLogoBusyChange={setCompanyLogoBusy}
                            onError={(text) => message.error(text)}
                            onSuccess={(text) => message.success(text)}
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
                    </Space>
                </Col>

                {/* Sticky nav */}
                <Col xs={24} lg={8} xl={7} xxl={6}>
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
        </ConfigProvider>
        </PageContainer>
    );
}

// --------------------------- Sections ----------------------------------------
function CompanySection({
    orgId,
    apiFetch,
    value,
    onChange,
    onSave,
    saving,
    loading,
    disabled,
    onLogoBusyChange,
    onError,
    onSuccess,
}) {
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
                    <p style={{ marginTop: 0, color: "var(--kb-color-text-secondary)" }}>
                        These values are used in reports and invoice headers. The company logo below is the organization identity logo and the fallback invoice logo when Invoice Settings does not define its own uploaded logo.
                    </p>
                    <Form.Item label="Company Logo">
                        <CompanyLogoUploader
                            apiFetch={apiFetch}
                            orgId={orgId}
                            logoUrl={value?.logo_url || ""}
                            disabled={disabled}
                            onSettingsChange={(patch) => {
                                const next = { ...value, ...patch };
                                onChange(next);
                                form.setFieldsValue(next);
                            }}
                            onBusyChange={onLogoBusyChange}
                            onError={onError}
                            onSuccess={onSuccess}
                        />
                    </Form.Item>
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
            title="Invoice Defaults"
            extra={<SaveBar onSave={onSave} saving={saving} disabled={disabled} />}
            loading={loading}
        >
            {!loading && (
                <Form form={form} layout="vertical" onValuesChange={(_, all) => onChange(all)}>
                    <p style={{ marginTop: 0, color: "var(--kb-color-text-secondary)" }}>
                        These defaults are used when generating invoice numbers and pre-filling due dates for new invoices.
                    </p>
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
                            <Form.Item label="Default Due Days" name="default_due_days">
                                <InputNumber min={0} style={{ width: "100%" }} />
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
                    <p style={{ marginTop: 0, color: "var(--kb-color-text-secondary)" }}>
                        These tax settings currently feed report calculations and GST summaries. e-Invoice, e-Way Bill, and TDS switches stay hidden until the product has live handlers for them.
                    </p>
                    <Row gutter={12}>
                        <Col xs={24} md={12}>
                            <Form.Item label="GST Type" name="gst_type">
                                <Select options={[{ value: "regular" }, { value: "composition" }]} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item
                                label="Income Tax Rate (%)"
                                name="income_tax_rate"
                                extra="Used by report tax estimation when no stronger org-specific override exists."
                            >
                                <InputNumber min={0} max={100} step={0.1} style={{ width: "100%" }} />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>
            )}
        </SectionCard>
    );
}
