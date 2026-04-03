import { useEffect, useState } from "react";
import { getProfitSummary, getGstSummary, getTaxEstimate } from "./api";

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

export default function ProfitTaxPage() {
    const [range, setRange] = useState({ from: startOfMonth(), to: todayISO() });
    const [loading, setLoading] = useState(true);
    const [profitSummary, setProfitSummary] = useState(null);
    const [gstSummary, setGstSummary] = useState(null);
    const [taxEstimate, setTaxEstimate] = useState(null);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        Promise.all([
            getProfitSummary(range),
            getGstSummary(range),
            getTaxEstimate(range),
        ])
            .then(([profit, gst, tax]) => {
                if (cancelled) return;
                setProfitSummary(profit || null);
                setGstSummary(gst || null);
                setTaxEstimate(tax || null);
            })
            .catch((err) => {
                if (!cancelled) setError(err?.message || "Failed to load reports.");
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [range]);

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

    return (
        <div className="reports-page">
            <header className="reports-header">
                <div>
                    <p className="reports-eyebrow">Financial health</p>
                    <h1>Profit &amp; Tax</h1>
                    <p className="reports-subtitle">Understand your income, expenses, and tax obligations.</p>
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
                    <p>Loading profit and tax summary…</p>
                </section>
            ) : (
                <>
                    {summaryCards()}
                    <div className="reports-grid-2">
                        {renderAccountTable("Income by Account", profitSummary?.income?.by_account || profitSummary?.income_by_account)}
                        {renderAccountTable("Expense by Account", profitSummary?.expense?.by_account || profitSummary?.expense_by_account)}
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
                            These numbers are estimates based on entries recorded in Vyavhar. Work with your Chartered Accountant for
                            final filings. Adjustments such as depreciation, personal drawings, or non-GST-eligible expenses may alter the
                            actual tax payable.
                        </p>
                    </section>
                </>
            )}
        </div>
    );
}
