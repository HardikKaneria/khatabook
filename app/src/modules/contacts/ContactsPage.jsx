import { useEffect, useMemo, useState } from "react";
import { archiveContact, createContact, getContact, updateContact } from "./api";
import { useContacts } from "./hooks";
import ContactForm from "./ContactForm.jsx";
import ContactsList from "./ContactsList.jsx";
import { useToast } from "../../components/ToastProvider";
import { consumeQueryFlag } from "../../utils/locationFlags";

const DEFAULT_FILTERS = {
    q: "",
    type: "",
    status: "ACTIVE",
    page: 1,
    perPage: 10,
};

export default function ContactsPage() {
    const [filters, setFilters] = useState(DEFAULT_FILTERS);
    const [refreshKey, setRefreshKey] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [editingContactId, setEditingContactId] = useState(null);
    const [editingContact, setEditingContact] = useState(null);
    const [loadingEditContact, setLoadingEditContact] = useState(false);
    const [actionError, setActionError] = useState("");
    const { data, loading, error } = useContacts(filters, refreshKey);
    const toast = useToast();

    useEffect(() => {
        if (consumeQueryFlag("create")) {
            setShowForm(true);
        }
    }, []);

    const contacts = useMemo(() => data?.data || [], [data]);
    const pagination = data?.pagination || { page: 1, per_page: filters.perPage, total: contacts.length };
    const totalPages = Math.max(1, Math.ceil((pagination.total || 0) / (pagination.per_page || filters.perPage || 10)));

    const handleFilterChange = (key) => (event) => {
        const value = event.target.value;
        setFilters((prev) => ({
            ...prev,
            [key]: value,
            page: 1,
        }));
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingContactId(null);
        setEditingContact(null);
        setLoadingEditContact(false);
        setActionError("");
    };

    const handleCreateContact = async (payload) => {
        try {
            setActionError("");
            await createContact(payload);
            toast.success("Contact created successfully.");
            closeForm();
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to create contact.");
        }
    };

    const handleUpdateContact = async (payload) => {
        if (!editingContactId) {
            return;
        }
        try {
            setActionError("");
            await updateContact(editingContactId, payload);
            toast.success("Contact updated successfully.");
            closeForm();
            setRefreshKey((value) => value + 1);
        } catch (err) {
            setActionError(err?.message || "Unable to update contact.");
        }
    };

    const handleEdit = async (contactId) => {
        setActionError("");
        setEditingContactId(contactId);
        setLoadingEditContact(true);
        setShowForm(true);
        try {
            const contact = await getContact(contactId);
            setEditingContact(contact);
        } catch (err) {
            setActionError(err?.message || "Unable to load contact details.");
        } finally {
            setLoadingEditContact(false);
        }
    };

    const handleArchive = async (contact) => {
        if (!contact?.id || contact.status === "ARCHIVED") {
            return;
        }
        const confirmed = window.confirm(`Archive ${contact.name}? This will remove the contact from active selection lists.`);
        if (!confirmed) {
            return;
        }
        try {
            await archiveContact(contact.id);
            toast.success("Contact archived.");
            setRefreshKey((value) => value + 1);
        } catch (err) {
            toast.error(err?.message || "Unable to archive contact.");
        }
    };

    const summary = useMemo(() => {
        const counts = {
            CUSTOMER: 0,
            VENDOR: 0,
            BOTH: 0,
        };
        contacts.forEach((contact) => {
            const type = String(contact?.type || "").toUpperCase();
            if (counts[type] !== undefined) {
                counts[type] += 1;
            }
        });
        return counts;
    }, [contacts]);

    return (
        <div className="contacts-page">
            <header className="contacts-header">
                <div>
                    <p className="contacts-eyebrow">Relationship records</p>
                    <h1>Contacts</h1>
                    <p className="contacts-subtitle">Manage customers and vendors used across invoices and expenses.</p>
                </div>
                <button className="contacts-primary-btn" onClick={() => setShowForm(true)}>
                    Add Contact
                </button>
            </header>

            <section className="contacts-summary-grid">
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Customers</span>
                    <strong>{summary.CUSTOMER}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Vendors</span>
                    <strong>{summary.VENDOR}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Dual Contacts</span>
                    <strong>{summary.BOTH}</strong>
                </article>
                <article className="contacts-summary-card">
                    <span className="contacts-summary-label">Total Matching</span>
                    <strong>{pagination.total || 0}</strong>
                </article>
            </section>

            <section className="contacts-card">
                <div className="contacts-card-header">
                    <div>
                        <h3>Filters</h3>
                        <p>Search by name, email, or phone and narrow by contact type or status.</p>
                    </div>
                </div>
                <div className="contacts-filter-row">
                    <div className="field">
                        <label>Search</label>
                        <input
                            value={filters.q}
                            onChange={handleFilterChange("q")}
                            placeholder="Name, email, or phone"
                        />
                    </div>
                    <div className="field">
                        <label>Type</label>
                        <select value={filters.type} onChange={handleFilterChange("type")}>
                            <option value="">All Active Types</option>
                            <option value="CUSTOMER">Customer</option>
                            <option value="VENDOR">Vendor</option>
                            <option value="BOTH">Both</option>
                        </select>
                    </div>
                    <div className="field">
                        <label>Status</label>
                        <select value={filters.status} onChange={handleFilterChange("status")}>
                            <option value="ACTIVE">Active</option>
                            <option value="ARCHIVED">Archived</option>
                        </select>
                    </div>
                </div>
            </section>

            <section className="contacts-card">
                <div className="contacts-card-header contacts-card-header--split">
                    <div>
                        <h3>Contact Directory</h3>
                        <p>Archive inactive records to keep invoice and expense pickers clean.</p>
                    </div>
                    <span className="kb-muted">
                        Page {pagination.page || 1} of {totalPages}
                    </span>
                </div>
                <ContactsList
                    contacts={contacts}
                    loading={loading}
                    error={error}
                    onEdit={handleEdit}
                    onArchive={handleArchive}
                />

                {!loading && !error ? (
                    <div className="contacts-pagination">
                        <button
                            type="button"
                            className="kb-btn kb-btn--ghost"
                            disabled={(pagination.page || 1) <= 1}
                            onClick={() => setFilters((prev) => ({ ...prev, page: Math.max(1, (prev.page || 1) - 1) }))}
                        >
                            Previous
                        </button>
                        <span className="kb-muted">
                            Showing {(contacts.length ? ((pagination.page - 1) * pagination.per_page) + 1 : 0)}-
                            {Math.min((pagination.page || 1) * (pagination.per_page || 10), pagination.total || 0)} of {pagination.total || 0}
                        </span>
                        <button
                            type="button"
                            className="kb-btn kb-btn--ghost"
                            disabled={(pagination.page || 1) >= totalPages}
                            onClick={() => setFilters((prev) => ({ ...prev, page: (prev.page || 1) + 1 }))}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </section>

            {showForm ? (
                <Modal
                    title={editingContactId ? "Edit Contact" : "New Contact"}
                    onClose={closeForm}
                    width="min(760px, 96vw)"
                >
                    {actionError ? <p className="text-red-600 text-sm">{actionError}</p> : null}
                    {loadingEditContact ? (
                        <p className="kb-muted">Loading contact details…</p>
                    ) : editingContactId && !editingContact ? (
                        <div className="space-y-3">
                            <p className="kb-muted">The contact details could not be loaded. Close this dialog and try again.</p>
                            <div className="flex justify-end">
                                <button type="button" className="kb-btn kb-btn--ghost" onClick={closeForm}>
                                    Close
                                </button>
                            </div>
                        </div>
                    ) : (
                        <ContactForm
                            initialData={editingContact}
                            onSubmit={editingContactId ? handleUpdateContact : handleCreateContact}
                            onCancel={closeForm}
                            submitLabel={editingContactId ? "Save Changes" : "Create Contact"}
                        />
                    )}
                </Modal>
            ) : null}
        </div>
    );
}

function Modal({ title, children, onClose, width = "min(520px, 95vw)" }) {
    return (
        <div
            style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.35)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 1000,
            }}
        >
            <div className="kb-card" style={{ padding: 24, width, maxHeight: "90vh", overflowY: "auto", borderRadius: "var(--kb-radius-lg)" }}>
                <div className="flex items-center justify-between" style={{ marginBottom: 16 }}>
                    <h3 className="kb-h3" style={{ margin: 0 }}>
                        {title}
                    </h3>
                    <button onClick={onClose} className="kb-btn kb-btn--ghost" style={{ padding: "4px 8px" }}>
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
