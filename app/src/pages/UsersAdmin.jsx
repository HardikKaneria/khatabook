// src/pages/UsersAdmin.jsx
import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
	Space,
	Form,
	Input,
	Select,
	Button,
	Table,
	Popconfirm,
} from "antd";
import { MailOutlined, PlusOutlined, ReloadOutlined } from "@ant-design/icons";
import { getAuth } from "../utils/authStorage";
import { useToast } from "../components/ToastProvider";
import { makeDefaultApiFetch } from "../utils/apiClient";
import Card from "../components/ui/Card.jsx";
import FeedbackState from "../components/ui/FeedbackState.jsx";
import PageContainer from "../components/ui/PageContainer.jsx";
import PageHeader from "../components/ui/PageHeader.jsx";

const ROLE_OPTIONS = [
	{ value: "company_admin", label: "Company Admin" },
	{ value: "c_manager", label: "Manager" },
	{ value: "c_employee", label: "Employee" },
];

const ROLE_LABELS = {
	company_admin: "Company Admin",
	c_manager: "Manager",
	c_employee: "Employee",
	administrator: "Administrator",
};

const ROLE_SET = new Set(ROLE_OPTIONS.map((opt) => opt.value));

const normalizeRole = (role) => {
	if (!role) return "c_employee";
	const lower = String(role).toLowerCase();
	return ROLE_SET.has(lower) ? lower : lower;
};

const getOrgIdFromAuth = (auth) => {
	const user = auth?.user || {};
	return (
		user?.org_id ??
		user?.orgId ??
		auth?.org_id ??
		auth?.orgId ??
		(Array.isArray(user?.orgs) &&
			(user.orgs.find((o) => o.is_primary)?.org_id ?? user.orgs[0]?.org_id))
	);
};

const getCurrentOrgRole = (auth, orgId) => {
	const user = auth?.user || {};
	if (orgId && Array.isArray(user?.orgs)) {
		const activeOrg = user.orgs.find((org) => Number(org?.org_id) === Number(orgId));
		if (activeOrg?.role) {
			return normalizeRole(activeOrg.role);
		}
	}
	return normalizeRole(user?.role || "");
};

const formatInviteDate = (value) => {
	if (!value) return "—";
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return value;
	}
	return new Intl.DateTimeFormat(undefined, {
		year: "numeric",
		month: "short",
		day: "numeric",
		hour: "numeric",
		minute: "2-digit",
	}).format(date);
};

