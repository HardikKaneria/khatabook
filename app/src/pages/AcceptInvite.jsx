import { useEffect, useMemo, useState } from "react";
import { Alert, Button, Card, Descriptions, Space, Spin, Typography } from "antd";
import { CheckCircleOutlined, LoginOutlined, ReloadOutlined } from "@ant-design/icons";
import { saveAuth } from "../utils/authStorage";
import { useToast } from "../components/ToastProvider";

const { Title, Text } = Typography;

const ROLE_LABELS = {
    company_admin: "Company Admin",
    c_manager: "Manager",
    c_employee: "Employee",
};

function getInviteParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        invite: params.get("invite") || "",
        email: params.get("email") || "",
    };
}

function formatRole(role) {
    return ROLE_LABELS[role] || role || "Employee";
}

function formatDate(value) {
    if (!value) return "Not specified";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
}

export default function AcceptInvite({ auth, onAuthenticated }) {
    const toast = useToast();
    const params = useMemo(() => getInviteParams(), []);
    const [loading, setLoading] = useState(true);
    const [accepting, setAccepting] = useState(false);
    const [inviteState, setInviteState] = useState(null);
    const [messageText, setMessageText] = useState("");

    const loadInvite = async () => {
        if (!params.invite || !params.email) {
            setInviteState({
                status: "invalid",
                message: "Invite link is missing the required token or email address.",
            });
            setLoading(false);
            return;
        }

        setLoading(true);
        try {
            const query = new URLSearchParams({
                invite: params.invite,
                email: params.email,
            });
            const response = await fetch(`/wp-json/kbs/v1/invite?${query.toString()}`, {
                method: "GET",
                credentials: "include",
            });

            let result = {};
            try {
                result = await response.json();
            } catch {
                result = {};
            }

            setInviteState({
                status: result?.status || (response.ok ? "valid" : "invalid"),
                message: result?.message || "Unable to load invitation details.",
                invite: result?.invite || null,
                meta: result?.meta || {},
            });
            setMessageText("");
        } catch (error) {
            console.error("[AcceptInvite] Failed to load invite", error);
            setInviteState({
                status: "invalid",
                message: "Could not load the invitation right now. Please try again.",
            });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadInvite();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleAccept = async () => {
        if (!params.invite || !params.email) {
            return;
        }

        if (auth?.user && inviteState?.meta && !inviteState.meta.email_matches_current_user) {
            const mismatch = "This invite belongs to a different email address. Log out first and open the link again.";
            setMessageText(mismatch);
            toast.error(mismatch);
            return;
        }

        setAccepting(true);
        setMessageText("");
        try {
            const response = await fetch("/wp-json/kbs/v1/accept-invite", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "include",
                body: JSON.stringify({
                    invite: params.invite,
                    email: params.email,
                }),
            });

            let result = {};
            try {
                result = await response.json();
            } catch {
                result = {};
            }

            if (response.ok && result?.status === "authenticated") {
                await saveAuth(result);
                onAuthenticated?.(result);
                toast.success(result?.message || "Invitation accepted.");
                window.location.href = "/home";
                return;
            }

            setInviteState((current) => ({
                status: result?.status || current?.status || "invalid",
                message: result?.message || current?.message || "Failed to accept invitation.",
                invite: result?.invite || current?.invite || null,
                meta: current?.meta || {},
            }));

            const nextMessage = result?.message || "Failed to accept invitation.";
            setMessageText(nextMessage);
            if (result?.status === "already_accepted") {
                toast.warning(nextMessage);
            } else {
                toast.error(nextMessage);
            }
        } catch (error) {
            console.error("[AcceptInvite] Failed to accept invite", error);
            const nextMessage = "Network error while accepting the invitation.";
            setMessageText(nextMessage);
            toast.error(nextMessage);
        } finally {
            setAccepting(false);
        }
    };

    const status = inviteState?.status || "invalid";
    const invite = inviteState?.invite || null;
    const canAccept =
        status === "valid" &&
        (!auth?.user || inviteState?.meta?.email_matches_current_user);

    const alertType = status === "valid"
        ? "info"
        : status === "already_accepted"
            ? "success"
            : "error";
    const alertMessage = messageText || inviteState?.message || "Invitation status unavailable.";

    return (
        <div
            style={{
                minHeight: "100vh",
                minWidth: "100vw",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                padding: 16,
                background: "#f5f7fb",
            }}
        >
            <Card
                style={{
                    width: "100%",
                    maxWidth: 560,
                    borderRadius: 16,
                    boxShadow: "0 14px 40px rgba(15, 23, 42, 0.08)",
                }}
                styles={{ body: { padding: 28 } }}
            >
                <Space direction="vertical" size={18} style={{ width: "100%" }}>
                    <div style={{ textAlign: "center" }}>
                        <Title level={3} style={{ marginBottom: 4 }}>
                            Accept Invitation
                        </Title>
                        <Text type="secondary">
                            Confirm the invite details below before joining the organization.
                        </Text>
                    </div>

                    {loading ? (
                        <div style={{ textAlign: "center", padding: "24px 0" }}>
                            <Spin size="large" />
                        </div>
                    ) : (
                        <>
                            <Alert
                                type={alertType}
                                showIcon
                                message={alertMessage}
                            />

                            {invite ? (
                                <Descriptions
                                    column={1}
                                    bordered
                                    size="small"
                                    items={[
                                        {
                                            key: "org",
                                            label: "Organization",
                                            children: invite.org_name || "-",
                                        },
                                        {
                                            key: "email",
                                            label: "Email",
                                            children: invite.email || "-",
                                        },
                                        {
                                            key: "role",
                                            label: "Role",
                                            children: formatRole(invite.role),
                                        },
                                        {
                                            key: "expires",
                                            label: "Expires",
                                            children: formatDate(invite.expires_at),
                                        },
                                    ]}
                                />
                            ) : null}

                            {auth?.user && inviteState?.meta && !inviteState.meta.email_matches_current_user ? (
                                <Alert
                                    type="warning"
                                    showIcon
                                    message={`You are signed in as ${auth.user.email}. This invite is for a different email address.`}
                                />
                            ) : null}

                            <Space wrap>
                                {canAccept ? (
                                    <Button
                                        type="primary"
                                        icon={<CheckCircleOutlined />}
                                        loading={accepting}
                                        onClick={handleAccept}
                                    >
                                        Accept Invitation
                                    </Button>
                                ) : null}

                                {status === "already_accepted" || status === "invalid" || status === "expired" ? (
                                    <Button
                                        icon={<LoginOutlined />}
                                        onClick={() => {
                                            window.location.href = auth?.user ? "/home" : "/login";
                                        }}
                                    >
                                        {auth?.user ? "Open App" : "Go to Login"}
                                    </Button>
                                ) : null}

                                {auth?.user && inviteState?.meta && !inviteState.meta.email_matches_current_user ? (
                                    <Button
                                        danger
                                        onClick={() => {
                                            window.location.href = "/login?action=logout";
                                        }}
                                    >
                                        Log Out
                                    </Button>
                                ) : null}

                                <Button
                                    icon={<ReloadOutlined />}
                                    onClick={loadInvite}
                                    disabled={loading || accepting}
                                >
                                    Refresh
                                </Button>
                            </Space>
                        </>
                    )}
                </Space>
            </Card>
        </div>
    );
}
