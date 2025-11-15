import React, {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from "react";
import { createPortal } from "react-dom";

const ToastContext = createContext(null);

const DEFAULT_TITLES = {
    success: "Success",
    error: "Something went wrong",
    info: "Heads up",
    warning: "Please check",
};

const FALLBACK = {
    push: () => {},
    dismiss: () => {},
    success: () => {},
    error: () => {},
    info: () => {},
    warning: () => {},
};

const ICONS = {
    success: (
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path
                fill="currentColor"
                d="M9.55 16.45 5.4 12.3l1.4-1.4 2.75 2.75 7.3-7.3 1.4 1.4-8.7 8.7z"
            />
        </svg>
    ),
    error: (
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path
                fill="currentColor"
                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"
            />
        </svg>
    ),
    info: (
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path
                fill="currentColor"
                d="M11 17h2v-6h-2v6zm0-8h2V7h-2v2zm1-7C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"
            />
        </svg>
    ),
    warning: (
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path
                fill="currentColor"
                d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"
            />
        </svg>
    ),
};

const variantList = ["success", "error", "info", "warning"];

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const push = useCallback((toast) => {
        const id =
            toast?.id ||
            (typeof crypto !== "undefined" && crypto.randomUUID
                ? crypto.randomUUID()
                : `${Date.now()}-${Math.random()}`);

        setToasts((prev) => [
            ...prev,
            {
                id,
                title: toast.title || DEFAULT_TITLES[toast.tone || "info"],
                description: toast.description || "",
                tone: variantList.includes(toast.tone) ? toast.tone : "info",
                duration:
                    typeof toast.duration === "number" ? toast.duration : 4500,
                action: toast.action,
            },
        ]);
        return id;
    }, []);

    const dismiss = useCallback((id) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    }, []);

    const contextValue = useMemo(() => {
        const trigger = (tone) => (description, opts = {}) =>
            push({
                tone,
                description,
                title: opts.title || DEFAULT_TITLES[tone],
                duration: opts.duration,
                action: opts.action,
            });

        return {
            push,
            dismiss,
            success: trigger("success"),
            error: trigger("error"),
            info: trigger("info"),
            warning: trigger("warning"),
        };
    }, [push, dismiss]);

    const portalTarget =
        typeof document !== "undefined" ? document.body : null;

    return (
        <ToastContext.Provider value={contextValue}>
            {children}
            {portalTarget
                ? createPortal(
                      <div className="kb-toast-stack" role="status">
                          {toasts.map((toast) => (
                              <ToastItem
                                  key={toast.id}
                                  {...toast}
                                  onDismiss={() => dismiss(toast.id)}
                              />
                          ))}
                      </div>,
                      portalTarget
                  )
                : null}
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext) || FALLBACK;
}

function ToastItem({
    title,
    description,
    tone = "info",
    duration = 4500,
    action,
    onDismiss,
}) {
    useEffect(() => {
        if (!duration) return undefined;
        const timer = setTimeout(onDismiss, duration);
        return () => clearTimeout(timer);
    }, [duration, onDismiss]);

    const Icon = ICONS[tone] || ICONS.info;

    return (
        <div className={`kb-toast kb-toast--${tone}`}>
            <div className="kb-toast__status" aria-hidden="true">
                {Icon}
            </div>
            <div className="kb-toast__body">
                <p className="kb-toast__title">{title}</p>
                {description ? (
                    <p className="kb-toast__message">{description}</p>
                ) : null}
                {action ? (
                    <button
                        type="button"
                        className="kb-toast__action"
                        onClick={() => {
                            action?.onClick?.();
                            onDismiss();
                        }}
                    >
                        {action.label}
                    </button>
                ) : null}
            </div>
            <button
                type="button"
                className="kb-toast__close"
                aria-label="Dismiss notification"
                onClick={onDismiss}
            >
                ×
            </button>
        </div>
    );
}

export default ToastProvider;
