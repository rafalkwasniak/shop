<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Optymalizacja i zapis zdjęć produktu. Skaluje dłuższy bok do `shop.product_images.max_side`
 * px i ponownie koduje przez GD jako WebP — niezależnie od formatu wejściowego (jpg/png/webp).
 * WebP daje mniejsze pliki niż JPEG/PNG przy tej samej jakości i obsługuje przezroczystość, więc
 * trzymamy jeden format. Ponowne kodowanie usuwa metadane (EXIF). Oryginały nie są przechowywane.
 * Zapis na dysku `public` w katalogu produktu.
 */
class ProductImageService
{
    /**
     * Zapisuje zoptymalizowane zdjęcie (WebP) i zwraca jego ścieżkę na dysku `public`.
     */
    public function store(UploadedFile $file, Product $product): string
    {
        $binary = $this->optimize($file);
        $path = 'products/'.$product->id.'/'.Str::uuid()->toString().'.webp';

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Kopiuje plik już zapisanego zdjęcia do katalogu innego produktu i zwraca
     * nową ścieżkę (`null`, gdy pliku nie ma na dysku — brakujące zdjęcie nie
     * może przerwać klonowania produktu).
     *
     * Kopia produktu nie może DZIELIĆ plików z oryginałem: usunięcie zdjęcia
     * w kopii zabrałoby je wtedy z karty źródłowej. Obrazu nie przetwarzamy
     * ponownie — jest już zoptymalizowany, a każde kolejne kodowanie WebP
     * odbierałoby mu jakość bez żadnego zysku.
     */
    public function copy(string $path, Product $product): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'webp';
        $copy = 'products/'.$product->id.'/'.Str::uuid()->toString().'.'.$extension;

        $disk->copy($path, $copy);

        return $copy;
    }

    /**
     * Skaluje i koduje obraz do WebP, zwraca binarną zawartość pliku.
     */
    private function optimize(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Nie udało się odczytać obrazu.');
        }

        $maxSide = (int) config('shop.product_images.max_side', 1600);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $quality = (int) config('shop.product_images.quality', 82);

        ob_start();
        imagewebp($canvas, null, $quality);
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $binary;
    }
}
