<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Baner strony głównej sklepu. Obrazek to komplet wariantów WebP
 * (`shops/{shop}/banners/{uuid}-{szerokość}.webp`) — usunięcie sklepu sprząta
 * katalog `shops/{shop}` w ShopEraser, usunięcie banera — hook `deleting`.
 */
#[Fillable(['alt', 'link_url', 'is_active', 'starts_on', 'ends_on', 'position'])]
class ShopBanner extends Model
{
    protected function casts(): array
    {
        return [
            'image_widths' => 'array',
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Hook nie odpala się przy kaskadzie FK — sklep sprząta ShopEraser.
        static::deleting(function (ShopBanner $banner): void {
            Storage::disk('public')->delete($banner->paths());
        });
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Banery, które DZIŚ mają prawo się pokazać: włączone i w oknie dat
     * (daty włącznie, w strefie aplikacji).
     *
     * @param  Builder<ShopBanner>  $query
     */
    public function scopeShowingToday(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public static function directory(int $shopId): string
    {
        return 'shops/'.$shopId.'/banners';
    }

    public function path(int $width): string
    {
        return self::directory($this->shop_id).'/'.$this->image_uuid.'-'.$width.'.webp';
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_map($this->path(...), $this->widths());
    }

    /** @return list<int> */
    public function widths(): array
    {
        $widths = array_map('intval', (array) $this->image_widths);
        sort($widths);

        return $widths;
    }

    /** Największy wariant — `src` dla przeglądarek bez `srcset` i podgląd w panelu. */
    public function url(): string
    {
        $widths = $this->widths();

        return Storage::disk('public')->url($this->path((int) end($widths)));
    }

    /** Miniatura do listy w panelu — najmniejszy wariant. */
    public function thumbnailUrl(): string
    {
        return Storage::disk('public')->url($this->path($this->widths()[0]));
    }

    public function srcset(): string
    {
        return collect($this->widths())
            ->map(fn (int $w) => Storage::disk('public')->url($this->path($w)).' '.$w.'w')
            ->implode(', ');
    }

    /**
     * Czy link prowadzi POZA sklep — wtedy otwiera się w nowym oknie. Adres
     * względny („/produkty") i pełny adres własnego sklepu to linki wewnętrzne.
     */
    public function linksOutside(Shop $shop): bool
    {
        if (blank($this->link_url) || str_starts_with($this->link_url, '/')) {
            return false;
        }

        $host = mb_strtolower((string) parse_url($this->link_url, PHP_URL_HOST));

        return ! in_array($host, array_filter([mb_strtolower($shop->host()), mb_strtolower((string) $shop->domain)]), true);
    }

    /**
     * Stan do pokazania w panelu: dlaczego baner jest (albo nie jest) widoczny.
     * `$withinLimit` = czy mieści się w limicie pakietu wg kolejności.
     */
    public function statusLabel(bool $withinLimit): string
    {
        $today = now()->startOfDay();

        return match (true) {
            ! $this->is_active => 'Ukryty',
            ! $withinLimit => 'Poza limitem pakietu',
            $this->starts_on !== null && $this->starts_on->gt($today) => 'Od '.$this->starts_on->format('d.m.Y'),
            $this->ends_on !== null && $this->ends_on->lt($today) => 'Zakończony '.$this->ends_on->format('d.m.Y'),
            $this->ends_on !== null => 'Widoczny do '.$this->ends_on->format('d.m.Y'),
            default => 'Widoczny',
        };
    }
}
