import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function InvoiceList({ invoices = [], loading, error, onSelectInvoice }) {
    if (loading) {
        return <FeedbackState title="Loading invoices" description="Fetching the current invoice list." tone="loading" />;
    }

    if (error) {
        return <FeedbackState title="Unable to load invoices" description={error.message} tone="error" />;
    }

    if (!invoices.length) {
        return <FeedbackState title="No invoices yet" description="Create the first invoice to start tracking sales and collections." tone="empty" />;
    }

    return (
        <div className="ui-table-wrap">
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
                            <td>
                                ₹ {formatCurrency(invoice.adjusted_total ?? invoice.total)}
                                {Number(invoice.adjusted_total ?? invoice.total) !== Number(invoice.total ?? 0) ? (
                                    <div className="kb-muted" style={{ fontSize: 12 }}>
                                        Base ₹ {formatCurrency(invoice.total)}
                                    </div>
                                ) : null}
                            </td>
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
