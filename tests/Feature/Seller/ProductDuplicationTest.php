<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kopia produktu. Katalog rośnie wariantami tego samego towaru, więc kopia ma
 * oszczędzić przepisywania tych samych pól, ale nie może przenieść niczego, co
 * należy wyłącznie do oryginału: jego plików ani jego historii cen.
 */
class ProductDuplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop, 2: Product}
     */
    private function sellerWithProduct(array $attributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'name' => 'Kubek ceramiczny',
            'price_gross' => 29.00,
            'is_active' => true,
        ], $attributes));

        return [$seller, $shop, $product];
    }

    public function test_copy_carries_fields_and_tags(): void
    {
        [$seller, $shop, $product] = $this->sellerWithProduct([
            'description' => 'Ręcznie robiony, szkliwiony.',
            'withdrawal_excluded' => true,
        ]);

        $product->tags()->attach($shop->tags()->create(['name' => 'ceramika', 'slug' => 'ceramika'])->id);

        $this->actingAs($seller)
            ->post(route('seller.products.duplicate', $product))
            ->assertRedirect();

        $copy = Product::query()->where('id', '!=', $product->id)->sole();

        $this->assertSame('Kubek ceramiczny (kopia)', $copy->name);
        $this->assertSame('kubek-ceramiczny-kopia', $copy->slug);
        $this->assertSame('Ręcznie robiony, szkliwiony.', $copy->description);
        $this->assertSame('29.00', $copy->price_gross);
        $this->assertTrue($copy->withdrawal_excluded);
        $this->assertSame($product->tags->pluck('id')->all(), $copy->tags->pluck('id')->all());
    }

    /**
     * Kopia jest półproduktem: dopóki sprzedawca jej nie dokończy, sklep nie może
     * pokazywać drugi raz tego samego towaru — a już na pewno nie na stronie
     * głównej, gdzie miejsc jest kilka i są limitowane.
     */
    public function test_copy_starts_hidden_and_unpromoted(): void
    {
        [$seller, , $product] = $this->sellerWithProduct(['show_on_homepage' => true]);

        $this->actingAs($seller)->post(route('seller.products.duplicate', $product));

        $copy = Product::query()->where('id', '!=', $product->id)->sole();

        $this->assertFalse($copy->is_active);
        $this->assertFalse($copy->show_on_homepage);
        $this->assertTrue($product->fresh()->is_active);
    }

    /**
     * Wspólny plik sprawiłby, że usunięcie zdjęcia w kopii zabiera je z karty
     * oryginału — czyli jedno kliknięcie w półprodukcie psuje towar, który
     * właśnie się sprzedaje.
     */
    public function test_copy_gets_its_own_image_files(): void
    {
        Storage::fake('public');
        [$seller, , $product] = $this->sellerWithProduct();

        Storage::disk('public')->put('products/'.$product->id.'/oryginal.webp', 'binarka');
        $product->images()->create(['path' => 'products/'.$product->id.'/oryginal.webp', 'position' => 0]);

        $this->actingAs($seller)->post(route('seller.products.duplicate', $product));

        $copy = Product::query()->where('id', '!=', $product->id)->sole();
        $copied = $copy->images()->sole();

        $this->assertNotSame($product->images()->sole()->path, $copied->path);
        $this->assertStringStartsWith('products/'.$copy->id.'/', $copied->path);
        Storage::disk('public')->assertExists($copied->path);
        Storage::disk('public')->assertExists('products/'.$product->id.'/oryginal.webp');
    }

    /**
     * Brakujący plik na dysku nie może wywrócić klonowania — produkt jest
     * ważniejszy niż zdjęcie, którego i tak już nie ma.
     */
    public function test_missing_image_file_does_not_break_the_copy(): void
    {
        Storage::fake('public');
        [$seller, , $product] = $this->sellerWithProduct();

        $product->images()->create(['path' => 'products/'.$product->id.'/znikniete.webp', 'position' => 0]);

        $this->actingAs($seller)
            ->post(route('seller.products.duplicate', $product))
            ->assertRedirect();

        $copy = Product::query()->where('id', '!=', $product->id)->sole();

        $this->assertSame(0, $copy->images()->count());
    }

    /**
     * „Najniższa cena z 30 dni" (Omnibus) mówi o ofercie TEGO produktu. Kopia
     * niczego jeszcze nie oferowała, więc przeniesiona historia obiecywałaby
     * cenę, której nigdy nie było — kopia zaczyna własną, od jednego wpisu
     * zapisanego przy utworzeniu.
     */
    public function test_copy_does_not_inherit_price_history(): void
    {
        [$seller, , $product] = $this->sellerWithProduct();

        $product->update(['price_gross' => 19.00]);
        $product->update(['price_gross' => 39.00]);
        $this->assertGreaterThan(1, $product->priceHistory()->count());

        $this->actingAs($seller)->post(route('seller.products.duplicate', $product));

        $copy = Product::query()->where('id', '!=', $product->id)->sole();

        $this->assertSame(1, $copy->priceHistory()->count());
        $this->assertSame('39.00', $copy->priceHistory()->sole()->price_gross);
    }

    public function test_seller_cannot_copy_another_shops_product(): void
    {
        [$seller] = $this->sellerWithProduct();
        $cudzy = Product::factory()->create(['shop_id' => Shop::factory()->create()->id]);

        $this->actingAs($seller)
            ->post(route('seller.products.duplicate', $cudzy))
            ->assertForbidden();

        $this->assertSame(1, Product::query()->where('shop_id', $cudzy->shop_id)->count());
    }

    /**
     * Kopia to nowy produkt, więc liczy się do limitu pakietu tak samo jak
     * dodany ręcznie — inaczej przycisk „Kopiuj" byłby obejściem limitu, a to
     * on jest w Kramio granicą między pakietami.
     */
    public function test_copy_respects_the_package_product_limit(): void
    {
        [$seller, $shop, $product] = $this->sellerWithProduct();

        $shop->update(['entitlements' => array_merge($shop->entitlements, ['max_products' => 1])]);

        $this->actingAs($seller)
            ->post(route('seller.products.duplicate', $product))
            ->assertRedirect(route('seller.products.index'));

        $this->assertSame(1, $shop->products()->count());
    }

    /**
     * Opis SEO od automatu należy do tekstu, z którego powstał — kopia dostaje
     * własny. Opis napisany ręcznie jest pracą sprzedawcy i jedzie dalej.
     */
    public function test_generated_seo_description_is_not_carried_but_manual_one_is(): void
    {
        [$seller, , $product] = $this->sellerWithProduct([
            'meta_description' => 'Opis od automatu.',
            'meta_description_manual' => false,
        ]);

        $this->actingAs($seller)->post(route('seller.products.duplicate', $product));
        $copy = Product::query()->where('id', '!=', $product->id)->sole();
        $this->assertNull($copy->meta_description);

        $product->update(['meta_description' => 'Napisane ręcznie.', 'meta_description_manual' => true]);

        $this->actingAs($seller)->post(route('seller.products.duplicate', $product));
        $manualCopy = Product::query()->orderByDesc('id')->first();
        $this->assertSame('Napisane ręcznie.', $manualCopy->meta_description);
    }

    /**
     * Kopiowanie pyta, mimo że niczego nie psuje: przycisk stoi tuż obok
     * „Edytuj", a pomyłka kończy się drugim produktem, o którym sprzedawca
     * się nie dowie — kopia jest ukryta, więc sklep tego nie zdradzi.
     */
    public function test_copying_is_confirmed_on_the_tile(): void
    {
        [$seller, , $product] = $this->sellerWithProduct();

        $html = $this->actingAs($seller)->get(route('seller.products.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            '<form method="POST" action="'.route('seller.products.duplicate', $product).'"',
            $html
        );
        $this->assertSame(2, substr_count($html, 'wire:click="ask"'));
        $this->assertStringContainsString('Kopiuj', $html);
        // Podpis przycisku potwierdzenia dociera do komponentu (Livewire zamienia
        // `confirm-label` na `confirmLabel`) — inaczej kopia pytałaby „Tak, usuń".
        $this->assertStringContainsString('Tak, kopiuj', $html);
    }
}
