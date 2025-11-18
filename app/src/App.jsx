// src/App.jsx
import { useEffect, useState } from "react";
import Login from "./pages/Login";
import Home from "./pages/Home";
import CompanySettings from "./pages/CompanySettings";
import DashboardLayout from "./layouts/DashboardLayout";
import { loadAuth, clearAuth } from "./utils/authStorage";
import Users from "./pages/UsersAdmin";
import ToastProvider from "./components/ToastProvider";
import { configureApiClient } from "./lib/apiClient";
import AccountsPage from "./modules/accounts/AccountsPage.jsx";
import AccountDetailPage from "./modules/accounts/AccountDetailPage.jsx";
import InvoicesPage from "./modules/invoices/InvoicesPage.jsx";
import InvoiceDetailPage from "./modules/invoices/InvoiceDetailPage.jsx";
import ExpensesPage from "./modules/expenses/ExpensesPage.jsx";
import ExpenseDetailPage from "./modules/expenses/ExpenseDetailPage.jsx";
import ProfitTaxPage from "./modules/reports/ProfitTaxPage.jsx";
import InvoiceSettingsPage from "./modules/settings/invoices/InvoiceSettingsPage.jsx";

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

    // 6) Lightweight auth check
    const isAuthed =
        !!auth?.token &&
        !!auth?.user &&
        (!auth?.expires_at || Date.now() / 1000 < auth.expires_at);

    // 7) Route guards without flicker
    useEffect(() => {
        if (!ready) return;
        if (!isAuthed && route.slug !== "login") {
            navigate("/login", { replace: true });
        } else if (isAuthed && route.slug === "login") {
            navigate("/home", { replace: true });
        }
    }, [ready, isAuthed, route.slug]);

    // 8) Avoid flash while deciding redirects
    if (!ready) return null;
    if (!isAuthed && route.slug !== "login") return null;
    if (isAuthed && route.slug === "login") return null;

    const role = auth?.user?.role || "";
    const userForLayout = {
        id: auth?.user?.id,
        name: auth?.user?.display_name || auth?.user?.name || "User",
        display_name: auth?.user?.display_name,
        role,
        orgId: auth?.user?.org_id ?? auth?.user?.orgId ?? 1,
    };

    const Page = (() => {
        switch (route.slug) {
            case "home":
                return <Home />;
            case "accounts":
                return route.param ? (
                    <AccountDetailPage accountId={route.param} />
                ) : (
                    <AccountsPage />
                );
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

    const content = !isAuthed ? (
        <Login />
    ) : (
        <DashboardLayout user={userForLayout}>{Page}</DashboardLayout>
    );

    return <ToastProvider>{content}</ToastProvider>;
}
