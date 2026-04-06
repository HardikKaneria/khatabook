import { getAccounts, getAccount, getAccountStatement } from "./api";
import useAsyncResource from "../../hooks/useAsyncResource";

export const useAccounts = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getAccounts(params), [JSON.stringify(params), refreshKey]);

export const useAccount = (id, refreshKey = 0) =>
    useAsyncResource(
        () => (id ? getAccount(id) : Promise.resolve(null)),
        [id, refreshKey]
    );

export const useAccountStatement = (id, filters = {}, refreshKey = 0) =>
    useAsyncResource(
        () => (id ? getAccountStatement(id, filters) : Promise.resolve(null)),
        [id, JSON.stringify(filters), refreshKey]
    );
