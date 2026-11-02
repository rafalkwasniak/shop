<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Zdjęcie w treści stron „Informacje". Plik: `shops/{shop}/pages/{uuid}.webp`
 * — usunięcie sklepu sprząta katalog `shops/{shop}` w ShopEraser, usunięcie
 * rekordu — hook `deleting`.
 */
class PageImage extends Model
{
    protected static function booted(): void
    {
        // Hook nie odpala się przy kaskadzie FK — sklep sprząta ShopEraser.
        static::deleting(fn (PageImage $image) => Storage::disk('public')->delete($image->path()));
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public static function directory(int $shopId): string
    {
        return 'shops/'.$shopId.'/pages';
    }

    public function path(): string
    {
        return self::directory($this->shop_id).'/'.$this->uuid.'.webp';
    }

    /**
     * Adres do wstawienia w treść — WZGLĘDNY (`/storage/…`), nie pełny.
     * Treść stron przeżywa zmianę domeny (przeprowadzka sklepu dedykowanego,
     * subdomena vs centrala w Kramio); pełny adres zostałby w niej na zawsze.
     */
    public function src(): string
    {
        return (string) parse_url(Storage::disk('public')->url($this->path()), PHP_URL_PATH);
    }
}
