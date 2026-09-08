import FooterLinks from './FooterLinks';

export default function Footer() {
    const popularCategories = [
        {label: 'Onderdelen', href: '/onderdelen'},
        {label: 'Minifiguren', href: '/minifiguren'},
        {label: 'Sets', href: '/sets'},
    ];
    const customerService = [
        {label: 'Mijn account', href: '/account/orders'},
        {label: 'Nieuws', href: '/blog'},
        {label: 'Over ons', href: '/pages/over-ons'},
        {label: 'Contact', href: '/pages/contact'},
        {label: 'Verzending', href: '/pages/verzending'},
        {label: 'Retourneren', href: '/pages/retourneren'},
    ];

    return (
        <footer className="relative bg-accent py-10 pb-8 px-8 min-h-62.5 border-brick-top-accent">
            {window.location.pathname === '/' && (
                <img src="/images/elements/alien.png" alt="" className="absolute bottom-full right-8 translate-y-1/2 -rotate-12 hidden lg:block pointer-events-none" />
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-8">
                <img src="/images/coco-logo-small.svg" alt="Collect2Connect" className="w-full max-w-60 hidden md:block" />
                <img src="/images/coco-logo.svg" alt="Collect2Connect" className="w-full max-w-60 md:hidden" />

                <FooterLinks title="Ontdek de collectie" links={popularCategories} />
                <FooterLinks title="Klantenservice" links={customerService} />
            </div>

            <p className="mt-8 text-sm">Testomgeving — betalingen worden gesimuleerd.</p>

            <hr className="border-gray-300 my-8" />

            <p className="text-sm text-gray-500">&copy; {new Date().getFullYear()} Collect2Connect. All rights reserved.</p>
            <p className="text-sm text-gray-500">LEGO, the LEGO logo, the Minifigure, DUPLO, MINDSTORMS and LEGENDS OF CHIMA are trademarks of the LEGO Group. © 2025 The LEGO Group.</p>
        </footer>
    );
}
