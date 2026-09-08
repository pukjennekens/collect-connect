import SearchBar from '../Navigation/SearchBar';
import SearchOverlay from '../Navigation/SearchOverlay';
import CartIcon from '../Navigation/CartIcon';
import HeaderTopBar from './HeaderTopBar';
import {Link, usePage} from "@inertiajs/react";
import {useEffect, useState} from 'react';
import Button from '../UI/Button.jsx';

/**
 * @param {Object} props
 * @param {string} [props.currentRoute]
 * @param {() => void} [props.onOpenCart]
 */
export default function Header({currentRoute = '/', onOpenCart}) {
    const {navigation = []} = usePage().props;
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchOrigin, setSearchOrigin] = useState(null);

    const openSearch = (origin) => {
        setSearchOrigin(origin);
        setSearchOpen(true);
    };

    // Lets any page open the shared overlay, the way the cart drawer works.
    useEffect(() => {
        const handler = (event) => openSearch(event.detail?.origin ?? null);
        window.addEventListener('search:open', handler);
        return () => window.removeEventListener('search:open', handler);
    }, []);

    return (
        <header className="sticky top-0 z-50 bg-white border-b border-[#e1e1e1]">
            <HeaderTopBar/>

            <nav className="px-4 lg:px-8 py-4 flex items-center justify-between gap-2 lg:gap-4">
                <Link href="/" className="flex-shrink-0">
                    <img className="hidden lg:block min-h-12" src="/images/coco-logo.svg" alt="collect2connect" />
                    <img className="block lg:hidden min-h-12 w-10" src="/images/coco-logo-small.svg" alt="collect2connect" />
                </Link>

                <SearchBar onOpen={openSearch}/>

                <div className="flex items-center gap-2">
                    <Button as="link" href="/account" aria-label="Mijn account">
                        <svg width="17" height="21" viewBox="0 0 17 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.625 10.125C5.4025 10.125 5.18499 10.059 4.99998 9.9354C4.81498 9.81179 4.67078 9.63609 4.58564 9.43052C4.50049 9.22495 4.47821 8.99875 4.52162 8.78052C4.56502 8.56229 4.67217 8.36184 4.8295 8.2045C4.98684 8.04717 5.18729 7.94002 5.40552 7.89662C5.62375 7.85321 5.84995 7.87549 6.05552 7.96064C6.26109 8.04578 6.43679 8.18998 6.5604 8.37498C6.68402 8.55999 6.75 8.7775 6.75 9C6.75 9.29837 6.63147 9.58452 6.4205 9.7955C6.20952 10.0065 5.92337 10.125 5.625 10.125ZM10.875 7.875C10.6525 7.875 10.435 7.94098 10.25 8.0646C10.065 8.18821 9.92078 8.36391 9.83564 8.56948C9.75049 8.77505 9.72821 9.00125 9.77162 9.21948C9.81502 9.43771 9.92217 9.63816 10.0795 9.7955C10.2368 9.95283 10.4373 10.06 10.6555 10.1034C10.8738 10.1468 11.1 10.1245 11.3055 10.0394C11.5111 9.95422 11.6868 9.81002 11.8104 9.62502C11.934 9.44001 12 9.2225 12 9C12 8.70163 11.8815 8.41548 11.6705 8.2045C11.4595 7.99353 11.1734 7.875 10.875 7.875ZM10.4747 12.1153C9.80671 12.5299 9.03617 12.7496 8.25 12.7496C7.46383 12.7496 6.69329 12.5299 6.02531 12.1153C5.85698 12.0091 5.65337 11.9742 5.45927 12.0181C5.26517 12.0621 5.09648 12.1814 4.99031 12.3497C4.88414 12.518 4.84919 12.7216 4.89314 12.9157C4.9371 13.1098 5.05636 13.2785 5.22469 13.3847C6.13232 13.9505 7.18044 14.2504 8.25 14.2504C9.31956 14.2504 10.3677 13.9505 11.2753 13.3847C11.3587 13.3321 11.4308 13.2636 11.4877 13.1832C11.5446 13.1027 11.5851 13.0118 11.6069 12.9157C11.6286 12.8196 11.6312 12.7202 11.6146 12.623C11.5979 12.5259 11.5623 12.433 11.5097 12.3497C11.4571 12.2663 11.3886 12.1942 11.3082 12.1373C11.2277 12.0804 11.1368 12.0399 11.0407 12.0181C10.9446 11.9964 10.8452 11.9938 10.748 12.0104C10.6509 12.0271 10.558 12.0627 10.4747 12.1153ZM16.5 6V15C16.4994 15.6651 16.2782 16.3112 15.871 16.8371C15.4638 17.363 14.8937 17.739 14.25 17.9062V19.5C14.25 19.8978 14.092 20.2794 13.8107 20.5607C13.5294 20.842 13.1478 21 12.75 21H3.75C3.35218 21 2.97064 20.842 2.68934 20.5607C2.40804 20.2794 2.25 19.8978 2.25 19.5V17.9062C1.60626 17.739 1.03616 17.363 0.629005 16.8371C0.221847 16.3112 0.000628828 15.6651 0 15V6C0 5.20435 0.31607 4.44129 0.87868 3.87868C1.44129 3.31607 2.20435 3 3 3H4.5V1.5C4.5 1.10218 4.65804 0.720644 4.93934 0.43934C5.22064 0.158035 5.60218 0 6 0H10.5C10.8978 0 11.2794 0.158035 11.5607 0.43934C11.842 0.720644 12 1.10218 12 1.5V3H13.5C14.2956 3 15.0587 3.31607 15.6213 3.87868C16.1839 4.44129 16.5 5.20435 16.5 6ZM6 3H10.5V1.5H6V3ZM12.75 19.5V18H3.75V19.5H12.75ZM15 6C15 5.60218 14.842 5.22064 14.5607 4.93934C14.2794 4.65804 13.8978 4.5 13.5 4.5H3C2.60218 4.5 2.22064 4.65804 1.93934 4.93934C1.65804 5.22064 1.5 5.60218 1.5 6V15C1.5 15.3978 1.65804 15.7794 1.93934 16.0607C2.22064 16.342 2.60218 16.5 3 16.5H13.5C13.8978 16.5 14.2794 16.342 14.5607 16.0607C14.842 15.7794 15 15.3978 15 15V6Z" fill="#333333"/>
                        </svg>

                        <span className="hidden lg:block font-semibold text-sm uppercase">Mijn account</span>
                    </Button>

                    <CartIcon onOpen={onOpenCart}/>
                </div>
            </nav>

            <SearchOverlay isOpen={searchOpen} origin={searchOrigin} menu={navigation} onClose={() => setSearchOpen(false)} />
        </header>
    );
}
