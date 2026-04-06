import { Table } from "antd";
import FeedbackState from "../../components/ui/FeedbackState.jsx";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDisplayDate = (value) => {
    if (!value) return "—";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
};

export default function AccountStatementTable({ lines = [], loading, error }) {
    if (loading) {
        return <FeedbackState title="Loading statement" description="Fetching transactions for the selected date range." tone="loading" />;
    }
    if (error) {
        return <FeedbackState title="Unable to load statement" description={error.message} tone="error" />;
    }
    if (!lines.length) {
        return <FeedbackState title="No transactions in this range" description="Adjust the date filters to inspect a different ledger period." tone="empty" />;
    }

    const columns = [
        {
            title: "Date",
            dataIndex: "date",
            key: "date",
            render: (_, record) => (
                <div>
                    <div className="kb-table__date">{formatDisplayDate(record.date)}</div>
                    {record.journal_id ? (
                        <div className="kb-muted" style={{ fontSize: 12 }}>
                            #{record.journal_id}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            title: "Description",
            dataIndex: "description",
            key: "description",
            render: (value) => <div className="kb-table__description">{value || "—"}</div>,
        },
        {
            title: "Debit",
            dataIndex: "debit",
            key: "debit",
            align: "right",
            render: (value) => (
                <span className="kb-amount kb-amount--debit">{value ? formatCurrency(value) : "—"}</span>
            ),
        },
        {
            title: "Credit",
            dataIndex: "credit",
            key: "credit",
            align: "right",
            render: (value) => (
                <span className="kb-amount kb-amount--credit">{value ? formatCurrency(value) : "—"}</span>
            ),
        },
        {
            title: "Balance",
            dataIndex: "balance",
            key: "balance",
            align: "right",
            render: (value) => <span className="kb-table__pill">{formatCurrency(value)}</span>,
        },
    ];

    const dataSource = lines.map((line, idx) => ({
        key: line.journal_id ? `${line.journal_id}` : `${idx}`,
        ...line,
    }));

    return <Table className="kb-antd-table" columns={columns} dataSource={dataSource} pagination={false} />;
}
