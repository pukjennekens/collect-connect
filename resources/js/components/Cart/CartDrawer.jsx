import { Link } from '@inertiajs/react';
import { ShoppingCartIcon } from '@phosphor-icons/react/dist/csr/ShoppingCart';
import { XIcon } from '@phosphor-icons/react/dist/csr/X';
import { useEffect, useRef } from 'react';
import EmptyCart from './EmptyCart.jsx';
import CartItem from './CartItem.jsx';
import Button from '../UI/Button.jsx';
import Price from '../Shop/Price.jsx';
import { useCart } from '../../hooks/useCart.js';

export default function CartDrawer({ isOpen, onClose }) {
    const { items, total } = useCart();
    const dialog = useRef(null);
    useEffect(() => {
        if (!isOpen) {
            dialog.current?.close();
            return;
        }
        const previousFocus = document.activeElement;
        const previousOverflow = document.body.style.overflow;
        dialog.current?.showModal();
        document.body.style.overflow = 'hidden';
        return () => {
            dialog.current?.close();
            document.body.style.overflow = previousOverflow;
            if (previousFocus?.isConnected) previousFocus.focus();
        };
    }, [isOpen]);
    return <dialog ref={dialog} aria-labelledby="cart-drawer-title" onCancel={e => { e.preventDefault(); onClose(); }} onClick={e => { if (e.target === e.currentTarget) onClose(); }} className="fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-dvh w-full max-w-md border-0 bg-white p-0 shadow-xl backdrop:bg-black/40 open:flex open:flex-col md:rounded-l-xl">
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div className="inline-flex items-center gap-2"><ShoppingCartIcon size={24} /><h2 id="cart-drawer-title" className="text-lg font-semibold">Winkelwagen</h2></div>
                <Button type="button" onClick={onClose} iconOnly aria-label="Winkelwagen sluiten" autoFocus><XIcon weight="bold" /></Button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto px-5 py-2">
                {items.length === 0 ? <div className="flex h-full items-center justify-center"><EmptyCart /></div> : items.map(item => <CartItem key={item.id} item={item} />)}
            </div>
            <div className="space-y-3 border-t border-gray-200 bg-white p-5">
                <div className="flex justify-between gap-4"><span>Subtotaal</span><strong><Price price={Math.round(total * 100)} /></strong></div>
                <p className="text-xs text-gray-600">Verzendkosten worden bij het afrekenen berekend.</p>
                <Link href="/cart" onClick={onClose} className="block min-h-11 rounded-lg border border-gray-200 px-4 py-3 text-center font-medium hover:bg-gray-50">Winkelwagen bekijken</Link>
                {items.length > 0 && <Link href="/checkout" onClick={onClose} className="block min-h-11 rounded-lg bg-primary px-4 py-3 text-center font-semibold text-white">Afrekenen</Link>}
            </div>
        </div>
    </dialog>;
}
