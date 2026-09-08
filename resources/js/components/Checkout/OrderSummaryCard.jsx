import { ShoppingBagIcon } from '@phosphor-icons/react/dist/csr/ShoppingBag';
import { TrashIcon } from '@phosphor-icons/react/dist/csr/Trash';
import { useCart } from '../../hooks/useCart';
import Price from '../Shop/Price';
import Button from '../UI/Button';
import Card from '../UI/Card';

/** CartService returns euros; Price renders cents. */
const toCents = (euros) => Math.round(Number(euros) * 100);

/**
 * @param {Object} props
 * @param {Array<Object>} props.items
 * @param {number} props.subtotal - Euros, from CartService.
 * @param {number} props.shippingCents
 * @param {boolean} props.processing
 * @param {string} [props.error]
 */
export default function OrderSummaryCard({ items, subtotal, shippingCents, processing, error, canSubmit = true }) {
    const { removeItem, processing: cartProcessing } = useCart();

    const subtotalCents = toCents(subtotal);
    const totalCents = subtotalCents + (shippingCents ?? 0);

    return (
        <Card className="p-6 lg:sticky lg:top-44">
            <h2 className="text-lg font-semibold text-gray-900">Samenvatting van de bestelling</h2>

            <ul className="mt-4 max-h-80 divide-y divide-gray-100 overflow-y-auto">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className={`flex gap-3 py-4 transition-opacity ${
                            cartProcessing === item.id ? 'pointer-events-none opacity-50' : ''
                        }`}
                    >
                        <div className="flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                            {item.image ? (
                                <img
                                    src={item.image}
                                    alt={item.name}
                                    className="h-full w-full object-contain"
                                />
                            ) : (
                                <ShoppingBagIcon size={24} className="text-gray-300" />
                            )}
                        </div>

                        <div className="min-w-0 flex-1">
                            <div className="flex items-start justify-between gap-2">
                                <p className="break-words text-sm font-medium text-gray-900">
                                    {item.name}
                                </p>
                                <button
                                    type="button"
                                    className="cursor-pointer p-1 text-gray-400 transition hover:text-red-500"
                                    onClick={() => removeItem(item.id)}
                                    aria-label={`${item.name} verwijderen`}
                                >
                                    <TrashIcon size={16} />
                                </button>
                            </div>

                            {item.color && (
                                <div className="mt-0.5 flex items-center gap-1.5">
                                    {item.color_hex && (
                                        <span
                                            className="inline-block h-3 w-3 flex-shrink-0 rounded-full border border-gray-200"
                                            style={{ backgroundColor: `#${item.color_hex}` }}
                                        />
                                    )}
                                    <span className="text-xs text-gray-400">{item.color}</span>
                                </div>
                            )}

                            <div className="mt-2 flex items-center justify-between text-sm">
                                <span className="text-gray-500">Aantal: {item.quantity}</span>
                                <span className="font-medium text-gray-900">
                                    <Price price={toCents(item.price * item.quantity)} />
                                </span>
                            </div>
                        </div>
                    </li>
                ))}
            </ul>

            <dl className="space-y-2 border-t border-gray-200 pt-4 text-sm">
                <div className="flex justify-between">
                    <dt className="text-gray-500">Subtotaal</dt>
                    <dd className="text-gray-900">
                        <Price price={subtotalCents} />
                    </dd>
                </div>
                <div className="flex justify-between">
                    <dt className="text-gray-500">Verzending</dt>
                    <dd className="text-gray-900">
                        {shippingCents === null ? "Nog niet beschikbaar" : <Price price={shippingCents} />}
                    </dd>
                </div>
                <div className="flex justify-between border-t border-gray-200 pt-3 text-base font-semibold">
                    <dt>Totaal</dt>
                    <dd>
                        {shippingCents === null ? "Nog te bepalen" : <Price price={totalCents} />}
                    </dd>
                </div>
                <p className="text-xs text-gray-400">Inclusief btw</p>
            </dl>

            <Button
                type="submit"
                variant="primary"
                className="mt-5 w-full justify-center"
                disabled={processing || !canSubmit}
            >
                {processing ? 'Bezig…' : 'Bestelling bevestigen'}
            </Button>

            {error && <p role="alert" className="mt-3 text-sm text-red-600">{error}</p>}
        </Card>
    );
}
