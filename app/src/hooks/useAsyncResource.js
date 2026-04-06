import { useEffect, useState } from "react";

export default function useAsyncResource(asyncFn, deps) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError(null);

        asyncFn()
            .then((result) => {
                if (!cancelled) {
                    setData(result);
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

    const refresh = () => asyncFn().then(setData).catch(setError);

    return { data, loading, error, refresh };
}
