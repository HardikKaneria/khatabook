import RecordHistoryCard from "../../components/ui/RecordHistoryCard.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function InvoiceDetail({ invoice }) {
    if (!invoice) {
        return <p>No invoice data.</p>;
    }

    const items = invoice.items || [];
    const payments = invoice.payments || [];
    const history = invoice.history || [];

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
                            ₹ {formatCurrency(invoice.total)}
                        </p>
                        <p className="kb-muted" style={{ margin: 0 }}>
                            Paid: ₹ {formatCurrency(invoice.paid_amount)} · Due: ₹{" "}
                            {formatCurrency((invoice.total || 0) - (invoice.paid_amount || 0))}
                        </p>
                    </div>
                </div>
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
                    <p style={{ fontWeight: 700 }}>Total: ₹ {formatCurrency(invoice.total)}</p>
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

            <RecordHistoryCard title="Invoice Activity" entries={history} />
        </div>
    );
}
