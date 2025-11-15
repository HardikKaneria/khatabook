import { useState } from 'react';
import {
	Form,
	Input,
	Button,
	Typography,
	Progress,
	Alert,
} from 'antd';
import {
	PoweroffOutlined,
	SyncOutlined,
} from '@ant-design/icons';
import { useToast } from "../components/ToastProvider";

const { Title, Text } = Typography;

export default function Registration() {
	const [form] = Form.useForm();
	const [step, setStep] = useState('form'); // 'form' | 'otp' | 'done'
	const [otp, setOtp] = useState('');
	const [serverOtp, setServerOtp] = useState('');
	const [formData, setFormData] = useState({});
	const [loading, setLoading] = useState(false);
	const [errorMessage, setErrorMessage] = useState('');

	const password = Form.useWatch('password', form);
	const message = useToast();

	const getPasswordStrength = (value) => {
		let score = 0;
		if (!value) return { label: '', score: 0, color: 'default' };

		if (value.length >= 8) score += 1;
		if (/[A-Z]/.test(value)) score += 1;
		if (/[0-9]/.test(value)) score += 1;
		if (/[^A-Za-z0-9]/.test(value)) score += 1;

		if (score <= 1) return { label: 'Weak', score: 25, color: 'red' };
		if (score === 2) return { label: 'Fair', score: 50, color: 'orange' };
		if (score === 3) return { label: 'Good', score: 75, color: 'blue' };
		return { label: 'Strong', score: 100, color: 'green' };
	};

	const onFinishForm = async (values) => {
		if (values.password !== values.confirmPassword) {
			message.error("Passwords do not match");
			return;
		}

		setLoading(true);
		setErrorMessage("");

		const payload = {
			name: values.name,
			email: values.email,
			company: values.company,
			password: values.password,
			context: 'register',
		};
		setFormData(payload);

		try {
			const res = await fetch('/wp-json/kbs/v1/send-otp', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: payload.email, context: payload.context }),
			});

			const data = await res.json();

			if (res.status === 404) {
				const msg = "Server not found. Please try again later.";
				message.error(msg);
				setErrorMessage(msg);
			} else if (res.status === 400 && data.code === 'missing_fields') {
				message.error(data.message);
				setErrorMessage(data.message);
			} else if (data.status === "sent") {
				message.success(data.message || "OTP sent successfully");
				setStep('otp');
			} else {
				const msg = data.message || 'Failed to send OTP';
				message.error(msg);
				setErrorMessage(msg);
			}
		} catch (error) {
			const msg = "Network error. Please try again.";
			message.error(msg);
			setErrorMessage(msg);
		} finally {
			setLoading(false);
		}
	};

	const verifyOtp = async () => {
		if (!otp) {
			message.error("Please enter the OTP.");
			return;
		}

		setLoading(true);
		setErrorMessage("");

		try {
			const response = await fetch("/wp-json/kbs/v1/submit-registration", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ ...formData, otp }),
			});

			const result = await response.json();

			if (response.status === 404) {
				const msg = "Server not found. Please try again later.";
				message.error(msg);
				setErrorMessage(msg);
			} else if (response.status === 400 && result.code === "missing_fields") {
				message.error(result.message);
				setErrorMessage(result.message);
			} else if (response.ok) {
				message.success(result.message || "Registered successfully!");
				setStep("done");
			} else {
				const msg = result.message || "Invalid OTP.";
				message.error(msg);
				setErrorMessage(msg);
			}
		} catch (error) {
			const msg = "Network error. Please try again.";
			message.error(msg);
			setErrorMessage(msg);
		} finally {
			setLoading(false);
		}
	};

	const strength = getPasswordStrength(password);

	return (
		<div
			style={{
				minHeight: "100vh",
				minWidth: "100vw",
				display: "flex",
				justifyContent: "center",
				alignItems: "center",
				background: "#f0f2f5",
			}}
		>
			<div
				style={{
					width: 400,
					background: "#fff",
					padding: 32,
					borderRadius: 8,
					boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
				}}
			>
				{step === 'form' && (
					<>
						<Title level={3} style={{ textAlign: 'center' }}>Register</Title>

						{errorMessage && (
							<Alert
								message={errorMessage}
								type="error"
								showIcon
								closable
								style={{ marginBottom: 16, fontSize: 14 }}
								onClose={() => setErrorMessage("")}
							/>
						)}

						<Form
							layout="vertical"
							form={form}
							onFinish={onFinishForm}
							onValuesChange={() => setErrorMessage("")}
						>
							<Form.Item label="Full Name" name="name" rules={[{ required: true }]}>
								<Input />
							</Form.Item>
							<Form.Item label="Email" name="email" rules={[{ required: true, type: 'email' }]}>
								<Input />
							</Form.Item>
							<Form.Item label="Company Name" name="company" rules={[{ required: true }]}>
								<Input />
							</Form.Item>

							<Form.Item
								label="Password"
								name="password"
								rules={[{ required: true, min: 6, message: "Minimum 6 characters" }]}
							>
								<Input.Password />
							</Form.Item>

							{password && (
								<div style={{ marginBottom: 12 }}>
									<Text type="secondary">Strength: <b style={{ color: strength.color }}>{strength.label}</b></Text>
									<Progress percent={strength.score} showInfo={false} strokeColor={strength.color} />
								</div>
							)}

							<Form.Item
								label="Confirm Password"
								name="confirmPassword"
								dependencies={['password']}
								rules={[
									{ required: true },
									({ getFieldValue }) => ({
										validator(_, value) {
											if (!value || getFieldValue('password') === value) {
												return Promise.resolve();
											}
											return Promise.reject(new Error("Passwords do not match"));
										},
									}),
								]}
							>
								<Input.Password />
							</Form.Item>

							<Form.Item>
								<Button
									type="primary"
									htmlType="submit"
									icon={loading ? <SyncOutlined spin /> : <PoweroffOutlined />}
									disabled={loading}
									block
								>
									{loading ? "Sending OTP..." : "Send OTP"}
								</Button>
							</Form.Item>
						</Form>
					</>
				)}

				{step === 'otp' && (
					<>
						<Title level={4} style={{ textAlign: 'center' }}>Verify OTP</Title>

						{errorMessage && (
							<Alert
								message={errorMessage}
								type="error"
								showIcon
								closable
								style={{ marginBottom: 16, fontSize: 14 }}
								onClose={() => setErrorMessage("")}
							/>
						)}

						<Form layout="vertical" onFinish={verifyOtp}>
							<Form.Item label="Enter OTP sent to your email">
								<Input value={otp} onChange={(e) => setOtp(e.target.value)} />
							</Form.Item>
							<Form.Item>
								<Button
									type="primary"
									htmlType="submit"
									icon={loading ? <SyncOutlined spin /> : <PoweroffOutlined />}
									disabled={loading}
									block
								>
									{loading ? "Verifying..." : "Verify OTP"}
								</Button>
							</Form.Item>
						</Form>
					</>
				)}

				{step === 'done' && (
					<div style={{ textAlign: 'center' }}>
						<Title level={4}>Registration Submitted</Title>
						<Text>Admin will approve your request shortly.</Text>
					</div>
				)}
			</div>
		</div>
	);
}
