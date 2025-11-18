import { useState } from "react";

const formatAccounts = (accounts = []) => accounts.map((acct) => ({ id: acct.id, name: acct.name }));

export default function MoneyInForm({ onSubmit, onCancel, moneyAccounts = [], otherAccounts = [], defaultToAccountId }) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({
        date: today,
        amount: "",
        to_account_id: defaultToAccountId || moneyAccounts[0]?.id || "",
        from_account_id: otherAccounts[0]?.id || "",
        description: "",
        reference: "",
    });

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            date: form.date,
            amount: parseFloat(form.amount) || 0,
            to_account_id: Number(form.to_account_id),
            from_account_id: Number(form.from_account_id),
            description: form.description,
            reference: form.reference,
        });
    };

    const moneyOptions = formatAccounts(moneyAccounts);
    const otherOptions = formatAccounts(otherAccounts);

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div>
                <label className="kb-muted">Date</label>
                <input type="date" className="kb-input" value={form.date} onChange={handleChange("date")} />
            </div>
            <div>
                <label className="kb-muted">Amount</label>
                <input type="number" step="0.01" className="kb-input" value={form.amount} onChange={handleChange("amount")} required />
            </div>
            <div>
                <label className="kb-muted">To Account (Bank / Cash)</label>
                <select className="kb-input" value={form.to_account_id} onChange={handleChange("to_account_id")}>
                    {moneyOptions.map((acct) => (
                        <option key={acct.id} value={acct.id}>
                            {acct.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">From Account (Customer / Income)</label>
                <select className="kb-input" value={form.from_account_id} onChange={handleChange("from_account_id")}>
                    {otherOptions.map((acct) => (
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
                    Record Receipt
                </button>
            </div>
        </form>
    );
}
