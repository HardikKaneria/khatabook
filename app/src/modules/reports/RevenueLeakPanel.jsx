const formatCurrency = (value) =>
    `₹ ${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const severityLabels = {
    critical: "Critical",
    warning: "Warning",
    info: "Info",
};

export default function RevenueLeakPanel({
    summary,
    loading = false,
    compact = false,
    onNavigate,
}) {
    if (loading) {
        return <p className="kb-muted">Loading revenue leak signals…</p>;
    }

    if (!summary) {
        return (
            <p className="kb-muted" style={{ margin: 0 }}>
                Revenue leak signals are not available right now.
            </p>
        );
    }

    const alerts = Array.isArray(summary.alerts) ? summary.alerts : [];
    const visibleAlerts = compact ? alerts.slice(0, 3) : alerts;

    return (
        <div className="reports-leaks">
            <div className="reports-summary-grid">
                <article className="reports-summary-card">
                    <span className="reports-summary-label">Signals</span>
                    <strong>{summary.alert_count || 0}</strong>
                    <p>{summary.headline || "Revenue leak summary."}</p>
                </article>
                <article className="reports-summary-card">
                    <span className="reports-summary-label">Affected Records</span>
                    <strong>{summary.affected_record_count || 0}</strong>
                    <p>Invoices, promises, or recurring plans currently need action.</p>
                </article>
                <article className="reports-summary-card">
                    <span className="reports-summary-label">Estimated Amount</span>
                    <strong>{formatCurrency(summary.estimated_amount || 0)}</strong>
                    <p>{summary.as_of ? `As of ${summary.as_of}.` : "Live as of today."}</p>
                </article>
            </div>

            {visibleAlerts.length ? (
                <div className="reports-leaks-list">
                    {visibleAlerts.map((alert) => (
                        <article key={alert.key} className={`reports-leak-card reports-leak-card--${alert.severity || "info"}`}>
                            <div className="reports-leak-card__head">
                                <div>
                                    <strong>{alert.label}</strong>
                                    <p>{alert.detail}</p>
                                </div>
                                <span className={`reports-leak-pill reports-leak-pill--${alert.severity || "info"}`}>
                                    {severityLabels[alert.severity] || alert.severity || "Info"}
                                </span>
                            </div>
                            <div className="reports-leak-card__meta">
                                <span>{alert.record_count || 0} item{Number(alert.record_count || 0) === 1 ? "" : "s"}</span>
                                <strong>{formatCurrency(alert.estimated_amount || 0)}</strong>
                            </div>
                            {alert.action ? <p className="reports-leak-card__action">{alert.action}</p> : null}
                            {Array.isArray(alert.records) && alert.records.length ? (
                                <div className="reports-leak-records">
                                    {alert.records.map((record) => {
                                        const content = (
                                            <>
                                                <div>
                                                    <strong>{record.label}</strong>
                                                    <p>
                                                        {record.secondary_label ? `${record.secondary_label} · ` : ""}
                                                        {record.detail}
                                                    </p>
                                                </div>
                                                <div className="reports-leak-record__meta">
                                                    <strong>{formatCurrency(record.amount || 0)}</strong>
                                                    <span>
                                                        {record.days_open > 0 ? `${record.days_open} day${record.days_open === 1 ? "" : "s"} open` : record.date}
                                                    </span>
                                                </div>
                                            </>
                                        );

                                        return record.link_path && onNavigate ? (
                                            <button
                                                key={`${record.record_type}-${record.record_id}`}
                                                type="button"
                                                className="reports-leak-record"
                                                onClick={() => onNavigate(record.link_path)}
                                            >
                                                {content}
                                            </button>
                                        ) : (
                                            <div key={`${record.record_type}-${record.record_id}`} className="reports-leak-record">
                                                {content}
                                            </div>
                                        );
                                    })}
                                    {alert.remaining_count ? (
                                        <p className="kb-muted" style={{ margin: 0 }}>
                                            + {alert.remaining_count} more item{alert.remaining_count === 1 ? "" : "s"} in this group.
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                        </article>
                    ))}
                </div>
            ) : (
                <div className="reports-leak-empty">
                    <strong>No current leak signals</strong>
                    <p>Recurring runs, invoice follow-ups, and promise tracking are currently aligned.</p>
                </div>
            )}

            {Array.isArray(summary.actions) && summary.actions.length ? (
                <div className="reports-brief-list">
                    {summary.actions.map((action) => (
                        <p key={action}>- {action}</p>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
