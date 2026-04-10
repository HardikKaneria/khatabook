import { getExpense, getExpenses, getExpenseSummary } from "./api";
import useAsyncResource from "../../hooks/useAsyncResource";

export const useExpenses = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getExpenses(params), [JSON.stringify(params), refreshKey]);

export const useExpenseSummary = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => getExpenseSummary(params), [JSON.stringify(params), refreshKey]);

export const useExpense = (id, refreshKey = 0) =>
    useAsyncResource(() => (id ? getExpense(id) : Promise.resolve(null)), [id, refreshKey]);
