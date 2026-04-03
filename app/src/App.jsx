// src/App.jsx
import { useEffect, useState } from "react";
import Login from "./pages/Login";
import Home from "./pages/Home";
import CompanySettings from "./pages/CompanySettings";
import DashboardLayout from "./layouts/DashboardLayout";
import { loadAuth, clearAuth, saveAuth } from "./utils/authStorage";
import Users from "./pages/UsersAdmin";
import ToastProvider from "./components/ToastProvider";
import apiClient, { configureApiClient } from "./lib/apiClient";
import AccountsPage from "./modules/accounts/AccountsPage.jsx";
import AccountDetailPage from "./modules/accounts/AccountDetailPage.jsx";
import InvoicesPage from "./modules/invoices/InvoicesPage.jsx";
import InvoiceDetailPage from "./modules/invoices/InvoiceDetailPage.jsx";
import ExpensesPage from "./modules/expenses/ExpensesPage.jsx";
import ExpenseDetailPage from "./modules/expenses/ExpenseDetailPage.jsx";
import PaymentsPage from "./modules/payments/PaymentsPage.jsx";
import ProfitTaxPage from "./modules/reports/ProfitTaxPage.jsx";
import InvoiceSettingsPage from "./modules/settings/invoices/InvoiceSettingsPage.jsx";
import ContactsPage from "./modules/contacts/ContactsPage.jsx";
import AcceptInvite from "./pages/AcceptInvite.jsx";

const getOrgIdFromAuth = (auth) => {
    const user = auth?.user || {};
    return (
        user?.org_id ??
        user?.orgId ??
        auth?.org_id ??
        auth?.orgId ??
        (Array.isArray(user?.orgs) &&
            (user.orgs.find((org) => org.is_primary)?.org_id ?? user.orgs[0]?.org_id))
    );
};

const getActiveOrgRole = (auth) => {
    const user = auth?.user || {};
    const orgId = getOrgIdFromAuth(auth);
    if (orgId && Array.isArray(user?.orgs)) {
        const activeOrg = user.orgs.find((org) => Number(org?.org_id) === Number(orgId));
        if (activeOrg?.role) {
            return String(activeOrg.role).toLowerCase();
        }
    }
    return String(user?.role || "").toLowerCase();
};

const getActiveOrgName = (auth) => {
    const user = auth?.user || {};
    const orgId = getOrgIdFromAuth(auth);
    if (orgId && Array.isArray(user?.orgs)) {
        const activeOrg = user.orgs.find((org) => Number(org?.org_id) === Number(orgId));
        if (activeOrg?.org_name) {
            return activeOrg.org_name;
        }
    }
    return user?.org_name || null;
};

// --- tiny router helpers -----------------------------------------------------
function getRoute() {
    const seg = window.location.pathname.split("/").filter(Boolean);
    return {
        slug: seg[0] || "home",
        param: seg[1] || null,
    };
}

// client-side navigate without reload
export function navigate(path, { replace = false } = {}) {
    if (replace) window.history.replaceState({}, "", path);
    else window.history.pushState({}, "", path);
    window.dispatchEvent(new PopStateEvent("popstate"));
}
// ----------------------------------------------------------------------------

const syncAuthHeaders = (authState) => {
    if (authState?.token) {
        localStorage.setItem("vy_token", authState.token);
    } else {
        localStorage.removeItem("vy_token");
    }

    const orgId = authState?.user?.org_id ?? authState?.user?.orgId ?? authState?.rest?.org_id;
    if (orgId) {
        localStorage.setItem("vy_active_org_id", String(orgId));
    } else {
        localStorage.removeItem("vy_active_org_id");
    }

    if (authState?.rest?.nonce) {
        localStorage.setItem("vy_wp_rest_nonce", authState.rest.nonce);
    } else {
        localStorage.removeItem("vy_wp_rest_nonce");
    }
};

