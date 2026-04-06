export default function RouteLoadingState({
    compact = false,
    title = "Loading application",
    description = "Preparing the current view.",
}) {
    if (compact) {
        return (
            <div className="kb-card" style={{ minHeight: 220, display: "grid", placeItems: "center" }}>
                <div style={{ maxWidth: 420, textAlign: "center" }}>
                    <h3 className="kb-h3" style={{ marginBottom: 8 }}>
                        {title}
                    </h3>
                    <p className="kb-muted" style={{ margin: 0 }}>
                        {description}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            style={{
                minHeight: "100vh",
                display: "grid",
                placeItems: "center",
                padding: 24,
                background: "var(--kb-color-bg)",
            }}
        >
            <div className="kb-card" style={{ maxWidth: 480, width: "100%", textAlign: "center" }}>
                <h2 className="kb-h2" style={{ marginTop: 0, marginBottom: 8 }}>
                    {title}
                </h2>
                <p className="kb-muted" style={{ margin: 0 }}>
                    {description}
                </p>
            </div>
        </div>
    );
}
