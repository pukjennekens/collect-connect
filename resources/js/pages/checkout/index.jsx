import { useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import ContactSection from '../../components/Checkout/ContactSection';
import OrderSummaryCard from '../../components/Checkout/OrderSummaryCard';
import PaymentMethodTabs from '../../components/Checkout/PaymentMethodTabs';
import ShippingAddressSection from '../../components/Checkout/ShippingAddressSection';
import ShippingMethodCards from '../../components/Checkout/ShippingMethodCards';
import Container from '../../components/Container';

/**
 * Splits a stored full name into the two fields the form shows. The backend
 * keeps a single `name` column, so submit() joins them back together.
 */
function splitName(fullName) {
    const parts = (fullName ?? '').trim().split(/\s+/).filter(Boolean);

    return {
        first_name: parts.shift() ?? '',
        last_name: parts.join(' '),
    };
}

export default function CheckoutPage({
    items = [],
    subtotal = 0,
    shippingMethods = [],
    paymentMethods = [],
    defaultAddress = null,
    user = null,
    country = 'NL',
    checkoutToken = '',
    countries = [],
    addresses = [],
    shippingError = null,
}) {
    const [loadingRates, setLoadingRates] = useState(false);
    const firstMethod = shippingMethods[0];
    const { first_name, last_name } = splitName(defaultAddress?.name ?? user?.name);

    const form = useForm({
        checkout_token: checkoutToken,
        save_address: false,
        house_number: defaultAddress?.house_number ?? '',
        house_addition: defaultAddress?.house_addition ?? '',
        first_name,
        last_name,
        email: defaultAddress?.email ?? user?.email ?? '',
        phone: defaultAddress?.phone ?? '',
        company: defaultAddress?.company ?? '',
        line1: defaultAddress?.line1 ?? '',
        line2: defaultAddress?.line2 ?? '',
        postal_code: defaultAddress?.postal_code ?? '',
        city: defaultAddress?.city ?? '',
        country_code: defaultAddress?.country_code ?? country,
        shipping_method_id: firstMethod?.id ?? 0,
        shipping_cents: firstMethod?.price_cents ?? 0,
        payment_method: paymentMethods[0]?.id ?? '',
        create_account: false,
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        form.setData(data => {
            const selected = shippingMethods.find(method => String(method.id) === String(data.shipping_method_id)) ?? shippingMethods[0];
            const payment = paymentMethods.find(method => method.id === data.payment_method) ?? paymentMethods[0];
            return {...data, checkout_token: checkoutToken, shipping_method_id: selected?.id ?? 0, shipping_cents: selected?.price_cents ?? 0, payment_method: payment?.id ?? ''};
        });
    }, [shippingMethods, paymentMethods, checkoutToken]);

    const reloadRates = (code) => {
        setLoadingRates(true);
        router.get('/checkout', {country: code}, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setLoadingRates(false),
        });
    };

    const selectShipping = (method) => {
        form.setData((data) => ({
            ...data,
            shipping_method_id: method.id,
            shipping_cents: method.price_cents,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        if (loadingRates || shippingMethods.length === 0 || paymentMethods.length === 0 || shippingError) return;

        form.transform((data) => ({
            ...data,
            name: `${data.first_name} ${data.last_name}`.trim(),
        }));

        form.post('/checkout');
    };

    const firstError = Object.values(form.errors)[0];

    return (
        <Container className="my-8 max-w-6xl">
            <Head title="Afrekenen" />
            <Link href="/cart" className="mb-4 inline-block underline">Terug naar winkelwagen</Link>
            <h1 className="border-b border-gray-200 pb-6 text-4xl font-bold">Afrekenen</h1>

            <p className="mt-4 rounded-lg bg-amber-50 p-4 text-sm">Testomgeving: betalingen worden gesimuleerd. Er wordt geen geld afgeschreven.</p>
            <form onSubmit={submit} className="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-5">
                <div className="space-y-8 lg:col-span-3">
                    <ContactSection form={form} user={user} />
                    <ShippingAddressSection form={form} countries={countries} addresses={addresses} canSave={Boolean(user) || form.data.create_account} reloadRates={reloadRates} />
                    {shippingError && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">{shippingError}</p>}
                    {loadingRates && <p role="status">Verzendmethoden worden bijgewerkt…</p>}
                    <ShippingMethodCards
                        methods={shippingMethods}
                        selectedId={form.data.shipping_method_id}
                        onSelect={selectShipping}
                        error={form.errors.shipping_method_id}
                    />
                    <PaymentMethodTabs
                        methods={paymentMethods}
                        selectedId={form.data.payment_method}
                        onSelect={(id) => form.setData('payment_method', id)}
                        error={form.errors.payment_method}
                    />
                </div>

                <div className="lg:col-span-2">

                    <OrderSummaryCard
                        items={items}
                        subtotal={subtotal}
                        shippingCents={shippingError || loadingRates || shippingMethods.length === 0 ? null : form.data.shipping_cents}
                        processing={form.processing || loadingRates}
                        canSubmit={shippingMethods.length > 0 && paymentMethods.length > 0 && !shippingError && !loadingRates}
                        error={firstError}
                    />

                </div>
            </form>
        </Container>
    );
}
