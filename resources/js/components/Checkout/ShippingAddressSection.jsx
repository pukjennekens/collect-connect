import Input from '../UI/Input';
import Select from '../UI/Select';
import CheckoutSection from './CheckoutSection';

/**
 * @param {Object} props
 * @param {import('@inertiajs/react').InertiaFormProps} props.form
 */
export default function ShippingAddressSection({ form, countries = [], addresses = [], canSave = false, reloadRates }) {
    // Shipping methods and payment methods are country dependent, so the server
    // re-resolves them when the country changes.
    const changeCountry = (code) => {
        form.setData('country_code', code);
        reloadRates(code);
    };

    const selectAddress = (id) => {
        const address = addresses.find(item => String(item.id) === id);
        if (!address) return;
        const names = (address.name ?? '').trim().split(/\s+/);
        form.setData(data => ({...data,
            first_name: names.shift() ?? '', last_name: names.join(' '),
            ...Object.fromEntries(['company', 'line1', 'line2', 'house_number', 'house_addition', 'postal_code', 'city', 'country_code', 'phone'].map(key => [key, address[key] ?? ''])),
        }));
        reloadRates(address.country_code);
    };
    const countryNames = new Intl.DisplayNames(['nl'], {type: 'region'});
    const availableCountries = [...new Set([form.data.country_code, ...countries].filter(Boolean))];

    return (
        <CheckoutSection title="Verzendinformatie">
            {addresses.length > 0 && <Select label="Opgeslagen adres" defaultValue="" onChange={e => selectAddress(e.target.value)}>
                <option value="">Kies een adres of vul hieronder een nieuw adres in</option>
                {addresses.map(address => <option key={address.id} value={address.id}>{address.name} — {address.line1} {address.house_number} {address.city}</option>)}
            </Select>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                    label="Voornaam"
                    autoComplete="given-name"
                    required
                    value={form.data.first_name}
                    error={form.errors.name}
                    onChange={(e) => form.setData('first_name', e.target.value)}
                />
                <Input
                    label="Achternaam"
                    autoComplete="family-name"
                    required
                    value={form.data.last_name}
                    onChange={(e) => form.setData('last_name', e.target.value)}
                />
            </div>

            <Input
                label="Bedrijf"
                optional
                autoComplete="organization"
                value={form.data.company}
                error={form.errors.company}
                onChange={(e) => form.setData('company', e.target.value)}
            />

            <Input
                label="Straat"
                autoComplete="address-line1"
                required
                value={form.data.line1}
                error={form.errors.line1}
                onChange={(e) => form.setData('line1', e.target.value)}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label="Huisnummer" value={form.data.house_number} error={form.errors.house_number} onChange={e => form.setData('house_number', e.target.value)} />
                <Input label="Toevoeging" optional value={form.data.house_addition} error={form.errors.house_addition} onChange={e => form.setData('house_addition', e.target.value)} />
            </div>
            <Input
                label="Appartement, suite, enz."
                optional
                value={form.data.line2}
                error={form.errors.line2}
                onChange={(e) => form.setData('line2', e.target.value)}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                    label="Postcode"
                    autoComplete="postal-code"
                    required
                    value={form.data.postal_code}
                    error={form.errors.postal_code}
                    onChange={(e) => form.setData('postal_code', e.target.value)}
                />
                <Input
                    label="Stad"
                    autoComplete="address-level2"
                    required
                    value={form.data.city}
                    error={form.errors.city}
                    onChange={(e) => form.setData('city', e.target.value)}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select
                    label="Land"
                    value={form.data.country_code}
                    error={form.errors.country_code}
                    onChange={(e) => changeCountry(e.target.value)}
                >
                    {availableCountries.map((code) => (
                        <option key={code} value={code}>
                            {countryNames.of(code) ?? code}
                        </option>
                    ))}
                </Select>

                <Input
                    label="Telefoon"
                    type="tel"
                    optional
                    autoComplete="tel"
                    value={form.data.phone}
                    error={form.errors.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
            </div>
            {canSave && <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.save_address} onChange={e => form.setData('save_address', e.target.checked)} />Adres opslaan voor een volgende bestelling</label>}
            <p className="text-sm text-gray-600">Dit adres wordt ook gebruikt als factuuradres.</p>
        </CheckoutSection>
    );
}
