import { MinusIcon } from '@phosphor-icons/react/dist/csr/Minus';
import { PlusIcon } from '@phosphor-icons/react/dist/csr/Plus';
import { ShoppingCartIcon } from '@phosphor-icons/react/dist/csr/ShoppingCart';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../components/UI/Button';
import Container from '../../components/Container';
import { useCart } from '../../hooks/useCart';
import Price from "../../components/Shop/Price.jsx";
import StockIndicator from "../../components/Shop/Products/StockIndicator.jsx";
import ProductSiblingsOverview from "../../components/Shop/Products/ProductSiblingsOverview.jsx";
import InlineProduct from "../../components/Shop/Products/InlineProduct.jsx";

/**
 * @param {Object} props
 */
export default function ProductPage({ product: { data: product }, suggestions: { data: suggestions }, status, related_sets }) {
    const { items, addItem, open, processing } = useCart();

    const currentCartQty = items.find((i) => i.id === product.id)?.quantity ?? 0;
    const available = product.stock - currentCartQty;
    const canAdd = available > 0;
    const isProcessing = processing === product.id;

    const [quantity, setQuantity] = useState(1);
    const [notifyEmail, setNotifyEmail] = useState('');
    const [notifySent, setNotifySent] = useState(false);

    function decrement() {
        setQuantity((q) => Math.max(1, q - 1));
    }

    function increment() {
        setQuantity((q) => Math.min(available, q + 1));
    }

    function handleAddToCart() {
        addItem(product.id, quantity, {
            onSuccess: () => {
                setQuantity(1);
                open();
            },
        });
    }

    return (
        <Container className="max-w-250 my-8">
            <div className="py-10 grid grid-cols-1 md:grid-cols-5 gap-10 md:shadow-[0_0px_10px_rgba(0,0,0,0.1)] md:px-6 rounded-xl">
                <div className="rounded-xl overflow-hidden flex items-center justify-center md:col-span-2 max-h-60 md:max-h-none mx-auto">
                    {product.image ? (
                        <img
                            src={product.image}
                            alt={product.title}
                            className="object-contain w-60 h-60"
                        />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full text-gray-300">
                            <ShoppingCartIcon size={64} />
                        </div>
                    )}
                </div>

                <div className="flex flex-col gap-6 md:col-span-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{product.title}</h1>

                        <div className="flex items-center gap-4 mt-2">
                            {product.color?.name && (
                                <div className="inline-flex items-center gap-2">
                                    {product.color.hex && (
                                        <span
                                            className="w-4 h-4 rounded-full border border-gray-200 inline-block"
                                            style={{ backgroundColor: `#${product.color.hex}` }}
                                        />
                                    )}
                                    <span className="text-sm text-gray-500">{product.color.name}</span>
                                </div>
                            )}

                            <StockIndicator stock={product.stock} />
                        </div>
                    </div>

                    <span className="text-3xl font-semibold">
                        <Price price={product.price} />
                    </span>

                    {canAdd ? (
                        <div className="flex flex-col sm:flex-row items-center gap-4">
                            <div className="inline-flex items-center border border-gray-200 rounded-md overflow-hidden w-full sm:w-auto">
                                <button
                                    className="px-3 py-2 hover:bg-gray-100 transition disabled:opacity-40 cursor-pointer"
                                    onClick={decrement}
                                    disabled={quantity <= 1}
                                >
                                    <MinusIcon size={16} />
                                </button>

                                <input
                                    type="number"
                                    className="h-full py-2 px-2 w-[4ch] text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    min={1}
                                    value={quantity}
                                    onChange={e => setQuantity(
                                        Math.max(1, Math.min(available, parseInt(e.target.value) || 1))
                                    )}
                                />

                                <button
                                    className="px-3 py-2 hover:bg-gray-100 transition disabled:opacity-40 cursor-pointer"
                                    onClick={increment}
                                    disabled={quantity >= available}
                                >
                                    <PlusIcon size={16} />
                                </button>
                            </div>

                            <Button
                                variant="primary"
                                onClick={handleAddToCart}
                                disabled={isProcessing}
                                className="flex-1 justify-center w-full"
                            >
                                <ShoppingCartIcon size={18} />
                                {isProcessing ? 'Toevoegen...' : 'In winkelmandje'}
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-sm text-gray-500">
                                {product.stock === 0
                                    ? 'Dit product is momenteel uitverkocht'
                                    : 'Je hebt al de maximale hoeveelheid in je winkelmandje'}
                            </p>
                            {product.stock === 0 && (
                                <form
                                    className="flex flex-col sm:flex-row gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        router.post(`/products/${product.id}/stock-notifications`, {
                                            email: notifyEmail,
                                        }, {
                                            preserveScroll: true,
                                            onSuccess: () => setNotifySent(true),
                                        });
                                    }}
                                >
                                    <input
                                        type="email"
                                        required
                                        value={notifyEmail}
                                        onChange={(e) => setNotifyEmail(e.target.value)}
                                        placeholder="jouw@email.nl"
                                        className="flex-1 border border-gray-200 rounded-md px-3 py-2"
                                    />
                                    <Button type="submit" variant="primary">Houd me op de hoogte</Button>
                                </form>
                            )}
                            {(notifySent || status) && (
                                <p className="text-sm text-green-700">{status || 'Bedankt! We mailen je bij voorraad.'}</p>
                            )}
                        </div>
                    )}

                    {product.attributes?.length > 0 && (
                        <dl className="mt-2 space-y-2 border-t border-gray-100 pt-4">
                            {product.attributes.map((attr) => (
                                <div key={attr.label} className="flex gap-4 text-sm">
                                    <dt className="w-32 text-gray-500">{attr.label}</dt>
                                    <dd className="font-medium text-gray-900">{attr.value}</dd>
                                </div>
                            ))}
                        </dl>
                    )}

                    {product.sibling_colors?.length > 0 && (
                        <ProductSiblingsOverview productSiblings={product.sibling_colors} />
                    )}
                </div>
            </div>

            {suggestions.length > 0 && (
                <div className="mt-8">
                    <h2 className="text-2xl font-semibold mb-4">Misschien ook interessant:</h2>

                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        {suggestions.map(suggestedProduct => (
                            <InlineProduct key={suggestedProduct.id} product={suggestedProduct} />
                        ))}
                    </div>
                </div>
            )}
            {related_sets?.data?.length > 0 && <section className="mt-10">
                <h2 className="text-xl font-semibold">Komt voor in deze sets</h2>
                <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    {related_sets.data.map((set) => <Link key={set.id} href={set.url} className="rounded-lg border border-gray-200 p-3">
                        {set.image && <img src={set.image} alt={set.name} className="h-32 w-full object-contain" />}
                        <p>{set.set_num} · {set.name}</p>
                    </Link>)}
                </div>
                <nav className="mt-4 flex gap-4" aria-label="Sets pagina's">
                    {related_sets.links?.prev && <Link href={related_sets.links.prev}>Vorige</Link>}
                    {related_sets.links?.next && <Link href={related_sets.links.next}>Volgende</Link>}
                </nav>
            </section>}
        </Container>
    );
}
