import { useMemo, useState } from "react";
import { createPayment, createReceipt, createTransfer } from "./api";
import { useAccount, useAccountStatement, useAccounts } from "./hooks";
import AccountStatementTable from "./AccountStatementTable.jsx";
import MoneyInForm from "./MoneyInForm.jsx";
import MoneyOutForm from "./MoneyOutForm.jsx";
import TransferForm from "./TransferForm.jsx";

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
    const [moneyInError, setMoneyInError] = useState("");
    const [moneyOutError, setMoneyOutError] = useState("");
    const [transferError, setTransferError] = useState("");

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

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const header = (() => {
        if (accountLoading) {
            return <p>Loading account…</p>;
        }
        if (accountError) {
            return <p className="text-red-600">{accountError.message}</p>;
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
            </div>
        );
    })();

    return (
        <div className="space-y-4">
            <header className="kb-card" style={{ padding: 24 }}>
                {header}
                <div className="flex gap-2 mt-3 flex-wrap">
                    <button
                        className="kb-btn kb-btn--primary"
                        disabled={!moneyAccounts.length || !account}
                        onClick={() => setShowMoneyIn(true)}
                    >
                        Money In
                    </button>
                    <button
                        className="kb-btn kb-btn--secondary"
                        disabled={!moneyAccounts.length || !otherAccounts.length || !account}
                        onClick={() => setShowMoneyOut(true)}
                    >
                        Money Out
                    </button>
                    <button
                        className="kb-btn kb-btn--ghost"
                        disabled={moneyAccounts.length < 2 || !account}
                        onClick={() => setShowTransfer(true)}
                    >
                        Transfer
                    </button>
                </div>
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
                    {moneyInError ? <p className="text-red-600 text-sm">{moneyInError}</p> : null}
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
                    {moneyOutError ? <p className="text-red-600 text-sm">{moneyOutError}</p> : null}
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
                    {transferError ? <p className="text-red-600 text-sm">{transferError}</p> : null}
                    <TransferForm
                        onSubmit={handleTransfer}
                        onCancel={() => setShowTransfer(false)}
                        moneyAccounts={moneyAccounts}
                        defaultFromAccountId={defaultMoneyAccountId}
                    />
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
