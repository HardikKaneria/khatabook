export function consumeQueryFlag(flagName, expectedValue = "1") {
    if (typeof window === "undefined") {
        return false;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get(flagName) !== expectedValue) {
        return false;
    }

    params.delete(flagName);
    const nextSearch = params.toString();
    const nextUrl = `${window.location.pathname}${nextSearch ? `?${nextSearch}` : ""}`;
    window.history.replaceState({}, "", nextUrl);

    return true;
}
