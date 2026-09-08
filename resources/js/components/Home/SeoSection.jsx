import Container from '../Container.jsx';

const DEFAULT_BLOCKS = [
    {
        title: 'LEGO® onderdelen kopen',
        body: 'Bij Collect2Connect vind je losse LEGO® steentjes in honderden kleuren en vormen. Zoek op naam, kleur of onderdeelnummer en bestel precies de stenen die je mist voor jouw project.',
    },
    {
        title: 'Minifiguren en accessoires',
        body: 'Vind personages voor jouw collectie. Bekijk per minifiguur de beschikbare informatie, prijs en voorraad.',
    },
    {
        title: 'Verzending van jouw bestelling',
        body: 'De beschikbare verzendmethoden en kosten verschijnen bij het afrekenen, op basis van je adres en bestelling.',
    },
    {
        title: 'Bouw jouw set weer compleet',
        body: 'Zoek jouw LEGO set en ontdek welke bijbehorende onderdelen en minifiguren beschikbaar zijn.',
    },
];

/**
 * @param {Object} props
 * @param {Array<{title: string, body: string}>} [props.blocks]
 */
export default function SeoSection({ blocks = DEFAULT_BLOCKS }) {
    if (blocks.length === 0) {
        return null;
    }

    return (
        <Container className="max-w-7xl my-8">
            <div className="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-x-12">
                {blocks.map((block) => (
                    <section key={block.title} className="min-w-0">
                        <h2 className="mb-2 text-xl font-semibold break-words text-gray-900 sm:text-2xl">
                            {block.title}
                        </h2>
                        <p className="text-base leading-relaxed text-gray-600 sm:text-lg">{block.body}</p>
                    </section>
                ))}
            </div>
        </Container>
    );
}
