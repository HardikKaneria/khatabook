import { useEffect, useState } from "react";
import Login from "../components/Login";

export default function Home() {
  const [isLoggedIn, setIsLoggedIn] = useState(null);

  useEffect(() => {
    fetch("/wp-json/wp/v2/users/me", {
      credentials: "include",
    })
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error("Not logged in");
      })
      .then(() => {
        window.location.href = "/dashboard"; // ✅ redirect if logged in
      })
      .catch(() => setIsLoggedIn(false));
  }, []);

  if (isLoggedIn === null) return <div>Loading...</div>;

  return <Login />;
}
