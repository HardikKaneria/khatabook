import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const today = () => new Date().toISOString().slice(0, 10);

const getDueState = (invoice) => {
    const status = String(invoice?.status || "").toUpperCase();
    const refundedAmount = Number(invoice?.refunded_amount || 0);
    const balanceDue = Number(invoice?.balance_due || 0);
    const dueDate = String(invoice?.due_date || "");

    if (status === "VOID" && refundedAmount > 0) {
        return { label: "Refunded", tone: "muted" };
    }
    if (status === "VOID") {
        return { label: "Voided", tone: "muted" };
    }
    if (balanceDue <= 0) {
        return { label: "Settled", tone: "success" };
    }
    if (!dueDate) {
        return { label: "No due date", tone: "muted" };
    }

    if (dueDate < today()) {
        return { label: "Overdue", tone: "danger" };
    }
    if (dueDate === today()) {
        return { label: "Due today", tone: "warning" };
    }

    return { label: `Due ${dueDate}`, tone: "info" };
};

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
            <table className="kb-data-table kb-invoice-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Schedule</th>
                        <th>Collections</th>
                        <th>Status</th>
                        <th className="kb-data-table__actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {invoices.map((invoice) => {
                        const dueState = getDueState(invoice);

                        return (
                            <tr key={invoice.id}>
                                <td>
                                    <div className="kb-invoice-cell">
                                        <strong className="kb-data-table__primary">{invoice.invoice_number}</strong>
                                        <span className="kb-invoice-cell__meta">Issued {invoice.date || "—"}</span>
                                    </div>
                                </td>
                                <td>
                                    <div className="kb-invoice-cell">
                                        <strong className="kb-data-table__primary">{invoice.customer_name || "Walk-in customer"}</strong>
                                        {invoice.contact?.email || invoice.contact?.phone ? (
                                            <span className="kb-invoice-cell__meta">
                                                {[invoice.contact?.email, invoice.contact?.phone].filter(Boolean).join(" · ")}
                                            </span>
                                        ) : null}
                                    </div>
                                </td>
                                <td>
                                    <div className="kb-invoice-cell">
                                        <span className="kb-data-table__primary">{invoice.due_date || "No due date"}</span>
                                        <span className={`kb-invoice-chip kb-invoice-chip--${dueState.tone}`}>
                                            {dueState.label}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div className="kb-invoice-cell">
                                        <strong className="kb-data-table__primary">₹ {formatCurrency(invoice.adjusted_total ?? invoice.total)}</strong>
                                        <span className="kb-invoice-cell__meta">
                                            Due ₹ {formatCurrency(invoice.balance_due || 0)} · Net paid ₹ {formatCurrency(invoice.net_paid_amount ?? invoice.paid_amount)}
                                        </span>
                                        {Number(invoice.refunded_amount || 0) > 0 ? (
                                            <span className="kb-invoice-cell__meta">Refunded ₹ {formatCurrency(invoice.refunded_amount)}</span>
                                        ) : null}
                                        {Number(invoice.adjusted_total ?? invoice.total) !== Number(invoice.total ?? 0) ? (
                                            <span className="kb-invoice-cell__meta">Base total ₹ {formatCurrency(invoice.total)}</span>
                                        ) : null}
                                    </div>
                                </td>
                                <td>
                                    <div className="kb-invoice-status-stack">
                                        <span className={`kb-invoice-chip kb-invoice-chip--${String(invoice.status || "").toLowerCase()}`}>
                                            {invoice.status}
                                        </span>
                                        {Number(invoice.refunded_amount || 0) > 0 ? (
                                            <span className="kb-invoice-chip kb-invoice-chip--muted">Refund tracked</span>
                                        ) : Number(invoice.balance_due || 0) > 0 ? (
                                            <span className="kb-invoice-chip kb-invoice-chip--info">Collection open</span>
                                        ) : (
                                            <span className="kb-invoice-chip kb-invoice-chip--success">No balance due</span>
                                        )}
                                    </div>
                                </td>
                                <td className="kb-data-table__actions">
                                    <button
                                        className="kb-btn kb-btn--ghost kb-btn--small"
                                        onClick={() => onSelectInvoice?.(invoice.id)}
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
