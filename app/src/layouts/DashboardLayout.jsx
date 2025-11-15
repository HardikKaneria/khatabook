// ResponsiveShell.jsx
import React, { useEffect, useMemo, useState } from "react";
import {
    Layout,
    Menu,
    Grid,
    Button,
    Typography,
} from "antd";
import {
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    HomeOutlined,
    TeamOutlined,
    FileOutlined,
    SettingOutlined,
} from "@ant-design/icons";
import logo from "../assets/logo.svg";
import logoCompact from "../assets/V_logo.svg";

const { Header, Content, Footer, Sider } = Layout;
const { Title } = Typography;
const { useBreakpoint } = Grid;

const SIDEBAR_FULL = 240;   // expanded width
const SIDEBAR_COLLAPSED = 64; // collapsed width
const HEADER_HEIGHT = 64;

function navigate(href, { replace = false } = {}) {
    if (replace) window.history.replaceState({}, "", href);
    else window.history.pushState({}, "", href);
    window.dispatchEvent(new PopStateEvent("popstate"));
}

function getKeyFromLocation() {
    if (typeof window === "undefined") return "home";
    const seg = window.location.pathname.split("/").filter(Boolean)[0];
    return seg || "home";
}

export default function ResponsiveShell({ children, user }) {
    const screens = useBreakpoint();
    const isDesktop = !!screens.lg;

    // collapsed state (true: collapsed or closed)
    const [collapsed, setCollapsed] = useState(false);
    // active menu key from URL
    const [activeKey, setActiveKey] = useState(getKeyFromLocation());

    // When crossing the lg breakpoint, default to collapsed on mobile, expanded on desktop
    useEffect(() => {
        setCollapsed(!isDesktop); // collapsed on mobile, open on desktop
    }, [isDesktop]);

    // Sync active key on history changes
    useEffect(() => {
        const onPop = () => setActiveKey(getKeyFromLocation());
        window.addEventListener("popstate", onPop);
        return () => window.removeEventListener("popstate", onPop);
    }, []);

    // Menu items (example – add your own)
    const role = user?.role || "";
    const navItems = useMemo(() => {
        const base = [
            { key: "home", icon: <HomeOutlined />, label: "Home", href: "/home" },
            { key: "accounts", icon: <TeamOutlined />, label: "Accounts", href: "/accounts" },
            { key: "invoices", icon: <FileOutlined />, label: "Invoices", href: "/invoices" },
        ];
        if (role === "company_admin") {
            base.push(
                { key: "company-settings", icon: <SettingOutlined />, label: "Company Settings", href: "/company-settings" },
                { key: "users", icon: <TeamOutlined />, label: "Users", href: "/users" },
            );
        }
        return base;
    }, [role]);

    const menuItems = navItems.map((it) => ({
        key: it.key,
        icon: it.icon,
        label: (
            <a
                href={it.href}
                onClick={(e) => {
                    // SPA navigate
                    e.preventDefault();
                    navigate(it.href);
                    setActiveKey(it.key);
                    // On mobile, close the sidebar after navigation
                    if (!isDesktop) setCollapsed(true);
                }}
                style={{ color: "inherit" }}
                aria-current={activeKey === it.key ? "page" : undefined}
            >
                {it.label}
            </a>
        ),
    }));

    // Layout width offset for content when sidebar is visible on desktop
    const contentOffsetLeft = isDesktop
        ? (collapsed ? SIDEBAR_COLLAPSED : SIDEBAR_FULL)
        : 0;

    // Toggle button icon
    const ToggleIcon = collapsed ? MenuUnfoldOutlined : MenuFoldOutlined;

    const handleLogout = async () => {
        try {
            await fetch("/wp-json/kbs/v1/logout", { method: "POST", credentials: "include" });
        } catch (_) { }
        // local cleanup
        localStorage.removeItem("auth_token");
        localStorage.removeItem("token_expires_at");
        localStorage.removeItem("user_name");
        localStorage.removeItem("role");
        sessionStorage.removeItem("kbs_rest_root");
        sessionStorage.removeItem("kbs_rest_nonce");
        window.location.href = "/?action=logout";
    };

    return (
        <div style={{ minHeight: "100vh", background: "#F8FAFC" }}>
            {/* Fixed Sider */}
            <Sider
                // When on desktop we use collapsed width 64; on mobile we "collapse to 0"
                collapsedWidth={isDesktop ? SIDEBAR_COLLAPSED : 0}
                width={SIDEBAR_FULL}
                collapsed={collapsed}
                onCollapse={(v) => setCollapsed(v)}
                style={{
                    position: "fixed",
                    top: 0,
                    left: 0,
                    bottom: 0,
                    height: "100vh",
                    zIndex: 101,
                }}
                breakpoint="lg"
                theme="light"
            >
                <div
                    onClick={() => {
                        navigate("/home");
                        setActiveKey("home");
                        if (!isDesktop) setCollapsed(true);
                    }}
                    style={{
                        height: HEADER_HEIGHT,
                        display: "flex",
                        alignItems: "center",
                        padding: "0 16px",
                        fontWeight: 700,
                        fontSize: 18,
                        letterSpacing: 0.4,
                        color: "#121212",
                        borderBottom: "1px solid #e5e7eb",
                        cursor: "pointer",
                        justifyContent: "center",
                    }}
                >
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                        <img
                            src={collapsed ? logoCompact : logo}
                            alt="Vyavhar"
                            style={{ height: collapsed ? 24 : 26, width: "auto" }}
                        />
                    </div>
                </div>

                <Menu
                    mode="inline"
                    selectedKeys={[activeKey]}
                    items={menuItems}
                    style={{ borderRight: 0 }}
                />
            </Sider>

            {/* Mobile overlay mask when sidebar is open */}
            {!isDesktop && !collapsed && (
                <div
                    onClick={() => setCollapsed(true)}
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(0,0,0,0.35)",
                        zIndex: 100,
                    }}
                />
            )}

            {/* Main layout block shifted to the right on desktop */}
            <Layout style={{ marginLeft: contentOffsetLeft }}>
                {/* Fixed Header */}
                <Header
                    style={{
                        position: "fixed",
                        top: 0,
                        left: contentOffsetLeft,
                        right: 0,
                        height: HEADER_HEIGHT,
                        zIndex: 100,
                        background: "#FFFFFF",
                        padding: "0 16px",
                        boxShadow: "0 1px 8px rgba(18,18,18,0.06)",
                        display: "flex",
                        alignItems: "center",
                        gap: 12,
                    }}
                >
                    <Button
                        type="text"
                        icon={<ToggleIcon />}
                        onClick={() => setCollapsed((c) => !c)}
                        aria-label="Toggle sidebar"
                        style={{ fontSize: 18, width: 40, height: 40 }}
                    />
                    <Title level={4} style={{ margin: 0, flex: 1 }}>
                        Welcome{user?.name ? `, ${user.name}` : ""}
                    </Title>
                    <Button danger onClick={handleLogout}>
                        Logout
                    </Button>
                </Header>

                {/* Page content */}
                <Content style={{ padding: 16, paddingTop: HEADER_HEIGHT + 16 }}>
                    <div
                        style={{
                            minHeight: `calc(100vh - ${HEADER_HEIGHT + 16 + 96}px)`,
                            background: "#FFFFFF",
                            borderRadius: 12,
                            padding: 16,
                            boxShadow: "0 1px 6px rgba(16,24,40,0.08)",
                        }}
                    >
                        {children || "Content"}
                    </div>
                </Content>

                <Footer style={{ textAlign: "center", color: "#6b7280" }}>
                    ©{new Date().getFullYear()} Vyavhar SaaS
                </Footer>
            </Layout>
        </div>
    );
}
