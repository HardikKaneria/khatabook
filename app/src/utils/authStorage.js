// utils/authStorage.js
// Encrypted localStorage for auth using Web Crypto (AES-GCM)

const LS_KEY = "kbsaas_auth_v1";
const PEPPER = "kbs-static-pepper-v1"; // change to an env/secret string

// Derive a CryptoKey from origin + static pepper
async function getKey() {
    if (!window.crypto?.subtle) return null; // fallback later
    const enc = new TextEncoder();
    const material = enc.encode(`${window.location.origin}:${PEPPER}`);
    const keyMaterial = await crypto.subtle.importKey(
        "raw",
        material,
        "PBKDF2",
        false,
        ["deriveKey"]
    );
    return crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: enc.encode("kbsaas-auth-salt"),
            iterations: 100000,
            hash: "SHA-256",
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt", "decrypt"]
    );
}

function b64(buf) {
    return btoa(String.fromCharCode(...new Uint8Array(buf)));
}
function b64toBuf(b64str) {
    const bin = atob(b64str);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes.buffer;
}

// Save the raw auth object returned by /verify-otp
export async function saveAuth(raw) {
    // normalize a bit
    const auth = {
        token: raw?.token || null,
        expires_at: raw?.expires_at || null,
        user: raw?.user || null,
        rest: raw?.rest || null,
    };

    try {
        const key = await getKey();
        const json = JSON.stringify(auth);
        if (!key) {
            // fallback: not recommended, but prevents hard break
            localStorage.setItem(LS_KEY, JSON.stringify({ _plain: json }));
            return;
        }
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ct = await crypto.subtle.encrypt(
            { name: "AES-GCM", iv },
            key,
            new TextEncoder().encode(json)
        );
        const payload = JSON.stringify({ iv: b64(iv), ct: b64(ct) });
        localStorage.setItem(LS_KEY, payload);
    } catch (e) {
        console.error("saveAuth failed, falling back to plain:", e);
        localStorage.setItem(LS_KEY, JSON.stringify({ _plain: JSON.stringify(raw) }));
    }
}

export async function loadAuth() {
    const v = localStorage.getItem(LS_KEY);
    if (!v) return null;

    try {
        const parsed = JSON.parse(v);
        if (parsed?._plain) return JSON.parse(parsed._plain);

        const key = await getKey();
        if (!key) return JSON.parse(parsed._plain || "null");

        const iv = b64toBuf(parsed.iv);
        const ct = b64toBuf(parsed.ct);
        const pt = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv: new Uint8Array(iv) },
            key,
            ct
        );
        return JSON.parse(new TextDecoder().decode(pt));
    } catch (e) {
        console.warn("loadAuth failed:", e);
        return null;
    }
}

export function clearAuth() {
    localStorage.removeItem(LS_KEY);
}

export async function isLoggedIn() {
    const a = await loadAuth();
    if (!a?.token || !a?.user) return false;
    if (a.expires_at && Date.now() / 1000 > a.expires_at) return false;
    return true;
}

// convenience getters
export async function getAuth() {
    const v = await loadAuth();
    console.log("getAuth", v);
    return v;
}