import { useMemo, useState } from "react";
import { Table } from "antd";
import { createAccount } from "./api";
import { useAccounts } from "./hooks";
import AccountsSummary from "./AccountsSummary.jsx";
import MoneyAccountsList from "./MoneyAccountsList.jsx";
import AccountForm from "./AccountForm.jsx";
import FeedbackState from "../../components/ui/FeedbackState.jsx";
import InlineNotice from "../../components/ui/InlineNotice.jsx";

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
        if (loading) {
            return <FeedbackState title="Loading accounts" description="Fetching the current ledger list." tone="loading" />;
        }
        if (error) {
            return <FeedbackState title="Unable to load accounts" description={error.message} tone="error" />;
        }
        if (!accounts.length) {
            return <FeedbackState title="No accounts yet" description="Create an account to start recording balances and transactions." tone="empty" />;
        }
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
        <div className="accounts-page">
            <header className="accounts-header">
                <div>
                    <p className="accounts-eyebrow">Financial overview</p>
                    <h1>Accounts</h1>
                    <p className="accounts-subtitle">Monitor balances and manage ledgers effortlessly.</p>
                </div>
                <button className="accounts-primary-btn" onClick={() => setShowAccountForm(true)}>
                    Add Account
                </button>
            </header>

            <div className="accounts-grid">
                <div className="accounts-column">
                    <section className="accounts-card">
                        {loading && !accounts.length ? (
                            <FeedbackState title="Loading account summary" description="Preparing the balance overview for all current accounts." tone="loading" />
                        ) : (
                            <AccountsSummary accounts={accounts} />
                        )}
                    </section>

                    <section className="accounts-card">
                        <div className="accounts-card-header">
                            <div>
                                <h3>Money Accounts</h3>
                                <p>Tap a card to open the ledger.</p>
                            </div>
                        </div>
                        {loading && !accounts.length ? (
                            <FeedbackState title="Loading money accounts" description="Preparing the current cash and bank accounts." tone="loading" />
                        ) : (
                            <MoneyAccountsList accounts={moneyAccounts} onSelectAccount={goToAccount} />
                        )}
                    </section>
                    <section className="accounts-card accounts-table-card">
                        <div className="accounts-card-header">
                            <div>
                                <h3>All Accounts</h3>
                                <p>Full ledger view with balances and types.</p>
                            </div>
                        </div>
                        {allAccountsTable}
                    </section>
                </div>
            </div>

            {showAccountForm && (
                <Modal title="New Account" onClose={() => setShowAccountForm(false)}>
                    <InlineNotice message={actionError} />
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
