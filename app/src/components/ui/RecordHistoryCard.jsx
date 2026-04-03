export default function RecordHistoryCard({ title = "Activity History", entries = [] }) {
    return (
        <section className="kb-card" style={{ padding: 24 }}>
            <h3 className="kb-h3" style={{ marginBottom: 12 }}>
                {title}
            </h3>
            {entries.length ? (
                <div className="space-y-4">
                    {entries.map((entry) => (
                        <div
                            key={entry.id || `${entry.action}-${entry.created_at}`}
                            style={{
                                borderBottom: "1px solid var(--kb-color-border)",
                                paddingBottom: 12,
                            }}
                        >
                            <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}>
                                <p style={{ margin: 0, fontWeight: 600 }}>{entry.summary}</p>
                                <p className="kb-muted" style={{ margin: 0 }}>
                                    {entry.actor_label || "System"} · {entry.created_at || "—"}
                                </p>
                            </div>
                            {entry.details?.length ? (
                                <ul className="kb-muted" style={{ marginTop: 8, marginBottom: 0, paddingLeft: 18 }}>
                                    {entry.details.map((detail, index) => (
                                        <li key={`${entry.id || entry.action}-${index}`}>{detail}</li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : (
                <p>No activity recorded yet.</p>
            )}
        </section>
    );
}
