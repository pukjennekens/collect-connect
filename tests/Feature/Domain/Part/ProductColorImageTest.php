<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Part;

use App\Http\Resources\Product\ProductResource;
use App\Models\Color;
use App\Models\Part;
use App\Models\Pivots\PartColor;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ProductColorImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.queue_conversions_by_default' => false]);
    }

    public function test_product_resource_returns_the_bricqer_image_for_its_own_color(): void
    {
        $part = Part::factory()->create();
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);

        $redImage = $this->attachImage($part, $red, 'red.png');
        $blueImage = $this->attachImage($part, $blue, 'blue.png');

        $redProduct = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => $red->id,
        ]);
        $blueProduct = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => $blue->id,
        ]);

        $redProduct->load(['productable.partColors.media', 'color']);
        $blueProduct->load(['productable.partColors.media', 'color']);

        $request = Request::create('/');

        $redData = (new ProductResource($redProduct))->toArray($request);
        $blueData = (new ProductResource($blueProduct))->toArray($request);

        $this->assertNotEmpty($redData['image']);
        $this->assertNotEmpty($blueData['image']);
        $this->assertNotSame($redData['image'], $blueData['image']);
        $this->assertStringContainsString((string) $redImage->id, $redData['image']);
        $this->assertStringContainsString((string) $blueImage->id, $blueData['image']);

        // The single product page serves the large conversion (generated
        // synchronously in tests), not the original.
        $this->assertStringContainsString(PartColor::LARGE_CONVERSION, $redData['image']);
        $this->assertStringStartsWith('/', $redData['image']);
        $this->assertStringNotContainsString('cdn.bricqer.com', $redData['image']);
    }

    public function test_product_resource_does_not_fall_back_to_bricqer_cdn_for_parts(): void
    {
        $part = Part::factory()->create(['bricklink_id' => '3001']);
        $color = Color::factory()->create([
            'name' => 'Red',
            'bricklink_color_id' => '5',
        ]);

        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => $color->id,
        ]);

        $product->load(['productable.partColors.media', 'color']);

        $data = (new ProductResource($product))->toArray(Request::create('/'));

        $this->assertNull($data['image']);
    }

    private function attachImage(Part $part, Color $color, string $fileName): Media
    {
        $pivot = PartColor::factory()->create([
            'part_id' => $part->id,
            'color_id' => $color->id,
        ]);

        return $pivot
            ->addMediaFromString($this->pngBytes())
            ->usingFileName($fileName)
            ->toMediaCollection(PartColor::BRICQER_IMAGE_COLLECTION);
    }

    /**
     * A valid PNG so Spatie can generate conversions during the test.
     */
    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
