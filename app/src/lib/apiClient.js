import { makeDefaultApiFetch } from "../utils/apiClient";
import { getAuth } from "../utils/authStorage";

let fetchFnPromise = null;

function setClientFromAuth(auth) {
    fetchFnPromise = Promise.resolve(makeDefaultApiFetch(auth?.rest, auth?.token));
}

async function getFetchFn() {
    if (!fetchFnPromise) {
        fetchFnPromise = (async () => {
            const auth = await getAuth();
            return makeDefaultApiFetch(auth?.rest, auth?.token);
        })();
    }
    return fetchFnPromise;
}

async function request(path, options = {}) {
    const client = await getFetchFn();
    const { method = "GET", body, headers, ...rest } = options;
    return client(path, { method, body, headers, ...rest });
}

const apiClient = {
    request,
    get: (path, options = {}) => request(path, { ...options, method: "GET" }),
    post: (path, body, options = {}) => request(path, { ...options, method: "POST", body }),
    put: (path, body, options = {}) => request(path, { ...options, method: "PUT", body }),
    del: (path, options = {}) => request(path, { ...options, method: "DELETE" }),
};

export function configureApiClient(auth) {
    if (!auth) {
        fetchFnPromise = null;
        return;
    }
    setClientFromAuth(auth);
}

export default apiClient;
