const moneyTypes = new Set(["BANK", "CASH", "WALLET"]);

const formatCurrency = (value) => Number(value ?? 0).toLocaleString();

export default function AccountsSummary({ accounts = [] }) {
    const summary = accounts.reduce(
        (acc, acct) => {
            const balance = Number(acct.currentBalance ?? acct.balance ?? 0);
            if (moneyTypes.has((acct.sub_type || "").toUpperCase())) {
                acc.total += balance;
            }
            if ((acct.sub_type || "").toUpperCase() === "BANK") {
                acc.bank += balance;
            }
            if ((acct.sub_type || "").toUpperCase() === "CASH") {
                acc.cash += balance;
            }
            return acc;
        },
        { total: 0, bank: 0, cash: 0 }
    );

    const cards = [
        { label: "Total Balance", value: summary.total, accent: "var(--kb-color-primary)" },
        { label: "Bank Accounts", value: summary.bank, accent: "#4b3fc0" },
        { label: "Cash in Hand", value: summary.cash, accent: "#e84393" },
    ];

    return (
        <div className="grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 16 }}>
            {cards.map((card) => (
                <div
                    key={card.label}
                    className="kb-card"
                    style={{
                        padding: 20,
                        borderRadius: "var(--kb-radius-lg)",
                        boxShadow: "var(--kb-shadow-md)",
                        borderTop: `4px solid ${card.accent}`,
                    }}
                >
                    <p className="kb-muted" style={{ marginBottom: 4 }}>
                        {card.label}
                    </p>
                    <p style={{ fontSize: 24, fontWeight: 700 }}>
                        ₹ {formatCurrency(card.value)}
                    </p>
                </div>
            ))}
        </div>
    );
}
