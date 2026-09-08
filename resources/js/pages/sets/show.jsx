import Pagination from '../../components/UI/Pagination';
import { router } from '@inertiajs/react';
import Container from '../../components/Container';
import SingleInlineProduct from '../../components/Shop/Products/SingleInlineProduct.jsx';

/**
 * @param {Object} props
 * @param {{ data: { id: number, set_num: string, name: string, year: number, num_parts: number, image: string|null } }} props.set
 * @param {Array} props.in_stock_parts
 * @param {Array} props.out_of_stock_parts
 */
export default function SetPage({ set: { data: set }, in_stock_parts, out_of_stock_parts, filters, active, theme, parts, minifigs }) {
    function updateFilter(key, value) {
        router.get(set.url, { ...active, [key]: value || undefined }, { preserveState: true, preserveScroll: true });
    }
    return (
        <Container className="max-w-250 my-8">
            <div className="py-10 grid grid-cols-1 md:grid-cols-5 gap-10 md:shadow-[0_0px_10px_rgba(0,0,0,0.1)] md:px-6 rounded-xl">
                <div className="rounded-xl overflow-hidden flex items-center justify-center aspect-square md:col-span-2 max-h-60 md:max-h-none mx-auto">
                    {set.image ? (
                        <img
                            src={set.image}
                            alt={set.name}
                            className="object-contain w-full h-full"
                        />
                    ) : (
                        <div className="w-full h-full bg-gray-100 rounded-xl" />
                    )}
                </div>

                <div className="flex flex-col gap-4 md:col-span-3">
                    <div>
                        <p className="text-sm text-gray-400 mb-1">{set.set_num}</p>
                        <h1 className="text-2xl font-bold text-gray-900">{set.name}</h1>
                    </div>

                    <dl className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                            <dt className="text-sm text-gray-500 w-32">Jaar</dt>
                            <dd className="text-sm font-medium text-gray-900">{set.year}</dd>
                        </div>
                        <div className="flex items-center gap-2">
                            <dt className="text-sm text-gray-500 w-32">Aantal onderdelen</dt>
                            <dd className="text-sm font-medium text-gray-900">{set.num_parts}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {theme && <p className="mt-4 text-gray-600">Thema: {theme}</p>}
            <div className="my-6 grid gap-4 sm:grid-cols-2">
                {[['category_id', 'Categorie', filters?.categories], ['color_id', 'Kleur', filters?.colors]].map(([key, label, options]) => <label key={key} className="flex flex-col gap-2">
                    {label}
                    <select value={active?.[key] ?? ''} onChange={(event) => updateFilter(key, event.target.value)} className="rounded-md border border-gray-200 p-2">
                        <option value="">Alles</option>
                        {(options ?? []).map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                    </select>
                </label>)}
            </div>
            {(active?.category_id || active?.color_id) && <button onClick={() => router.get(set.url)} className="min-h-11 text-primary underline">Filters wissen</button>}
            {[["Onderdelen", parts], ["Minifiguren", minifigs]].map(([label, listing]) => <section key={label} className="mt-10">
                <h2 className="text-xl font-semibold">{label} <span className="text-base font-normal text-gray-600">({listing?.total ?? 0})</span></h2>
                {!listing?.data?.length && <p className="mt-4 text-gray-600">Geen {label.toLowerCase()} gevonden voor deze selectie.</p>}
                <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    {(listing?.data ?? []).map((product, index) => <SingleInlineProduct key={`${product.id}-${product.lego_number}-${product.color?.name}-${product.is_spare}-${index}`} product={product} quantityInSet={product.quantity_in_set} />)}
                </div>
                <Pagination pagination={listing} label={`${label} paginering`} />
            </section>)}
        </Container>
    );
}
