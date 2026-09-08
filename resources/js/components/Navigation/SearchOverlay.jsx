import { X } from "@phosphor-icons/react/dist/csr/X";
import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import gsap from "gsap";
import { useMediaQuery } from "../../hooks/useMediaQuery.js";
import Button from "../UI/Button.jsx";
import ModeToggle from "./ModeToggle.jsx";
import MegaMenu from "./MegaMenu.jsx";
import MobileMenu from "./MobileMenu.jsx";
import SearchResults from "./SearchResults.jsx";

/**
 * Builds a CSS clip-path inset() that matches the given trigger rect,
 * so the panel can "flow out" of the search bar towards full screen.
 *
 * @param {DOMRect|null} origin
 * @returns {string}
 */
function insetFromOrigin(origin) {
    if (!origin) {
        return "inset(50% 50% 50% 50% round 8px)";
    }

    const top = origin.top;
    const right = window.innerWidth - origin.right;
    const bottom = window.innerHeight - origin.bottom;
    const left = origin.left;

    return `inset(${top}px ${right}px ${bottom}px ${left}px round 8px)`;
}

/**
 * @param {Object} props
 * @param {boolean} props.isOpen
 * @param {DOMRect|null} [props.origin]
 * @param {() => void} props.onClose
 * @param {Array<Object>} [props.menu] - Navigation tree shared from Inertia.
 */
