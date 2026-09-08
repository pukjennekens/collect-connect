import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Container from '../../components/Container';
import Price from '../../components/Shop/Price';
import Button from '../../components/UI/Button';
import { statusLabel } from '../account/orderStatus';

const paymentLabels = { ideal: 'iDEAL', card: 'Creditcard', paypal: 'PayPal', bank: 'Bankoverschrijving', bancontact: 'Bancontact' };
export default function SimulatePayment({ order }) {
    const form = useForm({ outcome: 'paid' });
    const { flash } = usePage().props;
    const submit = outcome => {
        form.transform(data => ({ ...data, outcome }));
        form.post(`/checkout/betaling/${order.id}`);
    };
    return <Container className="max-w-lg my-12">
        <Head title="Testbetaling" />
        <div className="space-y-5 rounded-xl border border-gray-200 bg-white p-5 sm:p-6">
            <div className="space-y-2"><p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Testbetaling</p><h1 className="text-2xl font-bold">Betaling simuleren</h1><p className="text-sm text-gray-600">Er wordt geen geld afgeschreven. Kies een uitkomst om je bestelling te testen.</p></div>
            {flash?.status && <p role="status" className="rounded-lg bg-gray-50 p-3">{flash.status}</p>}
            {Object.values(form.errors).length > 0 && <div role="alert" className="rounded-lg bg-red-50 p-3 text-red-700">{Object.values(form.errors).map((error, index) => <p key={index}>{error}</p>)}</div>}
            <dl className="divide-y divide-gray-100 border-y border-gray-100 text-sm">
                <div className="flex justify-between gap-3 py-3"><dt>Bestelling</dt><dd className="font-medium">{order.number}</dd></div>
                <div className="flex justify-between gap-3 py-3"><dt>Betaalmethode</dt><dd>{paymentLabels[order.payment_method] || 'Testbetaling'}</dd></div>
                <div className="flex justify-between gap-3 py-3"><dt>Status</dt><dd>{statusLabel(order.payment_status || order.status)}</dd></div>
                <div className="flex justify-between gap-3 py-3"><dt>Totaal</dt><dd className="font-semibold"><Price price={order.total_cents} /></dd></div>
            </dl>
            {order.can_pay ? <>
                {order.expires_at && <p className="text-sm text-gray-600">Je voorraad is gereserveerd tot {new Date(order.expires_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}.</p>}
                <div className="flex flex-col gap-3">
                    <Button type="button" variant="primary" disabled={form.processing} onClick={() => submit('paid')}>Betaling laten slagen</Button>
                    <Button type="button" className="border border-gray-200" disabled={form.processing} onClick={() => submit('failed')}>Betaling laten mislukken</Button>
                    <Button type="button" className="text-red-700" disabled={form.processing} onClick={() => submit('cancelled')}>Bestelling annuleren</Button>
                </div>
            </> : <p role="status">{order.payment_status === 'paid' ? 'Deze bestelling is al betaald.' : 'Deze bestelling kan niet meer worden betaald. Begin een nieuwe bestelling met de actuele voorraad.'}</p>}
            <Link className="block py-2 text-center underline" href={order.confirmation_url || `/checkout/bevestiging/${order.id}`}>Bestelling bekijken</Link>
            {!order.can_pay && order.payment_status !== 'paid' && <Link href="/onderdelen" className="block py-2 text-center underline">Verder winkelen</Link>}
        </div>
    </Container>;
}
