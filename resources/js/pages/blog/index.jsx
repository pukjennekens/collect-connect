import {Head, Link} from '@inertiajs/react';
import Container from '../../components/Container';

export default function BlogIndex({articles}) {
    return <Container className="my-10 max-w-4xl space-y-6">
        <Head title="Blog" />
        <h1 className="text-3xl font-bold">Blog</h1>
        {articles.data.length === 0 && <p>Er zijn nog geen artikelen gepubliceerd.</p>}
        {articles.data.map(article => <article key={article.id} className="border-b pb-5">
            <Link href={`/blog/${article.slug}`} className="text-xl font-semibold hover:underline">{article.title}</Link>
            {article.meta_description && <p className="mt-2">{article.meta_description}</p>}
        </article>)}
        {articles.last_page > 1 && <nav aria-label="Paginering" className="flex justify-between">
            {articles.prev_page_url ? <Link href={articles.prev_page_url}>Vorige</Link> : <span />}
            <span>{articles.current_page} / {articles.last_page}</span>
            {articles.next_page_url ? <Link href={articles.next_page_url}>Volgende</Link> : <span />}
        </nav>}
    </Container>;
}
