import { Link } from '@inertiajs/react';

export default function Pagination({ pagination, label = 'Paginering' }) {
    const meta = pagination?.meta ?? pagination;
    if (!meta || meta.last_page <= 1) return null;
    const links = meta.links ?? [];
    return <nav aria-label={label} className="mt-8 flex flex-wrap items-center gap-2">
        {links.map((link, index) => link.url ? <Link key={index} href={link.url} preserveScroll aria-current={link.active ? 'page' : undefined} className={`inline-flex min-h-11 min-w-11 items-center justify-center rounded border px-3 ${link.active ? 'border-primary bg-primary text-white' : 'border-gray-300 hover:bg-accent'}`} dangerouslySetInnerHTML={{ __html: link.label.replace('Previous', 'Vorige').replace('Next', 'Volgende') }} /> : null)}
        <span className="text-sm text-gray-600">Pagina {meta.current_page} van {meta.last_page}</span>
    </nav>;
}
