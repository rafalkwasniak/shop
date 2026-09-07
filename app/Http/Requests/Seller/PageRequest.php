<?php

namespace App\Http\Requests\Seller;

use App\Models\Page;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Walidacja strony tekstowej („Informacje"). Slug liczymy z tytułu (kanoniczny
 * jest `id`, slug to ozdoba SEO). Treść to HTML z edytora Trix — sanityzujemy.
 * Strona systemowa (Regulamin) ma tytuł zablokowany i zawsze jest opublikowana;
 * pilnuje tego kontroler, tu tylko normalizujemy wspólne pola.
 */
class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    /**
     * Normalizacja przed walidacją: slug z tytułu, checkbox publikacji do bool,
     * treść przez sanitizer HTML.
     */
    protected function prepareForValidation(): void
    {
        // Strona systemowa (Regulamin) ma tytuł stały — pole jest w formularzu
        // zablokowane i nie leci w żądaniu, więc bierzemy tytuł z istniejącej
        // strony (inaczej `title` byłby pusty i walidacja odrzucałaby zapis).
        $page = $this->route('page');
        $title = $page instanceof Page && $page->is_system
            ? $page->title
            : (string) $this->input('title');

        $merge = [
            'title' => $title,
            'slug' => app(SlugService::class)->make($title),
            'published' => $this->boolean('published'),
            'show_on_homepage' => $this->boolean('show_on_homepage'),
        ];

        if ($this->has('content')) {
            $merge['content'] = app(HtmlSanitizer::class)->clean((string) $this->input('content'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string'],
            'content' => ['nullable', 'string', 'max:'.config('pages.content_max')],
            // Wersja wzoru wędruje ukrytym polem i zapisuje się razem z treścią —
            // po poprawkach prawnika po niej poznamy, kto ma starą wersję.
            'terms_template_version' => ['nullable', 'integer', 'min:1', 'max:65535'],
            // Opis SEO strony — wyłącznie ręczny (bez AI, patrz migracja).
            'meta_description' => ['nullable', 'string', 'max:255'],
            'published' => ['boolean'],
            'show_on_homepage' => ['boolean'],
        ];
    }

    /**
     * Limit wyróżnień na stronie głównej — reguła zależna od stanu sklepu, więc
     * poza tablicą `rules()` (bliźniak `ProductRequest::withValidator()`).
     * Blokujemy dopiero PRZEKROCZENIE, a nie odznaczanie. Na edycji pomijamy samą
     * stronę, żeby ponowny zapis już-wyróżnionej nie liczył jej podwójnie.
     *
     * Liczymy FLAGĘ, nie widoczność: strona wyróżniona, ale niepublikowana zajmuje
     * slot. Gdybyśmy liczyli tylko opublikowane, dałoby się obejść sufit —
     * wyróżnić szkice i opublikować je później.
     *
     * Pustej treści tu NIE pilnujemy: co sprzedawca pisze, to jego sprawa, a pusta
     * strona i tak nie dostanie kafelka (Page::hasContent).
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if (! $this->boolean('show_on_homepage')) {
                return;
            }

            $shop = $this->user()?->currentShop();
            if ($shop === null) {
                return;
            }

            $limit = (int) config('pages.homepage_promoted_limit');
            $current = $this->route('page');

            $promoted = $shop->pages()
                ->where('show_on_homepage', true)
                ->when($current, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->count();

            if ($promoted >= $limit) {
                // Bez liczebnika przy rzeczowniku — komunikat ma zostać poprawny
                // po polsku także wtedy, gdy ktoś zmieni sufit w configu na 5.
                $validator->errors()->add(
                    'show_on_homepage',
                    'Limit stron wyróżnionych na stronie głównej to '.$limit.'. Odznacz inną, aby zwolnić miejsce.',
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
            'title' => 'tytuł',
            'content' => 'treść',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Podaj tytuł strony.',
            'content.max' => 'Treść jest za długa — skróć ją nieco.',
        ];
    }
}
