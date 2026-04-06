import RecordHistoryCard from "../../components/ui/RecordHistoryCard.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function InvoiceDetail({
    invoice,
    onOpenNoteModal,
    onOpenPromiseModal,
    onPromiseStatusChange,
    onGenerateRecurring,
}) {
    if (!invoice) {
        return <p>No invoice data.</p>;
    }

    const items = invoice.items || [];
    const payments = invoice.payments || [];
    const notes = invoice.adjustments || [];
    const promises = invoice.promises || [];
    const history = invoice.history || [];
    const hasAdjustments = Number(invoice.credit_total || 0) > 0 || Number(invoice.debit_total || 0) > 0;

    return (
        <div className="space-y-4">
            <section className="kb-card" style={{ padding: 24 }}>
                <div className="flex flex-wrap justify-between gap-3">
                    <div>
                        <h2 className="kb-h2" style={{ margin: 0 }}>
                            {invoice.invoice_number}
                        </h2>
                        <p className="kb-muted" style={{ marginBottom: 8 }}>
                            {invoice.customer_name}
                        </p>
                        <p className="kb-muted">
                            Date: {invoice.date} · Due: {invoice.due_date || "—"}
                        </p>
                    </div>
                    <div style={{ textAlign: "right" }}>
                        <span
                            style={{
                                display: "inline-block",
                                padding: "4px 12px",
                                borderRadius: 999,
                                background: "var(--kb-color-gray-100)",
                                fontWeight: 600,
                            }}
                        >
                            {invoice.status}
                        </span>
                        <p style={{ fontSize: 24, fontWeight: 700, marginTop: 8 }}>
                            ₹ {formatCurrency(invoice.adjusted_total ?? invoice.total)}
                        </p>
                        <p className="kb-muted" style={{ margin: 0 }}>
                            Paid: ₹ {formatCurrency(invoice.paid_amount)} · Due: ₹{" "}
                            {formatCurrency(invoice.balance_due || 0)}
                        </p>
                    </div>
                </div>
                {hasAdjustments ? (
                    <p className="kb-muted" style={{ marginTop: 12 }}>
                        Base total ₹ {formatCurrency(invoice.total)} · Debit notes ₹ {formatCurrency(invoice.debit_total || 0)} · Credit notes ₹ {formatCurrency(invoice.credit_total || 0)}
                    </p>
                ) : null}
                {invoice.recurring_profile ? (
                    <p className="kb-muted" style={{ marginTop: 12 }}>
                        Generated from recurring plan <strong>{invoice.recurring_profile.profile_name}</strong>.
                    </p>
                ) : null}
                {invoice.source_recurring_profile ? (
                    <div className="kb-card" style={{ marginTop: 16, padding: 16, background: "var(--kb-color-gray-50)" }}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <strong>{invoice.source_recurring_profile.profile_name}</strong>
                                <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                                    Next run {invoice.source_recurring_profile.next_run_date || "—"} · {invoice.source_recurring_profile.interval_count} x {String(invoice.source_recurring_profile.frequency || "").toLowerCase()}
                                </p>
                            </div>
                            {invoice.source_recurring_profile.can_generate_now ? (
                                <button type="button" className="kb-btn kb-btn--secondary kb-btn--small" onClick={() => onGenerateRecurring?.(invoice.source_recurring_profile)}>
                                    Generate Due Invoice
                                </button>
                            ) : null}
                        </div>
                    </div>
                ) : null}
                {invoice.notes ? (
                    <p style={{ marginTop: 16 }}>
                        <strong>Notes:</strong> {invoice.notes}
                    </p>
                ) : null}
            </section>

            <section className="kb-card" style={{ padding: 24 }}>
                <h3 className="kb-h3" style={{ marginBottom: 12 }}>
                    Items
                </h3>
                {items.length ? (
                    <div className="overflow-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Tax %</th>
                                    <th>Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id || item.description} className="border-t">
                                        <td>{item.description}</td>
                                        <td>{item.quantity}</td>
                                        <td>₹ {formatCurrency(item.unit_price)}</td>
                                        <td>{item.tax_rate}</td>
                                        <td>₹ {formatCurrency(item.line_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p>No items.</p>
                )}
                <div className="flex flex-col items-end gap-1" style={{ marginTop: 16 }}>
                    <p>Subtotal: ₹ {formatCurrency(invoice.subtotal)}</p>
                    <p>Tax: ₹ {formatCurrency(invoice.tax_total)}</p>
                    {hasAdjustments ? (
                        <>
                            <p>Debit Notes: ₹ {formatCurrency(invoice.debit_total || 0)}</p>
                            <p>Credit Notes: ₹ {formatCurrency(invoice.credit_total || 0)}</p>
                        </>
                    ) : null}
                    <p style={{ fontWeight: 700 }}>Total Due Position: ₹ {formatCurrency(invoice.adjusted_total ?? invoice.total)}</p>
                </div>
            </section>

            <section className="kb-card" style={{ padding: 24 }}>
                <h3 className="kb-h3" style={{ marginBottom: 12 }}>
                    Payments
                </h3>
                {payments.length ? (
                    <div className="space-y-2">
                        {payments.map((payment) => (
                            <div key={payment.id} style={{ display: "flex", justifyContent: "space-between", borderBottom: "1px solid var(--kb-color-border)", paddingBottom: 8 }}>
                                <div>
                                    <p style={{ margin: 0, fontWeight: 600 }}>
                                        ₹ {formatCurrency(payment.amount)}
                                    </p>
                                    <p className="kb-muted" style={{ margin: 0 }}>
                                        {payment.date} · {payment.description || payment.reference || "Payment"}
                                    </p>
                                </div>
                                <p className="kb-muted" style={{ margin: 0 }}>
                                    Journal #{payment.journal_id || "—"}
                                </p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p>No payments recorded.</p>
                )}
            </section>

            <section className="kb-card" style={{ padding: 24 }}>
                <div className="flex flex-wrap items-center justify-between gap-2" style={{ marginBottom: 12 }}>
                    <h3 className="kb-h3" style={{ margin: 0 }}>
                        Adjustments
                    </h3>
                    <div className="flex gap-2">
                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onOpenNoteModal?.("CREDIT")}>
                            Add Credit Note
                        </button>
                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onOpenNoteModal?.("DEBIT")}>
                            Add Debit Note
                        </button>
                    </div>
                </div>
                {notes.length ? (
                    <div className="space-y-2">
                        {notes.map((note) => (
                            <div key={note.id} style={{ display: "flex", justifyContent: "space-between", gap: 16, borderBottom: "1px solid var(--kb-color-border)", paddingBottom: 10 }}>
                                <div>
                                    <strong>{note.note_number}</strong>
                                    <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                                        {note.note_type} · {note.note_date}
                                    </p>
                                    {note.reason ? (
                                        <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                                            {note.reason}
                                        </p>
                                    ) : null}
                                </div>
                                <strong>
                                    {note.note_type === "CREDIT" ? "-" : "+"} ₹ {formatCurrency(note.amount)}
                                </strong>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p>No credit or debit notes recorded yet.</p>
                )}
            </section>

            <section className="kb-card" style={{ padding: 24 }}>
                <div className="flex flex-wrap items-center justify-between gap-2" style={{ marginBottom: 12 }}>
                    <div>
                        <h3 className="kb-h3" style={{ margin: 0 }}>
                            Promises To Pay
                        </h3>
                        <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                            Track customer payment commitments against the live balance due.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="kb-btn kb-btn--ghost kb-btn--small"
                        disabled={Number(invoice.balance_due || 0) <= 0}
                        onClick={() => onOpenPromiseModal?.()}
                    >
                        Record Promise
                    </button>
                </div>
                {promises.length ? (
                    <div className="space-y-2">
                        {promises.map((promise) => (
                            <div key={promise.id} style={{ borderBottom: "1px solid var(--kb-color-border)", paddingBottom: 10 }}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <strong>₹ {formatCurrency(promise.promised_amount)}</strong>
                                        <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                                            Promised by {promise.promised_date} · {promise.status}
                                        </p>
                                        {promise.notes ? (
                                            <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                                                {promise.notes}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap gap-2 justify-end">
                                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onOpenPromiseModal?.(promise)}>
                                            Edit
                                        </button>
                                        {promise.status === "OPEN" ? (
                                            <>
                                                <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onPromiseStatusChange?.(promise, "KEPT")}>
                                                    Mark Kept
                                                </button>
                                                <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onPromiseStatusChange?.(promise, "BROKEN")}>
                                                    Mark Broken
                                                </button>
                                                <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onPromiseStatusChange?.(promise, "CANCELLED")}>
                                                    Cancel
                                                </button>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p>No promises recorded for this invoice.</p>
                )}
            </section>

            <RecordHistoryCard title="Invoice Activity" entries={history} />
        </div>
    );
}
