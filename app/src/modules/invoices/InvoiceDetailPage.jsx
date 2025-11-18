import { useEffect, useMemo, useState } from "react";
import { payInvoice } from "./api";
import { useInvoice } from "./hooks";
import InvoiceDetail from "./InvoiceDetail.jsx";
import InvoicePaymentForm from "./InvoicePaymentForm.jsx";
import { useAccounts } from "../accounts/hooks";
import apiClient from "../../lib/apiClient";
import { useToast } from "../../components/ToastProvider";

const isMoneyAccount = (acct) => ["BANK", "CASH", "WALLET"].includes((acct?.sub_type || "").toUpperCase());
const isIncomeAccount = (acct) => (acct?.type || "").toUpperCase() === "INCOME";

export default function InvoiceDetailPage({ invoiceId }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const [showEmailModal, setShowEmailModal] = useState(false);
    const [emailRecipients, setEmailRecipients] = useState("");
    const [emailError, setEmailError] = useState("");
    const [emailSending, setEmailSending] = useState(false);
    const [actionError, setActionError] = useState("");
    const { data: invoice, loading, error } = useInvoice(invoiceId, refreshKey);
    const { data: accounts = [] } = useAccounts({}, refreshKey);
    const toast = useToast();

    const moneyAccounts = useMemo(() => (accounts || []).filter(isMoneyAccount), [accounts]);
    const incomeAccounts = useMemo(() => (accounts || []).filter(isIncomeAccount), [accounts]);
    const hasInvoice = Boolean(invoice);
    const canRecordPayment = Boolean(invoice && moneyAccounts.length && incomeAccounts.length);

    useEffect(() => {
        if (!invoice) {
            setEmailRecipients("");
            return;
        }
        const defaultRecipients =
            invoice.customer_email ||
            (Array.isArray(invoice.email_sent_to)
                ? invoice.email_sent_to.join(", ")
                : invoice.email_sent_to || "");
        setEmailRecipients(defaultRecipients || "");
    }, [invoice]);

    const handlePayment = async (payload) => {
        try {
            setActionError("");
            await payInvoice(invoiceId, payload);
            setShowPaymentForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to record payment.");
        }
    };

    const handleSendEmail = async (event) => {
        event.preventDefault();
        if (!invoice) return;
        const recipients = (emailRecipients || "")
            .split(",")
            .map((email) => email.trim())
            .filter(Boolean);
        if (!recipients.length) {
            setEmailError("Enter at least one email address.");
            return;
        }
        setEmailError("");
        setEmailSending(true);
        try {
            await apiClient.post(`/vy/v1/invoices/${invoice.id}/email`, { recipients });
            toast.success("Invoice emailed successfully.");
            setShowEmailModal(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setEmailError(err?.message || "Unable to send invoice email.");
        } finally {
            setEmailSending(false);
        }
    };

    const emailInfo = invoice?.email_sent_at
        ? `Last emailed ${new Date(invoice.email_sent_at).toLocaleString()}${
              invoice.email_sent_to ? ` to ${invoice.email_sent_to}` : ""
          }`
        : "";

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    Invoice Detail
                </h2>
                <div style={{ textAlign: "right" }}>
                    <div className="flex flex-wrap gap-2 justify-end">
                        {invoice?.pdf_url ? (
                            <a
                                className="kb-btn kb-btn--secondary"
                                href={invoice.pdf_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Download PDF
                            </a>
                        ) : (
                            <button className="kb-btn kb-btn--ghost" disabled>
                                PDF not ready
                            </button>
                        )}
                        <button
                            className="kb-btn kb-btn--secondary"
                            disabled={!hasInvoice}
                            onClick={() => {
                                setEmailError("");
                                setShowEmailModal(true);
                            }}
                        >
                            Email Invoice
                        </button>
                        <button
                            className="kb-btn kb-btn--primary"
                            disabled={!hasInvoice}
                            onClick={() => hasInvoice && setShowPaymentForm(true)}
                        >
                            Record Payment
                        </button>
                    </div>
                    {invoice && !canRecordPayment ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            Add at least one money account and one income account to record payments.
                        </p>
                    ) : null}
                    {emailInfo ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            {emailInfo}
                        </p>
                    ) : null}
                </div>
            </div>

            {loading || error ? (
                <div className="kb-card" style={{ padding: 24 }}>
                    {loading ? <p>Loading invoice…</p> : <p className="text-red-600">{error?.message}</p>}
                </div>
            ) : (
                <InvoiceDetail invoice={invoice} />
            )}

            {showPaymentForm && (
                <Modal title="Record Payment" onClose={() => setShowPaymentForm(false)}>
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    {canRecordPayment ? (
                        <InvoicePaymentForm
                            invoice={invoice}
                            onSubmit={handlePayment}
                            onCancel={() => setShowPaymentForm(false)}
                            moneyAccounts={moneyAccounts}
                            incomeAccounts={incomeAccounts}
                        />
                    ) : (
                        <p className="kb-muted">
                            Add at least one money account and one income account before recording a payment.
                        </p>
                    )}
                </Modal>
            )}

            {showEmailModal && (
                <Modal title="Email Invoice" onClose={() => setShowEmailModal(false)}>
                    {emailError ? <p className="text-red-600 text-sm">{emailError}</p> : null}
                    <form className="space-y-3" onSubmit={handleSendEmail}>
                        <div>
                            <label className="kb-muted">Recipient Emails</label>
                            <input
                                className="kb-input"
                                value={emailRecipients}
                                onChange={(e) => setEmailRecipients(e.target.value)}
                                placeholder="customer@example.com, finance@example.com"
                            />
                            <p className="kb-muted" style={{ marginTop: 4 }}>
                                Separate multiple emails with commas.
                            </p>
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button
                                type="button"
                                className="kb-btn kb-btn--ghost"
                                onClick={() => setShowEmailModal(false)}
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="kb-btn kb-btn--primary"
                                disabled={emailSending}
                            >
                                {emailSending ? "Sending…" : "Send Email"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

function Modal({ title, children, onClose }) {
    return (
        <div
            style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.35)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 1000,
            }}
        >
            <div className="kb-card" style={{ padding: 24, width: "min(420px, 95vw)", borderRadius: "var(--kb-radius-lg)" }}>
                <div className="flex items-center justify-between" style={{ marginBottom: 16 }}>
                    <h3 className="kb-h3" style={{ margin: 0 }}>
                        {title}
                    </h3>
                    <button onClick={onClose} className="kb-btn kb-btn--ghost" style={{ padding: "4px 8px" }}>
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
