// src/App.jsx
import { useEffect, useMemo, useState } from "react";
import Login from "./pages/Login";
import Home from "./pages/Home";
import Accounts from "./pages/Accounts";
import Invoices from "./pages/Invoices";
import CompanySettings from "./pages/CompanySettings";
import DashboardLayout from "./layouts/DashboardLayout";
import UsersAdmin from "./pages/UsersAdmin";


function getSlug() {
  const seg = window.location.pathname.split("/").filter(Boolean)[0];
  return seg || "home";
}
function navigate(path, { replace = false } = {}) {
  if (replace) window.history.replaceState({}, "", path);
  else window.history.pushState({}, "", path);
  window.dispatchEvent(new PopStateEvent("popstate"));
}

export default function App() {
  const [slug, setSlug] = useState(getSlug());
  const [user, setUser] = useState(null);
  const [booting, setBooting] = useState(true);

  const hasAuthBlob = useMemo(() => {
    try { return !!localStorage.getItem("kbs_auth_v1"); } catch { return false; }
  }, []);

  useEffect(() => {
    let alive = true;
    (() => {
      try {
        if (isAuthValid()) {
          const auth = getAuth();
          if (alive) setUser(auth?.user || null);
        } else {
          clearAuth();
          if (alive) setUser(null);
        }
      } finally {
        if (alive) setBooting(false);
      }
    })();
    return () => { alive = false; };
  }, []);

  useEffect(() => {
    const onPop = () => setSlug(getSlug());
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, []);

  useEffect(() => {
    if (booting) return;
    if (!user && slug !== "login") navigate("/login", { replace: true });
    else if (user && (slug === "login" || slug === "")) navigate("/home", { replace: true });
  }, [user, slug, booting]);

  if (booting) {
    if (!hasAuthBlob) return <Login />;
    return <div style={{ height: "100vh", display: "grid", placeItems: "center" }}>Loading…</div>;
  }

  if (!user) return <Login />;

  const routeMap = {
    home: <Home />,
    accounts: <Accounts />,
    invoices: <Invoices />,
    "company-settings": <CompanySettings />,
    users: <UsersAdmin />,
  };

  const Page = routeMap[slug] || <div style={{ padding: 24 }}>404 - Page Not Found</div>;
  return <DashboardLayout user={user}>{Page}</DashboardLayout>;
}
