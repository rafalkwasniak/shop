<?php

namespace App\Services;

use App\Models\PageImage;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Zdjęcia w treści stron „Informacje": wgranie (WebP, dłuższy bok do
 * `shop.page_images.max_side`), rozpoznanie, które są w użyciu, i sprzątanie.
 *
 * ŹRÓDŁEM PRAWDY o użyciu jest TREŚĆ stron — zdjęcie wycięte z tekstu albo
 * razem ze stroną przestaje się liczyć do limitu i znika z dysku. Bez tego
 * limit zapychałby się plikami, których nigdzie już nie widać.
 */
class PageImageService
{
    public function store(UploadedFile $file, Shop $shop): PageImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Nie udało się odczytać obrazu.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, (int) config('shop.page_images.max_side') / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, (int) config('shop.page_images.quality'));
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        $image = new PageImage;
        $image->shop_id = $shop->id;
        $image->uuid = Str::uuid()->toString();
        $image->width = $newWidth;
        $image->height = $newHeight;

        Storage::disk('public')->put($image->path(), $binary);
        $image->save();

        return $image;
    }

    /**
     * UUID-y zdjęć TEGO sklepu, do których odwołuje się treść.
     *
     * @return list<string>
     */
    public function referencedUuids(?string $html, int $shopId): array
    {
        $pattern = '~/'.preg_quote(PageImage::directory($shopId), '~').'/([0-9a-f-]{36})\.webp~i';
        preg_match_all($pattern, (string) $html, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /** @return list<string> */
    public function usedUuids(Shop $shop): array
    {
        return $shop->pages()
            ->pluck('content')
            ->flatMap(fn (?string $html) => $this->referencedUuids($html, $shop->id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Po zapisie albo usunięciu strony: zdjęcia, które były w jej starej
     * treści, a nie ma ich już w ŻADNEJ stronie sklepu — kasujemy od razu.
     * Miejsce w limicie zwalnia się w chwili zapisu, a nie po nocy.
     */
    public function releaseRemoved(Shop $shop, ?string $previousHtml): void
    {
        $removed = array_diff($this->referencedUuids($previousHtml, $shop->id), $this->usedUuids($shop));

        if ($removed !== []) {
            PageImage::query()->where('shop_id', $shop->id)->whereIn('uuid', $removed)->get()->each->delete();
        }
    }

    /**
     * Zdjęcia wgrane, ale nigdy niezapisane w treści (edytor zamknięty bez
     * „Zapisz") — po `orphan_hours` znikają. Świeżych nie ruszamy: ktoś może
     * właśnie pisać stronę w drugiej karcie.
     */
    public function prune(): int
    {
        $deleted = 0;
        $cutoff = now()->subHours((int) config('shop.page_images.orphan_hours'));

        PageImage::query()
            ->where('created_at', '<', $cutoff)
            ->with('shop')
            ->get()
            ->groupBy('shop_id')
            ->each(function ($images) use (&$deleted): void {
                $shop = $images->first()->shop;
                $used = $shop ? $this->usedUuids($shop) : [];

                foreach ($images as $image) {
                    if (! in_array(strtolower($image->uuid), $used, true)) {
                        $image->delete();
                        $deleted++;
                    }
                }
            });

        return $deleted;
    }
}
