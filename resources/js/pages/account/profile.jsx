import { Link, useForm, usePage } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';
import AccountNavigation from './AccountNavigation';

export default function Profile({ profile }) {
    const details = useForm(profile);
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const { flash } = usePage().props;
    return (
        <Container className="max-w-2xl my-8 space-y-8">
            <AccountNavigation />
            <h1 className="text-3xl font-bold">Mijn account</h1>
            {flash?.status && <p role="status">{flash.status}</p>}
            <form className="space-y-4" onSubmit={e => { e.preventDefault(); details.patch('/account/profile'); }}>
                {['name', 'email'].map(field => <Input key={field} label={field === 'name' ? 'Naam' : 'E-mail'} error={details.errors[field]} name={field} autoComplete={field} type={field === 'email' ? 'email' : 'text'} value={details.data[field]} onChange={e => details.setData(field, e.target.value)} required />)}
                <Button type="submit" disabled={details.processing}>Gegevens opslaan</Button>
            </form>
            <form className="space-y-4" onSubmit={e => { e.preventDefault(); password.put('/account/password', { onSuccess: () => password.reset() }); }}>
                <h2 className="text-xl font-semibold">Wachtwoord wijzigen</h2>
                {[['current_password', 'Huidig wachtwoord'], ['password', 'Nieuw wachtwoord'], ['password_confirmation', 'Herhaal nieuw wachtwoord']].map(([field, label]) => <Input key={field} label={label} error={password.errors[field]} name={field} type="password" autoComplete={field === 'current_password' ? 'current-password' : 'new-password'} value={password.data[field]} onChange={e => password.setData(field, e.target.value)} required />)}
                <Button type="submit" disabled={password.processing}>Wachtwoord wijzigen</Button>
            </form>
        </Container>
    );
}
