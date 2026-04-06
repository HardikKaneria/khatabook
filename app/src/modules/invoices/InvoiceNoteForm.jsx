import { useState } from "react";

export default function InvoiceNoteForm({ noteType = "CREDIT", invoice, onSubmit, onCancel }) {
    const [form, setForm] = useState({
        note_date: new Date().toISOString().slice(0, 10),
        amount: invoice?.balance_due || "",
        reason: "",
    });

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            note_type: noteType,
            note_date: form.note_date,
            amount: parseFloat(form.amount) || 0,
            reason: form.reason,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div>
                <label className="kb-muted">Date</label>
                <input type="date" className="kb-input" value={form.note_date} onChange={handleChange("note_date")} required />
            </div>
            <div>
                <label className="kb-muted">Amount</label>
                <input type="number" step="0.01" min="0.01" className="kb-input" value={form.amount} onChange={handleChange("amount")} required />
                <p className="kb-muted" style={{ marginTop: 4 }}>
                    Current balance due: ₹ {Number(invoice?.balance_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </p>
            </div>
            <div>
                <label className="kb-muted">Reason</label>
                <textarea className="kb-input" rows="4" value={form.reason} onChange={handleChange("reason")} placeholder={`${noteType === "CREDIT" ? "Discount, return, or billing correction" : "Additional charges or revised billing"}...`} />
            </div>
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    Save {noteType === "CREDIT" ? "Credit" : "Debit"} Note
                </button>
            </div>
        </form>
    );
}
