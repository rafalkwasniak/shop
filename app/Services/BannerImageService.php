<?php

namespace App\Services;

use App\Models\ShopBanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Obrazek banera → komplet wariantów WebP o szerokościach z
 * `shop.banners.widths` (do `srcset`). Ta sama technika co zdjęcia produktów
 * (GD, przezroczystość zachowana), tylko kilka szerokości zamiast jednej:
 * baner idzie na całą szerokość ekranu, więc telefon nie powinien ciągnąć
 * pliku przygotowanego pod monitor 1920 px.
 *
 * Wariantów SZERSZYCH niż oryginał nie robimy — powiększanie tylko rozmywa.
 * Obrazek węższy niż największy wariant dostaje dodatkowo plik w swoim rozmiarze.
 */
class BannerImageService
{
    /**
     * @return array{image_uuid: string, image_widths: list<int>, image_width: int, image_height: int}
     */
    public function store(UploadedFile $file, int $shopId): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Nie udało się odczytać obrazu.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $uuid = Str::uuid()->toString();

        $configured = array_map('intval', (array) config('shop.banners.widths'));
        $widths = array_values(array_filter($configured, fn (int $w) => $w <= $width));

        // Oryginał węższy niż największy wariant dostaje też wariant w swoim
        // rozmiarze — inaczej obrazek 1000 px skończyłby jako 640 px.
        if ($width < max($configured) && ! in_array($width, $widths, true)) {
            $widths[] = $width;
        }

        foreach ($widths as $target) {
            $targetHeight = max(1, (int) round($height * $target / $width));
            Storage::disk('public')->put(
                ShopBanner::directory($shopId).'/'.$uuid.'-'.$target.'.webp',
                $this->webp($source, $width, $height, $target, $targetHeight),
            );
        }

        imagedestroy($source);

        $largest = max($widths);

        return [
            'image_uuid' => $uuid,
            'image_widths' => $widths,
            'image_width' => $largest,
            'image_height' => max(1, (int) round($height * $largest / $width)),
        ];
    }

    private function webp(\GdImage $source, int $width, int $height, int $targetWidth, int $targetHeight): string
    {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, (int) config('shop.banners.quality', 82));
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $binary;
    }
}
