export function broadcastAuthUpdated(auth) {
	if (typeof window === "undefined") {
		return;
	}

	window.dispatchEvent(
		new CustomEvent("kbs-auth-updated", {
			detail: { auth: auth || null },
		})
	);
}

export function subscribeAuthUpdated(callback) {
	if (typeof window === "undefined" || typeof callback !== "function") {
		return () => {};
	}

	const handler = (event) => {
		callback(event?.detail?.auth ?? null, event);
	};

	window.addEventListener("kbs-auth-updated", handler);
	return () => window.removeEventListener("kbs-auth-updated", handler);
}
