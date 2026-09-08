import { Link } from '@inertiajs/react';

/**
 * Promo card with the artwork clipped into the arc used across the shop. The
 * arc is flatter on the wide variant so the copy keeps its own column.
 *
 * @param {Object} props
 * @param {string} props.title
 * @param {string} props.href
 * @param {string} props.image
 * @param {"tall"|"short"} [props.size]
 * @param {string} [props.cta]
 */
export default function PromoBanner({ title, href, image, size = 'short', cta = 'Shop nu' }) {
    const isTall = size === 'tall';

    return (
        <div className="relative isolate flex h-full overflow-hidden rounded-xl border border-gray-200 bg-white">
            <img
                src={image}
                alt=""
                aria-hidden="true"
                className={`pointer-events-none absolute inset-y-0 right-0 h-full ${
                    isTall
                        ? 'w-[52%] object-cover [clip-path:ellipse(78%_130%_at_100%_50%)]'
                        : 'w-[40%] object-contain p-4'
                }`}
            />

            <div
                className={`relative flex w-full flex-col justify-between gap-6 p-4 sm:p-6 ${
                    isTall ? 'min-h-56 sm:min-h-72 lg:min-h-[26rem]' : 'min-h-40 sm:min-h-44'
                }`}
            >
                <h2 className={`font-bold uppercase text-gray-900 ${isTall ? "max-w-[46%] text-lg sm:text-2xl lg:text-3xl" : "max-w-[60%] text-lg sm:text-2xl"}`}>
                    {title}
                </h2>

                <Link
                    href={href}
                    className="inline-flex w-fit max-w-full items-center justify-center rounded-lg bg-secondary px-6 py-2.5 text-xs font-bold tracking-wide text-white uppercase transition hover:bg-primary sm:px-8 sm:py-3 sm:text-sm"
                >
                    {cta}
                </Link>
            </div>
        </div>
    );
}
