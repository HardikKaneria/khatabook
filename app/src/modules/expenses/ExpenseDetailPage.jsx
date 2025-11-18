import { useState } from "react";
import { useExpense } from "./hooks";
import ExpenseDetail from "./ExpenseDetail.jsx";

export default function ExpenseDetailPage({ expenseId }) {
    const [refreshKey] = useState(0);
    const { data: expense, loading, error } = useExpense(expenseId, refreshKey);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    Expense Detail
                </h2>
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
        </div>
    );
}
