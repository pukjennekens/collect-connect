import { Head, Link } from '@inertiajs/react';

const messages = {
    403: ['Geen toegang', 'Je hebt geen toegang tot deze pagina. Log in met het juiste account of ga terug naar de winkel.'],
    404: ['Pagina niet gevonden', 'Deze pagina bestaat niet meer of het adres klopt niet. Vind je product via de collectie.'],
    410: ['Deze pagina is verlopen', 'Ga terug naar de winkel om verder te gaan.'],
    500: ['Er ging iets mis', 'Probeer het later opnieuw. Je kunt terug naar de winkel.'],
    503: ['Even niet beschikbaar', 'We zijn tijdelijk niet bereikbaar. Probeer het later opnieuw.'],
};
export default function ErrorPage({ status }) {
    const [title, description] = messages[status] ?? messages[500];
    return <main className="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-6 p-6">
        <Head title={title} />
        <Link href="/"><img src="/images/coco-logo.svg" alt="Collect2Connect" className="w-64 max-w-full" /></Link>
        <p className="text-sm text-gray-500">{status}</p><h1 className="text-3xl font-bold">{title}</h1><p className="text-gray-600">{description}</p>
        <div className="flex flex-wrap gap-3"><Link href="/" className="rounded bg-primary px-5 py-3 text-white">Naar de winkel</Link><Link href="/zoeken" className="rounded border px-5 py-3">Zoeken</Link><Link href="/account" className="rounded border px-5 py-3">Mijn account</Link></div>
    </main>;
}
ErrorPage.layout = page => page;
