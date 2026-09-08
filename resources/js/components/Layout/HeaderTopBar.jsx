import { Link } from '@inertiajs/react';
import Container from '../Container.jsx';

export default function HeaderTopBar() {
    return <div className="bg-primary py-2 text-sm text-white">
        <Container className="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 sm:justify-between">
            <p>LEGO onderdelen en minifiguren</p>
            <Link href="/pages/contact" className="underline underline-offset-4">Contact</Link>
        </Container>
    </div>;
}
