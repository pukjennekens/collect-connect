import {Head} from '@inertiajs/react';
import Container from '../../components/Container';

function ContentBlock({block}) {
    const data = block.data ?? block;
    if (block.type === 'heading') return <h2 className="text-xl font-semibold">{data.text}</h2>;
    if (block.type === 'html') return <div className="prose max-w-none" dangerouslySetInnerHTML={{__html: data.html}} />;
    if (block.type === 'image') return /^https?:\/\//i.test(data.src ?? '') ? <img loading="lazy" src={data.src} alt={data.alt ?? ''} className="max-w-full rounded-lg" /> : null;
    return <p className="whitespace-pre-wrap">{data.text}</p>;
}

export default function CmsPage({page}) {
    const columns = ['md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4'];
    return <Container className="my-10 max-w-4xl space-y-8">
        <Head title={page.meta_title || page.title}><meta name="description" content={page.meta_description || ''} /></Head>
        <h1 className="text-3xl font-bold">{page.title}</h1>
        {(page.blocks ?? []).map((block, index) => block.columns ?
            <div key={index} className={`grid grid-cols-1 gap-4 ${columns[Math.min(block.columns.length, 4) - 1] || columns[0]}`}>
                {block.columns.map((column, key) => <div key={key} className="space-y-3">{(column.blocks ?? []).map((item, i) => <ContentBlock key={i} block={item} />)}</div>)}
            </div> : <ContentBlock key={index} block={block} />)}
    </Container>;
}
