// src/pages/UsersAdmin.jsx
import React, { useEffect, useMemo, useState, useCallback } from "react";
import {
  App,
  Card,
  Typography,
  Space,
  Form,
  Input,
  Select,
  Button,
  Table,
  Tag,
  Popconfirm,
} from "antd";
import { MailOutlined, PlusOutlined, ReloadOutlined } from "@ant-design/icons";

const { Title, Text } = Typography;

const ROLE_OPTIONS = [
  { value: "company_admin", label: "Company Admin" },
  { value: "c_manager", label: "Manager" },
  { value: "c_employee", label: "Employee" },
];

export default function UsersAdmin() {
  const { user, apiFetch } = useAuth();
  const { message } =
    App.useApp?.() || { message: { success: console.log, error: console.error } };

  const orgId = user?.orgId;
  const isAdmin = user?.role === "company_admin";

  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [inviting, setInviting] = useState(false);
  const [savingRole, setSavingRole] = useState(null);
  const [users, setUsers] = useState([]);

  const fetchUsers = useCallback(async () => {
    if (!orgId) return;
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
  }, [apiFetch, orgId, message]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleInvite = async (values) => {
    if (!isAdmin) return;
    setInviting(true);
    try {
      await apiFetch(`/kbs/v1/invite-user`, {
        method: "POST",
        body: { org_id: orgId, email: values.email.trim(), role: values.role },
      });
      message.success("Invitation sent");
      form.resetFields();
      fetchUsers();
    } catch (e) {
      message.error(e.message || "Failed to invite user");
    } finally {
      setInviting(false);
    }
  };

  const handleChangeRole = async (userRecord, nextRole) => {
    if (!isAdmin) return;
    setSavingRole(userRecord.id);
    const prev = userRecord.role;
    try {
      setUsers((arr) =>
        arr.map((u) => (u.id === userRecord.id ? { ...u, role: nextRole } : u))
      );
      await apiFetch(`/kbs/v1/user-role`, {
        method: "PUT",
        body: { org_id: orgId, user_id: userRecord.id, role: nextRole },
      });
      message.success("Role updated");
    } catch (e) {
      setUsers((arr) =>
        arr.map((u) => (u.id === userRecord.id ? { ...u, role: prev } : u))
      );
      message.error(e.message || "Failed to update role");
    } finally {
      setSavingRole(null);
    }
  };

  const handleResendInvite = async (userRecord) => {
    try {
      await apiFetch(`/kbs/v1/resend-invite`, {
        method: "POST",
        body: { org_id: orgId, user_id: userRecord.id },
      });
      message.success("Invite resent");
    } catch (e) {
      message.error(e.message || "Failed to resend invite");
    }
  };

  const handleRemoveUser = async (userRecord) => {
    try {
      await apiFetch(`/kbs/v1/user`, {
        method: "DELETE",
        body: { org_id: orgId, user_id: userRecord.id },
      });
      setUsers((arr) => arr.filter((u) => u.id !== userRecord.id));
      message.success("User removed");
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
        render: (role, record) => (
          <Select
            value={role}
            onChange={(val) => handleChangeRole(record, val)}
            options={ROLE_OPTIONS}
            style={{ width: 180 }}
            disabled={!isAdmin || savingRole === record.id}
          />
        ),
      },
      {
        title: "Status",
        dataIndex: "status",
        key: "status",
        render: (s) => {
          const color =
            s === "active" ? "green" : s === "invited" ? "blue" : "default";
          return <Tag color={color}>{(s || "active").toUpperCase()}</Tag>;
        },
      },
      {
        title: "Actions",
        key: "actions",
        render: (_, record) => (
          <Space>
            {record.status === "invited" && (
              <Button
                size="small"
                icon={<ReloadOutlined />}
                onClick={() => handleResendInvite(record)}
              >
                Resend Invite
              </Button>
            )}
            {isAdmin && record.id !== user?.id && (
              <Popconfirm
                title="Remove user?"
                okText="Remove"
                onConfirm={() => handleRemoveUser(record)}
              >
                <Button size="small" danger>
                  Remove
                </Button>
              </Popconfirm>
            )}
          </Space>
        ),
      },
    ],
    [handleChangeRole, isAdmin, savingRole, user?.id]
  );

  if (!isAdmin) {
    return (
      <div className="min-h-screen bg-slate-50 p-4 md:p-6 lg:p-8">
        <Card className="rounded-2xl shadow-lg border border-gray-100 bg-white">
          <Title level={4} style={{ marginTop: 0 }}>
            Users
          </Title>
          <Text type="secondary">You do not have permission to manage users.</Text>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 p-2 md:p-6 lg:p-4">
      <Space
        direction="vertical"
        size={16}
        className="w-full"
        style={{ display: "flex" }}
      >
        {/* Invite card */}
        <Card className="rounded-2xl shadow-lg border border-gray-100 bg-white transition-shadow hover:shadow-xl">
          <Space direction="vertical" size={4} className="w-full">
            <Title level={4} style={{ margin: 0 }}>
              Invite a user
            </Title>
            <Text type="secondary">
              Send an invitation to join your organization.
            </Text>
          </Space>

          <Form
            form={form}
            layout="inline"
            onFinish={handleInvite}
            className="mt-3"
            requiredMark={false}
          >
            <Form.Item
              name="email"
              rules={[
                { required: true, message: "Email is required" },
                { type: "email", message: "Enter a valid email" },
              ]}
            >
              <Input placeholder="name@company.com" style={{ width: 280 }} />
            </Form.Item>
            <Form.Item
              name="role"
              initialValue="c_employee"
              rules={[{ required: true, message: "Select a role" }]}
            >
              <Select style={{ width: 220 }} options={ROLE_OPTIONS} />
            </Form.Item>
            <Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                icon={<PlusOutlined />}
                loading={inviting}
              >
                Invite
              </Button>
            </Form.Item>
          </Form>
        </Card>

        {/* Users table card */}
        <Card className="rounded-2xl shadow-lg border border-gray-100 bg-white transition-shadow hover:shadow-xl">
          <Space direction="vertical" size={4} className="w-full">
            <Title level={4} style={{ margin: 0 }}>
              Organization Users
            </Title>
            <Text type="secondary">Manage members and their roles.</Text>
          </Space>

          <div className="mt-3 rounded-xl overflow-hidden border border-gray-100">
            {/* Wrapping the table with its own shadow/rounded frame looks nice */}
            <div className="bg-white">
              <Table
                rowKey="id"
                loading={loading}
                dataSource={users}
                columns={columns}
                pagination={{ pageSize: 10 }}
              />
            </div>
          </div>
        </Card>
      </Space>
    </div>
  );
}
