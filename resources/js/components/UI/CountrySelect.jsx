import Select from './Select';

const names = new Intl.DisplayNames(['nl'], { type: 'region' });
export default function CountrySelect({ countries = ['NL', 'BE', 'DE', 'FR'], value, label = 'Land', ...props }) {
    const codes = [...new Set([...countries, value].filter(code => typeof code === 'string' && /^[A-Z]{2}$/.test(code)))];
    return <Select label={label} value={value} {...props}>
        <option value="">Kies een land</option>
        {codes.sort((a, b) => names.of(a).localeCompare(names.of(b), 'nl')).map(code => <option key={code} value={code}>{names.of(code)}</option>)}
    </Select>;
}
