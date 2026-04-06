export default function InlineNotice({ message, tone = "error", className = "" }) {
    if (!message) {
        return null;
    }

    return (
        <p className={`ui-inline-notice ui-inline-notice--${tone} ${className}`.trim()}>
            {message}
        </p>
    );
}
