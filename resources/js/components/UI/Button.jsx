import { Link } from "@inertiajs/react";

/**
 * Shared button used across the navigation. Renders a <button>, <a>, or an
 * Inertia <Link> depending on `as`, while keeping a single visual style.
 *
 * @param {Object} props
 * @param {"button"|"a"|"link"} [props.as] - Underlying element/component.
 * @param {"ghost"|"primary"} [props.variant] - Visual style.
 * @param {boolean} [props.iconOnly] - Square icon button (no horizontal label padding).
 * @param {string} [props.className] - Extra classes appended last.
 * @param {React.ReactNode} props.children
 */
export default function Button({
    as = "button",
    variant = "ghost",
    iconOnly = false,
    className = "",
    children,
    ...props
}) {
    const base =
        "inline-flex items-center justify-center min-h-11 rounded-md transition cursor-pointer whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50";

    const sizing = iconOnly ? "min-w-11 p-2" : "px-4 py-2 gap-2";

    const variants = {
        ghost: "hover:bg-gray-100",
        primary: "bg-primary text-white hover:bg-primary/90",
    };

    const classes = `${base} ${sizing} ${variants[variant] ?? ""} ${className}`.trim();

    const Component = as === "link" ? Link : as;

    return (
        <Component className={classes} {...props}>
            {children}
        </Component>
    );
}
