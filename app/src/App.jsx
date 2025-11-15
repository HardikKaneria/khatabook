// src/App.jsx
import { useEffect, useState } from "react";
import Login from "./pages/Login";
import Home from "./pages/Home";
import Accounts from "./pages/Accounts";
import Invoices from "./pages/Invoices";
import CompanySettings from "./pages/CompanySettings";
import DashboardLayout from "./layouts/DashboardLayout";
import { loadAuth, clearAuth } from "./utils/authStorage";
import Users from "./pages/UsersAdmin";
import ToastProvider from "./components/ToastProvider";

// --- tiny router helpers -----------------------------------------------------
function getSlug() {
    const seg = window.location.pathname.split("/").filter(Boolean);
    return seg[0] || "home";
}

// client-side navigate without reload
export function navigate(path, { replace = false } = {}) {
    if (replace) window.history.replaceState({}, "", path);
    else window.history.pushState({}, "", path);
    window.dispatchEvent(new PopStateEvent("popstate"));
}
// ----------------------------------------------------------------------------

export default function App() {
    const [auth, setAuth] = useState(null); // { token, expires_at, user:{...}, rest:{...} }
    const [slug, setSlug] = useState(getSlug());
    const [ready, setReady] = useState(false);
    const [expiryTimer, setExpiryTimer] = useState(null);

    // 1) Initial auth hydrate (encrypted localStorage)
    useEffect(() => {
        let alive = true;
        (async () => {
            const a = await loadAuth();
            if (!alive) return;
            setAuth(a || null);
            setReady(true);
        })();
        return () => { alive = false; };
    }, []);

    // 2) Watch URL changes (back/forward and our own navigate())
    useEffect(() => {
        const onPop = () => setSlug(getSlug());
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

    // 6) Lightweight auth check
    const isAuthed =
        !!auth?.token &&
        !!auth?.user &&
        (!auth?.expires_at || Date.now() / 1000 < auth.expires_at);

    // 7) Route guards without flicker
    useEffect(() => {
        if (!ready) return;
        if (!isAuthed && slug !== "login") {
            navigate("/login", { replace: true });
        } else if (isAuthed && slug === "login") {
            navigate("/home", { replace: true });
        }
    }, [ready, isAuthed, slug]);

    // 8) Avoid flash while deciding redirects
    if (!ready) return null;
    if (!isAuthed && slug !== "login") return null;
    if (isAuthed && slug === "login") return null;

    const role = auth?.user?.role || "";
    const userForLayout = {
        id: auth?.user?.id,
        name: auth?.user?.display_name || auth?.user?.name || "User",
        display_name: auth?.user?.display_name,
        role,
        orgId: auth?.user?.org_id ?? auth?.user?.orgId ?? 1,
    };

    const routeMap = {
        home: <Home />,
        accounts: <Accounts />,
        invoices: <Invoices />,
        "company-settings": <CompanySettings />,
        users: <Users />,
    };

    const Page =
        routeMap[slug] || <div style={{ padding: 24 }}>404 - Page Not Found</div>;

    const content = !isAuthed ? (
        <Login />
    ) : (
        <DashboardLayout user={userForLayout}>{Page}</DashboardLayout>
    );

    return <ToastProvider>{content}</ToastProvider>;
}
