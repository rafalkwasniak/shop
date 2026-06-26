<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_is_public(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Regulamin');
    }

    public function test_privacy_page_is_public(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Polityka Prywatności');
    }

    public function test_url_segments_are_polish(): void
    {
        $this->assertSame(url('/regulamin'), route('legal.terms'));
        $this->assertSame(url('/polityka-prywatnosci'), route('legal.privacy'));
    }
}
