import { useId } from 'react';

/**
 * Labelled text input with inline validation message.
 *
 * @param {Object} props
 * @param {string} props.label
 * @param {string} [props.error] - Message from Inertia's form.errors.
 * @param {boolean} [props.optional] - Shows an "optioneel" hint next to the label.
 * @param {string} [props.className] - Extra classes on the wrapper.
 */
export default function Input({ label, error, optional = false, className = '', id, ...props }) {
    const generatedId = useId();
    const inputId = id ?? generatedId;

    return (
        <div className={`space-y-1.5 ${className}`.trim()}>
            <label htmlFor={inputId} className="flex items-baseline gap-2 text-sm text-gray-600">
                {label}
                {optional && <span className="text-xs text-gray-400">optioneel</span>}
            </label>

            <input
                id={inputId}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? `${inputId}-error` : undefined}
                className={`min-h-11 w-full rounded-lg border px-3 py-2 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary ${
                    error ? 'border-red-400' : 'border-gray-200'
                }`}
                {...props}
            />

            {error && <p id={`${inputId}-error`} className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