export default function App() {
    const [auth, setAuth] = useState(null); // { token, expires_at, user:{...}, rest:{...} }
    const [route, setRoute] = useState(getRoute());
    const [ready, setReady] = useState(false);
    const [expiryTimer, setExpiryTimer] = useState(null);
    const [orgSwitching, setOrgSwitching] = useState(false);

    // 1) Initial auth hydrate (encrypted localStorage)
    useEffect(() => {
        let alive = true;
        (async () => {
            const a = await loadAuth();
            if (!alive) return;
            syncAuthHeaders(a);
            configureApiClient(a);
            setAuth(a || null);
            setReady(true);
        })();
        return () => { alive = false; };
    }, []);

    // 2) Watch URL changes (back/forward and our own navigate())
    useEffect(() => {
        const onPop = () => setRoute(getRoute());
        window.addEventListener("popstate", onPop);
        return () => window.removeEventListener("popstate", onPop);
    }, []);

    // 3) Handle ?action=logout (once on mount and on popstate)
    useEffect(() => {
        const checkLogout = async () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get("action") === "logout") {
                await clearAuth();
                setAuth(null);
                // strip query and land on /login
                navigate("/login", { replace: true });
            }
        };
        checkLogout();

        const onPop = () => checkLogout();
        window.addEventListener("popstate", onPop);
        return () => window.removeEventListener("popstate", onPop);
    }, []);

    // 4) Auto-logout when token expires
    useEffect(() => {
        if (expiryTimer) {
            clearTimeout(expiryTimer);
            setExpiryTimer(null);
        }
        if (!auth?.expires_at) return;

        const msLeft = Math.max(0, auth.expires_at * 1000 - Date.now());
        if (msLeft === 0) {
            // already expired
            (async () => {
                await clearAuth();
                setAuth(null);
                navigate("/login", { replace: true });
            })();
            return;
        }

        const t = setTimeout(async () => {
            await clearAuth();
            setAuth(null);
            navigate("/login?expired=1", { replace: true });
        }, msLeft);
        setExpiryTimer(t);

        return () => clearTimeout(t);
    }, [auth?.expires_at]); // eslint-disable-line react-hooks/exhaustive-deps

    // 5) Cross-tab sync: if another tab logs out, follow along
    useEffect(() => {
        const onStorage = async (e) => {
            // We just reload auth on *any* storage mutation. Cheap & robust.
            const a = await loadAuth();
            setAuth(a || null);
        };
        window.addEventListener("storage", onStorage);
        return () => window.removeEventListener("storage", onStorage);
    }, []);

    // 5b) Keep API client headers in sync (Bearer + org header)
    useEffect(() => {
        syncAuthHeaders(auth);
        configureApiClient(auth || null);
    }, [auth]);

    useEffect(() => {
        const onAuthInvalid = () => {
            clearAuth();
            setAuth(null);
            navigate("/login?expired=1", { replace: true });
        };

        window.addEventListener("kbs-auth-invalid", onAuthInvalid);
        return () => window.removeEventListener("kbs-auth-invalid", onAuthInvalid);
    }, []);

    // 6) Lightweight auth check
    const isAuthed =
        !!auth?.token &&
        !!auth?.user &&
        (!auth?.expires_at || Date.now() / 1000 < auth.expires_at);
    const isPublicRoute = route.slug === "login" || route.slug === "accept-invite";

    // 7) Route guards without flicker
    useEffect(() => {
        if (!ready) return;
        if (!isAuthed && !isPublicRoute) {
            navigate("/login", { replace: true });
        } else if (isAuthed && route.slug === "login") {
            navigate("/home", { replace: true });
        }
    }, [ready, isAuthed, isPublicRoute, route.slug]);

    useEffect(() => {
        if (!ready || !isAuthed) return;

        const params = new URLSearchParams(window.location.search);
        const requestedOrgId = Number(params.get("org_id") || 0);
        const currentOrgId = Number(getOrgIdFromAuth(auth) || 0);
        if (!requestedOrgId || requestedOrgId === currentOrgId) {
            return;
        }

        const hasRequestedOrg = Array.isArray(auth?.user?.orgs)
            && auth.user.orgs.some((org) => Number(org?.org_id) === requestedOrgId);

        const stripOrgQuery = () => {
            params.delete("org_id");
            const nextSearch = params.toString();
            const nextUrl = `${window.location.pathname}${nextSearch ? `?${nextSearch}` : ""}`;
            window.history.replaceState({}, "", nextUrl);
        };

        if (!hasRequestedOrg) {
            stripOrgQuery();
            return;
        }

        let cancelled = false;
        setOrgSwitching(true);

        (async () => {
            try {
                const nextAuth = await apiClient.post("/kbs/v1/active-org", { org_id: requestedOrgId });
                if (cancelled) return;
                await saveAuth(nextAuth);
                setAuth(nextAuth);
            } catch (_) {
                if (!cancelled) {
                    stripOrgQuery();
                }
            } finally {
                if (!cancelled) {
                    stripOrgQuery();
                    setOrgSwitching(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [ready, isAuthed, auth, route]);

    // 8) Avoid flash while deciding redirects
    if (!ready) return null;
    if (!isAuthed && !isPublicRoute) return null;
    if (isAuthed && route.slug === "login") return null;

    const role = getActiveOrgRole(auth);
    const userForLayout = {
        id: auth?.user?.id,
        name: auth?.user?.display_name || auth?.user?.name || "User",
        display_name: auth?.user?.display_name,
        role,
        orgId: getOrgIdFromAuth(auth) ?? 1,
        orgName: getActiveOrgName(auth),
        orgs: Array.isArray(auth?.user?.orgs) ? auth.user.orgs : [],
        orgSwitching,
        onSwitchOrg: async (nextOrgId) => {
            if (!nextOrgId || Number(nextOrgId) === Number(getOrgIdFromAuth(auth) || 0)) {
                return;
            }

            setOrgSwitching(true);
            try {
                const nextAuth = await apiClient.post("/kbs/v1/active-org", { org_id: Number(nextOrgId) });
                await saveAuth(nextAuth);
                setAuth(nextAuth);
                navigate("/home", { replace: true });
            } finally {
                setOrgSwitching(false);
            }
        },
    };

    const Page = (() => {
        switch (route.slug) {
            case "home":
                return <Home user={userForLayout} />;
            case "accounts":
                return route.param ? (
                    <AccountDetailPage accountId={route.param} />
                ) : (
                    <AccountsPage />
                );
            case "contacts":
                return <ContactsPage />;
            case "invoices":
                return route.param ? (
                    <InvoiceDetailPage invoiceId={route.param} />
                ) : (
                    <InvoicesPage />
                );
            case "expenses":
                return route.param ? (
                    <ExpenseDetailPage expenseId={route.param} />
                ) : (
                    <ExpensesPage />
                );
            case "payments":
                return <PaymentsPage />;
            case "reports":
                return <ProfitTaxPage />;
            case "settings":
                if (route.param === "invoices") {
                    return <InvoiceSettingsPage />;
                }
                return <div style={{ padding: 24 }}>Select a settings section.</div>;
            case "company-settings":
                return <CompanySettings />;
            case "users":
                return <Users />;
            default:
                return <div style={{ padding: 24 }}>404 - Page Not Found</div>;
        }
    })();

    const content = route.slug === "accept-invite" ? (
        <AcceptInvite auth={auth} onAuthenticated={setAuth} />
    ) : !isAuthed ? (
        <Login />
    ) : (
        <DashboardLayout user={userForLayout}>{Page}</DashboardLayout>
    );

    return <ToastProvider>{content}</ToastProvider>;
}
