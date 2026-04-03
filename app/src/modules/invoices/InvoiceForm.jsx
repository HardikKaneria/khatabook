import { useEffect, useState, useCallback } from "react";
import apiClient from "../../lib/apiClient";
import ContactSuggestInput from "../contacts/ContactSuggestInput.jsx";
import { getInvoiceDescriptions } from "./api";

const parseTermsDays = (value) => {
    if (typeof value === "number" && !Number.isNaN(value)) {
        return value;
    }
    if (typeof value === "string") {
        const match = value.match(/-?\d+/);
        if (match) {
            return parseInt(match[0], 10);
        }
    }
    return null;
};

const computeDueDate = (dateStr, days) => {
    if (!dateStr || typeof days !== "number") return dateStr;
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return dateStr;
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
};

const getActiveOrgId = () => {
    if (typeof window === "undefined") return null;
    try {
        return window.localStorage.getItem("vy_active_org_id");
    } catch {
        return null;
    }
};

const createEmptyItem = () => ({
    description: "",
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
});

const buildInitialForm = (initialData, today) => ({
    invoice_number: initialData?.invoice_number || "",
    date: initialData?.date || today,
    due_date: initialData?.due_date || initialData?.date || today,
    customer_name: initialData?.customer_name || "",
    customer_email: initialData?.customer_email || "",
    customer_phone: initialData?.customer_phone || "",
    notes: initialData?.notes || "",
});

const buildInitialItems = (initialData) => {
    if (Array.isArray(initialData?.items) && initialData.items.length) {
        return initialData.items.map((item) => ({
            description: item?.description || "",
            quantity: item?.quantity ?? 1,
            unit_price: item?.unit_price ?? 0,
            tax_rate: item?.tax_rate ?? 0,
        }));
    }

    return [createEmptyItem()];
};

