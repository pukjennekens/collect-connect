import { Link } from "@inertiajs/react";
import { Tooltip as ReactTooltip } from "react-tooltip";
import Price from "../Price.jsx";

import "react-tooltip/dist/react-tooltip.css";

/**
 * Compact product card used in search results and lists. Each card represents a
 * part with one or more color variants.
 *
 * @param {Object} props
 * @param {import("../../../services/search.js").ProductResult} props.product
 */
export default function InlineProduct({ product }) {
    const siblingColors = product.sibling_colors ?? [];
    let priceMin = product.priceMin;
    let priceMax = product.priceMax;

    if (priceMin == null || priceMax == null) {
        priceMin = product.price;
        priceMax = product.price;
    }

    const priceLabel =
        priceMin !== priceMax
            ? <span><Price price={priceMin} /> – <Price price={priceMax} /></span>
            : <Price price={priceMin} />;

    const tooltipId = `inline-product-colors-${product.id}`;

    return (
        <Link
            href={product.url}
            className="group flex flex-col gap-2 rounded-lg border border-gray-200 p-3 transition hover:border-gray-300 hover:shadow-sm"
        >
            {product.image ? (
                <img
                    src={product.image}
                    alt={product.title}
                    className="aspect-square w-full rounded-md object-contain"
                />
            ) : (
                <div className="aspect-square w-full rounded-md bg-gray-100" />
            )}

            <div className="min-w-0">
                <p className="text-xs text-gray-400">{product.lego_number}</p>
                <h3 className="line-clamp-2 min-h-10 text-sm font-medium text-gray-900 group-hover:underline">
                    {product.title}
                </h3>
                <p className="text-sm font-semibold text-gray-900">{priceLabel}</p>
                {product.color?.name && <p className="text-xs text-gray-600">{product.color.name}</p>}
                <p className="text-xs text-gray-600">{product.stock > 0 ? `${product.stock} op voorraad` : "Bekijk beschikbare varianten"}</p>
            </div>

            {siblingColors.length > 0 && (
                <>
                    <span data-tooltip-id={tooltipId} className="flex items-center gap-1">
                        {siblingColors.slice(0, 6).map((sibling) => (
                            <span
                                key={sibling.id ?? sibling.color?.name}
                                className="h-3 w-3 rounded-full border border-gray-200"
                                style={{ backgroundColor: `#${sibling.color?.hex ?? 'ccc'}` }}
                            />
                        ))}

                        {siblingColors.length > 6 && (
                            <span className="text-xs text-gray-400">+{siblingColors.length - 6}</span>
                        )}
                    </span>

                    <ReactTooltip id={tooltipId} place="top" className="z-50">
                        <span className="flex flex-col gap-1">
                            {siblingColors.map((sibling) => (
                                <span key={sibling.id ?? sibling.color?.name} className="flex items-center gap-2 whitespace-nowrap">
                                    <span
                                        className="inline-block h-3 w-3 shrink-0 rounded-sm border border-white/30"
                                        style={{ backgroundColor: `#${sibling.color?.hex ?? 'ccc'}` }}
                                    />
                                    <span className="flex-1">{sibling.color?.name}</span>
                                    <span className="text-gray-300">{sibling.stock}</span>
                                </span>
                            ))}
                        </span>
                    </ReactTooltip>
                </>
            )}
        </Link>
    );
}
