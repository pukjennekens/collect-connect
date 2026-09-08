import { Link } from '@inertiajs/react';
import Price from '../Price.jsx';
import AddToCartButton from './AddToCartButton.jsx';

/**
 * Compact product card for a single part+color variant. Used on set pages.
 * The color-dots slot from InlineProduct is replaced by AddToCartButton.
 *
 * When `product.url` is null the part has no matching product in the catalog
 * and is rendered as unavailable (no price, no cart button).
 *
 * @param {Object} props
 * @param {Object} props.product
 * @param {number} [props.quantityInSet]
 */
export default function SingleInlineProduct({ product, quantityInSet }) {
    const isAvailableInCatalog = product.url !== null;

    const image = product.image ? (
        <img
            src={product.image}
            alt={product.title}
            className="aspect-square w-full rounded-md object-contain"
        />
    ) : (
        <div className="aspect-square w-full rounded-md bg-gray-100" />
    );

    const info = (
        <div className="min-w-0">
            <div className="flex items-center justify-between gap-1">
                <p className="text-xs text-gray-400">{product.lego_number}</p>
                {quantityInSet > 0 && (
                    <span className="shrink-0 text-xs font-medium text-gray-400">
                        ×{quantityInSet}
                    </span>
                )}
            </div>

            <h3 className="line-clamp-2 min-h-10 text-sm font-medium text-gray-900">
                {product.title}
            </h3>

            {product.price !== null && (
                <p className="text-sm font-semibold text-gray-900">
                    <Price price={product.price} />
                </p>
            )}
        </div>
    );

    return (
        <div className="@container flex flex-col gap-2 rounded-lg border border-gray-200 p-3 transition hover:border-gray-300 hover:shadow-sm">
            {isAvailableInCatalog ? (
                <Link href={product.url} className="group flex flex-col gap-2">
                    {image}
                    <div className="min-w-0">
                        <div className="flex items-center justify-between gap-1">
                            <p className="text-xs text-gray-400">{product.lego_number}</p>
                            {quantityInSet > 0 && (
                                <span className="shrink-0 text-xs font-medium text-gray-400">
                                    ×{quantityInSet}
                                </span>
                            )}
                        </div>

                        <h3 className="line-clamp-2 min-h-10 text-sm font-medium text-gray-900 group-hover:underline">
                            {product.title}
                        </h3>

                        {product.price !== null && (
                            <p className="text-sm font-semibold text-gray-900">
                                <Price price={product.price} />
                            </p>
                        )}
                    </div>
                </Link>
            ) : (
                <div className="flex flex-col gap-2">
                    {image}
                    {info}
                </div>
            )}

            {product.color && <p className="text-xs text-gray-500">{product.color.name}</p>}
            {product.is_spare && <p className="text-xs text-gray-500">Reserveonderdeel</p>}
            {isAvailableInCatalog ? (
                <AddToCartButton product={product} />
            ) : (
                <p className="py-1 text-center text-xs text-gray-400">Niet in catalogus</p>
            )}
        </div>
    );
}
