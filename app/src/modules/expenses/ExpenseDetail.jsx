const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ExpenseDetail({ expense }) {
    if (!expense) return <p>No expense data.</p>;

    const isPaid = Boolean(expense.payment_journal_id);

    return (
        <div className="space-y-4">
            <section className="kb-card" style={{ padding: 24 }}>
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    {expense.category}
                </h2>
                <p className="kb-muted" style={{ marginBottom: 8 }}>
                    {expense.expense_date}
                </p>
                <p>
                    Payee: <strong>{expense.payee || "—"}</strong>
                </p>
                <p>Description: {expense.description || "—"}</p>
                <p style={{ fontSize: 20, fontWeight: 700 }}>
                    ₹ {formatCurrency(expense.amount)} {expense.currency || "INR"}
                </p>
                <p className="kb-muted">
                    Status: {expense.status || "POSTED"} · {isPaid ? "Paid" : "Unpaid"}
                </p>
            </section>

            <section className="kb-card" style={{ padding: 24 }}>
                <h3 className="kb-h3">Payment Information</h3>
                {isPaid ? (
                    <p>
                        Paid via journal #{expense.payment_journal_id}. Money left account{" "}
                        {expense.pay_from_account_name || expense.pay_from_account_id || ""}.
                    </p>
                ) : (
                    <p>No payment recorded for this expense yet.</p>
                )}
            </section>
        </div>
    );
}
