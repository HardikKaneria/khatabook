import { useEffect, useMemo, useState } from "react";
import {
    createInvoiceNote,
    createInvoicePromise,
    createRecurringProfile,
    generateRecurringProfile,
    payInvoice,
    refundInvoice,
    updateInvoice,
    updateInvoicePromise,
    updateRecurringProfile,
} from "./api";
import { useInvoice } from "./hooks";
import InvoiceDetail from "./InvoiceDetail.jsx";
import InvoicePaymentForm from "./InvoicePaymentForm.jsx";
import InvoiceForm from "./InvoiceForm.jsx";
import InvoiceNoteForm from "./InvoiceNoteForm.jsx";
import InvoicePromiseForm from "./InvoicePromiseForm.jsx";
import InvoiceRefundForm from "./InvoiceRefundForm.jsx";
import RecurringProfileForm from "./RecurringProfileForm.jsx";
import { useAccounts } from "../accounts/hooks";
import apiClient from "../../lib/apiClient";
import { useToast } from "../../components/ToastProvider";
import FeedbackState from "../../components/ui/FeedbackState.jsx";
import InlineNotice from "../../components/ui/InlineNotice.jsx";

const isMoneyAccount = (acct) => ["BANK", "CASH", "WALLET"].includes((acct?.sub_type || "").toUpperCase());
const isIncomeAccount = (acct) => (acct?.type || "").toUpperCase() === "INCOME";

