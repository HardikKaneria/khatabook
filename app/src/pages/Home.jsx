import { useMemo } from "react";
import { getAccounts } from "../modules/accounts/api";
import { getExpenses } from "../modules/expenses/api";
import { getInvoices } from "../modules/invoices/api";
import { getPayments } from "../modules/payments/api";
import {
    getBillingHealth,
    getGstSummary,
    getOwnerDailyBrief,
    getProfitSummary,
    getReceivablesSummary,
    getRevenueLeaks,
    getTaxEstimate,
} from "../modules/reports/api";
import RevenueLeakPanel from "../modules/reports/RevenueLeakPanel.jsx";
import useAsyncResource from "../hooks/useAsyncResource";

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

const EMPTY_HOME_STATE = {
    error: "",
    invoices: [],
    payments: [],
    expenses: [],
    accounts: [],
    receivables: null,
    profit: null,
    gst: null,
    tax: null,
    health: null,
    brief: null,
    revenueLeaks: null,
};

async function loadHomeState() {
    const range = buildRange();
    const results = await Promise.allSettled([
        getInvoices({ per_page: 5 }),
        getPayments({ per_page: 5 }),
        getExpenses({ per_page: 5, status: "ACTIVE" }),
        getAccounts(),
        getReceivablesSummary({ ...range, as_of: range.to }),
        getProfitSummary(range),
        getGstSummary(range),
        getTaxEstimate(range),
        getBillingHealth({ ...range, as_of: range.to }),
        getOwnerDailyBrief({ as_of: range.to }),
        getRevenueLeaks({ ...range, as_of: range.to }),
    ]);

    const [
        recentInvoicesResult,
        recentPaymentsResult,
        recentExpensesResult,
        accountsResult,
        receivablesResult,
        profitResult,
        gstResult,
        taxResult,
        healthResult,
        briefResult,
        revenueLeakResult,
    ] = results;

    const invoiceError = recentInvoicesResult.status === "rejected" ? recentInvoicesResult.reason : null;
    const expenseError = recentExpensesResult.status === "rejected" ? recentExpensesResult.reason : null;
    const accountError = accountsResult.status === "rejected" ? accountsResult.reason : null;
    const paymentError = recentPaymentsResult.status === "rejected" ? recentPaymentsResult.reason : null;
    const reportError = [
        receivablesResult,
        profitResult,
        gstResult,
        taxResult,
        healthResult,
        briefResult,
        revenueLeakResult,
    ].find((result) => result.status === "rejected")?.reason || null;

    return {
        error: invoiceError?.message || paymentError?.message || expenseError?.message || accountError?.message || reportError?.message || "",
        invoices: recentInvoicesResult.status === "fulfilled" ? (recentInvoicesResult.value?.data || []) : [],
        payments: recentPaymentsResult.status === "fulfilled" ? (recentPaymentsResult.value?.data || []) : [],
        expenses: recentExpensesResult.status === "fulfilled" ? (recentExpensesResult.value?.data || []) : [],
        accounts: accountsResult.status === "fulfilled" ? (accountsResult.value || []) : [],
        receivables: receivablesResult.status === "fulfilled" ? receivablesResult.value : null,
        profit: profitResult.status === "fulfilled" ? profitResult.value : null,
        gst: gstResult.status === "fulfilled" ? gstResult.value : null,
        tax: taxResult.status === "fulfilled" ? taxResult.value : null,
        health: healthResult.status === "fulfilled" ? healthResult.value : null,
        brief: briefResult.status === "fulfilled" ? briefResult.value : null,
        revenueLeaks: revenueLeakResult.status === "fulfilled" ? revenueLeakResult.value : null,
    };
}

