import {Head} from '@inertiajs/react';
import Container from '../../components/Container';

export default function Article({article}) {
    return <Container className="my-10 max-w-4xl space-y-8">
        <Head title={article.meta_title || article.title}><meta name="description" content={article.meta_description || ''} /></Head>
        <h1 className="text-3xl font-semibold">{article.title}</h1>
        <article className="prose max-w-none" dangerouslySetInnerHTML={{__html: article.content}} />
    </Container>;
}
