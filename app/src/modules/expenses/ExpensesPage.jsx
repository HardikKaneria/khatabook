import { useMemo, useState } from "react";
import { createExpense } from "./api";
import { useExpenses } from "./hooks";
import ExpensesList from "./ExpensesList.jsx";
import ExpenseForm from "./ExpenseForm.jsx";
import { useAccounts } from "../accounts/hooks";

const isMoneyAccount = (acct) => ["BANK", "CASH", "WALLET"].includes((acct?.sub_type || "").toUpperCase());
const isExpenseAccount = (acct) => (acct?.type || "").toUpperCase() === "EXPENSE";

export default function ExpensesPage() {
    const [filters, setFilters] = useState(() => {
        const to = new Date();
        const from = new Date();
        from.setDate(from.getDate() - 30);
        return {
            from: from.toISOString().slice(0, 10),
            to: to.toISOString().slice(0, 10),
            category: "",
        };
    });
    const [refreshKey, setRefreshKey] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [actionError, setActionError] = useState("");

    const { data, loading, error } = useExpenses(filters, refreshKey);
    const { data: accounts = [] } = useAccounts({}, refreshKey);

    const expenses = useMemo(() => {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.items)) return data.items;
        if (Array.isArray(data?.data)) return data.data;
        return [];
    }, [data]);

    const moneyAccounts = useMemo(() => (accounts || []).filter(isMoneyAccount), [accounts]);
    const expenseAccounts = useMemo(() => (accounts || []).filter(isExpenseAccount), [accounts]);

    const goToExpense = (id) => {
        window.history.pushState({}, "", `/expenses/${id}`);
        window.dispatchEvent(new PopStateEvent("popstate"));
    };

    const handleFilterChange = (key) => (event) => {
        setFilters((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleCreateExpense = async (payload) => {
        try {
            setActionError("");
            await createExpense(payload);
            setShowForm(false);
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
                    <p className="expenses-subtitle">Track spend, payouts, and categories.</p>
                </div>
                <button className="expenses-primary-btn" onClick={() => setShowForm(true)}>
                    Add Expense
                </button>
            </header>

            <section className="expenses-card">
                <div className="expenses-card-header">
                    <div>
                        <h3>Filters</h3>
                        <p>Use date range and category to narrow down expenses.</p>
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
                </div>
            </section>

            <section className="expenses-card">
                <div className="expenses-card-header">
                    <div>
                        <h3>Expense List</h3>
                        <p>Select an expense row to view details.</p>
                    </div>
                </div>
                <ExpensesList expenses={expenses} loading={loading} error={error} onSelectExpense={goToExpense} />
            </section>

            {showForm && (
                <Modal title="New Expense" onClose={() => setShowForm(false)}>
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    <ExpenseForm
                        onSubmit={handleCreateExpense}
                        onCancel={() => setShowForm(false)}
                        moneyAccounts={moneyAccounts}
                        expenseAccounts={expenseAccounts}
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
