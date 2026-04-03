import { useState } from "react";
import { useExpense } from "./hooks";
import ExpenseDetail from "./ExpenseDetail.jsx";
import ExpenseForm from "./ExpenseForm.jsx";
import { archiveExpense, updateExpense } from "./api";
import { useToast } from "../../components/ToastProvider";

export default function ExpenseDetailPage({ expenseId }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [showEditForm, setShowEditForm] = useState(false);
    const [actionError, setActionError] = useState("");
    const { data: expense, loading, error } = useExpense(expenseId, refreshKey);
    const toast = useToast();

    const handleUpdateExpense = async (payload) => {
        try {
            setActionError("");
            await updateExpense(expenseId, payload);
            toast.success("Expense updated successfully.");
            setShowEditForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to update expense.");
        }
    };

    const handleArchiveExpense = async () => {
        if (!expense?.id || !expense?.can_archive) {
            return;
        }
        const confirmed = window.confirm(`Archive ${expense.category}? Archived expenses are removed from the default active list.`);
        if (!confirmed) {
            return;
        }
        try {
            await archiveExpense(expense.id);
            toast.success("Expense archived.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            toast.error(err?.message || "Unable to archive expense.");
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    Expense Detail
                </h2>
                {expense ? (
                    <div style={{ textAlign: "right" }}>
                        <div className="flex flex-wrap gap-2 justify-end">
                            <button
                                type="button"
                                className="kb-btn kb-btn--secondary"
                                disabled={!expense.can_edit}
                                onClick={() => {
                                    setActionError("");
                                    setShowEditForm(true);
                                }}
                                title={!expense.can_edit ? expense.edit_block_reason || "This expense can no longer be edited." : undefined}
                            >
                                Edit Expense
                            </button>
                            <button
                                type="button"
                                className="kb-btn kb-btn--ghost"
                                disabled={!expense.can_archive}
                                onClick={handleArchiveExpense}
                                title={!expense.can_archive ? expense.archive_block_reason || "This expense can no longer be archived." : undefined}
                            >
                                Archive Expense
                            </button>
                        </div>
                        {!expense.can_edit && expense.edit_block_reason ? (
                            <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                                {expense.edit_block_reason}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <div className="kb-card" style={{ padding: 24 }}>
                {loading ? (
                    <p>Loading expense…</p>
                ) : error ? (
                    <p className="text-red-600">{error.message}</p>
                ) : (
                    <ExpenseDetail expense={expense} />
                )}
            </div>

            {showEditForm && expense ? (
                <Modal title="Edit Expense" onClose={() => setShowEditForm(false)}>
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    <ExpenseForm
                        initialData={expense}
                        onSubmit={handleUpdateExpense}
                        onCancel={() => setShowEditForm(false)}
                        submitLabel="Save Changes"
                        showPaymentFields={false}
                    />
                </Modal>
            ) : null}
        </div>
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
