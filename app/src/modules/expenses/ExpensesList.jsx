const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ExpensesList({ expenses = [], loading, error, onSelectExpense }) {
    if (loading) return <p className="kb-muted">Loading expenses…</p>;
    if (error) return <p className="text-red-600">{error.message}</p>;
    if (!expenses.length) return <p>No expenses recorded yet.</p>;

    return (
        <div className="overflow-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-gray-500">
                        <th>Date</th>
                        <th>Category</th>
                        <th>Payee</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {expenses.map((expense) => (
                        <tr key={expense.id} className="border-t">
                            <td>{expense.expense_date}</td>
                            <td>{expense.category}</td>
                            <td>{expense.payee || "—"}</td>
                            <td>₹ {formatCurrency(expense.amount)}</td>
                            <td>{expense.status || "POSTED"}</td>
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
