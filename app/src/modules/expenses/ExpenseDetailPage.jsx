import { useEffect, useMemo, useState } from "react";
import { useExpense } from "./hooks";
import ExpenseDetail from "./ExpenseDetail.jsx";
import ExpenseForm from "./ExpenseForm.jsx";
import { archiveExpense, settleExpense, updateExpense } from "./api";
import { useAccounts } from "../accounts/hooks";
import { useToast } from "../../components/ToastProvider";
import FeedbackState from "../../components/ui/FeedbackState.jsx";
import InlineNotice from "../../components/ui/InlineNotice.jsx";

export default function ExpenseDetailPage({ expenseId }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [showEditForm, setShowEditForm] = useState(false);
    const [showSettlementForm, setShowSettlementForm] = useState(false);
    const [actionError, setActionError] = useState("");
    const [actionBusy, setActionBusy] = useState(false);
    const { data: expense, loading, error } = useExpense(expenseId, refreshKey);
    const { data: accounts = [] } = useAccounts({}, refreshKey);
    const toast = useToast();
    const moneyAccounts = useMemo(
        () => (accounts || []).filter((account) => ["BANK", "CASH", "WALLET"].includes(String(account?.sub_type || "").toUpperCase())),
        [accounts]
    );

    const handleUpdateExpense = async (payload) => {
        try {
            setActionError("");
            setActionBusy(true);
            await updateExpense(expenseId, payload);
            toast.success("Expense updated successfully.");
            setShowEditForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to update expense.");
        } finally {
            setActionBusy(false);
        }
    };

    const handleArchiveExpense = async () => {
        if (!expense?.id || !expense?.can_archive || actionBusy) {
            return;
        }
        const confirmed = window.confirm(`Archive ${expense.category}? Archived expenses are removed from the default active list.`);
        if (!confirmed) {
            return;
        }
        try {
            setActionBusy(true);
            await archiveExpense(expense.id);
            toast.success("Expense archived.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            toast.error(err?.message || "Unable to archive expense.");
        } finally {
            setActionBusy(false);
        }
    };

    const handleSettleExpense = async (payload) => {
        try {
            setActionError("");
            setActionBusy(true);
            await settleExpense(expenseId, payload);
            toast.success(expense?.document_type === "BILL" ? "Vendor bill settled." : "Expense payment recorded.");
            setShowSettlementForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to record payment.");
        } finally {
            setActionBusy(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    {expense?.document_type === "BILL" ? "Vendor Bill Detail" : "Expense Detail"}
                </h2>
                {expense ? (
                    <div style={{ textAlign: "right" }}>
                        <div className="flex flex-wrap gap-2 justify-end">
                            <button
                                type="button"
                                className="kb-btn kb-btn--secondary"
                                disabled={!expense.can_settle || actionBusy}
                                onClick={() => {
                                    setActionError("");
                                    setShowSettlementForm(true);
                                }}
                                title={!expense.can_settle ? expense.settle_block_reason || "This expense cannot be settled." : undefined}
                            >
                                {expense.document_type === "BILL" ? "Record Bill Payment" : "Record Payment"}
                            </button>
                            <button
                                type="button"
                                className="kb-btn kb-btn--secondary"
                                disabled={!expense.can_edit || actionBusy}
                                onClick={() => {
                                    setActionError("");
                                    setShowEditForm(true);
                                }}
                                title={!expense.can_edit ? expense.edit_block_reason || "This expense can no longer be edited." : undefined}
                            >
                                {expense.document_type === "BILL" ? "Edit Bill" : "Edit Expense"}
                            </button>
                            <button
                                type="button"
                                className="kb-btn kb-btn--ghost"
                                disabled={!expense.can_archive || actionBusy}
                                onClick={handleArchiveExpense}
                                title={!expense.can_archive ? expense.archive_block_reason || "This expense can no longer be archived." : undefined}
                            >
                                Archive Expense
                            </button>
                        </div>
                        {!expense.can_settle && expense.settle_block_reason ? (
                            <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                                {expense.settle_block_reason}
                            </p>
                        ) : !expense.can_edit && expense.edit_block_reason ? (
                            <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                                {expense.edit_block_reason}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <div className="kb-card" style={{ padding: 24 }}>
                {loading ? (
                    <FeedbackState title="Loading expense" description="Fetching the current expense details." tone="loading" />
                ) : error ? (
                    <FeedbackState title="Unable to load expense" description={error.message} tone="error" />
                ) : (
                    <ExpenseDetail expense={expense} />
                )}
            </div>

            {showEditForm && expense ? (
                <Modal title="Edit Expense" onClose={() => setShowEditForm(false)}>
                    <InlineNotice message={actionError} />
                    <ExpenseForm
                        initialData={expense}
                        onSubmit={handleUpdateExpense}
                        onCancel={() => setShowEditForm(false)}
                        submitLabel="Save Changes"
                        showPaymentFields={false}
                        showExpenseAccountField={false}
                        disabled={actionBusy}
                    />
                </Modal>
            ) : null}

            {showSettlementForm && expense ? (
                <Modal title={expense.document_type === "BILL" ? "Record Bill Payment" : "Record Expense Payment"} onClose={() => setShowSettlementForm(false)}>
                    <InlineNotice message={actionError} />
                    <SettlementForm
                        expense={expense}
                        moneyAccounts={moneyAccounts}
                        onSubmit={handleSettleExpense}
                        onCancel={() => setShowSettlementForm(false)}
                        disabled={actionBusy}
                    />
                </Modal>
            ) : null}
        </div>
    );
}

function SettlementForm({ expense, moneyAccounts = [], onSubmit, onCancel, disabled = false }) {
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [payFromAccountId, setPayFromAccountId] = useState(() => String(moneyAccounts[0]?.id || ""));

    useEffect(() => {
        if (!payFromAccountId && moneyAccounts[0]?.id) {
            setPayFromAccountId(String(moneyAccounts[0].id));
        }
    }, [moneyAccounts, payFromAccountId]);

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            date,
            pay_from_account_id: payFromAccountId ? Number(payFromAccountId) : 0,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div className="kb-card" style={{ padding: 16, background: "var(--kb-surface-muted)" }}>
                <p style={{ margin: 0, fontWeight: 600 }}>{expense.category}</p>
                <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                    {expense.document_type === "BILL" ? "Vendor bill" : "Expense"} · {expense.payee || "Vendor"} · ₹{" "}
                    {Number(expense.amount ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </p>
            </div>

            <div>
                <label className="kb-muted">Payment Date</label>
                <input type="date" className="kb-input" value={date} onChange={(event) => setDate(event.target.value)} required disabled={disabled} />
            </div>

            <div>
                <label className="kb-muted">Pay From (Bank/Cash)</label>
                <select
                    className="kb-input"
                    value={payFromAccountId}
                    onChange={(event) => setPayFromAccountId(event.target.value)}
                    required
                    disabled={disabled || moneyAccounts.length === 0}
                >
                    <option value="">Select account</option>
                    {moneyAccounts.map((account) => (
                        <option key={account.id} value={account.id}>
                            {account.name}
                        </option>
                    ))}
                </select>
                {moneyAccounts.length === 0 ? (
                    <p className="kb-muted" style={{ marginTop: 8 }}>
                        Create an active bank, cash, or wallet account before recording payment.
                    </p>
                ) : null}
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel} disabled={disabled}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary" disabled={disabled || !payFromAccountId}>
                    Record Payment
                </button>
            </div>
        </form>
    );
}

function Modal({ title, children, onClose, width = "min(520px, 95vw)" }) {
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
            <div className="kb-card" style={{ padding: 24, width, maxHeight: "90vh", overflowY: "auto", borderRadius: "var(--kb-radius-lg)" }}>
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
