const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function InvoiceList({ invoices = [], loading, error, onSelectInvoice }) {
    if (loading) {
        return <p className="kb-muted">Loading invoices…</p>;
    }

    if (error) {
        return <p className="text-red-600">{error.message}</p>;
    }

    if (!invoices.length) {
        return <p>No invoices yet.</p>;
    }

    return (
        <div className="overflow-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-gray-500">
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Due</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Paid</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {invoices.map((invoice) => (
                        <tr key={invoice.id} className="border-t">
                            <td>{invoice.invoice_number}</td>
                            <td>{invoice.customer_name}</td>
                            <td>{invoice.date}</td>
                            <td>{invoice.due_date || "—"}</td>
                            <td>₹ {formatCurrency(invoice.total)}</td>
                            <td>
                                <span
                                    style={{
                                        padding: "2px 8px",
                                        borderRadius: 999,
                                        background: "var(--kb-color-gray-100)",
                                        fontSize: 12,
                                        fontWeight: 600,
                                    }}
                                >
                                    {invoice.status}
                                </span>
                            </td>
                            <td>₹ {formatCurrency(invoice.paid_amount)}</td>
                            <td>
                                <button
                                    style={{
                                        background: "none",
                                        border: "none",
                                        color: "var(--kb-color-primary)",
                                        cursor: "pointer",
                                        fontWeight: 600,
                                    }}
                                    onClick={() => onSelectInvoice?.(invoice.id)}
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