export default function Home({ user }) {
    const { data: loadedState, loading } = useAsyncResource(() => loadHomeState(), [user?.orgId]);
    const state = loadedState || EMPTY_HOME_STATE;

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
            averageDaysOverdue: Number(state.receivables?.average_days_overdue || 0),
            asOf: state.receivables?.as_of || "",
        };
    }, [state.receivables]);

    const monthExpenseTotal = useMemo(
        () => state.expenses.reduce((sum, expense) => sum + Number(expense?.amount || 0), 0),
        [state.expenses]
    );

    const healthSummary = state.health || null;
    const briefSummary = state.brief || null;
    const revenueLeakSummary = state.revenueLeaks || null;

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
                    detail={
                        receivables.invoiceCount
                            ? `${receivables.invoiceCount} invoice${receivables.invoiceCount === 1 ? "" : "s"} awaiting payment as of ${receivables.asOf || "today"}`
                            : `No open invoices as of ${receivables.asOf || "today"}`
                    }
                    loading={loading}
                />
                <MetricCard
                    label="Overdue Amount"
                    value={`₹ ${formatCurrency(receivables.overdueAmount)}`}
                    detail={
                        receivables.overdueCount
                            ? `${receivables.overdueCount} overdue invoice${receivables.overdueCount === 1 ? "" : "s"} · avg ${receivables.averageDaysOverdue.toFixed(1)} days late`
                            : `No overdue invoices as of ${receivables.asOf || "today"}`
                    }
                    tone={receivables.overdueAmount > 0 ? "warning" : "neutral"}
                    loading={loading}
                />
                <MetricCard
                    label="Money Account Balance"
                    value={`₹ ${formatCurrency(moneyAccounts.reduce((sum, account) => sum + Number(account?.balance || 0), 0))}`}
                    detail={`${moneyAccounts.length} active bank, cash, or wallet accounts`}
                    loading={loading}
                />
                <MetricCard
                    label="Month Profit"
                    value={`₹ ${formatCurrency(state.profit?.profit || 0)}`}
                    detail={`Estimated tax ₹ ${formatCurrency(state.tax?.estimated_income_tax || 0)}`}
                    loading={loading}
                />
            </section>

            <div className="dashboard-home__grid">
                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Billing Health Score"
                        subtitle="A transparent score built from live receivables, payables, promise, and margin signals."
                        actionLabel="Open reports"
                        onAction={() => navigate("/reports")}
                    />
                    {loading ? (
                        <p className="kb-muted">Loading billing health…</p>
                    ) : healthSummary ? (
                        <div className="dashboard-home__health">
                            <div className={`dashboard-home__health-score dashboard-home__health-score--${healthSummary.status || "steady"}`}>
                                <strong>{healthSummary.score ?? 0}</strong>
                                <span>/ {healthSummary.max_score ?? 100}</span>
                            </div>
                            <div className="dashboard-home__health-copy">
                                <h4>{healthSummary.headline || "Billing health summary"}</h4>
                                <p>{healthSummary.as_of ? `As of ${healthSummary.as_of}.` : "Live health summary."}</p>
                            </div>
                            <div className="dashboard-home__health-components">
                                {(healthSummary.components || []).map((component) => (
                                    <div key={component.key} className="dashboard-home__health-component">
                                        <div>
                                            <strong>{component.label}</strong>
                                            <p>{component.detail}</p>
                                        </div>
                                        <span>{component.points}/{component.max_points}</span>
                                    </div>
                                ))}
                            </div>
                            {Array.isArray(healthSummary.actions) && healthSummary.actions.length ? (
                                <div className="dashboard-home__brief-list">
                                    {healthSummary.actions.map((action) => (
                                        <p key={action}>- {action}</p>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    ) : (
                        <EmptyState
                            title="Billing health unavailable"
                            description="Health scoring needs current receivables, payables, promise, and margin data."
                            actionLabel="Open reports"
                            onAction={() => navigate("/reports")}
                        />
                    )}
                </section>

                <section className="dashboard-home__card">
                    <SectionHeader
                        title="Owner Daily Brief"
                        subtitle="Yesterday’s movement plus today’s collection and vendor-payment follow-ups."
                        actionLabel="Open reports"
                        onAction={() => navigate("/reports")}
                    />
                    {loading ? (
                        <p className="kb-muted">Loading daily brief…</p>
                    ) : briefSummary ? (
                        <div className="dashboard-home__brief">
                            <div className="dashboard-home__snapshot-grid">
                                <SnapshotCard
                                    label="Collections Yesterday"
                                    value={`₹ ${formatCurrency(briefSummary.summary?.collections_yesterday_amount || 0)}`}
                                />
                                <SnapshotCard
                                    label="Invoices Yesterday"
                                    value={`${briefSummary.summary?.invoices_created_yesterday_count || 0}`}
                                />
                                <SnapshotCard
                                    label="Expenses Yesterday"
                                    value={`₹ ${formatCurrency(briefSummary.summary?.expenses_added_yesterday_amount || 0)}`}
                                />
                                <SnapshotCard
                                    label="Promises Due Today"
                                    value={`₹ ${formatCurrency(briefSummary.summary?.promises_due_today_amount || 0)}`}
                                />
                            </div>
                            <div className="dashboard-home__brief-meta">
                                <p>
                                    Overdue invoices: <strong>{briefSummary.summary?.overdue_invoice_count || 0}</strong> · Due today:{" "}
                                    <strong>{briefSummary.summary?.due_today_invoice_count || 0}</strong>
                                </p>
                                <p>
                                    Overdue bills: <strong>{briefSummary.summary?.overdue_bill_count || 0}</strong> · Due today:{" "}
                                    <strong>{briefSummary.summary?.due_today_bill_count || 0}</strong>
                                </p>
                            </div>
                            {Array.isArray(briefSummary.priorities) && briefSummary.priorities.length ? (
                                <div className="dashboard-home__brief-list">
                                    {briefSummary.priorities.map((priority) => (
                                        <p key={priority}>- {priority}</p>
                                    ))}
                                </div>
                            ) : (
                                <p className="kb-muted">No urgent follow-ups are due today.</p>
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            title="Daily brief unavailable"
                            description="The brief needs recent invoice, payment, expense, and promise activity."
                            actionLabel="Open reports"
                            onAction={() => navigate("/reports")}
                        />
                    )}
                </section>
            </div>

            <section className="dashboard-home__card">
                <SectionHeader
                    title="Revenue Leak Detector"
                    subtitle="Grounded warnings for missed recurring runs, broken promises, stale drafts, and overdue invoices."
                    actionLabel="Open reports"
                    onAction={() => navigate("/reports")}
                />
                <RevenueLeakPanel
                    summary={revenueLeakSummary}
                    loading={loading}
                    compact
                    onNavigate={navigate}
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
                    {loading ? (
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
                    {loading ? (
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
                    {loading ? (
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
                    {loading ? (
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
                    {loading ? (
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
