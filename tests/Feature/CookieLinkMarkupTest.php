<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * „Ciasteczka" (`x-cookie-settings-link`) to formularz. Formularz w akapicie
 * <p> jest niepoprawnym HTML-em: przeglądarka zamyka akapit przed nim, więc
 * sąsiednie linki wypadają z wyśrodkowania. Pilnujemy, żeby nikt go tam nie
 * wstawił z powrotem — w żadnym widoku.
 */
class CookieLinkMarkupTest extends TestCase
{
    public function test_cookie_settings_form_is_never_inside_a_paragraph(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/<p\b[^>]*>(?:(?!<\/p>).)*<x-cookie-settings-link/s',
                $view->getContents(),
                'Formularz ciasteczek w <p> w '.$view->getRelativePathname().' — użyj <div>.',
            );
        }
    }

    public function test_login_page_renders_the_links_in_a_centred_div(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<div class="mt-10 text-center text-sm text-stone-500">', false);
    }
}
