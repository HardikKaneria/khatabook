import { getContact, listContacts } from "./api";
import useAsyncResource from "../../hooks/useAsyncResource";

export const useContacts = (params = {}, refreshKey = 0) =>
    useAsyncResource(() => listContacts(params), [JSON.stringify(params), refreshKey]);

export const useContact = (id, refreshKey = 0) =>
    useAsyncResource(() => (id ? getContact(id) : Promise.resolve(null)), [id, refreshKey]);
