import { useState } from "react";
import InlineNotice from "../../components/ui/InlineNotice.jsx";

const formatAccounts = (accounts = []) =>
    accounts.map((acct) => ({ id: acct.id, name: acct.name }));

export default function TransferForm({
    onSubmit,
    onCancel,
    moneyAccounts = [],
    defaultFromAccountId,
    defaultToAccountId,
}) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({
        date: today,
        amount: "",
        from_account_id: defaultFromAccountId || moneyAccounts[0]?.id || "",
        to_account_id: defaultToAccountId || moneyAccounts[1]?.id || "",
        description: "",
    });
    const [formError, setFormError] = useState("");

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        if (form.from_account_id === form.to_account_id) {
            setFormError("Please choose two different money accounts.");
            return;
        }
        setFormError("");
        onSubmit?.({
            date: form.date,
            amount: parseFloat(form.amount) || 0,
            from_account_id: Number(form.from_account_id),
            to_account_id: Number(form.to_account_id),
            description: form.description,
        });
    };

    const moneyOptions = formatAccounts(moneyAccounts);

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
                <label className="kb-muted">From Account</label>
                <select className="kb-input" value={form.from_account_id} onChange={handleChange("from_account_id")}>
                    {moneyOptions.map((acct) => (
                        <option key={acct.id} value={acct.id}>
                            {acct.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">To Account</label>
                <select className="kb-input" value={form.to_account_id} onChange={handleChange("to_account_id")}>
                    {moneyOptions.map((acct) => (
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
            <InlineNotice message={formError} />
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    Transfer
                </button>
            </div>
        </form>
    );
}
