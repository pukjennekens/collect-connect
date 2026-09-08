import { MagnifyingGlassIcon } from "@phosphor-icons/react/dist/csr/MagnifyingGlass";
import { SpinnerGapIcon } from "@phosphor-icons/react/dist/csr/SpinnerGap";
import { Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { searchAll } from "../../services/search.js";
import InlineProduct from "../Shop/Products/InlineProduct.jsx";
import InlineSet from "../Shop/Sets/InlineSet.jsx";

/**
 * @param {Object} props
 * @param {string} props.query
 */
export default function SearchResults({ query }) {
    const [results, setResults] = useState({ products: [], minifigs: [], sets: [] });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retry, setRetry] = useState(0);
    const [activeTab, setActiveTab] = useState('products');

    const trimmedQuery = query.trim();

    useEffect(() => {
        if (trimmedQuery === "") {
            setResults({ products: [], minifigs: [], sets: [] });
            setIsLoading(false);
            return;
        }

        const controller = new AbortController();
        setIsLoading(true);
        setError(false);

        const timer = setTimeout(async () => {
            try {
                const data = await searchAll(trimmedQuery, { signal: controller.signal });
                setResults(data);
                setIsLoading(false);
                // Prefer the first tab that has results.
                if (data.products.length > 0) {
                    setActiveTab('products');
                } else if (data.minifigs.length > 0) {
                    setActiveTab('minifigs');
                } else if (data.sets.length > 0) {
                    setActiveTab('sets');
                } else {
                    setActiveTab('products');
                }
            } catch (error) {
                if (error.name !== "AbortError") {
                    setError(true);
                    setResults({ products: [], minifigs: [], sets: [] });
                    setIsLoading(false);
                }
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [trimmedQuery, retry]);

    if (trimmedQuery === "") {
        return (
            <div className="h-full flex items-center justify-center px-4">
                <div className="space-y-4 text-center">
                    <div className="bg-gray-200 text-gray-400 aspect-square rounded-full w-24 h-24 inline-flex items-center justify-center">
                        <MagnifyingGlassIcon size={48} />
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900">
                        Typ je zoekopdracht om te starten met zoeken
                    </h3>
                </div>
            </div>
        );
    }

    const hasProducts = results.products.length > 0;
    const hasMinifigs = results.minifigs.length > 0;
    const hasSets = results.sets.length > 0;
    const hasAny = hasProducts || hasMinifigs || hasSets;
    const tabCount = [hasProducts, hasMinifigs, hasSets].filter(Boolean).length;
    const hasMultipleTabs = tabCount > 1;

    const tabs = [
        hasProducts && { key: 'products', label: `Onderdelen (${results.products.length})` },
        hasMinifigs && { key: 'minifigs', label: `Minifiguren (${results.minifigs.length})` },
        hasSets && { key: 'sets', label: `Sets (${results.sets.length})` },
    ].filter(Boolean);

    const activeTabIndex = Math.max(0, tabs.findIndex((tab) => tab.key === activeTab));
    const panelWidthPct = tabs.length > 0 ? 100 / tabs.length : 100;

    const loadingOrEmpty = isLoading ? (
        <div className="flex items-center justify-center py-12 text-gray-400">
            <SpinnerGapIcon size={32} className="animate-spin" />
        </div>
    ) : error ? (
        <div role="alert" className="space-y-3 p-6 text-center"><p>Zoeken is tijdelijk niet beschikbaar.</p><button onClick={() => setRetry(value => value + 1)} className="rounded border px-4 py-3">Opnieuw proberen</button></div>
    ) : !hasAny ? (
        <p className="py-12 text-center text-gray-500">
            Geen resultaten gevonden voor <span className="font-semibold text-gray-900">"{query}"</span>.
        </p>
    ) : null;

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-gray-200 px-4 lg:px-8 py-4 shrink-0">
                <p className="text-gray-500">
                    Zoekresultaten voor <span className="font-semibold text-gray-900">"{query}"</span>…
                </p>
            </div>

            <Link href={`/zoeken?q=${encodeURIComponent(trimmedQuery)}`} className="mx-4 my-2 inline-flex min-h-11 items-center text-primary underline">Bekijk alle resultaten</Link>
            {/* ── Mobile layout ─────────────────────────────────────────── */}
            <div className="flex flex-1 min-h-0 flex-col lg:hidden">
                {loadingOrEmpty ? (
                    <div className="flex-1">{loadingOrEmpty}</div>
                ) : (
                    <>
                        {hasMultipleTabs && (
                            <div className="shrink-0 flex gap-2 px-4 py-3 bg-white shadow-md z-10 overflow-x-auto">
                                {tabs.map((tab) => (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`shrink-0 rounded-md px-4 py-2 text-sm font-medium transition border ${
                                            activeTab === tab.key
                                                ? 'bg-primary text-white border-primary'
                                                : 'bg-white text-gray-600 border-gray-200 hover:bg-accent'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                        )}

                        <div className="flex-1 min-h-0 overflow-hidden">
                            <div
                                className="flex h-full transition-transform duration-300 ease-in-out"
                                style={{
                                    width: `${tabs.length * 100}%`,
                                    transform: `translateX(-${activeTabIndex * panelWidthPct}%)`,
                                }}
                            >
                                {hasProducts && (
                                    <div className="overflow-y-auto h-full px-4 py-4" style={{ width: `${panelWidthPct}%` }}>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                            {results.products.map((product) => (
                                                <InlineProduct key={product.id} product={product} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {hasMinifigs && (
                                    <div className="overflow-y-auto h-full px-4 py-4" style={{ width: `${panelWidthPct}%` }}>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                            {results.minifigs.map((minifig) => (
                                                <InlineProduct key={`minifig-${minifig.id}`} product={minifig} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {hasSets && (
                                    <div className="overflow-y-auto h-full px-4 py-4" style={{ width: `${panelWidthPct}%` }}>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                            {results.sets.map((set) => (
                                                <InlineSet key={set.id} set={set} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </>
                )}
            </div>

            {/* ── Desktop layout ────────────────────────────────────────── */}
            <div className="hidden lg:flex lg:flex-1 lg:min-h-0 lg:flex-col lg:overflow-y-auto lg:px-8 lg:py-6">
                {loadingOrEmpty ?? (
                    <div className="flex flex-col gap-10">
                        {hasProducts && (
                            <section>
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">
                                    Onderdelen
                                </h4>
                                <div className="grid grid-cols-4 gap-4 xl:grid-cols-5">
                                    {results.products.map((product) => (
                                        <InlineProduct key={product.id} product={product} />
                                    ))}
                                </div>
                            </section>
                        )}
                        {hasMinifigs && (
                            <section>
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">
                                    Minifiguren
                                </h4>
                                <div className="grid grid-cols-4 gap-4 xl:grid-cols-5">
                                    {results.minifigs.map((minifig) => (
                                        <InlineProduct key={`minifig-${minifig.id}`} product={minifig} />
                                    ))}
                                </div>
                            </section>
                        )}
                        {hasSets && (
                            <section>
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">
                                    Sets
                                </h4>
                                <div className="grid grid-cols-4 gap-4 xl:grid-cols-5">
                                    {results.sets.map((set) => (
                                        <InlineSet key={set.id} set={set} />
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
