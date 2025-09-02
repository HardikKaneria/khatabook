import React, { useEffect, useMemo, useState } from "react";
import { Layout, Menu, Button, Typography, Space, Grid } from "antd";
import {
    HomeOutlined,
    TeamOutlined,
    FileOutlined,
    LogoutOutlined,
    SettingOutlined,
} from "@ant-design/icons";

const { Header, Content, Footer, Sider } = Layout;
const { Title } = Typography;
const { useBreakpoint } = Grid;

const SIDEBAR_WIDTH = 240;
const HEADER_HEIGHT = 64;

function getKeyFromLocation() {
    if (typeof window === "undefined") return "home";
    const seg = window.location.pathname.split("/").filter(Boolean)[0];
    return seg || "home";
}

function navigate(href, { replace = false } = {}) {
    if (replace) {
        window.history.replaceState({}, "", href);
    } else {
        window.history.pushState({}, "", href);
    }
    window.dispatchEvent(new PopStateEvent("popstate"));
}

function isNewTabEvent(e) {
    // allow normal browser behavior for new tab/window
    return e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1;
}

const DashboardLayout = ({ children, user }) => {
    const { logout } = useAuth();
    const [loggingOut, setLoggingOut] = useState(false);
    const screens = useBreakpoint();
    const isDesktop = !!screens.lg;

    const userName = user?.display_name || user?.name || "User";
    const role = user?.role || "";

    // Keep the selected key in sync with SPA navigation
    const [activeKey, setActiveKey] = useState(getKeyFromLocation());
    useEffect(() => {
        const onPop = () => setActiveKey(getKeyFromLocation());
        window.addEventListener("popstate", onPop);
        return () => window.removeEventListener("popstate", onPop);
    }, []);

    const navItems = useMemo(() => {
        const items = [
            { key: "home", label: "Home", icon: <HomeOutlined />, href: "/home" },
            { key: "accounts", label: "Accounts", icon: <TeamOutlined />, href: "/accounts" },
            { key: "invoices", label: "Invoices", icon: <FileOutlined />, href: "/invoices" },
        ];
        if (role === "company_admin") {
            items.push(
                { key: "company-settings", label: "Company Settings", icon: <SettingOutlined />, href: "/company-settings" },
                { key: "users", label: "Users", icon: <TeamOutlined />, href: "/users" });
        }
        return items;
    }, [role]);

    const menuItems = useMemo(
        () =>
            navItems.map((it) => ({
                key: it.key,
                icon: it.icon,
                label: (
                    <a
                        href={it.href}
                        onClick={(e) => {
                            if (isNewTabEvent(e)) return; // let browser open a new tab/window
                            e.preventDefault();
                            navigate(it.href);
                            setActiveKey(it.key);
                        }}
                        style={{ color: "inherit" }}
                        aria-label={it.label}
                        aria-current={activeKey === it.key ? "page" : undefined}
                    >
                        {it.label}
                    </a>
                ),
            })),
        [navItems, activeKey]
    );

    const handleLogout = async () => {
        if (loggingOut) return;
        setLoggingOut(true);
        try {
            await fetch("/wp-json/kbs/v1/logout", { method: "POST", credentials: "include" });
        } catch (e) {
            // Non-fatal: still clear client state
            console.error("Logout failed:", e);
        }
        logout();                 // clear in-memory auth (no storage, no reload)
        navigate("/login");       // SPA navigate to login
        setLoggingOut(false);
    };

    return (
        <Layout style={{ minHeight: "100vh", width: "100vw", background: "#F8FAFC" }}>
            {/* FIXED SIDEBAR (desktop) */}
            <Sider
                breakpoint="lg"
                collapsedWidth={0}
                width={SIDEBAR_WIDTH}
                style={{
                    position: isDesktop ? "fixed" : "static",
                    top: 0,
                    left: 0,
                    bottom: 0,
                    height: isDesktop ? "100vh" : "auto",
                    zIndex: 101,
                    background: "#FFFFFF",
                    borderRight: "1px solid #e5e7eb",
                }}
            >
                <div
                    style={{
                        height: HEADER_HEIGHT,
                        display: "flex",
                        alignItems: "center",
                        padding: "0 20px",
                        fontWeight: 700,
                        fontSize: 18,
                        letterSpacing: 0.4,
                        color: "#121212",
                        borderBottom: "1px solid #12121220",
                        fontFamily: "Urbanist, system-ui, sans-serif",
                        cursor: "pointer",
                    }}
                    onClick={(e) => {
                        if (isNewTabEvent(e)) return;
                        navigate("/home");
                        setActiveKey("home");
                    }}
                    title="Go to Home"
                    role="link"
                    tabIndex={0}
                    onKeyDown={(e) => {
                        if (e.key === "Enter" || e.key === " ") {
                            e.preventDefault();
                            navigate("/home");
                            setActiveKey("home");
                        }
                    }}
                >
                    KHATABOOK
                </div>

                <Menu
                    mode="inline"
                    selectedKeys={[activeKey]}
                    items={menuItems}
                    style={{ background: "transparent", padding: "8px 0" }}
                />
            </Sider>

            {/* MAIN: adds margin-left & padding-top to account for fixed sider/header */}
            <Layout
                style={{
                    marginLeft: isDesktop ? SIDEBAR_WIDTH : 0,
                    paddingTop: HEADER_HEIGHT,
                    minHeight: "100vh",
                    background: "transparent",
                }}
            >
                {/* FIXED HEADER */}
                <Header
                    style={{
                        position: "fixed",
                        top: 0,
                        left: isDesktop ? SIDEBAR_WIDTH : 0,
                        right: 0,
                        height: HEADER_HEIGHT,
                        zIndex: 100,
                        background: "#FFFFFF",
                        padding: "0 20px",
                        boxShadow: "0 1px 8px rgba(18,18,18,0.06)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                    }}
                >
                    <Space direction="vertical" size={0}>
                        <Title
                            level={4}
                            style={{ margin: 0, color: "#121212", fontFamily: "Urbanist, system-ui, sans-serif" }}
                        >
                            Welcome, {userName}
                        </Title>
                    </Space>

                    <Button
                        className="kb-logout-button"
                        danger
                        icon={<LogoutOutlined />}
                        onClick={handleLogout}
                        loading={loggingOut}
                    >
                        Logout
                    </Button>
                </Header>

                <Content style={{ margin: 16 }}>
                    <div style={{ minHeight: 360 }}>{children}</div>
                </Content>

                <Footer style={{ textAlign: "center", color: "#6b7280" }}>
                    ©{new Date().getFullYear()} Khatabook SaaS
                </Footer>
            </Layout>
        </Layout>
    );
};

export default DashboardLayout;
