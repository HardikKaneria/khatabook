export default function PageContainer({ children, className = "" }) {
    return <div className={`ui-page ${className}`.trim()}>{children}</div>;
}