export default function InvoiceForm({ onSubmit, onCancel, initialData = null, submitLabel }) {
    const today = new Date().toISOString().slice(0, 10);
    const isEditMode = Boolean(initialData?.id);
    const [form, setForm] = useState(() => buildInitialForm(initialData, today));
    const [items, setItems] = useState(() => buildInitialItems(initialData));
    const [dueOffsetDays, setDueOffsetDays] = useState(null);
    const [dueDateTouched, setDueDateTouched] = useState(isEditMode);
    const [invoiceNumberLoading, setInvoiceNumberLoading] = useState(false);
    const [selectedContactId, setSelectedContactId] = useState(initialData?.contact_id || null);
    const [descriptionSuggestions, setDescriptionSuggestions] = useState([]);

    useEffect(() => {
        setForm(buildInitialForm(initialData, today));
        setItems(buildInitialItems(initialData));
        setSelectedContactId(initialData?.contact_id || null);
        setDueDateTouched(Boolean(initialData?.id));
    }, [initialData, today]);

    useEffect(() => {
        let cancelled = false;
        const orgId = getActiveOrgId();
        if (!orgId) return;
        (async () => {
            try {
                const resp = await apiClient.get(`/kbs/v1/settings?org_id=${orgId}&category=sales`);
                if (cancelled) return;
                const settings = resp?.settings && typeof resp.settings === "object" ? resp.settings : {};
                let resolvedDays = null;
                if (settings.default_due_days !== undefined && settings.default_due_days !== null) {
                    const numeric = Number(settings.default_due_days);
                    if (!Number.isNaN(numeric)) {
                        resolvedDays = numeric;
                    }
                }
                if (resolvedDays === null) {
                    resolvedDays = parseTermsDays(settings.default_terms);
                }
                if (resolvedDays !== null) {
                    setDueOffsetDays(resolvedDays);
                }
            } catch (e) {
                console.warn("Failed to load sales settings", e);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, []);

    const fetchNextInvoiceNumber = useCallback(
        async (dateValue) => {
            const orgId = getActiveOrgId();
            if (!orgId) return;
            setInvoiceNumberLoading(true);
            try {
                const params = new URLSearchParams();
                if (dateValue) params.set("date", dateValue);
                const query = params.toString();
                const resp = await apiClient.get(
                    query ? `/vy/v1/invoices/next-number?${query}` : `/vy/v1/invoices/next-number`
                );
                const nextNumber = resp?.invoice_number || "";
                setForm((prev) => (prev.invoice_number === nextNumber ? prev : { ...prev, invoice_number: nextNumber }));
            } catch (e) {
                console.warn("Failed to fetch invoice number", e);
            } finally {
                setInvoiceNumberLoading(false);
            }
        },
        []
    );

    useEffect(() => {
        if (!isEditMode) {
            fetchNextInvoiceNumber(form.date);
        }
    }, [form.date, fetchNextInvoiceNumber, isEditMode]);

    const fetchDescriptionSuggestions = useCallback(async (term = "") => {
        try {
            const params = term && term.length >= 2 ? { q: term } : {};
            const resp = await getInvoiceDescriptions(params);
            setDescriptionSuggestions(resp?.data || []);
        } catch (e) {
            console.warn("Failed to fetch description suggestions", e);
        }
    }, []);

    useEffect(() => {
        fetchDescriptionSuggestions();
    }, [fetchDescriptionSuggestions]);

    useEffect(() => {
        if (dueDateTouched || dueOffsetDays === null) return;
        setForm((prev) => {
            const desiredDue = computeDueDate(prev.date, dueOffsetDays);
            if (!desiredDue || desiredDue === prev.due_date) {
                return prev;
            }
            return { ...prev, due_date: desiredDue };
        });
    }, [form.date, dueOffsetDays, dueDateTouched]);

    const handleChange = (key) => (event) => {
        if (key === "due_date") {
            setDueDateTouched(true);
        }
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
    };
    const handleCustomerNameChange = (value) => {
        setSelectedContactId(null);
        setForm((prev) => ({ ...prev, customer_name: value }));
    };

    const handleContactSelect = (contact) => {
        if (!contact) return;
        setSelectedContactId(contact.id);
        setForm((prev) => ({
            ...prev,
            customer_name: contact.name || prev.customer_name,
            customer_email: contact.email || prev.customer_email,
            customer_phone: contact.phone || prev.customer_phone,
        }));
    };

    const handleItemChange = (index, key) => (event) => {
        const value = event.target.value;
        setItems((prev) =>
            prev.map((item, idx) =>
                idx === index ? { ...item, [key]: value } : item
            )
        );
    };
    const handleDescriptionInputChange = (index) => (event) => {
        const handler = handleItemChange(index, "description");
        handler(event);
        const value = event.target.value;
        fetchDescriptionSuggestions(value);
    };

    const addItem = () => setItems((prev) => [...prev, createEmptyItem()]);

    const removeItem = (index) => {
        setItems((prev) => (prev.length > 1 ? prev.filter((_, idx) => idx !== index) : prev));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        const payload = {
            ...form,
            contact_id: selectedContactId,
            items: items.map((item) => ({
                description: item.description,
                quantity: parseFloat(item.quantity) || 0,
                unit_price: parseFloat(item.unit_price) || 0,
                tax_rate: parseFloat(item.tax_rate) || 0,
            })),
        };
        onSubmit?.(payload);
    };

    return (
        <form className="space-y-4" onSubmit={handleSubmit}>
            <datalist id="invoice-description-suggestions">
                {descriptionSuggestions.map((text) => (
                    <option key={text} value={text} />
                ))}
            </datalist>
            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Invoice #</label>
                    <input
                        className="kb-input"
                        value={invoiceNumberLoading && !form.invoice_number ? "Loading..." : form.invoice_number}
                        readOnly
                    />
                </div>
                <div>
                    <label className="kb-muted">Date</label>
                    <input type="date" className="kb-input" value={form.date} onChange={handleChange("date")} />
                </div>
                <div>
                    <label className="kb-muted">Due Date</label>
                    <input type="date" className="kb-input" value={form.due_date} onChange={handleChange("due_date")} />
                </div>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
                <ContactSuggestInput
                    label="Customer"
                    type="CUSTOMER"
                    value={form.customer_name}
                    onValueChange={handleCustomerNameChange}
                    onSelect={handleContactSelect}
                />
                <div>
                    <label className="kb-muted">Customer Email</label>
                    <input className="kb-input" value={form.customer_email} onChange={handleChange("customer_email")} />
                </div>
                <div>
                    <label className="kb-muted">Customer Phone</label>
                    <input className="kb-input" value={form.customer_phone} onChange={handleChange("customer_phone")} />
                </div>
            </div>

            <div>
                <label className="kb-muted">Notes</label>
                <textarea className="kb-input" rows={3} value={form.notes} onChange={handleChange("notes")} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h4 className="kb-h4">Line Items</h4>
                    <button type="button" className="kb-btn kb-btn--ghost" onClick={addItem}>
                        + Add Item
                    </button>
                </div>
                {items.map((item, index) => (
                    <div
                        key={index}
                        className="grid"
                        style={{
                            display: "grid",
                            gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))",
                            gap: 12,
                            border: "1px solid var(--kb-color-border)",
                            borderRadius: "var(--kb-radius-md)",
                            padding: 12,
                        }}
                    >
                        <div style={{ gridColumn: "1 / -1" }}>
                            <label className="kb-muted">Description</label>
                            <input
                                className="kb-input"
                                list="invoice-description-suggestions"
                                value={item.description}
                                onChange={handleDescriptionInputChange(index)}
                                required
                            />
                        </div>
                        <div>
                            <label className="kb-muted">Quantity</label>
                            <input
                                type="number"
                                step="0.01"
                                className="kb-input"
                                value={item.quantity}
                                onChange={handleItemChange(index, "quantity")}
                            />
                        </div>
                        <div>
                            <label className="kb-muted">Unit Price</label>
                            <input
                                type="number"
                                step="0.01"
                                className="kb-input"
                                value={item.unit_price}
                                onChange={handleItemChange(index, "unit_price")}
                            />
                        </div>
                        <div>
                            <label className="kb-muted">Tax Rate (%)</label>
                            <input
                                type="number"
                                step="0.01"
                                className="kb-input"
                                value={item.tax_rate}
                                onChange={handleItemChange(index, "tax_rate")}
                            />
                        </div>
                        <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "flex-end" }}>
                            <button
                                type="button"
                                className="kb-btn kb-btn--ghost"
                                onClick={() => removeItem(index)}
                                disabled={items.length === 1}
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    {submitLabel || (isEditMode ? "Save Changes" : "Create Invoice")}
                </button>
            </div>
        </form>
    );
}
