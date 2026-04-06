import Card from "./Card.jsx";
import FeedbackState from "./FeedbackState.jsx";

export default function RecordHistoryCard({ title = "Activity History", entries = [] }) {
    return (
        <Card title={title}>
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
                <FeedbackState
                    title="No activity recorded yet"
                    description="Record history entries will appear here after changes are posted to this record."
                    tone="empty"
                />
            )}
        </Card>
    );
}
