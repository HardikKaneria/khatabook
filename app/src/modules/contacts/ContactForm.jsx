import { useEffect, useState } from "react";

const buildInitialForm = (initialData = null) => ({
    type: initialData?.type || "CUSTOMER",
    name: initialData?.name || "",
    email: initialData?.email || "",
    phone: initialData?.phone || "",
    gstin: initialData?.gstin || "",
    billing_address: initialData?.billing_address || "",
    shipping_address: initialData?.shipping_address || "",
    notes: initialData?.notes || "",
});

export default function ContactForm({
    initialData = null,
    onSubmit,
    onCancel,
    submitLabel,
    disabled = false,
}) {
    const isEditMode = Boolean(initialData?.id);
    const [form, setForm] = useState(() => buildInitialForm(initialData));

    useEffect(() => {
        setForm(buildInitialForm(initialData));
    }, [initialData]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            ...form,
            type: form.type || "CUSTOMER",
        });
    };

    return (
        <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Contact Type</label>
                    <select className="kb-input" value={form.type} onChange={handleChange("type")} disabled={disabled}>
                        <option value="CUSTOMER">Customer</option>
                        <option value="VENDOR">Vendor</option>
                        <option value="BOTH">Both</option>
                    </select>
                </div>
                <div>
                    <label className="kb-muted">Name</label>
                    <input className="kb-input" value={form.name} onChange={handleChange("name")} required disabled={disabled} />
                </div>
                <div>
                    <label className="kb-muted">GSTIN</label>
                    <input className="kb-input" value={form.gstin} onChange={handleChange("gstin")} disabled={disabled} />
                </div>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Email</label>
                    <input className="kb-input" type="email" value={form.email} onChange={handleChange("email")} disabled={disabled} />
                </div>
                <div>
                    <label className="kb-muted">Phone</label>
                    <input className="kb-input" value={form.phone} onChange={handleChange("phone")} disabled={disabled} />
                </div>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Billing Address</label>
                    <textarea className="kb-input" rows={3} value={form.billing_address} onChange={handleChange("billing_address")} disabled={disabled} />
                </div>
                <div>
                    <label className="kb-muted">Shipping Address</label>
                    <textarea className="kb-input" rows={3} value={form.shipping_address} onChange={handleChange("shipping_address")} disabled={disabled} />
                </div>
            </div>

            <div>
                <label className="kb-muted">Notes</label>
                <textarea className="kb-input" rows={3} value={form.notes} onChange={handleChange("notes")} disabled={disabled} />
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel} disabled={disabled}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary" disabled={disabled}>
                    {submitLabel || (isEditMode ? "Save Changes" : "Create Contact")}
                </button>
            </div>
        </form>
    );
}
