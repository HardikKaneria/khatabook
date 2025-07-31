import { useState } from "react";
import { Form, Input, Button, Typography, message, Space } from "antd";

const { Title, Text } = Typography;

export default function Login() {
  const [form] = Form.useForm();
  const [otpSent, setOtpSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [email, setEmail] = useState("");

  const handleSendOtp = async (values) => {
    setLoading(true);
    const response = await fetch("/wp-json/kbs/v1/send-otp", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: values.email }),
    });
    const result = await response.json();
    if (response.ok) {
      message.success("OTP sent to your email.");
      setEmail(values.email);
      setOtpSent(true);
    } else {
      message.error(result.message || "Failed to send OTP.");
    }
    setLoading(false);
  };

  const handleVerifyOtp = async (values) => {
    setLoading(true);
    const response = await fetch("/wp-json/kbs/v1/verify-otp", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, otp: values.otp }),
    });
    const result = await response.json();
    if (response.ok) {
      message.success("Login successful!");
      window.location.reload(); // or navigate to dashboard
    } else {
      message.error(result.message || "Invalid OTP.");
    }
    setLoading(false);
  };

  return (
    <div style={{ minHeight: "100vh", display: "flex", justifyContent: "center", alignItems: "center", background: "#f0f2f5" }}>
      <div style={{ width: 400, background: "#fff", padding: 32, borderRadius: 8, boxShadow: "0 2px 8px rgba(0,0,0,0.1)" }}>
        <Title level={3} style={{ textAlign: "center" }}>Login</Title>
        <Form
          form={form}
          layout="vertical"
          onFinish={otpSent ? handleVerifyOtp : handleSendOtp}
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
                <Input />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={loading} block>
                  Send OTP
                </Button>
              </Form.Item>
            </>
          ) : (
            <>
              <Text>Email: <strong>{email}</strong></Text>
              <Form.Item
                label="Enter OTP"
                name="otp"
                rules={[{ required: true, message: "Please input the OTP!" }]}
                style={{ marginTop: 16 }}
              >
                <Input />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={loading} block>
                  Verify & Login
                </Button>
              </Form.Item>
            </>
          )}
        </Form>

        <Space direction="vertical" style={{ width: "100%", textAlign: "center" }}>
          <Text type="secondary">Don't have an account?</Text>
          <Button type="link" onClick={() => message.info("Registration requires admin approval.")}>
            Register
          </Button>
        </Space>
      </div>
    </div>
  );
}
