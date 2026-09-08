<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareDemoContent extends Command
{
    protected $signature = 'demo:prepare-content';

    protected $description = 'Create missing, clearly labelled demo CMS pages and news without overwriting editorial content';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Demo content is unavailable in production.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach ([
                'over-ons' => ['Over ons', 'Collect2Connect brengt LEGO onderdelen en minifiguren samen. Ontdek de collectie, zoek op LEGO-nummer en vind onderdelen voor jouw set.'],
                'contact' => ['Contact', 'Dit is een demonstratiewinkel. Voor vragen over deze demo kun je contact opnemen met degene die je de demo heeft gedeeld. Definitieve bedrijfs- en contactgegevens worden voor de opening toegevoegd.'],
                'verzending' => ['Verzending', 'Beschikbare verzendmethoden en kosten worden bij het afrekenen berekend op basis van je land en de inhoud van je winkelwagen. Er worden in deze demo geen echte bestellingen verzonden. Definitieve leveringsvoorwaarden volgen voor de opening.'],
                'retourneren' => ['Retourneren', 'Dit is demo-inhoud. Er vinden geen echte aankopen of retourzendingen plaats. De definitieve retourprocedure en voorwaarden worden voor de opening gepubliceerd.'],
            ] as $slug => [$title, $text]) {
                Page::query()->firstOrCreate(['slug' => $slug], [
                    'title' => $title,
                    'blocks' => [['type' => 'text', 'data' => ['text' => 'Demo-inhoud — deze winkel gebruikt testbetalingen.']], ['type' => 'text', 'data' => ['text' => $text]]],
                    'is_published' => true,
                    'published_at' => now(),
                ]);
            }

            foreach ([
                'welkom-bij-collect2connect' => ['Welkom bij Collect2Connect', 'Ontdek in deze demo hoe je losse LEGO onderdelen en minifiguren vindt. Zoek op naam of LEGO-nummer, bekijk een set en voeg beschikbare producten toe aan je winkelwagen.'],
                'vind-het-ontbrekende-onderdeel' => ['Vind het ontbrekende onderdeel', 'Gebruik de categorie- en kleurfilters om je selectie te verfijnen. Op de productpagina bekijk je de kleur, prijs en voorraad. Op setpagina’s vind je onderdelen met hun aantallen en eventuele reserveonderdelen.'],
            ] as $slug => [$title, $content]) {
                Article::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'content' => '<p>Demo-artikel</p><p>'.$content.'</p>', 'meta_description' => $content, 'is_published' => true, 'published_at' => now()]);
            }
        });

        $this->info('Missing demo content created; existing content preserved.');

        return self::SUCCESS;
    }
}