export default function UsersAdmin() {
	const [form] = Form.useForm();
	const message = useToast();

	const [auth, setAuth] = useState(null);
	const [authLoading, setAuthLoading] = useState(true);
	const [users, setUsers] = useState([]);
	const [loading, setLoading] = useState(true);
	const [inviting, setInviting] = useState(false);
	const [savingRole, setSavingRole] = useState(null);

	useEffect(() => {
		let alive = true;
		(async () => {
			try {
				const raw = await getAuth();
				if (!alive) return;
				setAuth(raw || {});
			} catch (e) {
				if (!alive) return;
				message.error("Failed to load auth details");
				setAuth({});
			} finally {
				if (alive) setAuthLoading(false);
			}
		})();
		return () => {
			alive = false;
		};
	}, [message]);

	const orgId = useMemo(() => getOrgIdFromAuth(auth), [auth]);
	const apiFetch = useMemo(() => {
		if (!auth) return null;
		return makeDefaultApiFetch(auth?.rest, auth?.token);
	}, [auth, auth?.rest, auth?.token]);

	const currentRole = useMemo(() => getCurrentOrgRole(auth, orgId), [auth, orgId]);
	const manageableRoles = useMemo(() => {
		if (currentRole === "administrator") {
			return ["company_admin", "c_manager", "c_employee"];
		}
		if (currentRole === "company_admin") {
			return ["c_manager", "c_employee"];
		}
		if (currentRole === "c_manager") {
			return ["c_employee"];
		}
		return [];
	}, [currentRole]);

	const canViewUsers = manageableRoles.length > 0;
	const roleOptionsForSelect = useMemo(
		() => ROLE_OPTIONS.filter((opt) => manageableRoles.includes(opt.value)),
		[manageableRoles]
	);
	const defaultInviteRole = roleOptionsForSelect[0]?.value || "c_employee";

	useEffect(() => {
		if (!roleOptionsForSelect.length) return;
		form.setFieldsValue({ role: defaultInviteRole });
	}, [roleOptionsForSelect, defaultInviteRole, form]);

	const canManageRole = useCallback(
		(role) => manageableRoles.includes(normalizeRole(role)),
		[manageableRoles]
	);

	const fetchUsers = useCallback(async () => {
		if (!orgId || !apiFetch || !canViewUsers) {
			setLoading(false);
			return;
		}
		setLoading(true);
		try {
			const qs = new URLSearchParams({ org_id: String(orgId) });
			const data = await apiFetch(`/kbs/v1/org-users?${qs.toString()}`, {
				method: "GET",
			});
			setUsers(
				Array.isArray(data?.users) ? data.users : Array.isArray(data) ? data : []
			);
		} catch (e) {
			message.error(e.message || "Failed to load users");
		} finally {
			setLoading(false);
		}
	}, [apiFetch, canViewUsers, message, orgId]);

	useEffect(() => {
		fetchUsers();
	}, [fetchUsers]);

	const handleInvite = async (values) => {
		if (!apiFetch || !orgId) return;
		const email = values.email?.trim();
		const role = values.role || defaultInviteRole;
		if (!email) {
			message.error("Email is required");
			return;
		}
		if (!manageableRoles.includes(role)) {
			message.error("You cannot invite users with that role.");
			return;
		}

		setInviting(true);
		try {
			await apiFetch(`/kbs/v1/invite-user`, {
				method: "POST",
				body: { org_id: orgId, email, role },
			});
			message.success("Invitation sent");
			form.resetFields(["email"]);
			fetchUsers();
		} catch (e) {
			message.error(e.message || "Failed to invite user");
		} finally {
			setInviting(false);
		}
	};

	const handleChangeRole = async (record, nextRole) => {
		if (!apiFetch || !orgId) return;
		if (record.status === "invited") return;
		if (!canManageRole(record.role)) {
			message.error("You cannot manage that user.");
			return;
		}
		if (!manageableRoles.includes(nextRole)) {
			message.error("You cannot assign that role.");
			return;
		}

		setSavingRole(record.id);
		const prevRole = record.role;
		try {
			setUsers((arr) =>
				arr.map((u) => (u.id === record.id ? { ...u, role: nextRole } : u))
			);
			await apiFetch(`/kbs/v1/user-role`, {
				method: "PUT",
				body: { org_id: orgId, user_id: record.id, role: nextRole },
			});
			message.success("Role updated");
		} catch (e) {
			setUsers((arr) =>
				arr.map((u) => (u.id === record.id ? { ...u, role: prevRole } : u))
			);
			message.error(e.message || "Failed to update role");
		} finally {
			setSavingRole(null);
		}
	};

	const handleResendInvite = async (record) => {
		if (!apiFetch || !orgId) return;
		if (record.status !== "invited") return;
		if (!canManageRole(record.role)) {
			message.error("You cannot manage that invite.");
			return;
		}
		try {
			await apiFetch(`/kbs/v1/resend-invite`, {
				method: "POST",
				body: { org_id: orgId, user_id: record.id },
			});
			message.success("Invite resent");
		} catch (e) {
			message.error(e.message || "Failed to resend invite");
		}
	};

	const handleRemoveUser = async (record) => {
		if (!apiFetch || !orgId) return;
		if (record.id === auth?.user?.id) {
			message.error("You cannot remove yourself.");
			return;
		}
		if (!canManageRole(record.role)) {
			message.error("You cannot remove that user.");
			return;
		}
		try {
			await apiFetch(`/kbs/v1/user`, {
				method: "DELETE",
				body: { org_id: orgId, user_id: record.id },
			});
			setUsers((arr) => arr.filter((u) => u.id !== record.id));
			message.success(
				record.status === "invited" ? "Invite removed" : "User removed"
			);
		} catch (e) {
			message.error(e.message || "Failed to remove user");
		}
	};

	const columns = useMemo(
		() => [
			{
				title: "Name",
				dataIndex: "display_name",
				key: "display_name",
				render: (v) => v || "-",
			},
			{
				title: "Email",
				dataIndex: "email",
				key: "email",
				render: (v) => (
					<a href={`mailto:${v}`}>
						<MailOutlined /> {v}
					</a>
				),
			},
			{
				title: "Role",
				dataIndex: "role",
				key: "role",
				render: (role, record) => {
					const normalized = normalizeRole(role);
					const label =
						ROLE_LABELS[normalized] || ROLE_LABELS[role] || record.role || "-";
					const canEdit =
						record.status !== "invited" &&
						canManageRole(normalized) &&
						roleOptionsForSelect.length > 1;

					if (!canEdit) {
						return label;
					}

					return (
						<Select
							value={normalized}
							onChange={(val) => handleChangeRole(record, val)}
							options={roleOptionsForSelect}
							style={{ width: 200 }}
							disabled={savingRole === record.id}
						/>
					);
				},
			},
			{
				title: "Actions",
				key: "actions",
				render: (_, record) => {
					const normalized = normalizeRole(record.role);
					const manageable = canManageRole(normalized);
					const isInvite = record.status === "invited";

					if (!manageable) return null;

					return (
						<Space>
							{isInvite && (
								<Button
									size="small"
									icon={<ReloadOutlined />}
									onClick={() => handleResendInvite(record)}
								>
									Resend Invite
								</Button>
							)}
							{record.id !== auth?.user?.id && (
								<Popconfirm
									title={isInvite ? "Remove invite?" : "Remove user?"}
									okText="Remove"
									onConfirm={() => handleRemoveUser(record)}
								>
									<Button size="small" danger>
										{isInvite ? "Delete Invite" : "Remove"}
									</Button>
								</Popconfirm>
							)}
						</Space>
					);
				},
			},
		],
		[
			auth?.user?.id,
			canManageRole,
			handleChangeRole,
			handleRemoveUser,
			handleResendInvite,
			roleOptionsForSelect,
			savingRole,
		]
	);

	const activeUsers = useMemo(
		() => users.filter((user) => user.status !== "invited"),
		[users]
	);

	const invitedUsers = useMemo(
		() => users.filter((user) => user.status === "invited"),
		[users]
	);

	const inviteActionColumn = useMemo(
		() => columns.find((column) => column.key === "actions") || null,
		[columns]
	);

	const inviteColumns = useMemo(
		() => [
			{
				title: "Email",
				dataIndex: "email",
				key: "email",
				render: (value) => (
					<a href={`mailto:${value}`}>
						<MailOutlined /> {value}
					</a>
				),
			},
			{
				title: "Role",
				dataIndex: "role",
				key: "role",
				render: (role) => ROLE_LABELS[normalizeRole(role)] || role || "-",
			},
			{
				title: "Invited",
				dataIndex: "created_at",
				key: "created_at",
				render: (value) => formatInviteDate(value),
			},
			{
				title: "Actions",
				key: "actions",
				render: (_, record) => inviteActionColumn?.render?.(_, record) ?? null,
			},
		],
		[inviteActionColumn]
	);

	const summaryCards = useMemo(
		() => [
			{
				label: "Active members",
				value: activeUsers.length,
				helper: "Current org membership rows",
			},
			{
				label: "Pending invites",
				value: invitedUsers.length,
				helper: "Sent but not accepted yet",
			},
			{
				label: "You can assign",
				value:
					manageableRoles.length > 0
						? manageableRoles.map((role) => ROLE_LABELS[role] || role).join(", ")
						: "No org roles",
				helper: "Based on your org-scoped role",
			},
		],
		[activeUsers.length, invitedUsers.length, manageableRoles]
	);

	const renderInviteCard = () => (
		<Card
			title="Invite a user"
			subtitle="Send an invitation to join your organization. New invites stay separate from active members until accepted."
		>
			<Form form={form} layout="inline" onFinish={handleInvite} requiredMark={false}>
				<Form.Item
					name="email"
					rules={[
						{ required: true, message: "Email is required" },
						{ type: "email", message: "Enter a valid email" },
					]}
				>
					<Input
						placeholder="name@company.com"
						style={{ width: 280 }}
						disabled={!manageableRoles.length}
					/>
				</Form.Item>
				<Form.Item name="role">
					<Select
						style={{ width: 220 }}
						options={roleOptionsForSelect}
						disabled={!roleOptionsForSelect.length}
					/>
				</Form.Item>
				<Form.Item>
					<Button
						type="primary"
						htmlType="submit"
						icon={<PlusOutlined />}
						loading={inviting}
						disabled={!roleOptionsForSelect.length}
					>
						Invite
					</Button>
				</Form.Item>
			</Form>
			<p className="ui-inline-note">
				You are currently operating as {ROLE_LABELS[currentRole] || currentRole || "User"}.
			</p>
		</Card>
	);

	const renderSummary = () => (
		<section className="ui-stat-grid">
			{summaryCards.map((card) => (
				<article key={card.label} className="ui-stat-card">
					<span className="ui-stat-card__label">{card.label}</span>
					<strong className="ui-stat-card__value">{card.value}</strong>
					<p className="ui-stat-card__helper">{card.helper}</p>
				</article>
			))}
		</section>
	);

	const renderMembersTable = () => (
		<Card
			title="Active members"
			subtitle="Update org roles here. Invite records are listed separately so operational actions stay clearer."
			actions={<span className="ui-card-meta">{activeUsers.length} members</span>}
		>
			<Table
				rowKey="id"
				loading={loading}
				dataSource={activeUsers}
				columns={columns}
				pagination={{ pageSize: 10 }}
				locale={{
					emptyText: "No active members found for this organization.",
				}}
			/>
		</Card>
	);

	const renderInvitesTable = () => (
		<Card
			title="Pending invites"
			subtitle="Resend or remove invites that have not been accepted yet."
			actions={<span className="ui-card-meta">{invitedUsers.length} invites</span>}
		>
			<Table
				rowKey="id"
				loading={loading}
				dataSource={invitedUsers}
				columns={inviteColumns}
				pagination={{ pageSize: 10 }}
				locale={{
					emptyText: "No pending invites for this organization.",
				}}
			/>
		</Card>
	);

	const pageShell = (children) => (
		<PageContainer>
			<PageHeader
				eyebrow="Team"
				title="Users"
				subtitle="Manage organization access, invite teammates, and update roles."
			/>
			{children}
		</PageContainer>
	);

	if (authLoading) {
		return pageShell(
			<Card>
				<FeedbackState
					title="Loading organization data"
					description="Fetching your current org membership and invite permissions."
					tone="loading"
				/>
			</Card>
		);
	}

	if (!canViewUsers) {
		return pageShell(
			<Card>
				<FeedbackState
					title="You cannot manage organization members"
					description="Only company admins and managers can access this screen for the current organization."
					tone="empty"
				/>
			</Card>
		);
	}

	return pageShell(
		<>
			{renderSummary()}
			{renderInviteCard()}
			{renderMembersTable()}
			{renderInvitesTable()}
		</>
	);
}
