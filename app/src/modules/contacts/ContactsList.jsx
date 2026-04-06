import FeedbackState from "../../components/ui/FeedbackState.jsx";

const typeLabels = {
    CUSTOMER: "Customer",
    VENDOR: "Vendor",
    BOTH: "Customer + Vendor",
};

const typeToneClass = {
    CUSTOMER: "kb-badge--violet",
    VENDOR: "kb-badge--magenta",
    BOTH: "kb-badge--gray",
};

export default function ContactsList({
    contacts = [],
    loading,
    error,
    onEdit,
    onArchive,
    onStatement,
}) {
    if (loading) {
        return <FeedbackState title="Loading contacts" description="Fetching the filtered contact directory." tone="loading" />;
    }

    if (error) {
        return <FeedbackState title="Unable to load contacts" description={error.message} tone="error" />;
    }

    if (!contacts.length) {
        return <FeedbackState title="No contacts match the current filters" description="Adjust the filters or add a new contact to populate this directory." tone="empty" />;
    }

    return (
        <div className="ui-table-wrap">
            <table className="kb-data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>GSTIN</th>
                        <th>Status</th>
                        <th className="kb-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {contacts.map((contact) => (
                        <tr key={contact.id}>
                            <td>
                                <div className="kb-data-table__primary">{contact.name}</div>
                            </td>
                            <td>
                                <span className={`kb-badge ${typeToneClass[contact.type] || "kb-badge--gray"}`}>
                                    {typeLabels[contact.type] || contact.type || "Unknown"}
                                </span>
                            </td>
                            <td>{contact.email || "—"}</td>
                            <td>{contact.phone || "—"}</td>
                            <td>{contact.gstin || "—"}</td>
                            <td>{contact.status || "ACTIVE"}</td>
                            <td className="kb-data-table__actions">
                                <button
                                    type="button"
                                    className="kb-btn kb-btn--ghost kb-btn--small"
                                    onClick={() => onEdit?.(contact.id)}
                                >
                                    Edit
                                </button>
                                {["CUSTOMER", "BOTH"].includes(String(contact.type || "").toUpperCase()) ? (
                                    <button
                                        type="button"
                                        className="kb-btn kb-btn--ghost kb-btn--small"
                                        onClick={() => onStatement?.(contact)}
                                    >
                                        Statement
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className="kb-btn kb-btn--ghost kb-btn--small kb-btn--danger"
                                    disabled={contact.status === "ARCHIVED"}
                                    onClick={() => onArchive?.(contact)}
                                >
                                    Archive
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
