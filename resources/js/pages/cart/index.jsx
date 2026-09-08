import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Container from '../../components/Container';
import CartItem from '../../components/Cart/CartItem';
import EmptyCart from '../../components/Cart/EmptyCart';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';
import Price from '../../components/Shop/Price';

export default function CartPage({ items = [], total = 0, messages = [] }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return items;
        return items.filter((item) =>
            [item.name, item.lego_number, item.color].filter(Boolean).some((v) => String(v).toLowerCase().includes(q)),
        );
    }, [items, query]);

    return (
        <Container className="max-w-4xl my-8">
            <Head title="Winkelwagen" />
            <h1 className="text-3xl font-bold mb-6">Winkelwagen</h1>

            {messages?.length > 0 && (
                <div role="status" className="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 space-y-1">
                    {messages.map((m, i) => <p key={i}>{m}</p>)}
                </div>
            )}

            {items.length === 0 ? (
                <EmptyCart />
            ) : (
                <>
                    <Input
                        label="Zoek in je winkelwagen"
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Zoek in je winkelwagen..."
                        className="mb-4"
                    />

                    {filtered.length === 0 && <p role="status" className="py-4 text-gray-600">Geen artikelen gevonden. Probeer een andere naam, kleur of artikelnummer.</p>}
                    <div className="bg-white rounded-xl border border-gray-100 divide-y">
                        {filtered.map((item) => (
                            <div key={item.id} className="px-4">
                                <CartItem item={item} />
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <p className="text-xl font-semibold">
                            Subtotaal: <Price price={Math.round(Number(total) * 100)} />
                            <span className="text-sm font-normal text-gray-500 ml-2">
                                ({items.length} verschillende artikelen)
                            </span>
                        </p>
                        <Button as="link" href="/checkout" variant="primary">Naar afrekenen</Button>
                    </div>
                </>
            )}
        </Container>
    );
}
