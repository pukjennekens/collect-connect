import { Head, Link } from '@inertiajs/react';
import Container from '../../../components/Container';
import Price from '../../../components/Shop/Price';
import AccountNavigation from '../AccountNavigation';
import { statusLabel } from '../orderStatus';

export default function OrderShow({ order: orderProp }) {
    const order = orderProp?.data ?? orderProp;
    const items = order.items?.data ?? order.items ?? [];

    return (
        <Container className="max-w-3xl my-8 space-y-6">
            <Head title={`Bestelling ${order.number}`} />
            <AccountNavigation />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold">{order.number}</h1>
                    <p className="text-gray-500">Bestelling: {statusLabel(order.fulfilment_status || order.status)}</p>
                    <p className="text-gray-500">Betaling: {statusLabel(order.payment_status || order.status)}</p>
                </div>
                <div className="flex flex-wrap gap-4 text-sm underline">
                    {order.invoice_available && order.invoice_url && <a href={order.invoice_url}>Factuur downloaden</a>}
                    {order.summary_url && <a href={order.summary_url}>Besteloverzicht downloaden</a>}
                </div>
            </div>

            {order.payment_url && <div className="rounded-xl border p-4 space-y-2"><p>Rond je testbetaling af zolang de voorraadreservering geldig is. Er wordt geen geld afgeschreven.</p><Link href={order.payment_url} className="underline font-semibold">Testbetaling openen</Link></div>}
            <section className="border rounded-xl p-4 space-y-2">
                <h2 className="font-semibold">Verzending</h2>
                <p className="text-sm">{order.name}<br />{order.shipping_line1}<br />{order.shipping_postal_code} {order.shipping_city}<br />{order.shipping_country_code}</p>
                <p className="text-sm text-gray-600">{order.shipping_method_name}</p>
                {order.tracking_code && (
                    <p className="text-sm">
                        {order.tracking_carrier ? `${order.tracking_carrier} — ` : ''}Track &amp; trace: {order.tracking_url ? <a className="underline" href={order.tracking_url}>{order.tracking_code}</a> : order.tracking_code}
                    </p>
                )}
            </section>

            {order.billing_address && <section className="border rounded-xl p-4 space-y-2">
                <h2 className="font-semibold">Factuuradres</h2>
                <p className="text-sm">{order.billing_address.name}<br />{order.billing_address.company && <>{order.billing_address.company}<br /></>}{order.billing_address.line1} {order.billing_address.house_number} {order.billing_address.house_addition}<br />{order.billing_address.postal_code} {order.billing_address.city}<br />{order.billing_address.country_code}</p>
            </section>}
            <section className="border rounded-xl p-4">
                <h2 className="font-semibold mb-3">Artikelen</h2>
                <ul className="space-y-2 text-sm">
                    {items.map((item) => (
                        <li key={item.id} className="flex justify-between gap-3">
                            <span>{item.lego_number && <span className="block text-gray-500">#{item.lego_number}</span>}{item.title} {item.color_name ? `(${item.color_name})` : ''} ×{item.quantity}</span>
                            <span className="shrink-0"><Price price={item.unit_price_cents * item.quantity} /></span>
                        </li>
                    ))}
                </ul>
                <div className="border-t mt-4 pt-3 text-sm space-y-1">
                    <div className="flex justify-between"><span>Subtotaal</span><span><Price price={order.subtotal_cents} /></span></div>
                    <div className="flex justify-between"><span>Verzending</span><span><Price price={order.shipping_cents} /></span></div>
                    <div className="flex justify-between font-semibold"><span>Totaal</span><span><Price price={order.total_cents} /></span></div>
                </div>
            </section>

            <Link href="/account/orders" className="text-sm underline">Terug naar bestellingen</Link>
        </Container>
    );
}
