import { Link, useForm, usePage } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    const { flash } = usePage().props;
    return <Container className="max-w-md my-12 space-y-4">
        <h1 className="text-2xl font-bold">Wachtwoord vergeten</h1>
        {flash?.status && <p role="status">{flash.status}</p>}
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); form.post('/forgot-password'); }}>
            <Input label="E-mail" error={form.errors.email} type="email" autoComplete="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} required />
            <Button type="submit" disabled={form.processing}>Herstellink versturen</Button>
        </form><Link href="/login" className="underline">Terug naar inloggen</Link>
    </Container>;
}
