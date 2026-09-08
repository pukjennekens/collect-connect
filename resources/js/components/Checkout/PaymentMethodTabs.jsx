import RadioCard from '../UI/RadioCard';
import CheckoutSection from './CheckoutSection';

/**
 * Payment options come from the server and vary per country, so they are
 * rendered from the prop rather than hardcoded. Card details are never
 * collected here: the selected gateway hosts its own payment page and the
 * customer is redirected there after the order is placed.
 *
 * @param {Object} props
 * @param {Array<{ id: string, label: string }>} props.methods
 * @param {string} props.selectedId
 * @param {(id: string) => void} props.onSelect
 * @param {string} [props.error]
 */
export default function PaymentMethodTabs({ methods, selectedId, onSelect, error }) {
    return (
        <CheckoutSection title="Betaling">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {methods.map((method) => (
                    <RadioCard
                        key={method.id}
                        name="payment_method"
                        checked={selectedId === method.id}
                        onSelect={() => onSelect(method.id)}
                    >
                        <span className="pr-6 font-medium text-gray-900">{method.label}</span>
                    </RadioCard>
                ))}
            </div>

            <p className="text-sm text-gray-500">
                Na bevestiging kies je de uitkomst van je testbetaling. Er wordt geen geld afgeschreven.
            </p>

            {error && <p className="text-sm text-red-600">{error}</p>}
        </CheckoutSection>
    );
}
