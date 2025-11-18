import { useEffect, useState } from "react";
import { getAccounts, getAccount, getAccountStatement } from "./api";

const useAsync = (asyncFn, deps) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);
        asyncFn()
            .then((res) => {
                if (!cancelled) {
                    setData(res);
                }
            })
            .catch((err) => {
                if (!cancelled) {
                    setError(err);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    return { data, loading, error, refresh: () => asyncFn().then(setData).catch(setError) };
};

export const useAccounts = (params = {}, refreshKey = 0) =>
    useAsync(() => getAccounts(params), [JSON.stringify(params), refreshKey]);

export const useAccount = (id, refreshKey = 0) =>
    useAsync(
        () => (id ? getAccount(id) : Promise.resolve(null)),
        [id, refreshKey]
    );

export const useAccountStatement = (id, filters = {}, refreshKey = 0) =>
    useAsync(
        () => (id ? getAccountStatement(id, filters) : Promise.resolve(null)),
        [id, JSON.stringify(filters), refreshKey]
    );
