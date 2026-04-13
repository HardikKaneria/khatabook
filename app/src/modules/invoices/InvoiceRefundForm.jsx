import { useEffect, useState } from "react";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const buildInitialState = (invoice, moneyAccounts, incomeAccounts) => ({
    date: new Date().toISOString().slice(0, 10),
    reason: "",
    payout_account_id: moneyAccounts[0]?.id || "",
    income_account_id: incomeAccounts[0]?.id || "",
    amount: Number(invoice?.refundable_amount || 0),
});

export default function InvoiceRefundForm({
    invoice,
    moneyAccounts = [],
    incomeAccounts = [],
    onSubmit,
    onCancel,
    submitting = false,
}) {
    const [form, setForm] = useState(() => buildInitialState(invoice, moneyAccounts, incomeAccounts));

    useEffect(() => {
        setForm(buildInitialState(invoice, moneyAccounts, incomeAccounts));
    }, [invoice, moneyAccounts, incomeAccounts]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({
            ...prev,
            [key]: event.target.value,
        }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            date: form.date,
            reason: form.reason.trim(),
            payout_account_id: Number(form.payout_account_id || 0),
            income_account_id: Number(form.income_account_id || 0),
        });
    };

    return (
        <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="kb-card" style={{ padding: 16, background: "var(--kb-color-gray-50)" }}>
                <p style={{ margin: 0, fontWeight: 700 }}>
                    This will void invoice {invoice?.invoice_number || "—"} and record a full customer refund of ₹ {formatCurrency(form.amount)}.
                </p>
                <p className="kb-muted" style={{ margin: "8px 0 0" }}>
                    The original payment history is kept. A separate refund journal entry will be posted for traceability.
                </p>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Refund Date</label>
                    <input type="date" className="kb-input" value={form.date} onChange={handleChange("date")} required />
                </div>
                <div>
                    <label className="kb-muted">Refund Amount</label>
                    <input className="kb-input" value={`₹ ${formatCurrency(form.amount)}`} readOnly />
                </div>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Payout Account</label>
                    <select className="kb-input" value={form.payout_account_id} onChange={handleChange("payout_account_id")} required>
                        <option value="">Select payout account</option>
                        {moneyAccounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="kb-muted">Income Account To Reverse</label>
                    <select className="kb-input" value={form.income_account_id} onChange={handleChange("income_account_id")} required>
                        <option value="">Select income account</option>
                        {incomeAccounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div>
                <label className="kb-muted">Refund Reason</label>
                <textarea
                    className="kb-input"
                    rows={3}
                    value={form.reason}
                    onChange={handleChange("reason")}
                    placeholder="Why is this paid invoice being cancelled and refunded?"
                    required
                />
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel} disabled={submitting}>
                    Keep Invoice
                </button>
                <button type="submit" className="kb-btn kb-btn--primary" disabled={submitting}>
                    {submitting ? "Refunding…" : "Cancel & Refund"}
                </button>
            </div>
        </form>
    );
}
