import { useEffect, useState } from "react";

const buildInitialState = (invoice, promise) => ({
    promised_date: promise?.promised_date || new Date().toISOString().slice(0, 10),
    promised_amount: promise?.promised_amount || invoice?.balance_due || "",
    notes: promise?.notes || "",
    status: promise?.status || "OPEN",
    resolution_note: promise?.resolution_note || "",
});

export default function InvoicePromiseForm({ invoice, promise = null, onSubmit, onCancel }) {
    const [form, setForm] = useState(() => buildInitialState(invoice, promise));

    useEffect(() => {
        setForm(buildInitialState(invoice, promise));
    }, [invoice, promise]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            promised_date: form.promised_date,
            promised_amount: parseFloat(form.promised_amount) || 0,
            notes: form.notes,
            status: form.status,
            resolution_note: form.resolution_note,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Promised Date</label>
                    <input type="date" className="kb-input" value={form.promised_date} onChange={handleChange("promised_date")} required />
                </div>
                <div>
                    <label className="kb-muted">Promised Amount</label>
                    <input type="number" min="0.01" step="0.01" className="kb-input" value={form.promised_amount} onChange={handleChange("promised_amount")} required />
                </div>
                {promise ? (
                    <div>
                        <label className="kb-muted">Status</label>
                        <select className="kb-input" value={form.status} onChange={handleChange("status")}>
                            <option value="OPEN">Open</option>
                            <option value="KEPT">Kept</option>
                            <option value="BROKEN">Broken</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                    </div>
                ) : null}
            </div>
            <p className="kb-muted" style={{ margin: 0 }}>
                Current balance due: ₹ {Number(invoice?.balance_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </p>
            <div>
                <label className="kb-muted">Promise Notes</label>
                <textarea className="kb-input" rows="4" value={form.notes} onChange={handleChange("notes")} placeholder="What did the customer commit to and why?" />
            </div>
            {form.status !== "OPEN" ? (
                <div>
                    <label className="kb-muted">Resolution Note</label>
                    <textarea className="kb-input" rows="3" value={form.resolution_note} onChange={handleChange("resolution_note")} placeholder="Optional follow-up note for the outcome." />
                </div>
            ) : null}
            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    {promise ? "Update Promise" : "Save Promise"}
                </button>
            </div>
        </form>
    );
}