export default function SearchOverlay({ isOpen, origin, onClose, menu = [] }) {
    const [mounted, setMounted] = useState(false);
    const [query, setQuery] = useState("");
    const [mode, setMode] = useState("menu");
    // Matches the `md` breakpoint the header layout uses.
    const isDesktop = useMediaQuery("(min-width: 768px)");
    const rootRef = useRef(null);
    const backdropRef = useRef(null);
    const panelRef = useRef(null);
    const contentRef = useRef(null);
    const inputRef = useRef(null);
    const timelineRef = useRef(null);
    const returnFocusRef = useRef(null);

    // Mount on open; unmount only after the close animation has finished.
    useEffect(() => {
        if (isOpen) {
            returnFocusRef.current = document.activeElement;
            setMounted(true);
        }
    }, [isOpen]);

    // Close the overlay whenever an Inertia navigation starts (covers clicking
    // InlineProduct, InlineSet, and any other Link inside the panel).
    useEffect(() => {
        return router.on('start', () => {
            if (isOpen) {
                onClose();
            }
        });
    }, [isOpen, onClose]);

    // Lock body scroll + escape-to-close while mounted. Typing a printable key
    // anywhere (e.g. while hovering the menu) switches to search mode.
    useEffect(() => {
        if (!mounted) {
            return;
        }

        const handleKeyDown = (e) => {
            if (e.key === 'Tab') {
                const controls = [...panelRef.current.querySelectorAll('a[href], button:not([disabled]), input, select, [tabindex="0"]')].filter(element => element.getClientRects().length > 0);
                const first = controls[0];
                const last = controls.at(-1);
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus(); }
                if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
            }
            if (e.key === "Escape") {
                onClose();
                return;
            }

            const isPrintable = e.key.length === 1 && !e.metaKey && !e.ctrlKey && !e.altKey;
            if (isPrintable && document.activeElement !== inputRef.current) {
                e.preventDefault();
                setMode("search");
                setQuery((prev) => prev + e.key);
                inputRef.current?.focus();
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        document.body.style.overflow = "hidden";

        return () => {
            window.removeEventListener("keydown", handleKeyDown);
            document.body.style.overflow = "";
            returnFocusRef.current?.focus();
        };
    }, [mounted, onClose]);

    // Drive the open/close animation with GSAP.
    useEffect(() => {
        if (!mounted) {
            return;
        }

        const ctx = gsap.context(() => {
            const tl = gsap.timeline({ paused: true });

            tl.set(panelRef.current, { clipPath: insetFromOrigin(origin) })
                .set(backdropRef.current, { opacity: 0 })
                .set(contentRef.current, { opacity: 0, y: 12 })
                .to(backdropRef.current, { opacity: 1, duration: 0.3, ease: "power1.out" })
                .to(
                    panelRef.current,
                    {
                        clipPath: "inset(0px 0px 0px 0px round 0px)",
                        duration: 0.5,
                        ease: "power3.inOut",
                    },
                    "<"
                )
                .to(
                    contentRef.current,
                    { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" },
                    "-=0.2"
                );

            timelineRef.current = tl;
            tl.play();
        }, rootRef);

        return () => ctx.revert();
        // origin is captured once on mount; re-running on its change would restart the animation.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mounted]);

    // Play forward on open, reverse on close (then unmount).
    useEffect(() => {
        const tl = timelineRef.current;
        if (!tl) {
            return;
        }

        if (isOpen) {
            tl.play();
            // Focus the input once the panel has expanded.
            const focusTimer = setTimeout(() => inputRef.current?.focus(), 350);
            return () => clearTimeout(focusTimer);
        }

        setQuery("");
        setMode("menu");
        tl.eventCallback("onReverseComplete", () => setMounted(false));
        tl.reverse();
    }, [isOpen, mounted]);

    const navigate = (href) => {
        onClose();
        router.visit(href);
    };

    const handleQueryChange = (value) => {
        setQuery(value);

        // Typing immediately switches to search mode.
        if (value.trim() !== "") {
            setMode("search");
        }
    };

    const isSearching = query.trim() !== "";

    // Desktop follows the mode toggle; mobile has no toggle, so it follows the query.
    const showResults = isDesktop ? mode === "search" : isSearching;

    if (!mounted) {
        return null;
    }

    return (
        <div ref={rootRef} className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Zoeken en navigatie">
            <div
                ref={backdropRef}
                className="absolute inset-0 bg-black/50 backdrop-blur-sm"
                style={{ opacity: 0 }}
                onClick={onClose}
            />

            <div
                ref={panelRef}
                className="absolute inset-0 flex flex-col bg-white"
                style={{ clipPath: insetFromOrigin(origin) }}
            >
                <div className="border-b border-gray-200 px-4 lg:px-8 py-4">
                    <div className="flex items-center gap-4 max-w-7xl mx-auto">
                        <div className="hidden md:block">
                            <ModeToggle mode={mode} onChange={setMode} />
                        </div>
                        <input
                            ref={inputRef}
                            type="search"
                            aria-label="Zoek op naam of LEGO-nummer"
                            onKeyDown={(event) => { if (event.key === 'Enter' && query.trim()) { event.preventDefault(); navigate(`/zoeken?q=${encodeURIComponent(query.trim())}`); } }}
                            value={query}
                            onChange={(e) => handleQueryChange(e.target.value)}
                            placeholder="Zoek op naam, onderdelen, minifiguren, lego-nummer..."
                            className="min-w-0 flex-1 text-lg bg-transparent outline-none placeholder-gray-400"
                        />
                        <Button onClick={onClose} aria-label="Zoeken sluiten" iconOnly className="shrink-0">
                            <X size={24} />
                        </Button>
                    </div>
                </div>

                <div ref={contentRef} className="flex-1 min-h-0" style={{ opacity: 0 }}>
                    {/*
                     * Exactly one SearchResults instance is mounted. Rendering it in two
                     * CSS-hidden wrappers would mount it twice, and each instance would
                     * fire its own debounced /api/search request per keystroke.
                     */}
                    {showResults ? (
                        <div className="h-full md:max-w-7xl md:mx-auto md:w-full">
                            <SearchResults query={query} />
                        </div>
                    ) : isDesktop ? (
                        /* Desktop: mode-driven (toggle + typing). */
                        <div className="h-full max-w-7xl mx-auto w-full">
                            <MegaMenu menu={menu} onNavigate={navigate} />
                        </div>
                    ) : (
                        /* Mobile: query-driven, no mode switch. */
                        <div className="h-full">
                            <MobileMenu menu={menu} onNavigate={navigate} />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
