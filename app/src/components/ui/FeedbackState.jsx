export default function FeedbackState({
    title,
    description,
    tone = "neutral",
    className = "",
}) {
    return (
        <div className={`ui-state ui-state--${tone} ${className}`.trim()}>
            {title ? <h4 className="ui-state__title">{title}</h4> : null}
            {description ? <p className="ui-state__description">{description}</p> : null}
        </div>
    );
}
