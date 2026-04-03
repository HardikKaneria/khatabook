import RecordHistoryCard from "../../components/ui/RecordHistoryCard.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ExpenseDetail({ expense }) {
    if (!expense) return <p>No expense data.</p>;

    const isPaid = Boolean(expense.payment_journal_id);
    const history = expense.history || [];

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
                {expense.contact?.type ? (
                    <p>
                        Contact Type: <strong>{expense.contact.type}</strong>
                    </p>
                ) : null}
                <p>Description: {expense.description || "—"}</p>
                <p style={{ fontSize: 20, fontWeight: 700 }}>
                    ₹ {formatCurrency(expense.amount)} {expense.currency || "INR"}
                </p>
                <p className="kb-muted">
                    Status: {expense.status || "POSTED"} · {isPaid ? "Paid" : "Unpaid"}
                </p>
                <p className="kb-muted">
                    GST: {expense.gst_rate ? `${expense.gst_rate}%` : "No GST"} · Input credit {expense.is_gst_input_eligible ? "eligible" : "not eligible"}
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
                {!expense.can_archive && expense.archive_block_reason ? (
                    <p className="kb-muted" style={{ marginTop: 12 }}>
                        {expense.archive_block_reason}
                    </p>
                ) : null}
            </section>

            <RecordHistoryCard title="Expense Activity" entries={history} />
        </div>
    );
}
