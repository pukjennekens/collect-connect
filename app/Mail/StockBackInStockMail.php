<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockBackInStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Weer op voorraad: '.($this->product->productable->name ?? $this->product->commerce_title ?? 'Product'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Goed nieuws! <strong>'.e($this->product->productable->name ?? $this->product->commerce_title ?? 'Product').'</strong> is weer op voorraad.</p>'
                .'<p><a href="'.e(route('product.show', $this->product)).'">Bekijk product</a></p>',
        );
    }
}
