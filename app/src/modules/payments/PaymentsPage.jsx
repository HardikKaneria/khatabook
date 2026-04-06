import { useEffect, useMemo, useState } from "react";
import { getPayments, getPromises } from "./api";
import Card from "../../components/ui/Card.jsx";
import FeedbackState from "../../components/ui/FeedbackState.jsx";
import PageContainer from "../../components/ui/PageContainer.jsx";
import PageHeader from "../../components/ui/PageHeader.jsx";

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
    const [promiseState, setPromiseState] = useState({
        loading: true,
        error: "",
        promises: [],
    });

    useEffect(() => {
        let cancelled = false;

        (async () => {
            setState((prev) => ({ ...prev, loading: true, error: "" }));
            setPromiseState((prev) => ({ ...prev, loading: true, error: "" }));
            try {
                const [response, promisesResponse] = await Promise.all([
                    getPayments({
                        ...filters,
                        page,
                        per_page: 20,
                    }),
                    getPromises({
                        status: "OPEN",
                    }),
                ]);
                if (cancelled) {
                    return;
                }
                setState({
                    loading: false,
                    error: "",
                    payments: response?.data || [],
                    pagination: response?.pagination || { page, per_page: 20, total: 0 },
                });
                setPromiseState({
                    loading: false,
                    error: "",
                    promises: promisesResponse?.data || [],
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
                setPromiseState({
                    loading: false,
                    error: error?.message || "Unable to load payment promises.",
                    promises: [],
                });
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
        <PageContainer>
            <PageHeader
                eyebrow="Collections visibility"
                title="Payments"
                subtitle="Review invoice receipts, the receiving account, and the linked invoice record from one operational list."
            />

            <Card
                title="Filters"
                subtitle="Filter payment activity by recorded date."
                actions={
                    <div className="kb-muted" style={{ textAlign: "right" }}>
                        <strong style={{ display: "block", color: "var(--kb-color-black)" }}>
                            ₹ {formatCurrency(totalCollected)}
                        </strong>
                        <span>Collected in loaded page</span>
                    </div>
                }
            >
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
            </Card>

            <Card
                title="Open Promises To Pay"
                subtitle="Active customer payment commitments across the current organization."
            >
                {promiseState.loading ? (
                    <FeedbackState title="Loading payment promises" description="Fetching the overdue collection commitments for the current organization." tone="loading" />
                ) : promiseState.error ? (
                    <FeedbackState title="Unable to load promises" description={promiseState.error} tone="error" />
                ) : promiseState.promises.length ? (
                    <div className="dashboard-home__list">
                        {promiseState.promises.map((promise) => (
                            <article key={promise.id} className="payments-list-item">
                                <div className="payments-list-item__main">
                                    <div>
                                        <strong>₹ {formatCurrency(promise.promised_amount)}</strong>
                                        <span>
                                            {promise.invoice?.invoice_number || "Invoice"} · {promise.invoice?.customer_name || "Customer"}
                                        </span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>Promised by {promise.promised_date}</span>
                                        <strong>{promise.is_overdue ? "OVERDUE" : promise.status}</strong>
                                    </div>
                                </div>
                                <div className="payments-list-item__meta">
                                    <span>Balance due: ₹ {formatCurrency(promise.invoice?.balance_due || 0)}</span>
                                    <span>{promise.notes || "No promise notes recorded."}</span>
                                </div>
                                <div className="payments-list-item__actions">
                                    {promise.invoice?.id ? (
                                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => navigate(`/invoices/${promise.invoice.id}`)}>
                                            Open Invoice
                                        </button>
                                    ) : null}
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <FeedbackState
                        title="No overdue promises"
                        description="Overdue promise-to-pay commitments will appear here once customers miss a recorded collection date."
                        tone="empty"
                    />
                )}
            </Card>

            <Card
                title="Payment Activity"
                subtitle="Invoice receipts recorded through the current accounting flow."
                actions={
                    <div className="flex items-center gap-2">
                        <button className="kb-btn kb-btn--ghost kb-btn--small" type="button" disabled={!canGoBack} onClick={() => setPage((value) => Math.max(1, value - 1))}>
                            Previous
                        </button>
                        <button className="kb-btn kb-btn--ghost kb-btn--small" type="button" disabled={!canGoForward} onClick={() => setPage((value) => value + 1)}>
                            Next
                        </button>
                    </div>
                }
            >

                {state.loading ? (
                    <FeedbackState title="Loading payment activity" description="Fetching the current invoice receipt list." tone="loading" />
                ) : state.error ? (
                    <FeedbackState title="Unable to load payments" description={state.error} tone="error" />
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
                    <FeedbackState
                        title="No payments in this range"
                        description="Recorded invoice receipts will appear here once collections are posted."
                        tone="empty"
                    />
                )}
            </Card>
        </PageContainer>
    );
}
