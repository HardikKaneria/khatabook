import { useMemo, useState } from "react";

const toOptions = (accounts = []) => accounts.map((acct) => ({ id: acct.id, name: acct.name }));

export default function InvoicePaymentForm({
    invoice,
    moneyAccounts = [],
    incomeAccounts = [],
    onSubmit,
    onCancel,
}) {
    const today = new Date().toISOString().slice(0, 10);
    const remaining = Math.max(0, (invoice?.total || 0) - (invoice?.paid_amount || 0));
    const [form, setForm] = useState({
        date: today,
        amount: remaining || "",
        to_account_id: moneyAccounts[0]?.id || "",
        income_account_id: incomeAccounts[0]?.id || "",
        description: `Payment for ${invoice?.invoice_number || "invoice"}`,
        reference: "",
    });

    const moneyOptions = useMemo(() => toOptions(moneyAccounts), [moneyAccounts]);
    const incomeOptions = useMemo(() => toOptions(incomeAccounts), [incomeAccounts]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            date: form.date,
            amount: parseFloat(form.amount) || 0,
            to_account_id: Number(form.to_account_id),
            income_account_id: Number(form.income_account_id),
            description: form.description,
            reference: form.reference,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div>
                <label className="kb-muted">Date</label>
                <input type="date" className="kb-input" value={form.date} onChange={handleChange("date")} />
            </div>
            <div>
                <label className="kb-muted">Amount</label>
                <input type="number" step="0.01" className="kb-input" value={form.amount} onChange={handleChange("amount")} required />
                {remaining ? (
                    <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                        Remaining due: ₹ {remaining.toLocaleString()}
                    </p>
                ) : null}
            </div>
            <div>
                <label className="kb-muted">Deposit To (Bank / Cash)</label>
                <select className="kb-input" value={form.to_account_id} onChange={handleChange("to_account_id")}>
                    {moneyOptions.map((acct) => (
                        <option key={acct.id} value={acct.id}>
                            {acct.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">Income Account</label>
                <select className="kb-input" value={form.income_account_id} onChange={handleChange("income_account_id")}>
                    {incomeOptions.map((acct) => (
                        <option key={acct.id} value={acct.id}>
                            {acct.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">Description</label>
                <input className="kb-input" value={form.description} onChange={handleChange("description")} />
            </div>
            <div>
                <label className="kb-muted">Reference</label>
                <input className="kb-input" value={form.reference} onChange={handleChange("reference")} />
            </div>
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    Record Payment
                </button>
            </div>
        </form>
    );
}
