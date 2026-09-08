import { Head, Link } from '@inertiajs/react';
import Container from '../../../components/Container';
import Price from '../../../components/Shop/Price';
import AccountNavigation from '../AccountNavigation';
import { statusLabel } from '../orderStatus';

export default function OrdersIndex({ orders }) {
    const rows = orders?.data ?? [];
    const pagination = orders?.meta ?? orders ?? {};
    const previous = orders?.links?.prev ?? orders?.prev_page_url;
    const next = orders?.links?.next ?? orders?.next_page_url;

    return (
        <Container className="max-w-4xl my-8">
            <Head title="Mijn bestellingen" />
            <AccountNavigation />
            <h1 className="text-3xl font-bold mb-6">Mijn bestellingen</h1>
            {rows.length === 0 ? (
                <p className="text-gray-500">Je hebt nog geen bestellingen.</p>
            ) : (
                <div className="space-y-3">
                    {rows.map((order) => (
                        <Link key={order.id} href={`/account/orders/${order.id}`} className="block border rounded-xl p-4 hover:border-gray-400">
                            <div className="flex flex-wrap justify-between gap-4">
                                <div>
                                    <p className="font-semibold">{order.number}</p>
                                    <p className="text-sm text-gray-500">{statusLabel(order.fulfilment_status || order.status)} · {statusLabel(order.payment_status || order.status)}</p>
                                </div>
                                <p className="font-medium"><Price price={order.total_cents} /></p>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
            {pagination.last_page > 1 && <nav aria-label="Paginering bestellingen" className="mt-6 flex items-center justify-between gap-4">
                {previous ? <Link href={previous} className="underline">Vorige</Link> : <span />}
                <span>{pagination.current_page} / {pagination.last_page}</span>
                {next ? <Link href={next} className="underline">Volgende</Link> : <span />}
            </nav>}
        </Container>
    );
}
