import { useEffect, useState } from "react";
import { getInvoices, getInvoice } from "./api";

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
                if (!cancelled) setData(res);
            })
            .catch((err) => {
                if (!cancelled) setError(err);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    const refresh = () => asyncFn().then(setData).catch(setError);

    return { data, loading, error, refresh };
};

export const useInvoices = (params = {}, refreshKey = 0) =>
    useAsync(() => getInvoices(params), [JSON.stringify(params), refreshKey]);

export const useInvoice = (id, refreshKey = 0) =>
    useAsync(
        () => (id ? getInvoice(id) : Promise.resolve(null)),
        [id, refreshKey]
    );
