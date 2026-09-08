import { useForm } from '@inertiajs/react';
import Container from '../../components/Container';
import Button from '../../components/UI/Button';
import Input from '../../components/UI/Input';

export default function ResetPassword({ token, email }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    return <Container className="max-w-md my-12 space-y-4">
        <h1 className="text-2xl font-bold">Wachtwoord herstellen</h1>
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); form.post('/reset-password'); }}>
            {[['email', 'E-mail'], ['password', 'Nieuw wachtwoord'], ['password_confirmation', 'Herhaal nieuw wachtwoord']].map(([field, label]) => <Input key={field} label={label} error={form.errors[field]} name={field} type={field === 'email' ? 'email' : 'password'} autoComplete={field === 'email' ? 'email' : 'new-password'} value={form.data[field]} onChange={e => form.setData(field, e.target.value)} required />)}
            <Button type="submit" disabled={form.processing}>Wachtwoord opslaan</Button>
        </form>
    </Container>;
}
