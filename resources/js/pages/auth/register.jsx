import { Head, Link, useForm } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    return <Container className="max-w-md my-12 space-y-6">
        <Head title="Account aanmaken" /><h1 className="text-2xl font-bold">Account aanmaken</h1>
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') }); }}>
            {[['name', 'Naam'], ['email', 'E-mail'], ['password', 'Wachtwoord'], ['password_confirmation', 'Bevestig wachtwoord']].map(([field, label]) => <Input key={field} label={label} name={field} type={field.startsWith('password') ? 'password' : field === 'email' ? 'email' : 'text'} autoComplete={field.startsWith('password') ? 'new-password' : field} value={form.data[field]} onChange={e => form.setData(field, e.target.value)} error={form.errors[field]} required />)}
            <Button type="submit" variant="primary" className="min-h-11 w-full" disabled={form.processing}>{form.processing ? 'Account aanmaken…' : 'Registreren'}</Button>
        </form>
        <p>Al een account? <Link href="/login" className="underline">Inloggen</Link></p>
    </Container>;
}
