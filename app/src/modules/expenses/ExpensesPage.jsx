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
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="kb-h2" style={{ marginBottom: 4 }}>
                        Expenses
                    </h2>
                    <p className="kb-muted" style={{ margin: 0 }}>
                        Track operational spend and payouts.
                    </p>
                </div>
                <button className="kb-btn kb-btn--primary" onClick={() => setShowForm(true)}>
                    Add Expense
                </button>
            </div>

            <div className="kb-card" style={{ padding: 16 }}>
                <div className="flex flex-wrap gap-3">
                    <div>
                        <label className="kb-muted">From</label>
                        <input type="date" className="kb-input" value={filters.from} onChange={handleFilterChange("from")} />
                    </div>
                    <div>
                        <label className="kb-muted">To</label>
                        <input type="date" className="kb-input" value={filters.to} onChange={handleFilterChange("to")} />
                    </div>
                    <div style={{ flex: "1 1 200px" }}>
                        <label className="kb-muted">Category</label>
                        <input className="kb-input" value={filters.category} onChange={handleFilterChange("category")} placeholder="All" />
                    </div>
                </div>
            </div>

            <div className="kb-card" style={{ padding: 24 }}>
                <ExpensesList expenses={expenses} loading={loading} error={error} onSelectExpense={goToExpense} />
            </div>

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
