import { Link, usePage } from '@inertiajs/react';

export default function AccountNavigation() {
    const { url } = usePage();
    return <nav aria-label="Mijn account" className="mb-6 flex flex-wrap items-center gap-2 text-sm">
        {[['/account/orders', 'Bestellingen'], ['/account/profile', 'Accountgegevens'], ['/account/addresses', 'Adressen']].map(([href, label]) => <Link key={href} href={href} aria-current={url.startsWith(href) ? 'page' : undefined} className={`inline-flex min-h-11 items-center rounded-lg px-3 ${url.startsWith(href) ? 'bg-gray-100 font-semibold' : 'hover:bg-gray-50 underline'}`}>{label}</Link>)}
        <Link href="/logout" method="post" as="button" className="min-h-11 rounded-lg px-3 underline hover:bg-gray-50 sm:ml-auto">Uitloggen</Link>
    </nav>;
}
