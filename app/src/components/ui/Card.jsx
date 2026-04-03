export default function Card({ title, subtitle, actions, children, className = "" }) {
    return (
        <section className={`ui-card ${className}`.trim()}>
            {(title || subtitle || actions) && (
                <div className="ui-card-header">
                    <div>
                        {title ? <h3>{title}</h3> : null}
                        {subtitle ? <p>{subtitle}</p> : null}
                    </div>
                    {actions ? <div>{actions}</div> : null}
                </div>
            )}
            {children}
        </section>
    );
}
