import { Link } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { MinusIcon } from '@phosphor-icons/react/dist/csr/Minus';
import { PlusIcon } from '@phosphor-icons/react/dist/csr/Plus';
import { TrashIcon } from '@phosphor-icons/react/dist/csr/Trash';
import { ShoppingBagIcon } from '@phosphor-icons/react/dist/csr/ShoppingBag';
import { useCart } from '../../hooks/useCart';
import Price from '../Shop/Price';

export default function CartItem({ item }) {
    const { removeItem, updateQuantity, processing, error } = useCart();
    const [quantity, setQuantity] = useState(String(item.quantity));
    const [localError, setLocalError] = useState('');
    const inputId = useId();
    const isProcessing = processing === item.id;
    useEffect(() => { setQuantity(String(item.quantity)); }, [item.quantity]);
    const save = () => {
        const requested = Number(quantity);
        if (!Number.isInteger(requested) || requested < 1 || requested > item.stock) {
            setLocalError(`Kies een heel aantal tussen 1 en ${item.stock}.`);
            return;
        }
        setLocalError('');
        if (requested !== item.quantity) updateQuantity(item.id, requested);
    };
    const step = delta => {
        const base = Number(quantity);
        const next = Math.max(1, Math.min(item.stock, (Number.isInteger(base) ? base : item.quantity) + delta));
        setQuantity(String(next));
        setLocalError('');
        if (next !== item.quantity) updateQuantity(item.id, next);
    };
    return <div className="flex gap-3 border-b border-gray-100 py-4 last:border-b-0" aria-busy={isProcessing}>
        <Link href={`/products/${item.id}`} className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-50" aria-label={item.name}>
            {item.image ? <img src={item.image} alt="" className="h-full w-full object-contain" /> : <ShoppingBagIcon size={24} className="text-gray-400" />}
        </Link>
        <div className="min-w-0 flex-1 space-y-2">
            <Link href={`/products/${item.id}`} className="block text-sm font-medium text-gray-900 hover:underline">{item.name}</Link>
            <p className="text-xs text-gray-600">{item.lego_number ? `#${item.lego_number}` : `Product ${item.id}`}{item.color ? ` · ${item.color}` : ''}</p>
            <div className="flex flex-wrap justify-between gap-2 text-sm"><span className="text-gray-600"><Price price={Math.round(item.price * 100)} /> per stuk</span><strong><Price price={Math.round(item.price * 100) * item.quantity} /></strong></div>
            <form onSubmit={e => { e.preventDefault(); save(); }} className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <label htmlFor={inputId} className="mb-1 block text-xs text-gray-600">Aantal · {item.stock} beschikbaar</label>
                    <div className="inline-flex overflow-hidden rounded-lg border border-gray-300">
                        <button type="button" className="flex min-h-11 min-w-11 items-center justify-center hover:bg-gray-100 disabled:opacity-40" onClick={() => step(-1)} disabled={item.quantity <= 1 || isProcessing} aria-label={`Minder ${item.name}`}><MinusIcon size={16} /></button>
                        <input id={inputId} type="number" inputMode="numeric" min="1" max={item.stock} step="1" value={quantity} onChange={e => setQuantity(e.target.value)} onBlur={e => { if (!e.relatedTarget || !e.currentTarget.form?.contains(e.relatedTarget)) save(); }} disabled={isProcessing} aria-invalid={Boolean(localError || error)} aria-describedby={localError || error ? `${inputId}-error` : undefined} className="min-h-11 w-16 border-x border-gray-200 text-center text-sm tabular-nums focus:outline-primary" />
                        <button type="button" className="flex min-h-11 min-w-11 items-center justify-center hover:bg-gray-100 disabled:opacity-40" onClick={() => step(1)} disabled={item.quantity >= item.stock || isProcessing} aria-label={`Meer ${item.name}`}><PlusIcon size={16} /></button>
                    </div>
                </div>
                <button type="button" className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-gray-600 hover:bg-red-50 hover:text-red-600 disabled:opacity-40" onClick={() => removeItem(item.id)} disabled={isProcessing} aria-label={`Verwijder ${item.name}`}><TrashIcon size={20} /></button>
            </form>
            {(localError || error) && <p id={`${inputId}-error`} role="alert" className="text-sm text-red-600">{localError || error}</p>}
        </div>
    </div>;
}
