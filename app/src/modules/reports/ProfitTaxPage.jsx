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
            <div className="kb-grid-cards">
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
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="kb-h2" style={{ marginBottom: 4 }}>Profit &amp; Tax</h2>
                    <p className="kb-muted" style={{ margin: 0 }}>Understand your income, expenses, and tax obligations.</p>
                </div>
            </div>

            <div className="kb-card" style={{ padding: 16 }}>
                <div className="flex flex-wrap gap-3">
                    {rangePresets.map((preset) => (
                        <button
                            key={preset.key}
                            className="kb-btn kb-btn--ghost"
                            onClick={() => handlePreset(preset)}
                        >
                            {preset.label}
                        </button>
                    ))}
                    <div style={{ flexGrow: 1 }} />
                    <div>
                        <label className="kb-muted">From</label>
                        <input
                            type="date"
                            className="kb-input"
                            value={range.from}
                            onChange={(e) => setRange((prev) => ({ ...prev, from: e.target.value }))}
                        />
                    </div>
                    <div>
                        <label className="kb-muted">To</label>
                        <input
                            type="date"
                            className="kb-input"
                            value={range.to}
                            onChange={(e) => setRange((prev) => ({ ...prev, to: e.target.value }))}
                        />
                    </div>
                </div>
            </div>

            {error ? (
                <div className="kb-card" style={{ padding: 24 }}>
                    <p className="text-red-600">{error}</p>
                </div>
            ) : null}

            {loading ? (
                <div className="kb-card" style={{ padding: 24 }}>
                    <p>Loading profit and tax summary…</p>
                </div>
            ) : (
                <>
                    {summaryCards()}
                    <div className="kb-grid-2">
                        {renderAccountTable("Income by Account", profitSummary?.income?.by_account || profitSummary?.income_by_account)}
                        {renderAccountTable("Expense by Account", profitSummary?.expense?.by_account || profitSummary?.expense_by_account)}
                    </div>
                    <div className="kb-card" style={{ padding: 24 }}>
                        <h3 className="kb-h3" style={{ marginTop: 0 }}>GST Summary</h3>
                        {gstSummary ? (
                            <div className="kb-grid-3">
                                <div>
                                    <p className="kb-muted">Output Tax</p>
                                    <p style={{ fontWeight: 600 }}>{formatCurrency(gstSummary.output_tax)}</p>
                                </div>
                                <div>
                                    <p className="kb-muted">Input Tax</p>
                                    <p style={{ fontWeight: 600 }}>{formatCurrency(gstSummary.input_tax)}</p>
                                </div>
                                <div>
                                    <p className="kb-muted">Net GST Payable</p>
                                    <p style={{ fontWeight: 600 }}>{formatCurrency(gstSummary.net_gst_payable)}</p>
                                </div>
                            </div>
                        ) : (
                            <p className="kb-muted">No GST data for this period.</p>
                        )}
                    </div>
                    <div className="kb-card" style={{ padding: 20 }}>
                        <p className="kb-muted" style={{ marginBottom: 4 }}>
                            These numbers are estimates based on entries recorded in Vyavhar.
                        </p>
                        <p className="kb-muted" style={{ margin: 0 }}>
                            Work with your Chartered Accountant for final filings. Adjustments such as depreciation, personal drawings,
                            or non-GST-eligible expenses may alter the actual tax payable.
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}
