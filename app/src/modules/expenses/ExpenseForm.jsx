import { useEffect, useMemo, useState } from "react";
import ContactSuggestInput from "../contacts/ContactSuggestInput.jsx";

const toOptions = (accounts = []) =>
    accounts.map((acct) => ({ id: acct.id, name: acct.name }));

const buildInitialForm = (initialData, today, moneyAccounts, expenseAccounts, defaultDocumentType) => {
    const documentType = initialData?.document_type || defaultDocumentType || "EXPENSE";

    return {
        document_type: documentType,
        expense_date: initialData?.expense_date || today,
        due_date: initialData?.due_date || (documentType === "BILL" ? (initialData?.expense_date || today) : ""),
        category: initialData?.category || "",
        reference_number: initialData?.reference_number || "",
        payee: initialData?.payee || "",
        description: initialData?.description || "",
        amount: initialData?.amount ?? "",
        currency: initialData?.currency || "INR",
        pay_from_account_id: initialData?.payment_journal_id
            ? ""
            : (documentType === "BILL" ? "" : (moneyAccounts[0]?.id || "")),
        expense_account_id: initialData?.expense_account_id || expenseAccounts[0]?.id || "",
    };
};

export default function ExpenseForm({
    onSubmit,
    onCancel,
    moneyAccounts = [],
    expenseAccounts = [],
    initialData = null,
    defaultDocumentType = "EXPENSE",
    submitLabel,
    showPaymentFields = true,
    showExpenseAccountField = true,
    disabled = false,
}) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState(() => buildInitialForm(initialData, today, moneyAccounts, expenseAccounts, defaultDocumentType));
    const [selectedContactId, setSelectedContactId] = useState(initialData?.contact_id || null);

    const moneyOptions = useMemo(() => toOptions(moneyAccounts), [moneyAccounts]);
    const expenseOptions = useMemo(() => toOptions(expenseAccounts), [expenseAccounts]);

    useEffect(() => {
        setForm(buildInitialForm(initialData, today, moneyAccounts, expenseAccounts, defaultDocumentType));
        setSelectedContactId(initialData?.contact_id || null);
    }, [defaultDocumentType, expenseAccounts, initialData, moneyAccounts, today]);

    const handleChange = (key) => (event) => {
        const value = event.target.value;
        setForm((prev) => {
            if (key === "document_type") {
                return {
                    ...prev,
                    document_type: value,
                    due_date: value === "BILL" ? (prev.due_date || prev.expense_date || today) : "",
                };
            }

            if (key === "expense_date" && prev.document_type === "BILL" && !prev.due_date) {
                return { ...prev, expense_date: value, due_date: value };
            }

            return { ...prev, [key]: value };
        });
    };
    const handlePayeeChange = (value) => {
        setSelectedContactId(null);
        setForm((prev) => ({ ...prev, payee: value }));
    };

    const handleVendorSelect = (contact) => {
        if (!contact) return;
        setSelectedContactId(contact.id);
        setForm((prev) => ({
            ...prev,
            payee: contact.name || prev.payee,
        }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        onSubmit?.({
            document_type: form.document_type,
            expense_date: form.expense_date,
            due_date: form.document_type === "BILL" ? form.due_date || undefined : undefined,
            category: form.category,
            reference_number: form.reference_number || undefined,
            payee: form.payee,
            description: form.description,
            amount: parseFloat(form.amount) || 0,
            currency: form.currency,
            pay_from_account_id: form.pay_from_account_id ? Number(form.pay_from_account_id) : undefined,
            expense_account_id: form.expense_account_id ? Number(form.expense_account_id) : undefined,
            contact_id: selectedContactId,
        });
    };

    return (
        <form className="space-y-3" onSubmit={handleSubmit}>
            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Record Type</label>
                    <select className="kb-input" value={form.document_type} onChange={handleChange("document_type")} disabled={disabled}>
                        <option value="EXPENSE">Expense</option>
                        <option value="BILL">Vendor Bill</option>
                    </select>
                </div>
                <div>
                    <label className="kb-muted">Date</label>
                    <input type="date" className="kb-input" value={form.expense_date} onChange={handleChange("expense_date")} disabled={disabled} />
                </div>
                {form.document_type === "BILL" ? (
                    <div>
                        <label className="kb-muted">Due Date</label>
                        <input type="date" className="kb-input" value={form.due_date} onChange={handleChange("due_date")} required={form.document_type === "BILL"} disabled={disabled} />
                    </div>
                ) : null}
                <div>
                    <label className="kb-muted">Category</label>
                    <input className="kb-input" value={form.category} onChange={handleChange("category")} required disabled={disabled} />
                </div>
                {form.document_type === "BILL" ? (
                    <div>
                        <label className="kb-muted">Bill Number / Reference</label>
                        <input className="kb-input" value={form.reference_number} onChange={handleChange("reference_number")} placeholder="Optional vendor bill number" disabled={disabled} />
                    </div>
                ) : null}
                <ContactSuggestInput
                    label="Payee"
                    type="VENDOR"
                    value={form.payee}
                    onValueChange={handlePayeeChange}
                    onSelect={handleVendorSelect}
                />
            </div>

            <div>
                <label className="kb-muted">Description</label>
                <textarea className="kb-input" rows={3} value={form.description} onChange={handleChange("description")} disabled={disabled} />
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Amount</label>
                    <input type="number" step="0.01" className="kb-input" value={form.amount} onChange={handleChange("amount")} required disabled={disabled} />
                </div>
                <div>
                    <label className="kb-muted">Currency</label>
                    <input className="kb-input" value={form.currency} onChange={handleChange("currency")} disabled={disabled} />
                </div>
                {showExpenseAccountField ? (
                    <div>
                        <label className="kb-muted">Expense Account</label>
                        <select className="kb-input" value={form.expense_account_id} onChange={handleChange("expense_account_id")} disabled={disabled}>
                            <option value="">General Expenses</option>
                            {expenseOptions.map((acct) => (
                                <option key={acct.id} value={acct.id}>
                                    {acct.name}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null}
            </div>

            {showPaymentFields ? (
                <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 16 }}>
                    <div>
                        <label className="kb-muted">Pay Now From (Bank/Cash)</label>
                        <select className="kb-input" value={form.pay_from_account_id} onChange={handleChange("pay_from_account_id")} disabled={disabled}>
                            <option value="">{form.document_type === "BILL" ? "Leave bill open for later settlement" : "Unpaid / Record Later"}</option>
                            {moneyOptions.map((acct) => (
                                <option key={acct.id} value={acct.id}>
                                    {acct.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            ) : null}

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel} disabled={disabled}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary" disabled={disabled}>
                    {submitLabel || "Save Expense"}
                </button>
            </div>
        </form>
    );
}
