import {ListMagnifyingGlassIcon} from "@phosphor-icons/react/dist/csr/ListMagnifyingGlass";
import Button from "../UI/Button.jsx";

/**
 * @param {Object} props
 * @param {(origin: DOMRect) => void} [props.onOpen]
 */
export default function SearchBar({onOpen}) {
    const handleOpen = (e) => {
        onOpen?.(e.currentTarget.getBoundingClientRect());
    };

    return (
        <>
            {/* Desktop version - trigger button */}
            <button
                aria-label="Zoeken" onClick={handleOpen}
                className="hidden sm:flex w-full max-w-md bg-accent px-4 py-2 border border-gray-200 rounded-md gap-2 items-center cursor-pointer hover:bg-gray-50 transition mr-auto lg:mr-0"
            >
                <ListMagnifyingGlassIcon size={20}/>
                <span className="text-sm text-gray-400">Zoeken...</span>
            </button>

            {/* Mobile version - icon only */}
            <Button aria-label="Zoeken" onClick={handleOpen} iconOnly className="sm:hidden mr-auto">
                <ListMagnifyingGlassIcon size={24}/>
            </Button>
        </>
    );
}
