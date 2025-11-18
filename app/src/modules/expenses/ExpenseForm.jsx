import { useMemo, useState } from "react";
import ContactSuggestInput from "../contacts/ContactSuggestInput.jsx";

const toOptions = (accounts = []) =>
    accounts.map((acct) => ({ id: acct.id, name: acct.name }));

export default function ExpenseForm({
    onSubmit,
    onCancel,
    moneyAccounts = [],
    expenseAccounts = [],
}) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({
        expense_date: today,
        category: "",
        payee: "",
        description: "",
        amount: "",
        currency: "INR",
        pay_from_account_id: moneyAccounts[0]?.id || "",
        expense_account_id: expenseAccounts[0]?.id || "",
    });
    const [selectedContactId, setSelectedContactId] = useState(null);

    const moneyOptions = useMemo(() => toOptions(moneyAccounts), [moneyAccounts]);
    const expenseOptions = useMemo(() => toOptions(expenseAccounts), [expenseAccounts]);

    const handleChange = (key) => (event) => {
        setForm((prev) => ({ ...prev, [key]: event.target.value }));
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
            expense_date: form.expense_date,
            category: form.category,
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
                    <label className="kb-muted">Date</label>
                    <input type="date" className="kb-input" value={form.expense_date} onChange={handleChange("expense_date")} />
                </div>
                <div>
                    <label className="kb-muted">Category</label>
                    <input className="kb-input" value={form.category} onChange={handleChange("category")} required />
                </div>
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
                <textarea className="kb-input" rows={3} value={form.description} onChange={handleChange("description")} />
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Amount</label>
                    <input type="number" step="0.01" className="kb-input" value={form.amount} onChange={handleChange("amount")} required />
                </div>
                <div>
                    <label className="kb-muted">Currency</label>
                    <input className="kb-input" value={form.currency} onChange={handleChange("currency")} />
                </div>
            </div>

            <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 16 }}>
                <div>
                    <label className="kb-muted">Pay From (Bank/Cash)</label>
                    <select className="kb-input" value={form.pay_from_account_id} onChange={handleChange("pay_from_account_id")}>
                        <option value="">Unpaid / Record Later</option>
                        {moneyOptions.map((acct) => (
                            <option key={acct.id} value={acct.id}>
                                {acct.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="kb-muted">Expense Account</label>
                    <select className="kb-input" value={form.expense_account_id} onChange={handleChange("expense_account_id")}>
                        <option value="">General Expenses</option>
                        {expenseOptions.map((acct) => (
                            <option key={acct.id} value={acct.id}>
                                {acct.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex gap-2 justify-end">
                <button type="button" className="kb-btn kb-btn--ghost" onClick={onCancel}>
                    Cancel
                </button>
                <button type="submit" className="kb-btn kb-btn--primary">
                    Save Expense
                </button>
            </div>
        </form>
    );
}
