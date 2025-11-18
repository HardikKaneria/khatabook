import { useMemo, useState } from "react";
import { Table } from "antd";
import { createAccount } from "./api";
import { useAccounts } from "./hooks";
import AccountsSummary from "./AccountsSummary.jsx";
import MoneyAccountsList from "./MoneyAccountsList.jsx";
import AccountForm from "./AccountForm.jsx";

const isMoneyAccount = (acct) =>
    ["BANK", "CASH", "WALLET"].includes((acct?.sub_type || "").toUpperCase());

export default function AccountsPage() {
    const [refreshKey, setRefreshKey] = useState(0);
    const [showAccountForm, setShowAccountForm] = useState(false);
    const [actionError, setActionError] = useState("");
    const { data, loading, error } = useAccounts({}, refreshKey);
    const accounts = data || [];

    const goToAccount = (id) => {
        if (!id) return;
        window.history.pushState({}, "", `/accounts/${id}`);
        window.dispatchEvent(new PopStateEvent("popstate"));
    };

    const allAccountsTable = useMemo(() => {
        if (loading) return <p className="kb-muted">Loading accounts…</p>;
        if (error) return <p className="text-red-600">{error.message}</p>;
        if (!accounts.length) return <p>No accounts yet.</p>;
        const columns = [
            {
                title: "Name",
                dataIndex: "name",
                key: "name",
                render: (text, record) => (
                    <div>
                        <a
                            className="kb-table__link"
                            href={`/accounts/${record.id}`}
                            onClick={(e) => {
                                e.preventDefault();
                                goToAccount(record.id);
                            }}
                        >
                            {text}
                        </a>
                        <div className="kb-muted" style={{ fontSize: 12 }}>
                            {record.code || record.sub_type || "General"}
                        </div>
                    </div>
                ),
            },
            { title: "Type", dataIndex: "type", key: "type" },
            { title: "Subtype", dataIndex: "sub_type", key: "sub_type", render: (value) => value || "—" },
            { title: "Currency", dataIndex: "currency", key: "currency", render: (value) => value || "INR" },
            {
                title: "Balance",
                dataIndex: "balance",
                key: "balance",
                className: "kb-table__cell--number",
                render: (_, record) => formatCurrency(record.currentBalance ?? record.balance),
            },
        ];
        return (
            <Table
                dataSource={accounts}
                columns={columns}
                pagination={false}
                rowKey="id"
                className="kb-antd-table"
            />
        );
    }, [accounts, loading, error]);

    const handleCreateAccount = async (payload) => {
        try {
            setActionError("");
            await createAccount(payload);
            setShowAccountForm(false);
            setRefreshKey((key) => key + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to create account.");
        }
    };

    const moneyAccounts = accounts.filter(isMoneyAccount);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="kb-h2" style={{ marginBottom: 4 }}>
                        Accounts
                    </h2>
                    <p className="kb-muted" style={{ margin: 0 }}>
                        Monitor balances and quick actions.
                    </p>
                </div>
                <button className="kb-btn kb-btn--primary" onClick={() => setShowAccountForm(true)}>
                    Add Account
                </button>
            </div>

            <div className="">
                {loading && !accounts.length ? (
                    <p>Loading summary…</p>
                ) : (
                    <AccountsSummary accounts={accounts} />
                )}
            </div>

            <div className="">
                <div className="flex items-center justify-between" style={{ marginBottom: 16 }}>
                    <h3 className="kb-h3" style={{ margin: 0 }}>
                        Money Accounts
                    </h3>
                    <span className="kb-muted">Tap a card to open the ledger.</span>
                </div>
                {loading && !accounts.length ? (
                    <p>Loading…</p>
                ) : (
                    <MoneyAccountsList accounts={moneyAccounts} onSelectAccount={goToAccount} />
                )}
            </div>

            <div className="kb-card" style={{ padding: 24 }}>
                <h3 className="kb-h3" style={{ marginBottom: 16 }}>
                    All Accounts
                </h3>
                {allAccountsTable}
            </div>

            {showAccountForm && (
                <Modal title="New Account" onClose={() => setShowAccountForm(false)}>
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    <AccountForm onSubmit={handleCreateAccount} onCancel={() => setShowAccountForm(false)} />
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
            <div className="kb-card" style={{ padding: 24, width: "min(420px, 90vw)", borderRadius: "var(--kb-radius-lg)" }}>
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
