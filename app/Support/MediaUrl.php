<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Minifig;
use App\Models\Part;
use App\Models\Pivots\PartColor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Build browser-safe media URLs that do not depend on APP_URL being correct
 * (important when the app is served behind Docker, Traefik, or a public host
 * that differs from config('app.url')).
 */
final class MediaUrl
{
    /**
     * Conversions to try, largest first, when nothing more specific is asked for.
     *
     * @var list<string>
     */
    private const PART_CONVERSIONS = [
        PartColor::LARGE_CONVERSION,
        PartColor::MEDIUM_CONVERSION,
        PartColor::THUMB_CONVERSION,
    ];

    /**
     * @var list<string>
     */
    private const MINIFIG_CONVERSIONS = [
        Minifig::LARGE_CONVERSION,
        Minifig::MEDIUM_CONVERSION,
        Minifig::THUMB_CONVERSION,
    ];

    /**
     * @param  list<string>  $conversions  Preferred conversion names, in order.
     */
    public static function fromMedia(?Media $media, array $conversions = []): ?string
    {
        if ($media === null) {
            return null;
        }

        $url = $conversions === []
            ? $media->getUrl()
            : $media->getAvailableUrl($conversions);

        return self::toRelative($url);
    }

    /**
     * Shop images come only from the Spatie media library. Bricqer CDN URLs
     * are for import jobs only — never hotlinked to the storefront.
     *
     * @param  list<string>  $conversions  Preferred conversion names, in order.
     */
    public static function forPart(Part $part, int $colorId, array $conversions = self::PART_CONVERSIONS): ?string
    {
        $partColor = $part->partColors->firstWhere('color_id', $colorId);

        return self::fromMedia(
            $partColor?->getFirstMedia(PartColor::BRICQER_IMAGE_COLLECTION),
            $conversions,
        );
    }

    /**
     * Thumbnail for listings that represent a whole part (search cards): use
     * the exact color image when it exists, otherwise any other color of the
     * same part, so a single color without media never blanks out the card.
     *
     * @param  list<string>  $conversions  Preferred conversion names, in order.
     */
    public static function forPartAnyColor(Part $part, int $colorId, array $conversions = self::PART_CONVERSIONS): ?string
    {
        $partColors = $part->partColors
            ->sortByDesc(fn (PartColor $partColor): bool => $partColor->color_id === $colorId);

        foreach ($partColors as $partColor) {
            $url = self::fromMedia(
                $partColor->getFirstMedia(PartColor::BRICQER_IMAGE_COLLECTION),
                $conversions,
            );

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $conversions  Preferred conversion names, in order.
     */
    public static function forMinifig(Minifig $minifig, array $conversions = self::MINIFIG_CONVERSIONS): ?string
    {
        return self::fromMedia(
            $minifig->getFirstMedia(Minifig::BRICQER_IMAGE_COLLECTION),
            $conversions,
        );
    }

    public static function toRelative(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }
}
