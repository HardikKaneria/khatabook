import { useEffect, useMemo, useState } from "react";
import { createInvoice, generateRecurringProfile, updateRecurringProfile } from "./api";
import { useInvoices, useRecurringProfiles } from "./hooks";
import InvoiceList from "./InvoiceList.jsx";
import InvoiceForm from "./InvoiceForm.jsx";
import RecurringProfileForm from "./RecurringProfileForm.jsx";
import RecurringProfilesList from "./RecurringProfilesList.jsx";
import InlineNotice from "../../components/ui/InlineNotice.jsx";
import { consumeQueryFlag } from "../../utils/locationFlags";
import { useToast } from "../../components/ToastProvider";

export default function InvoicesPage() {
    const [filters, setFilters] = useState({ status: "", customer_name: "" });
    const [refreshKey, setRefreshKey] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [showRecurringModal, setShowRecurringModal] = useState(false);
    const [selectedRecurringProfile, setSelectedRecurringProfile] = useState(null);
    const [actionError, setActionError] = useState("");
    const [recurringError, setRecurringError] = useState("");
    const { data, loading, error } = useInvoices(filters, refreshKey);
    const { data: recurringData, loading: recurringLoading, error: recurringLoadError } = useRecurringProfiles({}, refreshKey);
    const toast = useToast();

    const invoices = useMemo(() => {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.items)) return data.items;
        if (Array.isArray(data?.data)) return data.data;
        if (Array.isArray(data?.records)) return data.records;
        return [];
    }, [data]);

    const recurringProfiles = useMemo(() => {
        if (Array.isArray(recurringData)) return recurringData;
        if (Array.isArray(recurringData?.data)) return recurringData.data;
        return [];
    }, [recurringData]);

    useEffect(() => {
        if (consumeQueryFlag("create")) {
            setShowForm(true);
        }
    }, []);

    const goToInvoice = (id) => {
        window.history.pushState({}, "", `/invoices/${id}`);
        window.dispatchEvent(new PopStateEvent("popstate"));
    };

    const handleFilterChange = (key) => (event) => {
        setFilters((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleCreateInvoice = async (payload) => {
        try {
            setActionError("");
            await createInvoice(payload);
            setShowForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to create invoice.");
        }
    };

    const handleEditRecurring = (profile) => {
        setSelectedRecurringProfile(profile);
        setRecurringError("");
        setShowRecurringModal(true);
    };

    const handleRecurringSubmit = async (payload) => {
        if (!selectedRecurringProfile?.id) return;
        try {
            setRecurringError("");
            await updateRecurringProfile(selectedRecurringProfile.id, payload);
            toast.success("Recurring billing plan updated.");
            setShowRecurringModal(false);
            setSelectedRecurringProfile(null);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRecurringError(err?.message || "Unable to update recurring plan.");
        }
    };

    const handleRecurringStatus = async (profile, status) => {
        try {
            setRecurringError("");
            await updateRecurringProfile(profile.id, { status });
            toast.success(`Recurring plan ${status === "PAUSED" ? "paused" : status === "ACTIVE" ? "resumed" : "updated"}.`);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRecurringError(err?.message || "Unable to update recurring plan.");
        }
    };

    const handleGenerateRecurring = async (profile) => {
        try {
            setRecurringError("");
            const response = await generateRecurringProfile(profile.id, {});
            const generatedCount = Array.isArray(response?.generated) ? response.generated.length : 0;
            toast.success(generatedCount ? `Generated ${generatedCount} recurring invoice${generatedCount === 1 ? "" : "s"}.` : "Recurring plan checked.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRecurringError(err?.message || "Unable to generate recurring invoice.");
        }
    };

    return (
        <div className="invoices-page">
            <header className="invoices-header">
                <div>
                    <p className="invoices-eyebrow">Sales pipeline</p>
                    <h1>Invoices</h1>
                    <p className="invoices-subtitle">Track sales, status, and incoming payments.</p>
                </div>
                <button className="invoices-primary-btn" onClick={() => setShowForm(true)}>
                    New Invoice
                </button>
            </header>

            <div className="invoices-grid">
                <section className="invoices-card">
                    <div className="invoices-card-header">
                        <div>
                            <h3>Filters</h3>
                            <p>Narrow down invoices by status or customer.</p>
                        </div>
                    </div>
                    <div className="invoices-filter-row">
                        <div className="field">
                            <label>Status</label>
                            <select value={filters.status} onChange={handleFilterChange("status")}>
                                <option value="">All</option>
                                <option value="DRAFT">Draft</option>
                                <option value="SENT">Sent</option>
                                <option value="PARTIAL">Partial</option>
                                <option value="PAID">Paid</option>
                                <option value="VOID">Void</option>
                            </select>
                        </div>
                        <div className="field">
                            <label>Customer / Invoice</label>
                            <input
                                placeholder="Search customer or number"
                                value={filters.customer_name}
                                onChange={handleFilterChange("customer_name")}
                            />
                        </div>
                    </div>
                </section>

                <section className="invoices-card">
                    <div className="invoices-card-header">
                        <div>
                            <h3>Invoice List</h3>
                            <p>Click an invoice to open the detail view.</p>
                        </div>
                    </div>
                    <InvoiceList
                        invoices={invoices}
                        loading={loading}
                        error={error}
                        onSelectInvoice={goToInvoice}
                    />
                </section>

                <section className="invoices-card">
                    <div className="invoices-card-header">
                        <div>
                            <h3>Recurring Billing</h3>
                            <p>Plans created from live invoices generate future invoices on the active `vy_*` billing path.</p>
                        </div>
                    </div>
                    <InlineNotice message={recurringError} />
                    <RecurringProfilesList
                        profiles={recurringProfiles}
                        loading={recurringLoading}
                        error={recurringLoadError}
                        onEdit={handleEditRecurring}
                        onToggleStatus={handleRecurringStatus}
                        onGenerate={handleGenerateRecurring}
                        onOpenSourceInvoice={goToInvoice}
                    />
                </section>
            </div>

            {showForm && (
                <Modal title="Create Invoice" onClose={() => setShowForm(false)}>
                    <InlineNotice message={actionError} />
                    <InvoiceForm onSubmit={handleCreateInvoice} onCancel={() => setShowForm(false)} />
                </Modal>
            )}

            {showRecurringModal && selectedRecurringProfile ? (
                <Modal title="Edit Recurring Plan" onClose={() => {
                    setSelectedRecurringProfile(null);
                    setShowRecurringModal(false);
                }} width="min(720px, 96vw)">
                    <InlineNotice message={recurringError} />
                    <RecurringProfileForm
                        sourceInvoice={selectedRecurringProfile.source_invoice}
                        initialData={selectedRecurringProfile}
                        submitLabel="Save Plan"
                        onSubmit={handleRecurringSubmit}
                        onCancel={() => {
                            setSelectedRecurringProfile(null);
                            setShowRecurringModal(false);
                        }}
                    />
                </Modal>
            ) : null}
        </div>
    );
}

function Modal({ title, children, onClose }) {
    return (
        <div
            style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.35)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 1000,
            }}
        >
            <div className="kb-card" style={{ padding: 24, width: "min(600px, 95vw)", maxHeight: "90vh", overflowY: "auto", borderRadius: "var(--kb-radius-lg)" }}>
                <div className="flex items-center justify-between" style={{ marginBottom: 16 }}>
                    <h3 className="kb-h3" style={{ margin: 0 }}>
                        {title}
                    </h3>
                    <button onClick={onClose} className="kb-btn kb-btn--ghost" style={{ padding: "4px 8px" }}>
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
