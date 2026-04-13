import { useMemo, useState } from "react";
import { createPayment, createReceipt, createTransfer, deleteAccount, updateAccount } from "./api";
import { useAccount, useAccountStatement, useAccounts } from "./hooks";
import AccountStatementTable from "./AccountStatementTable.jsx";
import MoneyInForm from "./MoneyInForm.jsx";
import MoneyOutForm from "./MoneyOutForm.jsx";
import TransferForm from "./TransferForm.jsx";
import AccountForm from "./AccountForm.jsx";
import FeedbackState from "../../components/ui/FeedbackState.jsx";
import InlineNotice from "../../components/ui/InlineNotice.jsx";

const moneyTypes = new Set(["BANK", "CASH", "WALLET"]);

const getDefaultRange = () => {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 30);
    return {
        from: from.toISOString().slice(0, 10),
        to: to.toISOString().slice(0, 10),
        page: 1,
        per_page: 50,
    };
};

const isMoneyAccount = (acct) => moneyTypes.has((acct?.sub_type || "").toUpperCase());

export default function AccountDetailPage({ accountId }) {
    const [filters, setFilters] = useState(getDefaultRange);
    const [refreshKey, setRefreshKey] = useState(0);
    const [showMoneyIn, setShowMoneyIn] = useState(false);
    const [showMoneyOut, setShowMoneyOut] = useState(false);
    const [showTransfer, setShowTransfer] = useState(false);
    const [showEditForm, setShowEditForm] = useState(false);
    const [moneyInError, setMoneyInError] = useState("");
    const [moneyOutError, setMoneyOutError] = useState("");
    const [transferError, setTransferError] = useState("");
    const [lifecycleError, setLifecycleError] = useState("");
    const [lifecycleInfo, setLifecycleInfo] = useState("");
    const [accountSaving, setAccountSaving] = useState(false);

    const { data: account, loading: accountLoading, error: accountError } = useAccount(accountId, refreshKey);
    const {
        data: statement,
        loading: statementLoading,
        error: statementError,
    } = useAccountStatement(accountId, filters, refreshKey);
    const { data: allAccounts } = useAccounts({}, refreshKey);

    const safeAccounts = Array.isArray(allAccounts) ? allAccounts : [];
    const moneyAccounts = useMemo(() => safeAccounts.filter(isMoneyAccount), [safeAccounts]);
    const otherAccounts = useMemo(() => safeAccounts.filter((acct) => !isMoneyAccount(acct)), [safeAccounts]);
    const accountLifecycle = account?.lifecycle || {};
    const isArchived = (account?.status || "ACTIVE") === "ARCHIVED";
    const canDeletePermanently = Boolean(accountLifecycle?.can_delete_permanently);
    const canEditStructure = Boolean(accountLifecycle?.can_edit_structure);
    const canEditAccount = Boolean(accountLifecycle?.can_edit);
    const canChangeStatus = Boolean(accountLifecycle?.can_change_status);

    const defaultMoneyAccountId = isMoneyAccount(account) ? account?.id : undefined;

    const handleMoneyIn = async (payload) => {
        try {
            setMoneyInError("");
            await createReceipt(payload);
            setShowMoneyIn(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setMoneyInError(err?.message || "Unable to record receipt.");
        }
    };

    const handleMoneyOut = async (payload) => {
        try {
            setMoneyOutError("");
            await createPayment(payload);
            setShowMoneyOut(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setMoneyOutError(err?.message || "Unable to record payment.");
        }
    };

    const handleTransfer = async (payload) => {
        try {
            setTransferError("");
            await createTransfer(payload);
            setShowTransfer(false);
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setTransferError(err?.message || "Unable to transfer amount.");
        }
    };

    const handleUpdateAccount = async (payload) => {
        if (!account?.id) return;
        try {
            setLifecycleError("");
            setLifecycleInfo("");
            setAccountSaving(true);
            await updateAccount(account.id, payload);
            setShowEditForm(false);
            setRefreshKey((value) => value + 1);
            setLifecycleInfo("Account details updated.");
        } catch (err) {
            setLifecycleError(err?.message || "Unable to update account.");
        } finally {
            setAccountSaving(false);
        }
    };

    const handleArchiveToggle = async () => {
        if (!account?.id || !canChangeStatus) return;
        try {
            setLifecycleError("");
            setLifecycleInfo("");
            setAccountSaving(true);
            await updateAccount(account.id, { status: isArchived ? "ACTIVE" : "ARCHIVED" });
            setRefreshKey((value) => value + 1);
            setLifecycleInfo(isArchived ? "Account reactivated for future transactions." : "Account archived for future transactions.");
        } catch (err) {
            setLifecycleError(err?.message || "Unable to update account status.");
        } finally {
            setAccountSaving(false);
        }
    };

    const handleDeleteAccount = async () => {
        if (!account?.id) return;
        const confirmed = window.confirm(
            canDeletePermanently
                ? "Delete this unused account permanently?"
                : "This account has journal history and will be archived instead of deleted. Continue?"
        );
        if (!confirmed) {
            return;
        }

        try {
            setLifecycleError("");
            setLifecycleInfo("");
            setAccountSaving(true);
            const result = await deleteAccount(account.id);
            if (result?.action === "deleted") {
                window.history.pushState({}, "", "/accounts");
                window.dispatchEvent(new PopStateEvent("popstate"));
                return;
            }
            setRefreshKey((value) => value + 1);
            setLifecycleInfo("Account archived because it has historical journal activity.");
        } catch (err) {
            setLifecycleError(err?.message || "Unable to remove account.");
        } finally {
            setAccountSaving(false);
        }
    };

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const header = (() => {
        if (accountLoading) {
            return <FeedbackState title="Loading account" description="Fetching the account header and current balance." tone="loading" />;
        }
        if (accountError) {
            return <FeedbackState title="Unable to load account" description={accountError.message} tone="error" />;
        }
        if (!account) return null;
        return (
            <div>
                <h2 className="kb-h2" style={{ margin: 0 }}>
                    {account.name}
                </h2>
                <p className="kb-muted">
                    {account.type} · {account.sub_type || "General"}
                </p>
                <p style={{ fontSize: 18, fontWeight: 600 }}>
                    Balance: {formatCurrency(account.currentBalance ?? account.balance)}
                </p>
                <p className="kb-muted" style={{ margin: "6px 0 0" }}>
                    {(accountLifecycle?.status || account?.status || "ACTIVE") === "ARCHIVED" ? "Inactive account" : "Active account"}
                    {accountLifecycle?.status_note ? ` · ${accountLifecycle.status_note}` : ""}
                </p>
            </div>
        );
    })();

    return (
        <div className="space-y-4">
            <header className="kb-card" style={{ padding: 24 }}>
                {header}
                <InlineNotice message={lifecycleError} />
                {lifecycleInfo ? (
                    <p className="kb-muted" style={{ margin: "8px 0 0" }}>
                        {lifecycleInfo}
                    </p>
                ) : null}
                <div className="flex gap-2 mt-3 flex-wrap">
                    <button
                        className="kb-btn kb-btn--secondary"
                        disabled={!account || !canEditAccount || accountSaving}
                        onClick={() => {
                            setLifecycleError("");
                            setLifecycleInfo("");
                            setShowEditForm(true);
                        }}
                    >
                        Edit Account
                    </button>
                    <button
                        className="kb-btn kb-btn--ghost"
                        disabled={!account || !canChangeStatus || accountSaving}
                        onClick={handleArchiveToggle}
                    >
                        {isArchived ? "Reactivate" : "Archive"}
                    </button>
                    <button
                        className="kb-btn kb-btn--ghost"
                        disabled={!account || accountSaving || (!canDeletePermanently && (!canChangeStatus || isArchived))}
                        onClick={handleDeleteAccount}
                    >
                        {canDeletePermanently ? "Delete Account" : "Archive Instead"}
                    </button>
                    <button
                        className="kb-btn kb-btn--primary"
                        disabled={!moneyAccounts.length || !account || isArchived}
                        onClick={() => setShowMoneyIn(true)}
                    >
                        Money In
                    </button>
                    <button
                        className="kb-btn kb-btn--secondary"
                        disabled={!moneyAccounts.length || !otherAccounts.length || !account || isArchived}
                        onClick={() => setShowMoneyOut(true)}
                    >
                        Money Out
                    </button>
                    <button
                        className="kb-btn kb-btn--ghost"
                        disabled={moneyAccounts.length < 2 || !account || isArchived}
                        onClick={() => setShowTransfer(true)}
                    >
                        Transfer
                    </button>
                </div>
                {isArchived ? (
                    <p className="kb-muted" style={{ margin: "8px 0 0" }}>
                        Archived accounts remain visible in history but cannot be used for new receipts, payments, or transfers.
                    </p>
                ) : null}
            </header>

            <section className="kb-card" style={{ padding: 24 }}>
                <div className="kb-filter-grid" style={{ marginBottom: 16 }}>
                    <div className="kb-filter-field">
                        <label>From</label>
                        <input type="date" className="kb-input" value={filters.from} onChange={handleFilterChange("from")} />
                    </div>
                    <div className="kb-filter-field">
                        <label>To</label>
                        <input type="date" className="kb-input" value={filters.to} onChange={handleFilterChange("to")} />
                    </div>
                </div>
                <AccountStatementTable lines={statement?.lines || []} loading={statementLoading} error={statementError} />
            </section>

            {showMoneyIn && (
                <Modal title="Record Receipt" onClose={() => setShowMoneyIn(false)}>
                    <InlineNotice message={moneyInError} />
                    <MoneyInForm
                        onSubmit={handleMoneyIn}
                        onCancel={() => setShowMoneyIn(false)}
                        moneyAccounts={moneyAccounts}
                        otherAccounts={otherAccounts}
                        defaultToAccountId={defaultMoneyAccountId}
                    />
                </Modal>
            )}

            {showMoneyOut && (
                <Modal title="Record Payment" onClose={() => setShowMoneyOut(false)}>
                    <InlineNotice message={moneyOutError} />
                    <MoneyOutForm
                        onSubmit={handleMoneyOut}
                        onCancel={() => setShowMoneyOut(false)}
                        moneyAccounts={moneyAccounts}
                        otherAccounts={otherAccounts}
                        defaultFromAccountId={defaultMoneyAccountId}
                    />
                </Modal>
            )}

            {showTransfer && (
                <Modal title="Transfer Funds" onClose={() => setShowTransfer(false)}>
                    <InlineNotice message={transferError} />
                    <TransferForm
                        onSubmit={handleTransfer}
                        onCancel={() => setShowTransfer(false)}
                        moneyAccounts={moneyAccounts}
                        defaultFromAccountId={defaultMoneyAccountId}
                    />
                </Modal>
            )}

            {showEditForm && account ? (
                <Modal title="Edit Account" onClose={() => setShowEditForm(false)}>
                    <InlineNotice message={lifecycleError} />
                    <AccountForm
                        initialData={account}
                        submitLabel="Save Changes"
                        onSubmit={handleUpdateAccount}
                        onCancel={() => setShowEditForm(false)}
                        loading={accountSaving}
                        showStatusField
                        structuralFieldsDisabled={!canEditStructure}
                        helpText={!canEditStructure ? "This account already has journal history, so only its display fields and active status can be changed." : ""}
                    />
                </Modal>
            ) : null}
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
            <div className="kb-card" style={{ padding: 24, width: "min(440px, 90vw)", borderRadius: "var(--kb-radius-lg)" }}>
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

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
