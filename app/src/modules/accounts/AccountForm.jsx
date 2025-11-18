import { useState } from "react";

const TYPES = ["ASSET", "LIABILITY", "EQUITY", "INCOME", "EXPENSE"];

export default function AccountForm({ onSubmit, onCancel, initialData = {} }) {
    const [form, setForm] = useState({
        name: initialData.name || "",
        type: initialData.type || "ASSET",
        sub_type: initialData.sub_type || "",
        opening_balance: initialData.opening_balance || 0,
        opening_balance_type: initialData.opening_balance_type || "DEBIT",
    });

    const handleChange = (key) => (event) => {
        const value = event.target.value;
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            name: form.name,
            type: form.type,
            sub_type: form.sub_type,
            opening_balance: parseFloat(form.opening_balance) || 0,
            opening_balance_type: form.opening_balance_type,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div>
                <label className="kb-muted">Name</label>
                <input className="kb-input" value={form.name} onChange={handleChange("name")} required />
            </div>
            <div>
                <label className="kb-muted">Type</label>
                <select className="kb-input" value={form.type} onChange={handleChange("type")}>
                    {TYPES.map((type) => (
                        <option key={type} value={type}>
                            {type}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">Subtype</label>
                <input className="kb-input" value={form.sub_type} onChange={handleChange("sub_type")} placeholder="BANK, CASH, etc." />
            </div>
            <div>
                <label className="kb-muted">Opening Balance</label>
                <input type="number" className="kb-input" value={form.opening_balance} onChange={handleChange("opening_balance")} />
            </div>
            <div>
                <label className="kb-muted">Opening Balance Type</label>
                <select className="kb-input" value={form.opening_balance_type} onChange={handleChange("opening_balance_type")}>
                    <option value="DEBIT">DEBIT</option>
                    <option value="CREDIT">CREDIT</option>
                </select>
            </div>
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    Save
                </button>
            </div>
        </form>
    );
}
