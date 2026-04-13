import { useEffect, useRef, useState } from "react";
import { searchContacts } from "./api";

export default function ContactSuggestInput({
    label,
    type = "CUSTOMER",
    value,
    onValueChange,
    onSelect,
    placeholder = "Start typing…",
}) {
    const [suggestions, setSuggestions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);
    const selectedValueRef = useRef("");

    useEffect(() => {
        const trimmedValue = value?.trim() || "";
        if (!trimmedValue || trimmedValue.length < 2) {
            setSuggestions([]);
            setOpen(false);
            return;
        }
        if (selectedValueRef.current !== "" && trimmedValue === selectedValueRef.current) {
            setSuggestions([]);
            setOpen(false);
            return;
        }
        let cancelled = false;
        setLoading(true);
        const timeout = setTimeout(async () => {
            try {
                const res = await searchContacts({ type, q: trimmedValue, perPage: 5 });
                if (cancelled) return;
                const nextSuggestions = res?.data || [];
                setSuggestions(nextSuggestions);
                setOpen(nextSuggestions.length > 0);
            } catch (err) {
                console.warn("contact search failed", err);
            } finally {
                if (!cancelled) setLoading(false);
            }
        }, 300);
        return () => {
            cancelled = true;
            clearTimeout(timeout);
        };
    }, [value, type]);

    useEffect(() => {
        const handleClick = (evt) => {
            if (!containerRef.current?.contains(evt.target)) {
                setOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClick);
        return () => document.removeEventListener("mousedown", handleClick);
    }, []);

    const handleSelect = (contact) => {
        selectedValueRef.current = String(contact?.name || value || "").trim();
        onSelect?.(contact);
        setOpen(false);
        setSuggestions([]);
    };

    return (
        <div className="kb-contact-picker" ref={containerRef}>
            {label ? <label className="kb-muted">{label}</label> : null}
            <input
                className="kb-input"
                value={value}
                placeholder={placeholder}
                onFocus={() => {
                    if ((value?.trim() || "").length >= 2 && value?.trim() !== selectedValueRef.current && suggestions.length) {
                        setOpen(true);
                    }
                }}
                onChange={(e) => {
                    if (selectedValueRef.current !== "") {
                        selectedValueRef.current = "";
                    }
                    onValueChange?.(e.target.value);
                    setOpen(true);
                }}
            />
            {loading && (
                <div className="kb-muted" style={{ fontSize: 12, marginTop: 4 }}>
                    Searching…
                </div>
            )}
            {open && suggestions.length ? (
                <div className="kb-contact-picker__dropdown">
                    {suggestions.map((contact) => (
                        <button
                            key={contact.id}
                            type="button"
                            className="kb-contact-picker__option"
                            onClick={() => handleSelect(contact)}
                        >
                            <span className="kb-contact-picker__option-name">{contact.name}</span>
                            {(contact.email || contact.phone) && (
                                <span className="kb-contact-picker__option-meta">
                                    {[contact.email, contact.phone].filter(Boolean).join(" · ")}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
