import { useEffect, useMemo, useState } from "react";
import { createExpense } from "./api";
import { useExpenseSummary, useExpenses } from "./hooks";
import ExpensesList from "./ExpensesList.jsx";
import ExpenseForm from "./ExpenseForm.jsx";
import { useAccounts } from "../accounts/hooks";
import InlineNotice from "../../components/ui/InlineNotice.jsx";
import { consumeQueryFlag } from "../../utils/locationFlags";

const isMoneyAccount = (acct) => ["BANK", "CASH", "WALLET"].includes((acct?.sub_type || "").toUpperCase());
const isExpenseAccount = (acct) => (acct?.type || "").toUpperCase() === "EXPENSE";
const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function ExpensesPage() {
    const [filters, setFilters] = useState(() => {
        const to = new Date();
        const from = new Date();
        from.setDate(from.getDate() - 30);
        return {
            from: from.toISOString().slice(0, 10),
            to: to.toISOString().slice(0, 10),
            category: "",
            document_type: "ALL",
            status: "ACTIVE",
            payment_state: "ALL",
            due_state: "ALL",
            page: 1,
            per_page: 10,
        };
    });
    const [refreshKey, setRefreshKey] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [createMode, setCreateMode] = useState("EXPENSE");
    const [actionError, setActionError] = useState("");

    const { data, loading, error } = useExpenses(filters, refreshKey);
    const summaryFilters = useMemo(() => {
        const { page, per_page, ...rest } = filters;
        return rest;
    }, [filters]);
    const { data: summary, loading: summaryLoading, error: summaryError } = useExpenseSummary(summaryFilters, refreshKey);
    const { data: accounts = [] } = useAccounts({}, refreshKey);

    const expenses = useMemo(() => {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.items)) return data.items;
        if (Array.isArray(data?.data)) return data.data;
        return [];
    }, [data]);
    const pagination = data?.pagination || { page: filters.page, per_page: filters.per_page, total: expenses.length };
    const totalPages = Math.max(1, Math.ceil((pagination.total || 0) / (pagination.per_page || filters.per_page || 10)));

    const moneyAccounts = useMemo(() => (accounts || []).filter(isMoneyAccount), [accounts]);
    const expenseAccounts = useMemo(() => (accounts || []).filter(isExpenseAccount), [accounts]);

    useEffect(() => {
        if (consumeQueryFlag("create")) {
            setCreateMode("EXPENSE");
            setShowForm(true);
        }
    }, []);

    const goToExpense = (id) => {
        window.history.pushState({}, "", `/expenses/${id}`);
        window.dispatchEvent(new PopStateEvent("popstate"));
    };

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setFilters((prev) => {
            if (key === "document_type") {
                return {
                    ...prev,
                    document_type: value,
                    due_state: value === "EXPENSE" ? "ALL" : prev.due_state,
                    page: 1,
                };
            }
            return { ...prev, [key]: value, page: 1 };
        });
    };

    const handleCreateExpense = async (payload) => {
        try {
            setActionError("");
            await createExpense(payload);
            setShowForm(false);
            setFilters((prev) => ({ ...prev, page: 1 }));
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to save expense.");
        }
    };

    return (
        <div className="expenses-page">
            <header className="expenses-header">
                <div>
                    <p className="expenses-eyebrow">Operational spend</p>
                    <h1>Expenses</h1>
                    <p className="expenses-subtitle">Track direct expenses, vendor bills, due dates, and settlement state.</p>
                </div>
                <div className="flex gap-2">
                    <button className="kb-btn kb-btn--secondary" onClick={() => { setCreateMode("BILL"); setShowForm(true); }}>
                        New Bill
                    </button>
                    <button className="expenses-primary-btn" onClick={() => { setCreateMode("EXPENSE"); setShowForm(true); }}>
                        Add Expense
                    </button>
                </div>
            </header>

            <section className="expenses-summary-grid">
                <article className="expenses-summary-card">
                    <span className="expenses-summary-label">Matching Records</span>
                    <strong>{summaryLoading ? "…" : (summary?.total_records || 0)}</strong>
                    <p>{summaryLoading ? "Refreshing totals…" : `₹ ${formatCurrency(summary?.total_amount || 0)} across the current filters`}</p>
                </article>
                <article className="expenses-summary-card">
                    <span className="expenses-summary-label">Open Bills</span>
                    <strong>{summaryLoading ? "…" : (summary?.open_bill_count || 0)}</strong>
                    <p>{summaryLoading ? "Refreshing totals…" : `₹ ${formatCurrency(summary?.open_bill_amount || 0)} still payable`}</p>
                </article>
                <article className="expenses-summary-card">
                    <span className="expenses-summary-label">Overdue Bills</span>
                    <strong>{summaryLoading ? "…" : (summary?.overdue_bill_count || 0)}</strong>
                    <p>{summaryLoading ? "Refreshing totals…" : `₹ ${formatCurrency(summary?.overdue_bill_amount || 0)} already overdue`}</p>
                </article>
                <article className="expenses-summary-card">
                    <span className="expenses-summary-label">Paid Records</span>
                    <strong>{summaryLoading ? "…" : (summary?.paid_count || 0)}</strong>
                    <p>{summaryLoading ? "Refreshing totals…" : `₹ ${formatCurrency(summary?.paid_amount || 0)} already settled`}</p>
                </article>
            </section>

            {summaryError ? (
                <InlineNotice message={summaryError?.message || "Unable to refresh expense summary."} />
            ) : null}

            <section className="expenses-card">
                <div className="expenses-card-header">
                    <div>
                        <h3>Filters</h3>
                        <p>Filter expenses and vendor bills by date, due state, and settlement status.</p>
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
                    <div className="field">
                        <label>Category</label>
                        <input
                            value={filters.category}
                            onChange={handleFilterChange("category")}
                            placeholder="All"
                        />
                    </div>
                    <div className="field">
                        <label>Type</label>
                        <select value={filters.document_type} onChange={handleFilterChange("document_type")}>
                            <option value="ALL">All</option>
                            <option value="EXPENSE">Expenses</option>
                            <option value="BILL">Vendor Bills</option>
                        </select>
                    </div>
                    <div className="field">
                        <label>Status</label>
                        <select value={filters.status} onChange={handleFilterChange("status")}>
                            <option value="ACTIVE">Active</option>
                            <option value="ARCHIVED">Archived</option>
                            <option value="ALL">All</option>
                        </select>
                    </div>
                    <div className="field">
                        <label>Payment</label>
                        <select value={filters.payment_state} onChange={handleFilterChange("payment_state")}>
                            <option value="ALL">All</option>
                            <option value="UNPAID">Unpaid</option>
                            <option value="PAID">Paid</option>
                        </select>
                    </div>
                    <div className="field">
                        <label>Bill Due State</label>
                        <select
                            value={filters.due_state}
                            onChange={handleFilterChange("due_state")}
                            disabled={filters.document_type === "EXPENSE"}
                        >
                            <option value="ALL">All Bills</option>
                            <option value="OVERDUE">Overdue</option>
                            <option value="DUE_TODAY">Due Today</option>
                            <option value="UPCOMING">Upcoming</option>
                        </select>
                    </div>
                </div>
            </section>

            <section className="expenses-card">
                <div className="expenses-card-header contacts-card-header--split">
                    <div>
                        <h3>Expense List</h3>
                        <p>Select a record to review bill due state, payment visibility, and history.</p>
                    </div>
                    <span className="kb-muted">
                        Page {pagination.page || 1} of {totalPages}
                    </span>
                </div>
                <ExpensesList expenses={expenses} loading={loading} error={error} onSelectExpense={goToExpense} />

                {!loading && !error ? (
                    <div className="contacts-pagination">
                        <button
                            type="button"
                            className="kb-btn kb-btn--ghost"
                            disabled={(pagination.page || 1) <= 1}
                            onClick={() => setFilters((prev) => ({ ...prev, page: Math.max(1, (prev.page || 1) - 1) }))}
                        >
                            Previous
                        </button>
                        <span className="kb-muted">
                            Showing {(expenses.length ? ((pagination.page - 1) * pagination.per_page) + 1 : 0)}-
                            {Math.min((pagination.page || 1) * (pagination.per_page || filters.per_page || 10), pagination.total || 0)} of {pagination.total || 0}
                        </span>
                        <button
                            type="button"
                            className="kb-btn kb-btn--ghost"
                            disabled={(pagination.page || 1) >= totalPages}
                            onClick={() => setFilters((prev) => ({ ...prev, page: Math.min(totalPages, (prev.page || 1) + 1) }))}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </section>

            {showForm && (
                <Modal title={createMode === "BILL" ? "New Vendor Bill" : "New Expense"} onClose={() => setShowForm(false)}>
                    <InlineNotice message={actionError} />
                    <ExpenseForm
                        defaultDocumentType={createMode}
                        onSubmit={handleCreateExpense}
                        onCancel={() => setShowForm(false)}
                        moneyAccounts={moneyAccounts}
                        expenseAccounts={expenseAccounts}
                        submitLabel={createMode === "BILL" ? "Save Bill" : "Save Expense"}
                    />
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
            <div className="kb-card" style={{ padding: 24, width: "min(520px, 95vw)", borderRadius: "var(--kb-radius-lg)" }}>
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