export default function InvoiceDetailPage({ invoiceId }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const [showEditForm, setShowEditForm] = useState(false);
    const [showEmailModal, setShowEmailModal] = useState(false);
    const [showRecurringForm, setShowRecurringForm] = useState(false);
    const [showPromiseForm, setShowPromiseForm] = useState(false);
    const [showRefundForm, setShowRefundForm] = useState(false);
    const [emailRecipients, setEmailRecipients] = useState("");
    const [emailError, setEmailError] = useState("");
    const [emailSending, setEmailSending] = useState(false);
    const [paymentSaving, setPaymentSaving] = useState(false);
    const [refundSaving, setRefundSaving] = useState(false);
    const [actionError, setActionError] = useState("");
    const [editError, setEditError] = useState("");
    const [recurringError, setRecurringError] = useState("");
    const [noteError, setNoteError] = useState("");
    const [promiseError, setPromiseError] = useState("");
    const [refundError, setRefundError] = useState("");
    const [noteType, setNoteType] = useState("");
    const [editingPromise, setEditingPromise] = useState(null);
    const [pdfGenerating, setPdfGenerating] = useState(false);
    const { data: invoice, loading, error } = useInvoice(invoiceId, refreshKey);
    const { data: accounts = [] } = useAccounts({}, refreshKey);
    const toast = useToast();

    const moneyAccounts = useMemo(() => (accounts || []).filter(isMoneyAccount), [accounts]);
    const incomeAccounts = useMemo(() => (accounts || []).filter(isIncomeAccount), [accounts]);
    const hasInvoice = Boolean(invoice);
    const canEditInvoice = Boolean(invoice?.can_edit);
    const invoiceBalanceDue = Math.max(0, Number(invoice?.balance_due || 0));
    const isFullyPaid = hasInvoice && invoiceBalanceDue <= 0;
    const canRecordPayment = Boolean(invoice && !isFullyPaid && moneyAccounts.length && incomeAccounts.length);
    const canRefundInvoice = Boolean(invoice?.can_refund && moneyAccounts.length && incomeAccounts.length);
    const sourceRecurringProfile = invoice?.source_recurring_profile || null;

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
            setPaymentSaving(true);
            await payInvoice(invoiceId, payload);
            setShowPaymentForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to record payment.");
        } finally {
            setPaymentSaving(false);
        }
    };

    const handleUpdateInvoice = async (payload) => {
        try {
            setEditError("");
            await updateInvoice(invoiceId, payload);
            toast.success("Invoice updated successfully.");
            setShowEditForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setEditError(err?.message || "Unable to update invoice.");
        }
    };

    const handleRefundInvoice = async (payload) => {
        try {
            setRefundError("");
            setRefundSaving(true);
            await refundInvoice(invoiceId, payload);
            toast.success("Invoice refunded and voided.");
            setShowRefundForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRefundError(err?.message || "Unable to refund invoice.");
        } finally {
            setRefundSaving(false);
        }
    };

    const handleRecurringSubmit = async (payload) => {
        if (!invoice) return;
        try {
            setRecurringError("");
            if (sourceRecurringProfile?.id) {
                await updateRecurringProfile(sourceRecurringProfile.id, payload);
                toast.success("Recurring billing plan updated.");
            } else {
                await createRecurringProfile(invoice.id, payload);
                toast.success("Recurring billing plan created.");
            }
            setShowRecurringForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRecurringError(err?.message || "Unable to save recurring billing.");
        }
    };

    const handleGenerateRecurring = async (profile) => {
        if (!profile?.id) return;
        try {
            setRecurringError("");
            const response = await generateRecurringProfile(profile.id, {});
            const generatedCount = Array.isArray(response?.generated) ? response.generated.length : 0;
            toast.success(generatedCount ? `Generated ${generatedCount} recurring invoice${generatedCount === 1 ? "" : "s"}.` : "Recurring plan checked.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setRecurringError(err?.message || "Unable to generate recurring invoice.");
        }
    };

    const handleCreateNote = async (payload) => {
        if (!invoice) return;
        try {
            setNoteError("");
            await createInvoiceNote(invoice.id, payload);
            toast.success(`${payload.note_type === "CREDIT" ? "Credit" : "Debit"} note saved.`);
            setRefreshKey((value) => value + 1);
            return true;
        } catch (err) {
            setNoteError(err?.message || "Unable to save invoice note.");
            return false;
        }
    };

    const handlePromiseSubmit = async (payload) => {
        if (!invoice) return;
        try {
            setPromiseError("");
            if (editingPromise?.id) {
                await updateInvoicePromise(editingPromise.id, payload);
                toast.success("Promise updated.");
            } else {
                await createInvoicePromise(invoice.id, payload);
                toast.success("Promise recorded.");
            }
            setEditingPromise(null);
            setShowPromiseForm(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setPromiseError(err?.message || "Unable to save promise.");
        }
    };

    const handleQuickPromiseStatus = async (promise, status) => {
        if (!promise?.id) return;
        try {
            setPromiseError("");
            await updateInvoicePromise(promise.id, { status });
            toast.success(`Promise marked ${status.toLowerCase()}.`);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setPromiseError(err?.message || "Unable to update promise.");
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

    const handleGeneratePdf = async () => {
        if (!invoice) return;
        setPdfGenerating(true);
        try {
            await apiClient.post(`/vy/v1/invoices/${invoice.id}/generate-pdf`, {});
            toast.success("Invoice PDF generated.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            toast.error(err?.message || "Unable to generate invoice PDF.");
        } finally {
            setPdfGenerating(false);
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
                            <button className="kb-btn kb-btn--ghost" disabled={!hasInvoice || pdfGenerating} onClick={handleGeneratePdf}>
                                {pdfGenerating ? "Generating PDF…" : "Generate PDF"}
                            </button>
                        )}
                        <button
                            className="kb-btn kb-btn--secondary"
                            disabled={!canEditInvoice}
                            onClick={() => {
                                setEditError("");
                                setShowEditForm(true);
                            }}
                            title={!canEditInvoice ? invoice?.edit_block_reason || "This invoice can no longer be edited." : undefined}
                        >
                            Edit Invoice
                        </button>
                        <button
                            className="kb-btn kb-btn--secondary"
                            disabled={!hasInvoice}
                            onClick={() => {
                                setRecurringError("");
                                setShowRecurringForm(true);
                            }}
                        >
                            {sourceRecurringProfile ? "Edit Recurring" : "Set Recurring"}
                        </button>
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
                        {!isFullyPaid ? (
                            <button
                                className="kb-btn kb-btn--primary"
                                disabled={!canRecordPayment || paymentSaving}
                                onClick={() => hasInvoice && canRecordPayment && setShowPaymentForm(true)}
                            >
                                {paymentSaving ? "Recording…" : "Record Payment"}
                            </button>
                        ) : null}
                        {hasInvoice && isFullyPaid ? (
                            <button
                                className="kb-btn kb-btn--secondary"
                                disabled={!canRefundInvoice || refundSaving}
                                onClick={() => {
                                    setRefundError("");
                                    setShowRefundForm(true);
                                }}
                                title={!canRefundInvoice ? invoice?.refund_block_reason || "This invoice cannot be refunded." : undefined}
                            >
                                {refundSaving ? "Refunding…" : "Cancel & Refund"}
                            </button>
                        ) : null}
                    </div>
                    {invoice && isFullyPaid ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            {invoice?.refunded_amount > 0 ? "This invoice has been refunded and voided." : "This invoice is fully paid."}
                        </p>
                    ) : null}
                    {invoice && isFullyPaid && invoice?.can_refund && (!moneyAccounts.length || !incomeAccounts.length) ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            Add at least one money account and one income account before processing a refund.
                        </p>
                    ) : null}
                    {invoice && !isFullyPaid && !canRecordPayment ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            Add at least one money account and one income account to record payments.
                        </p>
                    ) : null}
                    {invoice && isFullyPaid && !canRefundInvoice && invoice?.refund_block_reason ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            {invoice.refund_block_reason}
                        </p>
                    ) : null}
                    {invoice && !canEditInvoice && invoice?.edit_block_reason ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            {invoice.edit_block_reason}
                        </p>
                    ) : null}
                    {invoice?.risk_summary?.issue_count ? (
                        <p className="kb-muted" style={{ margin: "6px 0 0", fontSize: 13 }}>
                            {invoice.risk_summary.issue_count} invoice risk flag{invoice.risk_summary.issue_count === 1 ? "" : "s"} detected. Review the risk checks before emailing or relying on the PDF.
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
                    {loading ? (
                        <FeedbackState title="Loading invoice" description="Fetching the current invoice and payment state." tone="loading" />
                    ) : (
                        <FeedbackState title="Unable to load invoice" description={error?.message} tone="error" />
                    )}
                </div>
                    ) : (
                <InvoiceDetail
                    invoice={invoice}
                    onOpenNoteModal={(nextType) => {
                        setNoteType(nextType);
                        setNoteError("");
                        setShowEditForm(false);
                        setShowPaymentForm(false);
                    }}
                    onOpenPromiseModal={(promise = null) => {
                        setEditingPromise(promise);
                        setPromiseError("");
                        setShowPromiseForm(true);
                    }}
                    onPromiseStatusChange={handleQuickPromiseStatus}
                    onGenerateRecurring={handleGenerateRecurring}
                />
            )}

            {!loading && !error && invoice && noteType ? (
                <Modal
                    title={noteType === "CREDIT" ? "Add Credit Note" : "Add Debit Note"}
                    onClose={() => {
                        setNoteType("");
                        setNoteError("");
                    }}
                >
                    <InlineNotice message={noteError} />
                    <InvoiceNoteForm
                        noteType={noteType}
                        invoice={invoice}
                        onSubmit={async (payload) => {
                            const success = await handleCreateNote(payload);
                            if (success) {
                                setNoteType("");
                                setNoteError("");
                            }
                        }}
                        onCancel={() => {
                            setNoteType("");
                            setNoteError("");
                        }}
                    />
                </Modal>
            ) : null}

            {showPaymentForm && !isFullyPaid && (
                <Modal title="Record Payment" onClose={() => setShowPaymentForm(false)}>
                    <InlineNotice message={actionError} />
                    {canRecordPayment ? (
                        <InvoicePaymentForm
                            invoice={invoice}
                            onSubmit={handlePayment}
                            onCancel={() => setShowPaymentForm(false)}
                            moneyAccounts={moneyAccounts}
                            incomeAccounts={incomeAccounts}
                            submitting={paymentSaving}
                        />
                    ) : (
                        <p className="kb-muted">
                            Add at least one money account and one income account before recording a payment.
                        </p>
                    )}
                </Modal>
            )}

            {showEditForm && invoice ? (
                <Modal title="Edit Invoice" onClose={() => setShowEditForm(false)} width="min(700px, 96vw)">
                    <InlineNotice message={editError} />
                    <InvoiceForm
                        initialData={invoice}
                        submitLabel="Save Changes"
                        onSubmit={handleUpdateInvoice}
                        onCancel={() => setShowEditForm(false)}
                    />
                </Modal>
            ) : null}

            {showRefundForm && invoice ? (
                <Modal title="Cancel & Refund Invoice" onClose={() => setShowRefundForm(false)} width="min(640px, 96vw)">
                    <InlineNotice message={refundError} />
                    {canRefundInvoice ? (
                        <InvoiceRefundForm
                            invoice={invoice}
                            moneyAccounts={moneyAccounts}
                            incomeAccounts={incomeAccounts}
                            onSubmit={handleRefundInvoice}
                            onCancel={() => setShowRefundForm(false)}
                            submitting={refundSaving}
                        />
                    ) : (
                        <p className="kb-muted">
                            {invoice?.refund_block_reason || "This invoice cannot be refunded right now."}
                        </p>
                    )}
                </Modal>
            ) : null}

            {showRecurringForm && invoice ? (
                <Modal title={sourceRecurringProfile ? "Edit Recurring Plan" : "Create Recurring Plan"} onClose={() => setShowRecurringForm(false)} width="min(720px, 96vw)">
                    <InlineNotice message={recurringError} />
                    <RecurringProfileForm
                        sourceInvoice={invoice}
                        initialData={sourceRecurringProfile}
                        submitLabel={sourceRecurringProfile ? "Save Plan" : "Create Plan"}
                        onSubmit={handleRecurringSubmit}
                        onCancel={() => setShowRecurringForm(false)}
                    />
                    {sourceRecurringProfile?.can_generate_now ? (
                        <div className="flex justify-end" style={{ marginTop: 12 }}>
                            <button type="button" className="kb-btn kb-btn--secondary" onClick={() => handleGenerateRecurring(sourceRecurringProfile)}>
                                Generate Due Invoice
                            </button>
                        </div>
                    ) : null}
                </Modal>
            ) : null}

            {showPromiseForm && invoice ? (
                <Modal title={editingPromise ? "Update Promise to Pay" : "Record Promise to Pay"} onClose={() => {
                    setEditingPromise(null);
                    setShowPromiseForm(false);
                }}>
                    <InlineNotice message={promiseError} />
                    <InvoicePromiseForm
                        invoice={invoice}
                        promise={editingPromise}
                        onSubmit={async (payload) => {
                            await handlePromiseSubmit(payload);
                        }}
                        onCancel={() => {
                            setEditingPromise(null);
                            setShowPromiseForm(false);
                        }}
                    />
                </Modal>
            ) : null}

            {showEmailModal && (
                <Modal title="Email Invoice" onClose={() => setShowEmailModal(false)}>
                    <InlineNotice message={emailError} />
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

function Modal({ title, children, onClose, width = "min(420px, 95vw)" }) {
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
            <div className="kb-card" style={{ padding: 24, width, maxHeight: "90vh", overflowY: "auto", borderRadius: "var(--kb-radius-lg)" }}>
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
