import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    const { flash } = usePage().props;
    return <Container className="max-w-md my-12 space-y-6">
        <Head title="Inloggen" />
        <h1 className="text-2xl font-bold">Inloggen</h1>
        {flash?.status && <p role="status">{flash.status}</p>}
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); }}>
            <Input label="E-mail" name="email" type="email" autoComplete="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} error={form.errors.email} required />
            <Input label="Wachtwoord" name="password" type="password" autoComplete="current-password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} error={form.errors.password} required />
            <label className="flex min-h-11 items-center gap-3"><input type="checkbox" checked={form.data.remember} onChange={e => form.setData('remember', e.target.checked)} />Ingelogd blijven</label>
            <Button type="submit" variant="primary" className="min-h-11 w-full" disabled={form.processing}>{form.processing ? 'Inloggen…' : 'Inloggen'}</Button>
        </form>
        <Link href="/forgot-password" className="block underline">Wachtwoord vergeten?</Link>
        <p>Nog geen account? <Link href="/register" className="underline">Registreren</Link></p>
    </Container>;
}
