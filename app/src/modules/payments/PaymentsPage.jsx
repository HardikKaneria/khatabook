import { useEffect, useMemo, useState } from "react";
import { getPayments } from "./api";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const navigate = (path) => {
    window.history.pushState({}, "", path);
    window.dispatchEvent(new PopStateEvent("popstate"));
};

const buildDefaultFilters = () => {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 30);

    return {
        from: from.toISOString().slice(0, 10),
        to: to.toISOString().slice(0, 10),
    };
};

export default function PaymentsPage() {
    const [filters, setFilters] = useState(buildDefaultFilters);
    const [page, setPage] = useState(1);
    const [state, setState] = useState({
        loading: true,
        error: "",
        payments: [],
        pagination: { page: 1, per_page: 20, total: 0 },
    });

    useEffect(() => {
        let cancelled = false;

        (async () => {
            setState((prev) => ({ ...prev, loading: true, error: "" }));
            try {
                const response = await getPayments({
                    ...filters,
                    page,
                    per_page: 20,
                });
                if (cancelled) {
                    return;
                }
                setState({
                    loading: false,
                    error: "",
                    payments: response?.data || [],
                    pagination: response?.pagination || { page, per_page: 20, total: 0 },
                });
            } catch (error) {
                if (cancelled) {
                    return;
                }
                setState((prev) => ({
                    ...prev,
                    loading: false,
                    error: error?.message || "Unable to load payments.",
                    payments: [],
                }));
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [filters, page]);

    const totalCollected = useMemo(
        () => state.payments.reduce((sum, payment) => sum + Number(payment?.amount || 0), 0),
        [state.payments]
    );

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setPage(1);
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const canGoBack = page > 1;
    const canGoForward = page * Number(state.pagination?.per_page || 20) < Number(state.pagination?.total || 0);

    return (
        <div className="payments-page">
            <header className="expenses-header">
                <div>
                    <p className="expenses-eyebrow">Collections visibility</p>
                    <h1>Payments</h1>
                    <p className="expenses-subtitle">
                        Review invoice receipts, the receiving account, and the linked invoice record from one operational list.
                    </p>
                </div>
            </header>

            <section className="expenses-card">
                <div className="expenses-card-header">
                    <div>
                        <h3>Filters</h3>
                        <p>Filter payment activity by recorded date.</p>
                    </div>
                    <div className="kb-muted" style={{ textAlign: "right" }}>
                        <strong style={{ display: "block", color: "var(--kb-color-black)" }}>
                            ₹ {formatCurrency(totalCollected)}
                        </strong>
                        <span>Collected in loaded page</span>
                    </div>
                </div>
                <div className="expenses-filter-row">
                    <div className="field">
                        <label>From</label>
                        <input type="date" value={filters.from} onChange={handleFilterChange("from")} />
                    </div>
                    <div className="field">
                        <label>To</label>
                        <input type="date" value={filters.to} onChange={handleFilterChange("to")} />
                    </div>
                </div>
            </section>

            <section className="expenses-card">
                <div className="expenses-card-header">
                    <div>
                        <h3>Payment Activity</h3>
                        <p>Invoice receipts recorded through the current accounting flow.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button className="kb-btn kb-btn--ghost kb-btn--small" type="button" disabled={!canGoBack} onClick={() => setPage((value) => Math.max(1, value - 1))}>
                            Previous
                        </button>
                        <button className="kb-btn kb-btn--ghost kb-btn--small" type="button" disabled={!canGoForward} onClick={() => setPage((value) => value + 1)}>
                            Next
                        </button>
                    </div>
                </div>

                {state.loading ? (
                    <p className="kb-muted">Loading payment activity…</p>
                ) : state.error ? (
                    <p className="text-red-600 text-sm">{state.error}</p>
                ) : state.payments.length ? (
                    <div className="dashboard-home__list">
                        {state.payments.map((payment) => (
                            <article key={payment.id} className="payments-list-item">
                                <div className="payments-list-item__main">
                                    <div>
                                        <strong>₹ {formatCurrency(payment.amount)}</strong>
                                        <span>
                                            {payment.invoice?.invoice_number || "Invoice"} · {payment.invoice?.customer_name || "Customer"}
                                        </span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>{payment.date}</span>
                                        <strong>{payment.account?.name || `Journal #${payment.journal_id || "—"}`}</strong>
                                    </div>
                                </div>
                                <div className="payments-list-item__meta">
                                    <span>Status: {payment.invoice?.status || "—"}</span>
                                    <span>Balance due: ₹ {formatCurrency(payment.invoice?.balance_due || 0)}</span>
                                </div>
                                <div className="payments-list-item__actions">
                                    {payment.invoice?.id ? (
                                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => navigate(`/invoices/${payment.invoice.id}`)}>
                                            Open Invoice
                                        </button>
                                    ) : null}
                                    {payment.account?.id ? (
                                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => navigate(`/accounts/${payment.account.id}`)}>
                                            Open Account
                                        </button>
                                    ) : null}
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="dashboard-home__empty">
                        <h4>No payments in this range</h4>
                        <p>Recorded invoice receipts will appear here once collections are posted.</p>
                    </div>
                )}
            </section>
        </div>
    );
}
