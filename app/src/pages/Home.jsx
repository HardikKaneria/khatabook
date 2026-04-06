import { useEffect, useMemo, useState } from "react";
import { getAccounts } from "../modules/accounts/api";
import { getExpenses } from "../modules/expenses/api";
import { getInvoices } from "../modules/invoices/api";
import { getPayments } from "../modules/payments/api";
import { getGstSummary, getProfitSummary, getReceivablesSummary, getTaxEstimate } from "../modules/reports/api";

const isMoneyAccount = (account) =>
    ["BANK", "CASH", "WALLET"].includes(String(account?.sub_type || "").toUpperCase());

const navigate = (path) => {
    window.history.pushState({}, "", path);
    window.dispatchEvent(new PopStateEvent("popstate"));
};

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const buildRange = () => {
    const today = new Date();
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    return {
        from: monthStart.toISOString().slice(0, 10),
        to: today.toISOString().slice(0, 10),
    };
};

export default function Home({ user }) {
    const [state, setState] = useState({
        loading: true,
        error: "",
        invoices: [],
        payments: [],
        expenses: [],
        accounts: [],
        receivables: null,
        profit: null,
        gst: null,
        tax: null,
    });

    useEffect(() => {
        let cancelled = false;
        const range = buildRange();

        (async () => {
            setState((prev) => ({ ...prev, loading: true, error: "" }));

            const results = await Promise.allSettled([
                getInvoices({ per_page: 5 }),
                getPayments({ per_page: 5 }),
                getExpenses({ per_page: 5, status: "ACTIVE" }),
                getAccounts(),
                getReceivablesSummary({ ...range, as_of: range.to }),
                getProfitSummary(range),
                getGstSummary(range),
                getTaxEstimate(range),
            ]);

            if (cancelled) {
                return;
            }

            const [
                recentInvoicesResult,
                recentPaymentsResult,
                recentExpensesResult,
                accountsResult,
                receivablesResult,
                profitResult,
                gstResult,
                taxResult,
            ] = results;

            const invoiceError = recentInvoicesResult.status === "rejected" ? recentInvoicesResult.reason : null;
            const expenseError = recentExpensesResult.status === "rejected" ? recentExpensesResult.reason : null;
            const accountError = accountsResult.status === "rejected" ? accountsResult.reason : null;
            const paymentError = recentPaymentsResult.status === "rejected" ? recentPaymentsResult.reason : null;
            const reportError =
                receivablesResult.status === "rejected"
                    ? receivablesResult.reason
                    : profitResult.status === "rejected"
                    ? profitResult.reason
                    : gstResult.status === "rejected"
                        ? gstResult.reason
                        : taxResult.status === "rejected"
                            ? taxResult.reason
                            : null;

            setState({
                loading: false,
                error: invoiceError?.message || paymentError?.message || expenseError?.message || accountError?.message || reportError?.message || "",
                invoices: recentInvoicesResult.status === "fulfilled" ? (recentInvoicesResult.value?.data || []) : [],
                payments: recentPaymentsResult.status === "fulfilled" ? (recentPaymentsResult.value?.data || []) : [],
                expenses: recentExpensesResult.status === "fulfilled" ? (recentExpensesResult.value?.data || []) : [],
                accounts: accountsResult.status === "fulfilled" ? (accountsResult.value || []) : [],
                receivables: receivablesResult.status === "fulfilled" ? receivablesResult.value : null,
                profit: profitResult.status === "fulfilled" ? profitResult.value : null,
                gst: gstResult.status === "fulfilled" ? gstResult.value : null,
                tax: taxResult.status === "fulfilled" ? taxResult.value : null,
            });
        })();

        return () => {
            cancelled = true;
        };
    }, [user?.orgId]);

    const moneyAccounts = useMemo(
        () => state.accounts.filter(isMoneyAccount).sort((left, right) => Number(right.balance || 0) - Number(left.balance || 0)),
        [state.accounts]
    );

    const receivables = useMemo(() => {
        return {
            outstandingAmount: Number(state.receivables?.outstanding_amount || 0),
            overdueAmount: Number(state.receivables?.overdue_amount || 0),
            overdueCount: Number(state.receivables?.overdue_count || 0),
            invoiceCount: Number(state.receivables?.open_invoice_count || 0),
        };
    }, [state.receivables]);

    const monthExpenseTotal = useMemo(
        () => state.expenses.reduce((sum, expense) => sum + Number(expense?.amount || 0), 0),
        [state.expenses]
    );

    return (
        <div className="dashboard-home">
            <header className="dashboard-home__hero">
                <div>
                    <p className="dashboard-home__eyebrow">Operational home</p>
                    <h1>{user?.orgName ? `${user.orgName} Overview` : "Business Overview"}</h1>
                    <p className="dashboard-home__subtitle">
                        Track receivables, spending, balances, and tax exposure from the current organization in one place.
                    </p>
                </div>
                <div className="dashboard-home__actions">
                    <button className="kb-btn kb-btn--primary" onClick={() => navigate("/invoices?create=1")}>
                        New Invoice
                    </button>
                    <button className="kb-btn kb-btn--secondary" onClick={() => navigate("/expenses?create=1")}>
                        Add Expense
                    </button>
                    <button className="kb-btn kb-btn--ghost" onClick={() => navigate("/contacts?create=1")}>
                        Add Contact
                    </button>
                </div>
            </header>

            {state.error ? (
                <div className="kb-alert kb-alert--warning" style={{ marginBottom: 20 }}>
                    <div>
                        Some dashboard sections could not be refreshed. Showing the most recent data that was available.
                        <div style={{ marginTop: 4 }}>{state.error}</div>
                    </div>
                </div>
            ) : null}

            <section className="dashboard-home__kpis">
                <MetricCard
                    label="Open Receivables"
                    value={`₹ ${formatCurrency(receivables.outstandingAmount)}`}
                    detail={`${receivables.invoiceCount} invoice${receivables.invoiceCount === 1 ? "" : "s"} awaiting payment`}
                    loading={state.loading}
                />
                <MetricCard
                    label="Overdue Amount"
                    value={`₹ ${formatCurrency(receivables.overdueAmount)}`}
                    detail={receivables.overdueCount ? `${receivables.overdueCount} overdue invoice${receivables.overdueCount === 1 ? "" : "s"}` : "No overdue invoices in the loaded set"}
                    tone={receivables.overdueAmount > 0 ? "warning" : "neutral"}
                    loading={state.loading}
                />
                <MetricCard
                    label="Money Account Balance"
                    value={`₹ ${formatCurrency(moneyAccounts.reduce((sum, account) => sum + Number(account?.balance || 0), 0))}`}
                    detail={`${moneyAccounts.length} active bank, cash, or wallet accounts`}
                    loading={state.loading}
                />
                <MetricCard
                    label="Month Profit"
                    value={`₹ ${formatCurrency(state.profit?.profit || 0)}`}
                    detail={`Estimated tax ₹ ${formatCurrency(state.tax?.estimated_income_tax || 0)}`}
                    loading={state.loading}
                />
            </section>

            <div className="dashboard-home__grid">
                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Recent Invoices"
                        subtitle="Latest billing activity and payment exposure."
                        actionLabel="Open invoices"
                        onAction={() => navigate("/invoices")}
                    />
                    {state.loading ? (
                        <p className="kb-muted">Loading invoices…</p>
                    ) : state.invoices.length ? (
                        <div className="dashboard-home__list">
                            {state.invoices.map((invoice) => (
                                <button
                                    key={invoice.id}
                                    type="button"
                                    className="dashboard-home__list-item"
                                    onClick={() => navigate(`/invoices/${invoice.id}`)}
                                >
                                    <div>
                                        <strong>{invoice.invoice_number}</strong>
                                        <span>{invoice.customer_name || "Customer"}</span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>{invoice.due_date || invoice.date}</span>
                                        <strong>₹ {formatCurrency(invoice.balance_due ?? invoice.total)}</strong>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No invoices yet"
                            description="Create the first invoice to start tracking sales and receivables."
                            actionLabel="Create Invoice"
                            onAction={() => navigate("/invoices?create=1")}
                        />
                    )}
                </section>

                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Recent Expenses"
                        subtitle="Latest spend captured in the current organization."
                        actionLabel="Open expenses"
                        onAction={() => navigate("/expenses")}
                    />
                    {state.loading ? (
                        <p className="kb-muted">Loading expenses…</p>
                    ) : state.expenses.length ? (
                        <div className="dashboard-home__list">
                            {state.expenses.map((expense) => (
                                <button
                                    key={expense.id}
                                    type="button"
                                    className="dashboard-home__list-item"
                                    onClick={() => navigate(`/expenses/${expense.id}`)}
                                >
                                    <div>
                                        <strong>{expense.category}</strong>
                                        <span>{expense.payee || "Payee not set"}</span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>{expense.expense_date}</span>
                                        <strong>₹ {formatCurrency(expense.amount)}</strong>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No expenses recorded"
                            description="Record your first expense so operations and tax summaries stay current."
                            actionLabel="Add Expense"
                            onAction={() => navigate("/expenses?create=1")}
                        />
                    )}
                </section>

                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Recent Payments"
                        subtitle="Latest invoice receipts recorded against the active organization."
                        actionLabel="Open payments"
                        onAction={() => navigate("/payments")}
                    />
                    {state.loading ? (
                        <p className="kb-muted">Loading payments…</p>
                    ) : state.payments.length ? (
                        <div className="dashboard-home__list">
                            {state.payments.map((payment) => (
                                <button
                                    key={payment.id}
                                    type="button"
                                    className="dashboard-home__list-item"
                                    onClick={() => navigate(payment.invoice?.id ? `/invoices/${payment.invoice.id}` : "/payments")}
                                >
                                    <div>
                                        <strong>{payment.invoice?.invoice_number || "Invoice payment"}</strong>
                                        <span>{payment.account?.name || `Journal #${payment.journal_id || "—"}`}</span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>{payment.date}</span>
                                        <strong>₹ {formatCurrency(payment.amount)}</strong>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No payments recorded"
                            description="Invoice receipts will appear here once collections are posted."
                            actionLabel="Open Invoices"
                            onAction={() => navigate("/invoices")}
                        />
                    )}
                </section>
            </div>

            <div className="dashboard-home__grid">
                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Money Accounts"
                        subtitle="Top balances across bank, cash, and wallet accounts."
                        actionLabel="Open accounts"
                        onAction={() => navigate("/accounts")}
                    />
                    {state.loading ? (
                        <p className="kb-muted">Loading accounts…</p>
                    ) : moneyAccounts.length ? (
                        <div className="dashboard-home__list">
                            {moneyAccounts.slice(0, 5).map((account) => (
                                <button
                                    key={account.id}
                                    type="button"
                                    className="dashboard-home__list-item"
                                    onClick={() => navigate(`/accounts/${account.id}`)}
                                >
                                    <div>
                                        <strong>{account.name}</strong>
                                        <span>{account.sub_type || account.type}</span>
                                    </div>
                                    <div className="dashboard-home__list-meta">
                                        <span>{account.currency || "INR"}</span>
                                        <strong>₹ {formatCurrency(account.balance)}</strong>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No money accounts"
                            description="Add at least one bank, cash, or wallet account to track liquidity."
                            actionLabel="Open Accounts"
                            onAction={() => navigate("/accounts")}
                        />
                    )}
                </section>

                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Tax and Profit Snapshot"
                        subtitle="Current-month reporting pulled from the live org data."
                        actionLabel="Open reports"
                        onAction={() => navigate("/reports")}
                    />
                    {state.loading ? (
                        <p className="kb-muted">Loading summaries…</p>
                    ) : (
                        <div className="dashboard-home__snapshot-grid">
                            <SnapshotCard
                                label="Income"
                                value={`₹ ${formatCurrency(state.profit?.income?.total || 0)}`}
                            />
                            <SnapshotCard
                                label="Expenses"
                                value={`₹ ${formatCurrency(state.profit?.expense?.total || monthExpenseTotal || 0)}`}
                            />
                            <SnapshotCard
                                label="Output GST"
                                value={`₹ ${formatCurrency(state.gst?.output_tax || 0)}`}
                            />
                            <SnapshotCard
                                label="Input GST"
                                value={`₹ ${formatCurrency(state.gst?.input_tax || 0)}`}
                            />
                            <SnapshotCard
                                label="Net GST"
                                value={`₹ ${formatCurrency(state.gst?.net_gst_payable || 0)}`}
                            />
                            <SnapshotCard
                                label="Income Tax Estimate"
                                value={`₹ ${formatCurrency(state.tax?.estimated_income_tax || 0)}`}
                            />
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}

function MetricCard({ label, value, detail, tone = "neutral", loading = false }) {
    return (
        <article className={`dashboard-home__metric dashboard-home__metric--${tone}`}>
            <span>{label}</span>
            <strong>{loading ? "Loading…" : value}</strong>
            <p>{detail}</p>
        </article>
    );
}

function SectionHeader({ title, subtitle, actionLabel, onAction }) {
    return (
        <div className="dashboard-home__section-header">
            <div>
                <h3>{title}</h3>
                <p>{subtitle}</p>
            </div>
            {actionLabel ? (
                <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={onAction}>
                    {actionLabel}
                </button>
            ) : null}
        </div>
    );
}

function EmptyState({ title, description, actionLabel, onAction }) {
    return (
        <div className="dashboard-home__empty">
            <h4>{title}</h4>
            <p>{description}</p>
            {actionLabel ? (
                <button type="button" className="kb-btn kb-btn--secondary kb-btn--small" onClick={onAction}>
                    {actionLabel}
                </button>
            ) : null}
        </div>
    );
}

function SnapshotCard({ label, value }) {
    return (
        <div className="dashboard-home__snapshot-card">
            <span>{label}</span>
            <strong>{value}</strong>
        </div>
    );
}
