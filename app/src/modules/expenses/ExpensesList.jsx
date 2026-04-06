import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ExpensesList({ expenses = [], loading, error, onSelectExpense }) {
    if (loading) {
        return <FeedbackState title="Loading expenses" description="Fetching the current expense list." tone="loading" />;
    }
    if (error) {
        return <FeedbackState title="Unable to load expenses" description={error.message} tone="error" />;
    }
    if (!expenses.length) {
        return <FeedbackState title="No expenses recorded yet" description="Add an expense to start tracking operating spend." tone="empty" />;
    }

    return (
        <div className="ui-table-wrap">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-gray-500">
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Payee</th>
                        <th>Amount</th>
                        <th>State</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {expenses.map((expense) => (
                        <tr key={expense.id} className="border-t">
                            <td>{expense.expense_date}</td>
                            <td>{expense.document_type === "BILL" ? "Vendor Bill" : "Expense"}</td>
                            <td>
                                <div>
                                    <strong>{expense.category}</strong>
                                    {expense.reference_number ? (
                                        <div className="kb-muted" style={{ fontSize: 12 }}>
                                            Ref: {expense.reference_number}
                                        </div>
                                    ) : null}
                                </div>
                            </td>
                            <td>{expense.payee || "—"}</td>
                            <td>₹ {formatCurrency(expense.amount)}</td>
                            <td>
                                <div>
                                    <strong>{expense.workflow_status || expense.status || "POSTED"}</strong>
                                    {expense.document_type === "BILL" && expense.due_date ? (
                                        <div className="kb-muted" style={{ fontSize: 12 }}>
                                            Due {expense.due_date}
                                        </div>
                                    ) : null}
                                </div>
                            </td>
                            <td>
                                <button
                                    style={{
                                        background: "none",
                                        border: "none",
                                        color: "var(--kb-color-primary)",
                                        cursor: "pointer",
                                        fontWeight: 600,
                                    }}
                                    onClick={() => onSelectExpense?.(expense.id)}
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
