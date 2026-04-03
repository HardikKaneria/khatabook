export default function GhostButton({ children, className = "", ...rest }) {
    return (
        <button className={`ui-ghost-btn ${className}`.trim()} {...rest}>
            {children}
        </button>
    );
}
