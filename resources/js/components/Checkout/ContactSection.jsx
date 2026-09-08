import Input from '../UI/Input';
import CheckoutSection from './CheckoutSection';

/**
 * @param {Object} props
 * @param {import('@inertiajs/react').InertiaFormProps} props.form
 * @param {{ name: string, email: string }|null} props.user
 */
export default function ContactSection({ form, user }) {
    return (
        <CheckoutSection title="Contactgegevens">
            <Input
                label="E-mailadres"
                type="email"
                autoComplete="email"
                required
                value={form.data.email}
                error={form.errors.email}
                onChange={(e) => form.setData('email', e.target.value)}
            />

            {!user && (
                <div className="space-y-3">
                    <label className="flex items-center gap-2 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            className="rounded border-gray-300"
                            checked={form.data.create_account}
                            onChange={(e) => form.setData('create_account', e.target.checked)}
                        />
                        Account aanmaken om je bestellingen terug te zien
                    </label>

                    {form.data.create_account && (
                        <>
                        <Input
                            required
                            label="Wachtwoord"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            error={form.errors.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                        <Input label="Herhaal wachtwoord" type="password" required autoComplete="new-password" value={form.data.password_confirmation} error={form.errors.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} />
                        </>
                    )}
                </div>
            )}
        </CheckoutSection>
    );
}
