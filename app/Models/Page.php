<?php

namespace App\Models;

use App\Support\Excerpt;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Strona tekstowa sklepu („Informacje") — Regulamin i dowolne strony sprzedawcy.
 * `content` to HTML z edytora Trix. Kanoniczny adres to /informacje/{id}-{slug}
 * (styl PrestaShop — szukamy po ID, slug to ozdoba SEO). `position` daje jedną
 * wspólną kolejność w menu i stopce. `is_system` (Regulamin) nie jest
 * mass-assignable — strona systemowa powstaje wyłącznie przez ShopObserver.
 * `show_on_homepage` wyróżnia stronę kafelkiem na głównej (ta sama nazwa co przy
 * produktach — to samo pojęcie, więc to samo słowo).
 */
#[Fillable([
    'title', 'slug', 'content', 'meta_description', 'position', 'published', 'show_on_homepage',
    'terms_template_version',
])]
class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    /** `system_key` strony z zasadami punktów za zakupy. */
    public const LOYALTY_RULES = 'loyalty_rules';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published' => 'boolean',
            'is_system' => 'boolean',
            'terms_answers' => 'array',
            'show_on_homepage' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** Czy to strona „Zasady punktów" (zakładana przez system, nie przez sprzedawcę). */
    public function isLoyaltyRules(): bool
    {
        return $this->system_key === self::LOYALTY_RULES;
    }

    /**
     * Czy tytuł i widoczność są stałe — Regulamin oraz Zasady punktów. Obie
     * strony sprzedawca może tylko wypełnić treścią i przestawić w kolejności.
     */
    public function isLocked(): bool
    {
        return $this->is_system || $this->isLoyaltyRules();
    }

    /**
     * Czy stronę wolno usunąć. Regulaminu nigdy; Zasad punktów — dopiero gdy
     * sklep punkty wyłączy (decyzja Rafała 07.10).
     */
    public function isDeletable(): bool
    {
        if ($this->is_system) {
            return false;
        }

        return ! ($this->isLoyaltyRules() && $this->shop->loyalty_enabled);
    }

    /**
     * Kanoniczny adres strony na storefroncie: /informacje/{id}-{slug} (styl
     * PrestaShop — szukamy po ID, slug to ozdoba SEO). Względny, bo storefront
     * to jeden host; unika parametru {shop} przy generowaniu URLi.
     */
    public function storefrontPath(): string
    {
        return '/informacje/'.$this->id.'-'.$this->slug;
    }

    /**
     * Tylko opublikowane strony (widoczne na storefroncie), w ustalonej kolejności.
     *
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('published', true)->orderBy('position');
    }

    /**
     * Strony do kafelków na stronie głównej: wyróżnione ORAZ opublikowane, w tej
     * samej kolejności co menu. Szkic z zaznaczonym wyróżnieniem zajmuje slot
     * (patrz PageRequest), ale się nie pokazuje — flaga to zamiar sprzedawcy,
     * `published` to widoczność.
     *
     * Pustej treści NIE odfiltrujemy tutaj — „pusty" to `<div></div>` z Trixa,
     * które dopiero po oczyszczeniu okazuje się puste, a tego SQL nie wie.
     * Robi to `hasContent()` w warstwie wyżej.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeOnHomepage(Builder $query): void
    {
        $query->where('show_on_homepage', true)->where('published', true)->orderBy('position');
    }

    /**
     * Treść strony jako czysty tekst — bliźniak `Shop::aboutPlainText()`.
     */
    public function plainContent(): string
    {
        return Excerpt::plainText($this->content);
    }

    /**
     * Czy strona ma jakąkolwiek treść — bliźniak `Shop::hasAbout()`. Strony bez
     * treści nie blokujemy przy zapisie (sprzedawca sam decyduje, co pisze), ale
     * kafelka jej nie dajemy: pusty kafelek to dziura w siatce, a licznik i tak
     * zejdzie z 3 na 2.
     */
    public function hasContent(): bool
    {
        return $this->plainContent() !== '';
    }

    /**
     * Zajawka do kafelka na stronie głównej — ta sama reguła co dla „O sklepie".
     */
    public function excerpt(): Excerpt
    {
        return Excerpt::fromHtml($this->content, (int) config('pages.excerpt_length'));
    }
}
