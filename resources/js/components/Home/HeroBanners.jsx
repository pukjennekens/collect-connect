import Container from '../Container.jsx';
import PromoBanner from './PromoBanner.jsx';

/**
 * Promo row at the top of the homepage: one tall banner next to two short ones.
 *
 * @param {Object} props
 * @param {{title: string, href: string, image: string}} props.primary
 * @param {Array<{title: string, href: string, image: string}>} props.secondary
 */
export default function HeroBanners({ primary, secondary = [] }) {
    return (
        <Container className="max-w-7xl my-8">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <PromoBanner size="tall" {...primary} />

                <div className="grid grid-cols-1 gap-4 lg:grid-rows-2">
                    {secondary.map((banner) => (
                        <PromoBanner key={banner.title} {...banner} />
                    ))}
                </div>
            </div>
        </Container>
    );
}
