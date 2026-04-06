import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatSchedule = (profile) => {
    const interval = Number(profile?.interval_count || 1);
    const frequency = String(profile?.frequency || "MONTHLY").toLowerCase();
    return `${interval} x ${frequency}`;
};

export default function RecurringProfilesList({
    profiles = [],
    loading,
    error,
    onEdit,
    onToggleStatus,
    onGenerate,
    onOpenSourceInvoice,
}) {
    if (loading) {
        return <FeedbackState title="Loading recurring billing" description="Fetching recurring invoice plans for the current organization." tone="loading" />;
    }

    if (error) {
        return <FeedbackState title="Unable to load recurring plans" description={error.message} tone="error" />;
    }

    if (!profiles.length) {
        return (
            <FeedbackState
                title="No recurring plans yet"
                description="Open an invoice and create a recurring billing plan from that live invoice when you need automated repeat billing."
                tone="empty"
            />
        );
    }

    return (
        <div className="ui-table-wrap">
            <table className="kb-data-table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Customer</th>
                        <th>Schedule</th>
                        <th>Next Run</th>
                        <th>Status</th>
                        <th>Last Invoice</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {profiles.map((profile) => {
                        const isActive = String(profile?.status || "").toUpperCase() === "ACTIVE";
                        return (
                            <tr key={profile.id}>
                                <td>
                                    <strong>{profile.profile_name}</strong>
                                    <div className="kb-muted" style={{ fontSize: 12 }}>
                                        {profile.source_invoice?.invoice_number || "Source invoice unavailable"}
                                    </div>
                                </td>
                                <td>{profile.customer_name || "Customer"}</td>
                                <td>{formatSchedule(profile)}</td>
                                <td>{profile.next_run_date || "—"}</td>
                                <td>{profile.status}</td>
                                <td>{profile.last_invoice?.invoice_number || "—"}</td>
                                <td>
                                    <div className="flex flex-wrap gap-2 justify-end">
                                        {profile.source_invoice?.id ? (
                                            <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onOpenSourceInvoice?.(profile.source_invoice.id)}>
                                                Open Source
                                            </button>
                                        ) : null}
                                        <button type="button" className="kb-btn kb-btn--ghost kb-btn--small" onClick={() => onEdit?.(profile)}>
                                            Edit
                                        </button>
                                        {profile.can_generate_now ? (
                                            <button type="button" className="kb-btn kb-btn--secondary kb-btn--small" onClick={() => onGenerate?.(profile)}>
                                                Generate Due
                                            </button>
                                        ) : null}
                                        {profile.status !== "ENDED" ? (
                                            <button
                                                type="button"
                                                className="kb-btn kb-btn--ghost kb-btn--small"
                                                onClick={() => onToggleStatus?.(profile, isActive ? "PAUSED" : "ACTIVE")}
                                            >
                                                {isActive ? "Pause" : "Resume"}
                                            </button>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
