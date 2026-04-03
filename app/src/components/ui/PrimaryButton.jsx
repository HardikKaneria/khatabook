export default function PrimaryButton({ children, className = "", ...rest }) {
    return (
        <button className={`ui-primary-btn ${className}`.trim()} {...rest}>
            {children}
        </button>
    );
}
