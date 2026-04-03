import { useMemo, useState } from "react";
import { createInvoice } from "./api";
import { useInvoices } from "./hooks";
import InvoiceList from "./InvoiceList.jsx";
import InvoiceForm from "./InvoiceForm.jsx";

export default function InvoicesPage() {
    const [filters, setFilters] = useState({ status: "", customer_name: "" });
    const [refreshKey, setRefreshKey] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [actionError, setActionError] = useState("");
    const { data, loading, error } = useInvoices(filters, refreshKey);

    const invoices = useMemo(() => {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.items)) return data.items;
        if (Array.isArray(data?.data)) return data.data;
        if (Array.isArray(data?.records)) return data.records;
        return [];
    }, [data]);

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
            </div>

            {showForm && (
                <Modal title="Create Invoice" onClose={() => setShowForm(false)}>
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    <InvoiceForm onSubmit={handleCreateInvoice} onCancel={() => setShowForm(false)} />
                </Modal>
            )}
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
