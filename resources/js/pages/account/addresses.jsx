import { useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';
import AccountNavigation from './AccountNavigation';
import CountrySelect from '../../components/UI/CountrySelect';

const empty = { name: '', company: '', email: '', phone: '', line1: '', line2: '', house_number: '', house_addition: '', postal_code: '', city: '', country_code: 'NL', is_default: false };
const fields = [['name', 'Naam'], ['company', 'Bedrijf'], ['email', 'E-mail'], ['phone', 'Telefoon'], ['line1', 'Straat / adresregel'], ['house_number', 'Huisnummer'], ['house_addition', 'Toevoeging'], ['line2', 'Adresregel 2'], ['postal_code', 'Postcode'], ['city', 'Plaats']];

export default function Addresses({ addresses, countries = ['NL', 'BE', 'DE', 'FR'] }) {
    const [editing, setEditing] = useState(null);
    const form = useForm(empty);
    const { flash } = usePage().props;
    const reset = () => { setEditing(null); form.setData(empty); form.clearErrors(); };
    return <Container className="max-w-3xl my-8 space-y-6">
        <AccountNavigation />
        <h1 className="text-3xl font-bold">Mijn adressen</h1>
        {flash?.status && <p role="status">{flash.status}</p>}
        {addresses.length === 0 && <p>Je hebt nog geen adressen opgeslagen.</p>}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">{addresses.map(address => <article key={address.id} className="border rounded-xl p-4 space-y-2">
            <h2 className="font-semibold">{address.name}{address.is_default ? ' · Standaard' : ''}</h2>
            <p>{address.line1} {address.house_number} {address.house_addition}<br />{address.postal_code} {address.city}<br />{address.country_code}</p>
            <div className="flex flex-wrap gap-3"><button type="button" className="min-h-11 px-2 underline" onClick={() => { setEditing(address.id); form.setData(Object.fromEntries(Object.keys(empty).map(key => [key, address[key] ?? empty[key]]))); }}>Bewerken</button><button type="button" className="min-h-11 px-2 underline" onClick={() => router.delete(`/account/addresses/${address.id}`)}>Verwijderen</button></div>
        </article>)}</div>
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); const options = { onSuccess: reset }; editing ? form.patch(`/account/addresses/${editing}`, options) : form.post('/account/addresses', options); }}>
            <h2 className="text-xl font-semibold">{editing ? 'Adres bewerken' : 'Adres toevoegen'}</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">{fields.map(([field, label]) => <Input key={field} label={label} error={form.errors[field]} name={field} type={field === 'email' ? 'email' : 'text'} value={form.data[field]} onChange={e => form.setData(field, field === 'country_code' ? e.target.value.toUpperCase() : e.target.value)} required={['name', 'line1', 'postal_code', 'city', 'country_code'].includes(field)} maxLength={field === 'country_code' ? 2 : undefined} />)}<CountrySelect countries={countries} value={form.data.country_code} onChange={e => form.setData('country_code', e.target.value)} error={form.errors.country_code} required /></div>
            <label className="flex gap-2"><input type="checkbox" checked={form.data.is_default} onChange={e => form.setData('is_default', e.target.checked)} />Standaardadres</label>
            <div className="flex gap-4"><Button type="submit" disabled={form.processing}>Adres opslaan</Button>{editing && <button type="button" onClick={reset}>Annuleren</button>}</div>
        </form>
    </Container>;
}
