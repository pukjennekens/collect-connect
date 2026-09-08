import { Head, Link, usePage } from '@inertiajs/react';
import Container from '../../components/Container';

export default function CheckoutConfirmation({ order: orderProp }) {
    const order = orderProp?.data ?? orderProp;
    const { auth, flash } = usePage().props;
    const paid = order.payment_status === 'paid' || order.status === 'paid';
    const expired = ['cancelled', 'expired'].includes(order.status);
    return <Container className="max-w-2xl my-12 space-y-4">
        <Head title={`Bestelling ${order.number}`} />
        <h1 className="text-3xl font-bold">{paid ? 'Bedankt voor je bestelling' : expired ? 'Je reservering is verlopen' : 'Je bestelling is ontvangen'}</h1>
        {flash?.status && <p role="status" className="rounded-lg bg-gray-50 p-3">{flash.status}</p>}
        <p className="text-gray-600">Ordernummer: <strong>{order.number}</strong></p>
        <p>{paid ? 'Je testbetaling is verwerkt. Er is geen geld afgeschreven.' : expired ? 'Plaats een nieuwe bestelling om de actuele voorraad en prijzen te controleren.' : 'Je bestelling wacht nog op de testbetaling.'}</p>
        {order.payment_url && <Link href={order.payment_url} className="inline-block rounded-lg bg-primary px-4 py-3 font-semibold text-white">Testbetaling openen</Link>}
        {order.invoice_available && order.invoice_url && <p><a href={order.invoice_url} className="underline">Factuur downloaden</a></p>}
        {order.summary_url && <p><a href={order.summary_url} className="underline">Besteloverzicht downloaden</a></p>}
        <div className="flex flex-wrap gap-4 text-sm underline">
            <Link href="/onderdelen">Verder winkelen</Link>
            {auth?.user ? <Link href={`/account/orders/${order.id}`}>Bestelling bekijken</Link> : <Link href="/login">Inloggen</Link>}
        </div>
    </Container>;
}
