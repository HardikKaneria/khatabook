const moneyTypes = new Set(["BANK", "CASH", "WALLET"]);

const formatCurrency = (value) => Number(value ?? 0).toLocaleString();

export default function MoneyAccountsList({ accounts = [], onSelectAccount }) {
    const moneyAccounts = accounts.filter((acct) =>
        moneyTypes.has((acct.sub_type || "").toUpperCase())
    );

    if (!moneyAccounts.length) {
        return <p className="kb-muted">No money accounts yet.</p>;
    }

    return (
        <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 16 }}>
            {moneyAccounts.map((acct) => (
                <button
                    key={acct.id}
                    onClick={() => onSelectAccount?.(acct.id)}
                    className="kb-card"
                    style={{
                        padding: 20,
                        textAlign: "left",
                        borderRadius: "var(--kb-radius-lg)",
                        boxShadow: "var(--kb-shadow-md)",
                        border: "1px solid var(--kb-color-border)",
                    }}
                >
                    <div className="flex items-center justify-between" style={{ marginBottom: 8 }}>
                        <p style={{ fontWeight: 600, fontSize: 16 }}>{acct.name}</p>
                        <span
                            style={{
                                background: "var(--kb-color-gray-100)",
                                color: "var(--kb-color-primary)",
                                padding: "4px 10px",
                                borderRadius: 999,
                                fontSize: 12,
                                fontWeight: 600,
                            }}
                        >
                            {acct.sub_type || "Money"}
                        </span>
                    </div>
                    <p className="kb-muted" style={{ marginBottom: 8 }}>
                        {acct.type}
                    </p>
                    <p style={{ fontSize: 20, fontWeight: 700 }}>
                        ₹ {formatCurrency(acct.currentBalance ?? acct.balance)}
                    </p>
                </button>
            ))}
        </div>
    );
}
