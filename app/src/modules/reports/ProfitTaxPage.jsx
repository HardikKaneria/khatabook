import { useEffect, useMemo, useState } from "react";
import {
    getGstSummary,
    getMonthlyTrends,
    getProfitSummary,
    getReceivablesSummary,
    getTaxEstimate,
} from "./api";

const formatCurrency = (value) =>
    `₹ ${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const todayISO = () => new Date().toISOString().slice(0, 10);

const startOfMonth = () => {
    const d = new Date();
    d.setDate(1);
    return d.toISOString().slice(0, 10);
};

const startOfQuarter = () => {
    const d = new Date();
    const currentMonth = d.getMonth();
    const quarterStartMonth = currentMonth - (currentMonth % 3);
    d.setMonth(quarterStartMonth, 1);
    return d.toISOString().slice(0, 10);
};

const startOfFY = () => {
    const d = new Date();
    const year = d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1;
    const fy = new Date(year, 3, 1);
    return fy.toISOString().slice(0, 10);
};

const rangePresets = [
    { key: "month", label: "This Month", from: startOfMonth, to: todayISO },
    { key: "quarter", label: "This Quarter", from: startOfQuarter, to: todayISO },
    { key: "fy", label: "This FY", from: startOfFY, to: todayISO },
];

const statusLabels = {
    DRAFT: "Draft",
    SENT: "Sent",
    PARTIAL: "Partially Paid",
    PAID: "Paid",
    VOID: "Void",
};

const calcTrendMax = (months = []) =>
    months.reduce((max, month) => {
        const revenue = Number(month?.revenue_total ?? 0);
        const expense = Number(month?.expense_total ?? 0);
        return Math.max(max, revenue, expense);
    }, 0);

export default function ProfitTaxPage() {
    const [range, setRange] = useState({ from: startOfMonth(), to: todayISO() });
    const [loading, setLoading] = useState(true);
    const [profitSummary, setProfitSummary] = useState(null);
    const [gstSummary, setGstSummary] = useState(null);
    const [taxEstimate, setTaxEstimate] = useState(null);
    const [receivablesSummary, setReceivablesSummary] = useState(null);
    const [monthlyTrends, setMonthlyTrends] = useState(null);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        Promise.all([
            getProfitSummary(range),
            getGstSummary(range),
            getTaxEstimate(range),
            getReceivablesSummary({ ...range, as_of: todayISO() }),
            getMonthlyTrends(range),
        ])
            .then(([profit, gst, tax, receivables, trends]) => {
                if (cancelled) return;
                setProfitSummary(profit || null);
                setGstSummary(gst || null);
                setTaxEstimate(tax || null);
                setReceivablesSummary(receivables || null);
                setMonthlyTrends(trends || null);
            })
            .catch((err) => {
                if (!cancelled) {
                    setError(err?.message || "Failed to load reports.");
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [range]);

    const trendMax = useMemo(
        () => Math.max(calcTrendMax(monthlyTrends?.months), 1),
        [monthlyTrends]
    );

    const handlePreset = (preset) => {
        setRange({ from: preset.from(), to: preset.to() });
    };

    const renderAccountTable = (title, rows = []) => (
        <div className="kb-card" style={{ padding: 24 }}>
            <h3 className="kb-h3" style={{ marginTop: 0 }}>{title}</h3>
            {rows && rows.length ? (
                <table className="kb-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th style={{ textAlign: "right" }}>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.account_id}>
                                <td>{row.name}</td>
                                <td style={{ textAlign: "right" }}>{formatCurrency(row.amount)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <p className="kb-muted" style={{ margin: 0 }}>No data for this period.</p>
            )}
        </div>
    );

    const summaryCards = () => {
        if (!profitSummary || !taxEstimate || !gstSummary) return null;

        const cards = [
            { label: "Total Income", value: formatCurrency(profitSummary.income?.total ?? profitSummary.income_total ?? 0) },
            { label: "Total Expense", value: formatCurrency(profitSummary.expense?.total ?? profitSummary.expense_total ?? 0) },
            { label: "Profit", value: formatCurrency(profitSummary.profit ?? 0) },
            { label: "Estimated Income Tax", value: formatCurrency(taxEstimate.estimated_income_tax ?? 0) },
            { label: "Net GST Payable", value: formatCurrency(gstSummary.net_gst_payable ?? 0) },
        ];

        return (
            <div className="kb-grid-cards" style={{ marginBottom: 20 }}>
                {cards.map((card) => (
                    <div key={card.label} className="kb-card" style={{ padding: 20 }}>
                        <p className="kb-muted" style={{ marginBottom: 8 }}>{card.label}</p>
                        <p style={{ fontSize: 20, fontWeight: 600 }}>{card.value}</p>
                    </div>
                ))}
            </div>
        );
    };

    const receivableCards = () => {
        if (!receivablesSummary) return null;

        const cards = [
            { label: "Outstanding Receivables", value: formatCurrency(receivablesSummary.outstanding_amount), detail: `${receivablesSummary.open_invoice_count ?? 0} open invoices` },
            { label: "Overdue Amount", value: formatCurrency(receivablesSummary.overdue_amount), detail: `${receivablesSummary.overdue_count ?? 0} overdue invoices` },
            { label: "Average Days Overdue", value: `${Number(receivablesSummary.average_days_overdue ?? 0).toFixed(1)} days`, detail: "Across overdue invoices only" },
        ];

        return (
            <div className="reports-summary-grid">
                {cards.map((card) => (
                    <article key={card.label} className="reports-summary-card">
                        <span className="reports-summary-label">{card.label}</span>
                        <strong>{card.value}</strong>
                        <p>{card.detail}</p>
                    </article>
                ))}
            </div>
        );
    };

    const renderStatusTable = (rows = []) => {
        const visibleRows = rows.filter((row) => Number(row?.count ?? 0) > 0 || Number(row?.amount ?? 0) > 0);

        return (
            <section className="reports-card">
                <div className="reports-card-header">
                    <div>
                        <h3>Invoice Status Mix</h3>
                        <p>Invoices issued in the selected period, grouped by current status.</p>
                    </div>
                </div>
                {visibleRows.length ? (
                    <table className="kb-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th style={{ textAlign: "right" }}>Invoices</th>
                                <th style={{ textAlign: "right" }}>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibleRows.map((row) => (
                                <tr key={row.status}>
                                    <td>{statusLabels[row.status] || row.status}</td>
                                    <td style={{ textAlign: "right" }}>{row.count}</td>
                                    <td style={{ textAlign: "right" }}>{formatCurrency(row.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="kb-muted" style={{ margin: 0 }}>No invoice activity in the selected period.</p>
                )}
            </section>
        );
    };

    const renderAgingBuckets = (rows = []) => (
        <section className="reports-card">
            <div className="reports-card-header">
                <div>
                    <h3>Receivables Aging</h3>
                    <p>Open invoice balances as of {receivablesSummary?.as_of || todayISO()}.</p>
                </div>
            </div>
            {rows.length ? (
                <div className="reports-aging-list">
                    {rows.map((row) => (
                        <div key={row.bucket} className="reports-aging-row">
                            <div>
                                <strong>{row.label}</strong>
                                <p>{row.count} invoice{row.count === 1 ? "" : "s"}</p>
                            </div>
                            <strong>{formatCurrency(row.amount)}</strong>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="kb-muted" style={{ margin: 0 }}>No open receivables right now.</p>
            )}
        </section>
    );

    const renderTopCustomers = (rows = []) => (
        <section className="reports-card">
            <div className="reports-card-header">
                <div>
                    <h3>Top Customer Balances</h3>
                    <p>Customers with the highest outstanding amount right now.</p>
                </div>
            </div>
            {rows.length ? (
                <div className="reports-customer-list">
                    {rows.map((row) => (
                        <article key={`${row.label}-${row.email || "none"}`} className="reports-customer-row">
                            <div>
                                <strong>{row.label}</strong>
                                <p>
                                    {row.invoice_count} open invoice{row.invoice_count === 1 ? "" : "s"}
                                    {row.email ? ` · ${row.email}` : ""}
                                </p>
                            </div>
                            <div className="reports-customer-meta">
                                <strong>{formatCurrency(row.outstanding_amount)}</strong>
                                <span>
                                    {Number(row.overdue_amount ?? 0) > 0
                                        ? `${formatCurrency(row.overdue_amount)} overdue`
                                        : "Fully current"}
                                </span>
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <p className="kb-muted" style={{ margin: 0 }}>No customer balances to review.</p>
            )}
        </section>
    );

    const renderMonthlyTrends = (months = []) => (
        <section className="reports-card">
            <div className="reports-card-header">
                <div>
                    <h3>Monthly Billing &amp; Spend Trend</h3>
                    <p>Invoice totals and posted expenses across the selected period.</p>
                </div>
            </div>
            {months.length ? (
                <div className="reports-trend-list">
                    {months.map((month) => {
                        const revenueWidth = `${Math.max((Number(month.revenue_total ?? 0) / trendMax) * 100, 0)}%`;
                        const expenseWidth = `${Math.max((Number(month.expense_total ?? 0) / trendMax) * 100, 0)}%`;
                        return (
                            <article key={month.month} className="reports-trend-row">
                                <div className="reports-trend-head">
                                    <strong>{month.label}</strong>
                                    <span>{month.invoice_count} invoice{month.invoice_count === 1 ? "" : "s"} · {month.expense_count} expense{month.expense_count === 1 ? "" : "s"}</span>
                                </div>
                                <div className="reports-trend-bars">
                                    <div>
                                        <div className="reports-trend-label-row">
                                            <span>Revenue</span>
                                            <strong>{formatCurrency(month.revenue_total)}</strong>
                                        </div>
                                        <div className="reports-trend-track">
                                            <div className="reports-trend-fill reports-trend-fill--revenue" style={{ width: revenueWidth }} />
                                        </div>
                                    </div>
                                    <div>
                                        <div className="reports-trend-label-row">
                                            <span>Expense</span>
                                            <strong>{formatCurrency(month.expense_total)}</strong>
                                        </div>
                                        <div className="reports-trend-track">
                                            <div className="reports-trend-fill reports-trend-fill--expense" style={{ width: expenseWidth }} />
                                        </div>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            ) : (
                <p className="kb-muted" style={{ margin: 0 }}>No invoice or expense movement in this period.</p>
            )}
        </section>
    );

    return (
        <div className="reports-page">
            <header className="reports-header">
                <div>
                    <p className="reports-eyebrow">Financial health</p>
                    <h1>Profit &amp; Tax</h1>
                    <p className="reports-subtitle">Track profitability, current receivables, invoice mix, and monthly billing momentum.</p>
                </div>
            </header>

            <section className="reports-card">
                <div className="reports-card-header">
                    <div>
                        <h3>Date Range</h3>
                        <p>Use quick presets or pick a custom period.</p>
                    </div>
                </div>
                <div className="reports-filter-row">
                    {rangePresets.map((preset) => (
                        <button key={preset.key} className="reports-ghost-btn" onClick={() => handlePreset(preset)}>
                            {preset.label}
                        </button>
                    ))}
                    <div className="field">
                        <label>From</label>
                        <input
                            type="date"
                            value={range.from}
                            onChange={(e) => setRange((prev) => ({ ...prev, from: e.target.value }))}
                        />
                    </div>
                    <div className="field">
                        <label>To</label>
                        <input
                            type="date"
                            value={range.to}
                            onChange={(e) => setRange((prev) => ({ ...prev, to: e.target.value }))}
                        />
                    </div>
                </div>
            </section>

            {error ? (
                <section className="reports-card">
                    <p className="text-red-600">{error}</p>
                </section>
            ) : null}

            {loading ? (
                <section className="reports-card">
                    <p>Loading financial reports…</p>
                </section>
            ) : (
                <>
                    {summaryCards()}

                    <section className="reports-card">
                        <div className="reports-card-header">
                            <div>
                                <h3>Current Receivables Snapshot</h3>
                                <p>Open customer balances as of {receivablesSummary?.as_of || todayISO()}.</p>
                            </div>
                        </div>
                        {receivablesSummary ? (
                            <>
                                {receivableCards()}
                                <div className="reports-grid-2">
                                    {renderAgingBuckets(receivablesSummary.aging_buckets || [])}
                                    {renderTopCustomers(receivablesSummary.top_customers || [])}
                                </div>
                            </>
                        ) : (
                            <p className="kb-muted" style={{ margin: 0 }}>Receivables data is not available right now.</p>
                        )}
                    </section>

                    <div className="reports-grid-2">
                        {renderAccountTable("Income by Account", profitSummary?.income?.by_account || profitSummary?.income_by_account)}
                        {renderAccountTable("Expense by Account", profitSummary?.expense?.by_account || profitSummary?.expense_by_account)}
                    </div>

                    <div className="reports-grid-2">
                        {renderStatusTable(receivablesSummary?.invoice_status || [])}
                        {renderMonthlyTrends(monthlyTrends?.months || [])}
                    </div>

                    <section className="reports-card">
                        <h3 className="reports-card-title">GST Summary</h3>
                        {gstSummary ? (
                            <div className="reports-grid-3">
                                <div>
                                    <p className="reports-label">Output Tax</p>
                                    <p className="reports-value">{formatCurrency(gstSummary.output_tax)}</p>
                                </div>
                                <div>
                                    <p className="reports-label">Input Tax</p>
                                    <p className="reports-value">{formatCurrency(gstSummary.input_tax)}</p>
                                </div>
                                <div>
                                    <p className="reports-label">Net GST Payable</p>
                                    <p className="reports-value">{formatCurrency(gstSummary.net_gst_payable)}</p>
                                </div>
                            </div>
                        ) : (
                            <p className="kb-muted">No GST data for this period.</p>
                        )}
                    </section>

                    <section className="reports-card reports-note">
                        <p>
                            Profit and tax values are estimate-driven views on current journals and document data. Use them for operational control, then
                            reconcile with your accountant before filing returns or closing books.
                        </p>
                    </section>
                </>
            )}
        </div>
    );
}
