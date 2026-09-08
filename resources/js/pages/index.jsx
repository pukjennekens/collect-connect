import { Head } from '@inertiajs/react';
import HeroBanners from '../components/Home/HeroBanners.jsx';
import InstagramFeed from '../components/Home/InstagramFeed.jsx';
import PopularCategories from '../components/Home/PopularCategories.jsx';
import SearchHighlight from '../components/Home/SearchHighlight.jsx';
import SeoSection from '../components/Home/SeoSection.jsx';
import TrendingProducts from '../components/Home/TrendingProducts.jsx';

const PRIMARY_BANNER = {
    title: 'Vind jouw ontbrekende steentjes',
    href: '/onderdelen',
    image: '/images/home/hero-sale.jpg',
};

const SECONDARY_BANNERS = [
    { title: 'Minifiguren', href: '/minifiguren', image: '/images/elements/alien.png' },
    { title: 'Ontdek sets', href: '/sets', image: '/images/elements/rocket.png' },
];

/**
 * @param {Object} props
 * @param {{data: Array<Object>}|Array<Object>} [props.trendingProducts]
 * @param {Array<{id: number, name: string, url: string}>} [props.popularCategories]
 */
export default function Home({ trendingProducts = [], popularCategories = [] }) {
    const products = trendingProducts?.data ?? trendingProducts;

    return (
        <>
            <Head title="Dé plek voor al jouw LEGO® onderdelen en minifiguren" />

            <HeroBanners primary={PRIMARY_BANNER} secondary={SECONDARY_BANNERS} />

            <TrendingProducts products={products} />

            <SearchHighlight />

            <PopularCategories categories={popularCategories} />



            <SeoSection />
        </>
    );
}
