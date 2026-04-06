import { getInvoices, getInvoice, getRecurringProfiles } from "./api";
import useAsyncResource from "../../hooks/useAsyncResource";

export const useInvoices = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getInvoices(params), [JSON.stringify(params), refreshKey]);

export const useInvoice = (id, refreshKey = 0) =>
    useAsyncResource(
        () => (id ? getInvoice(id) : Promise.resolve(null)),
        [id, refreshKey]
    );

export const useRecurringProfiles = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getRecurringProfiles(params), [JSON.stringify(params), refreshKey]);
