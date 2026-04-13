import { useState } from "react";

const TYPES = ["ASSET", "LIABILITY", "EQUITY", "INCOME", "EXPENSE"];
const STATUSES = ["ACTIVE", "ARCHIVED"];

export default function AccountForm({
    onSubmit,
    onCancel,
    initialData = {},
    submitLabel = "Save",
    loading = false,
    showStatusField = false,
    structuralFieldsDisabled = false,
    helpText = "",
}) {
    const [form, setForm] = useState({
        name: initialData.name || "",
        code: initialData.code || "",
        type: initialData.type || "ASSET",
        sub_type: initialData.sub_type || "",
        currency: initialData.currency || "INR",
        opening_balance: initialData.opening_balance || 0,
        opening_balance_type: initialData.opening_balance_type || "DEBIT",
        status: initialData.status || "ACTIVE",
    });

    const handleChange = (key) => (event) => {
        const value = event.target.value;
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            name: form.name,
            code: form.code,
            type: form.type,
            sub_type: form.sub_type,
            currency: form.currency,
            opening_balance: parseFloat(form.opening_balance) || 0,
            opening_balance_type: form.opening_balance_type,
            status: form.status,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div>
                <label className="kb-muted">Name</label>
                <input className="kb-input" value={form.name} onChange={handleChange("name")} required disabled={loading} />
            </div>
            <div>
                <label className="kb-muted">Code</label>
                <input className="kb-input" value={form.code} onChange={handleChange("code")} placeholder="Optional account code" disabled={loading} />
            </div>
            <div>
                <label className="kb-muted">Type</label>
                <select className="kb-input" value={form.type} onChange={handleChange("type")} disabled={loading || structuralFieldsDisabled}>
                    {TYPES.map((type) => (
                        <option key={type} value={type}>
                            {type}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="kb-muted">Subtype</label>
                <input className="kb-input" value={form.sub_type} onChange={handleChange("sub_type")} placeholder="BANK, CASH, etc." disabled={loading || structuralFieldsDisabled} />
            </div>
            <div>
                <label className="kb-muted">Currency</label>
                <input className="kb-input" value={form.currency} onChange={handleChange("currency")} maxLength={10} disabled={loading || structuralFieldsDisabled} />
            </div>
            <div>
                <label className="kb-muted">Opening Balance</label>
                <input type="number" className="kb-input" value={form.opening_balance} onChange={handleChange("opening_balance")} disabled={loading || structuralFieldsDisabled} />
            </div>
            <div>
                <label className="kb-muted">Opening Balance Type</label>
                <select className="kb-input" value={form.opening_balance_type} onChange={handleChange("opening_balance_type")} disabled={loading || structuralFieldsDisabled}>
                    <option value="DEBIT">DEBIT</option>
                    <option value="CREDIT">CREDIT</option>
                </select>
            </div>
            {showStatusField ? (
                <div>
                    <label className="kb-muted">Status</label>
                    <select className="kb-input" value={form.status} onChange={handleChange("status")} disabled={loading}>
                        {STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                </div>
            ) : null}
            {helpText ? (
                <p className="kb-muted" style={{ margin: "4px 0 0" }}>
                    {helpText}
                </p>
            ) : null}
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel} disabled={loading}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary" disabled={loading}>
                    {loading ? "Saving…" : submitLabel}
                </button>
            </div>
        </form>
    );
}
