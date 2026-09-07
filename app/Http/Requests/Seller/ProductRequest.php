<?php

namespace App\Http\Requests\Seller;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Walidacja produktu (krok podstawowy — bez zdjęć i tagów). Cena podawana
 * brutto; slug liczymy z nazwy. Stan ma znaczenie tylko przy włączonej kontroli
 * stanu (`track_stock`).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    /**
     * Normalizacja przed walidacją: slug z nazwy, cena do kropki dziesiętnej,
     * checkboxy do wartości logicznych.
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'slug' => app(SlugService::class)->make((string) $this->input('name')),
            'price_gross' => str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $this->input('price_gross'))),
            'track_stock' => $this->boolean('track_stock'),
            'withdrawal_excluded' => $this->boolean('withdrawal_excluded'),
            'is_active' => $this->boolean('is_active'),
            'show_on_homepage' => $this->boolean('show_on_homepage'),
        ];

        // Stan może być ułamkowy przy sprzedaży na wagę (2,50 kg) — przecinek na kropkę.
        if ($this->filled('stock')) {
            $merge['stock'] = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $this->input('stock')));
        }

        // Opis to HTML z edytora Trix — sanityzujemy wąską whitelistą przed walidacją/zapisem.
        if ($this->has('description')) {
            $merge['description'] = app(HtmlSanitizer::class)->clean((string) $this->input('description'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:'.config('shop.product_description_max')],
            'price_gross' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'vat_rate' => ['required', Rule::enum(VatRate::class)],
            'sale_unit' => ['required', Rule::enum(SaleUnit::class)],
            'track_stock' => ['boolean'],
            'withdrawal_excluded' => ['boolean'],
            'stock' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'required_if:track_stock,true'],
            'is_active' => ['boolean'],
            'show_on_homepage' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
            // Opis SEO: w tagu i tak przycinamy do ~155 znaków, ale w bazie
            // zostawiamy zapas na roboczy zapis sprzedawcy.
            'meta_description' => ['nullable', 'string', 'max:255'],
            // Zdjęcia dodawane przy TWORZENIU produktu (na edycji galeria działa przez AJAX).
            'images' => ['nullable', 'array', 'max:'.config('shop.product_images.max_per_product')],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.config('shop.product_images.max_upload_kb')],
        ];
    }

    /**
     * Limit wyróżnień na stronie głównej — reguła zależna od stanu sklepu, więc
     * poza tablicą `rules()`. Blokujemy dopiero PRZEKROCZENIE (przy próbie
     * wyróżnienia ponad sufit), a nie odznaczanie. Na edycji pomijamy sam produkt,
     * żeby ponowny zapis już-wyróżnionego nie liczył go podwójnie.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('show_on_homepage')) {
                return;
            }

            $shop = $this->user()?->currentShop();
            if ($shop === null) {
                return;
            }

            $limit = (int) config('shop.homepage_promoted_limit');
            $current = $this->route('product');

            $promoted = $shop->products()
                ->where('show_on_homepage', true)
                ->when($current, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->count();

            if ($promoted >= $limit) {
                $validator->errors()->add(
                    'show_on_homepage',
                    'Na stronie głównej możesz wyróżnić maksymalnie '.$limit.' produktów. Odznacz inny, aby zwolnić miejsce.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa',
            'description' => 'opis',
            'price_gross' => 'cena',
            'vat_rate' => 'stawka VAT',
            'sale_unit' => 'jednostka sprzedaży',
            'stock' => 'stan magazynowy',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_gross.numeric' => 'Podaj cenę liczbą, np. 49,99.',
            'stock.required_if' => 'Podaj stan magazynowy lub wyłącz kontrolę stanu.',
            'images.max' => 'Możesz dodać maksymalnie '.config('shop.product_images.max_per_product').' zdjęć.',
            'images.*.image' => 'Każdy plik musi być obrazem (PNG, JPG lub WebP).',
            'images.*.mimes' => 'Dozwolone formaty zdjęć: PNG, JPG, WebP.',
            'images.*.max' => 'Zdjęcie może mieć maksymalnie '.(int) (config('shop.product_images.max_upload_kb') / 1024).' MB.',
        ];
    }
}
