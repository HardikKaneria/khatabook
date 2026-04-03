export default function PageHeader({ eyebrow, title, subtitle, actions, className = "" }) {
    return (
        <header className={`ui-header ${className}`.trim()}>
            <div>
                {eyebrow ? <p className="ui-header-eyebrow">{eyebrow}</p> : null}
                {title ? <h1>{title}</h1> : null}
                {subtitle ? <p>{subtitle}</p> : null}
            </div>
            {actions ? <div>{actions}</div> : null}
        </header>
    );
}
