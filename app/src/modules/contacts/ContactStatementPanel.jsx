import { useEffect, useMemo, useState } from "react";
import { getContactStatement } from "./api";
import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const buildDefaultFilters = () => {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 90);

    return {
        from: from.toISOString().slice(0, 10),
        to: to.toISOString().slice(0, 10),
    };
};

export default function ContactStatementPanel({ contact }) {
    const [filters, setFilters] = useState(buildDefaultFilters);
    const [state, setState] = useState({
        loading: true,
        error: "",
        statement: null,
    });

    useEffect(() => {
        if (!contact?.id) {
            return undefined;
        }

        let cancelled = false;
        (async () => {
            setState((prev) => ({ ...prev, loading: true, error: "" }));
            try {
                const statement = await getContactStatement(contact.id, filters);
                if (!cancelled) {
                    setState({
                        loading: false,
                        error: "",
                        statement,
                    });
                }
            } catch (error) {
                if (!cancelled) {
                    setState({
                        loading: false,
                        error: error?.message || "Unable to load the customer statement.",
                        statement: null,
                    });
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [contact?.id, filters]);

    const summary = state.statement?.summary || {};
    const entries = useMemo(() => state.statement?.entries || [], [state.statement]);
    const openInvoices = useMemo(() => state.statement?.open_invoices || [], [state.statement]);

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    return (
        <div className="space-y-4">
            <div className="contacts-filter-row">
                <div className="field">
                    <label>From</label>
                    <input type="date" value={filters.from} onChange={handleFilterChange("from")} />
                </div>
                <div className="field">
                    <label>To</label>
                    <input type="date" value={filters.to} onChange={handleFilterChange("to")} />
                </div>
            </div>

            <div className="contacts-summary-grid">
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Opening</span>
                    <strong>₹ {formatCurrency(summary.opening_balance || 0)}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Invoiced</span>
                    <strong>₹ {formatCurrency(summary.invoiced_total || 0)}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Debit Notes</span>
                    <strong>₹ {formatCurrency(summary.debit_notes_total || 0)}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Credit Notes</span>
                    <strong>₹ {formatCurrency(summary.credit_notes_total || 0)}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Payments</span>
                    <strong>₹ {formatCurrency(summary.payments_total || 0)}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Closing Balance</span>
                    <strong>₹ {formatCurrency(summary.closing_balance || 0)}</strong>
                </article>
            </div>

            {state.loading ? (
                <FeedbackState
                    title="Loading customer statement"
                    description="Gathering invoices, payments, and the current receivable balance for this customer."
                    tone="loading"
                />
            ) : state.error ? (
                <FeedbackState
                    title="Unable to load customer statement"
                    description={state.error}
                    tone="error"
                />
            ) : (
                <>
                    <section className="contacts-card">
                        <div className="contacts-card-header contacts-card-header--split">
                            <div>
                                <h3>Outstanding Invoices</h3>
                                <p>
                                    Open balance as of {state.statement?.to}. Overdue amount: ₹ {formatCurrency(summary.overdue_balance || 0)}.
                                </p>
                            </div>
                            <span className="kb-muted">
                                {summary.open_invoice_count || 0} open invoice{Number(summary.open_invoice_count || 0) === 1 ? "" : "s"}
                            </span>
                        </div>
                        {openInvoices.length ? (
                            <div className="ui-table-wrap">
                                <table className="kb-data-table">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Date</th>
                                            <th>Due</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Adjusted</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {openInvoices.map((invoice) => (
                                            <tr key={invoice.id}>
                                                <td>{invoice.invoice_number}</td>
                                                <td>{invoice.date}</td>
                                                <td>{invoice.due_date}</td>
                                                <td>{invoice.status}</td>
                                                <td>₹ {formatCurrency(invoice.total)}</td>
                                                <td>₹ {formatCurrency(invoice.adjusted_total ?? invoice.total)}</td>
                                                <td>₹ {formatCurrency(invoice.paid_amount)}</td>
                                                <td>₹ {formatCurrency(invoice.balance_due)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <FeedbackState
                                title="No outstanding invoices"
                                description="This customer has no unpaid invoices in the selected statement range."
                                tone="empty"
                            />
                        )}
                    </section>

                    <section className="contacts-card">
                        <div className="contacts-card-header">
                            <div>
                                <h3>Statement Activity</h3>
                                <p>Invoices are listed as debits and receipts are listed as credits, with a running balance.</p>
                            </div>
                        </div>
                        {entries.length ? (
                            <div className="ui-table-wrap">
                                <table className="kb-data-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Description</th>
                                            <th>Debit</th>
                                            <th>Credit</th>
                                            <th>Running Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {entries.map((entry) => (
                                            <tr key={`${entry.entry_type}-${entry.id}`}>
                                                <td>{entry.date}</td>
                                                <td>{entry.entry_type}</td>
                                                <td>{entry.reference}</td>
                                                <td>{entry.description}</td>
                                                <td>{entry.debit ? `₹ ${formatCurrency(entry.debit)}` : "—"}</td>
                                                <td>{entry.credit ? `₹ ${formatCurrency(entry.credit)}` : "—"}</td>
                                                <td>₹ {formatCurrency(entry.running_balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <FeedbackState
                                title="No statement activity"
                                description="There were no invoices or payments for this customer in the selected date range."
                                tone="empty"
                            />
                        )}
                    </section>
                </>
            )}
        </div>
    );
}
