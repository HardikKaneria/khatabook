import { getExpenses, getExpense } from "./api";
import useAsyncResource from "../../hooks/useAsyncResource";

export const useExpenses = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getExpenses(params), [JSON.stringify(params), refreshKey]);

export const useExpense = (id, refreshKey = 0) =>
    useAsyncResource(() => (id ? getExpense(id) : Promise.resolve(null)), [id, refreshKey]);
