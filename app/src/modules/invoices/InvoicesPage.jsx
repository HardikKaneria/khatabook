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
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="kb-h2" style={{ marginBottom: 4 }}>
                        Invoices
                    </h2>
                    <p className="kb-muted" style={{ margin: 0 }}>
                        Track sales, status, and incoming payments.
                    </p>
                </div>
                <button className="kb-btn kb-btn--primary" onClick={() => setShowForm(true)}>
                    New Invoice
                </button>
            </div>

            <div className="kb-card" style={{ padding: 16 }}>
                <div className="flex flex-wrap gap-3">
                    <div style={{ flex: "1 1 220px" }}>
                        <label className="kb-muted">Status</label>
                        <select className="kb-input" value={filters.status} onChange={handleFilterChange("status")}>
                            <option value="">All</option>
                            <option value="DRAFT">Draft</option>
                            <option value="SENT">Sent</option>
                            <option value="PARTIAL">Partial</option>
                            <option value="PAID">Paid</option>
                            <option value="VOID">Void</option>
                        </select>
                    </div>
                    <div style={{ flex: "2 1 260px" }}>
                        <label className="kb-muted">Customer / Invoice</label>
                        <input
                            className="kb-input"
                            placeholder="Search customer or number"
                            value={filters.customer_name}
                            onChange={handleFilterChange("customer_name")}
                        />
                    </div>
                </div>
            </div>

            <div className="kb-card" style={{ padding: 24 }}>
                <InvoiceList
                    invoices={invoices}
                    loading={loading}
                    error={error}
                    onSelectInvoice={goToInvoice}
                />
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
