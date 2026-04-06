import { useEffect, useState } from "react";

const calculateDueDays = (invoice) => {
    const date = invoice?.date ? new Date(invoice.date) : null;
    const dueDate = invoice?.due_date ? new Date(invoice.due_date) : null;
    if (!date || !dueDate || Number.isNaN(date.getTime()) || Number.isNaN(dueDate.getTime())) {
        return 0;
    }

    const diff = Math.round((dueDate.getTime() - date.getTime()) / 86400000);
    return Math.max(0, diff);
};

const buildInitialState = (sourceInvoice, initialData) => ({
    profile_name: initialData?.profile_name || `Recurring ${sourceInvoice?.invoice_number || "Invoice"}`,
    frequency: initialData?.frequency || "MONTHLY",
    interval_count: initialData?.interval_count || 1,
    start_date: initialData?.start_date || sourceInvoice?.date || new Date().toISOString().slice(0, 10),
    end_date: initialData?.end_date || "",
    due_days: initialData?.due_days ?? calculateDueDays(sourceInvoice),
    invoice_status: initialData?.invoice_status || "DRAFT",
    notes: initialData?.notes ?? sourceInvoice?.notes ?? "",
    status: initialData?.status || "ACTIVE",
});

export default function RecurringProfileForm({
    sourceInvoice,
    initialData = null,
    submitLabel = "Save Plan",
    onSubmit,
    onCancel,
}) {
    const [form, setForm] = useState(() => buildInitialState(sourceInvoice, initialData));

    useEffect(() => {
        setForm(buildInitialState(sourceInvoice, initialData));
    }, [sourceInvoice, initialData]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            profile_name: form.profile_name,
            frequency: form.frequency,
            interval_count: Number(form.interval_count) || 1,
            start_date: form.start_date,
            end_date: form.end_date || null,
            due_days: Math.max(0, Number(form.due_days) || 0),
            invoice_status: form.invoice_status,
            notes: form.notes,
            status: form.status,
        });
    };

    return (
        <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="kb-card kb-card--soft" style={{ padding: 16 }}>
                <p className="kb-muted" style={{ margin: 0 }}>
                    This recurring plan uses the current customer and item lines from{" "}
                    <strong>{sourceInvoice?.invoice_number || "the selected invoice"}</strong>. Update the plan schedule here, and create a new source invoice if pricing or line items change materially later.
                </p>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Plan Name</label>
                    <input className="kb-input" value={form.profile_name} onChange={handleChange("profile_name")} required />
                </div>
                <div>
                    <label className="kb-muted">Frequency</label>
                    <select className="kb-input" value={form.frequency} onChange={handleChange("frequency")}>
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                        <option value="QUARTERLY">Quarterly</option>
                        <option value="YEARLY">Yearly</option>
                    </select>
                </div>
                <div>
                    <label className="kb-muted">Every</label>
                    <input type="number" min="1" max="12" className="kb-input" value={form.interval_count} onChange={handleChange("interval_count")} />
                </div>
                <div>
                    <label className="kb-muted">Invoice Status</label>
                    <select className="kb-input" value={form.invoice_status} onChange={handleChange("invoice_status")}>
                        <option value="DRAFT">Draft</option>
                        <option value="SENT">Sent</option>
                    </select>
                </div>
                <div>
                    <label className="kb-muted">First Run Date</label>
                    <input type="date" className="kb-input" value={form.start_date} onChange={handleChange("start_date")} required />
                </div>
                <div>
                    <label className="kb-muted">End Date</label>
                    <input type="date" className="kb-input" value={form.end_date} onChange={handleChange("end_date")} />
                </div>
                <div>
                    <label className="kb-muted">Due Days</label>
                    <input type="number" min="0" className="kb-input" value={form.due_days} onChange={handleChange("due_days")} />
                </div>
                <div>
                    <label className="kb-muted">Plan Status</label>
                    <select className="kb-input" value={form.status} onChange={handleChange("status")}>
                        <option value="ACTIVE">Active</option>
                        <option value="PAUSED">Paused</option>
                        <option value="ENDED">Ended</option>
                    </select>
                </div>
            </div>

            <div>
                <label className="kb-muted">Plan Notes</label>
                <textarea className="kb-input" rows="4" value={form.notes} onChange={handleChange("notes")} />
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}
