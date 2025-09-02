import { useEffect, useMemo, useRef, useState } from "react";
import { Form, Input, Button, Typography, message, Space, Alert, Card, Divider } from "antd";
import { PoweroffOutlined, SyncOutlined, MailOutlined, SafetyCertificateOutlined, ArrowLeftOutlined, ReloadOutlined } from "@ant-design/icons";
import Registration from "./Registration";


const { Title, Text } = Typography;

export default function Login() {
    const [form] = Form.useForm();
    const [otpSent, setOtpSent] = useState(false);
    const [sending, setSending] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [email, setEmail] = useState("");
    const [showRegister, setShowRegister] = useState(false);
    const [errorMessage, setErrorMessage] = useState("");
    const [cooldown, setCooldown] = useState(0);
    const cooldownRef = useRef(0);

    // Cooldown ticker for resend OTP
    useEffect(() => {
        cooldownRef.current = cooldown;
        if (cooldown <= 0) return;
        const id = setInterval(() => {
            cooldownRef.current = cooldownRef.current - 1;
            setCooldown(cooldownRef.current);
            if (cooldownRef.current <= 0) clearInterval(id);
        }, 1000);
        return () => clearInterval(id);
    }, [cooldown]);

    const canResend = useMemo(
        () => !sending && !verifying && otpSent && cooldown <= 0,
        [sending, verifying, otpSent, cooldown]
    );

    const handleSendOtp = async (values) => {
        setSending(true);
        setErrorMessage("");
        try {
            const res = await fetch("/wp-json/kbs/v1/send-otp", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email: values?.email, context: "login" }),
            });

            let result = {};
            try { result = await res.json(); } catch { }

            if (res.status === 404) {
                const msg = "Server not found. Please try again later.";
                message.error(msg);
                setErrorMessage(msg);
                return;
            }

            if (result?.status === "not_registered") {
                const msg = result?.message || "Email is not registered.";
                message.error(msg);
                setErrorMessage(msg);
                return;
            }

            if (!res.ok) {
                const msg = result?.message || "Failed to send OTP.";
                message.error(msg);
                setErrorMessage(msg);
                return;
            }

            message.success("OTP sent to your email.");
            setEmail(values?.email || "");
            setOtpSent(true);
            setCooldown(45);
            setTimeout(() => form.setFieldsValue({ otp: "" }), 0);
        } catch (e) {
            const msg = "Network error. Please try again.";
            message.error(msg);
            setErrorMessage(msg);
        } finally {
            setSending(false);
        }
    };

    const handleVerifyOtp = async () => {
        try {
            const otp = form.getFieldValue("otp");
            if (!otp) {
                message.error("Please enter the OTP.");
                return;
            }

            setVerifying(true);
            setErrorMessage("");

            const response = await fetch("/wp-json/kbs/v1/verify-otp", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email, otp }),
                credentials: "include",
            });

            let result = {};
            try { result = await response.json(); } catch { }

            if (response.ok && result?.status === "authenticated") {
                await saveAuth(result);
                message.success("Logged in successfully.");
                window.location.href = "/home";
            } else {
                const msg = result?.message || "OTP verification failed.";
                message.error(msg);
                setErrorMessage(msg);
            }
        } catch (error) {
            console.error("OTP verification failed:", error);
            const msg = "Something went wrong. Please try again.";
            message.error(msg);
            setErrorMessage(msg);
        } finally {
            setVerifying(false);
        }
    };

    const handleFinish = async (values) => {
        if (!otpSent) return handleSendOtp(values);
        return handleVerifyOtp();
    };

    const handleResend = async () => {
        if (!canResend) return;
        const currentEmail = email || form.getFieldValue("email");
        if (!currentEmail) {
            message.warning("Please enter your email first.");
            return;
        }
        await handleSendOtp({ email: currentEmail });
    };

    const handleChangeEmail = () => {
        setOtpSent(false);
        setCooldown(0);
        setErrorMessage("");
        form.resetFields(["otp"]);
        setTimeout(() => {
            form.setFieldsValue({ email }); 
        }, 0);
    };

    if (showRegister) return <Registration />;

    return (
        <div
            style={{
                minHeight: "100vh",
                display: "flex",
                justifyContent: "center",
                alignItems: "center",
                background: "#FDFDFD",
                minWidth: "100vw",
                padding: 16,
            }}
        >
            <Card
                style={{
                    width: 420,
                    borderRadius: 12,
                    boxShadow: "0 8px 24px rgba(18, 18, 18, 0.08)",
                }}
                bodyStyle={{ padding: 28 }}
            >
                <div style={{ textAlign: "center", marginBottom: 8 }}>
                    <Title level={3} style={{ marginBottom: 4 }}>Login</Title>
                    <Text type="secondary">Enter your email to receive a one-time code</Text>
                </div>

                {errorMessage ? (
                    <Alert
                        message={errorMessage}
                        type="error"
                        showIcon
                        closable
                        style={{ marginBottom: 16, fontSize: 14, fontWeight: 600 }}
                        onClose={() => setErrorMessage("")}
                    />
                ) : null}

                <Form
                    form={form}
                    layout="vertical"
                    onFinish={handleFinish}
                    onValuesChange={() => setErrorMessage("")}
                    requiredMark={false}
                >
                    {!otpSent ? (
                        <>
                            <Form.Item
                                label="Email"
                                name="email"
                                rules={[
                                    { required: true, message: "Please input your email!" },
                                    { type: "email", message: "Please enter a valid email!" },
                                ]}
                            >
                                <Input
                                    prefix={<MailOutlined style={{ color: "#B2BEC3" }} />}
                                    placeholder="you@company.com"
                                    autoComplete="email"
                                />
                            </Form.Item>

                            <Form.Item>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    icon={sending ? <SyncOutlined spin /> : <PoweroffOutlined />}
                                    disabled={sending}
                                    block
                                >
                                    {sending ? "Sending OTP..." : "Send OTP"}
                                </Button>
                            </Form.Item>
                        </>
                    ) : (
                        <>
                            <Space
                                style={{
                                    width: "100%",
                                    justifyContent: "space-between",
                                    alignItems: "baseline",
                                }}
                            >
                                <Text>
                                    Email: <strong>{email}</strong>
                                </Text>
                                <Button
                                    size="small"
                                    type="link"
                                    className="b-text-primary"
                                    icon={<ArrowLeftOutlined />}
                                    onClick={handleChangeEmail}
                                >
                                    Change email
                                </Button>
                            </Space>

                            <Form.Item
                                label="Enter OTP"
                                name="otp"
                                rules={[{ required: true, message: "Please input the OTP!" }]}
                                style={{ marginTop: 12 }}
                            >
                                <Input
                                    prefix={<SafetyCertificateOutlined style={{ color: "#B2BEC3" }} />}
                                    placeholder="6-digit code"
                                    maxLength={6}
                                    inputMode="numeric"
                                    onChange={(e) => {
                                        const onlyDigits = e.target.value.replace(/\D/g, "");
                                        form.setFieldsValue({ otp: onlyDigits });
                                    }}
                                />
                            </Form.Item>

                            <Space style={{ width: "100%", justifyContent: "space-between" }}>
                                <Button
                                    type="default"
                                    icon={<ReloadOutlined />}
                                    onClick={handleResend}
                                    disabled={!canResend}
                                >
                                    {cooldown > 0 ? `Resend in ${cooldown}s` : "Resend OTP"}
                                </Button>

                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    icon={verifying ? <SyncOutlined spin /> : <PoweroffOutlined />}
                                    disabled={verifying}
                                >
                                    {verifying ? "Verifying..." : "Verify & Login"}
                                </Button>
                            </Space>
                        </>
                    )}
                </Form>

                <Divider />

                <Space direction="vertical" style={{ width: "100%", textAlign: "center" }} size={0}>
                    <Text type="secondary">Don&apos;t have an account?</Text>
                    <Button type="link" onClick={() => setShowRegister(true)} className="b-text-primary">
                        Register
                    </Button>
                </Space>
            </Card>
        </div>
    );
}
