<?php

namespace Tests\Feature\Seller;

use App\Livewire\ConfirmAction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Potwierdzenia w panelu mają jedną postać: pytanie rysowane w kafelku albo
 * w wierszu, z wypisanym skutkiem. Okienko `confirm()` odpada wszędzie tam,
 * gdzie coś znika — umie pokazać jedno zdanie bez formatowania, a skutki bywają
 * różne w zależności od stanu rekordu.
 */
class PanelConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Straż na przyszłość: nowy ekran panelu nie ma prawa wrócić do alertu
     * przeglądarki. Sklep (storefront) jest poza tą regułą świadomie — ma
     * własny motyw i własny język wizualny.
     */
    public function test_no_panel_view_falls_back_to_a_browser_alert(): void
    {
        $views = array_merge(
            File::allFiles(resource_path('views/seller')),
            File::allFiles(resource_path('views/administrator')),
        );

        foreach ($views as $view) {
            $this->assertStringNotContainsString(
                'confirm(',
                $view->getContents(),
                'Alert przeglądarki w '.$view->getRelativePathname().' — użyj <livewire:confirm-action>.'
            );
        }
    }

    /**
     * W wąskim wierszu nakładka nie ma się gdzie zmieścić, więc pytanie staje
     * W MIEJSCU przycisku. Przycisk musi wtedy zniknąć — inaczej wiersz miałby
     * dwa wezwania do tej samej rzeczy.
     */
    public function test_inline_question_replaces_the_button_it_came_from(): void
    {
        Livewire::test(ConfirmAction::class, [
            'action' => '/sprzedawca/informacje/1/usun',
            'title' => 'Usunąć stronę Dostawa?',
            'text' => 'Usuń',
            'inline' => true,
        ])
            ->assertSee('wire:click="ask"', false)
            ->call('ask')
            ->assertSee('Usunąć stronę Dostawa?')
            ->assertDontSee('wire:click="ask"', false)
            ->call('cancel')
            ->assertSee('wire:click="ask"', false);
    }

    /**
     * Nakładka zostaje nakładką: przy kafelku przycisk ma być widoczny obok
     * pytania, bo pytanie i tak przykrywa całą kartę.
     */
    public function test_overlay_keeps_the_button_underneath(): void
    {
        Livewire::test(ConfirmAction::class, ['action' => '/x', 'title' => 'Usunąć?'])
            ->call('ask')
            ->assertSee('absolute inset-0', false);
    }

    public function test_pages_list_asks_in_the_row(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        Page::factory()->create(['shop_id' => $shop->id, 'is_system' => false]);

        $this->actingAs($seller)
            ->get(route('seller.pages.index'))
            ->assertOk()
            ->assertDontSee('confirm(', false)
            ->assertSee('wire:click="ask"', false);
    }

    /**
     * Produkt, który był zamawiany, zostaje ukryty dla historii; nigdy
     * niezamawiany znika razem ze zdjęciami. Potwierdzenie musi mówić, która
     * z tych dwóch rzeczy nastąpi — to jest cały powód tej nakładki.
     */
    public function test_delete_warning_matches_what_deletion_really_does(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $nietkniety = Product::factory()->create(['shop_id' => $shop->id]);
        $zamawiany = Product::factory()->create(['shop_id' => $shop->id]);

        OrderItem::factory()->create([
            'order_id' => Order::factory()->for($shop)->create()->id,
            'product_id' => $zamawiany->id,
        ]);

        $this->assertStringContainsString('zniknie na zawsze', $nietkniety->deletionConsequences()[0]);
        $this->assertStringContainsString('zostanie ukryty', $zamawiany->deletionConsequences()[0]);
    }

    /**
     * Ostrzeżenie zależy od tego, czy produkt był zamawiany — lista musi to
     * wiedzieć bez pytania na każdy kafelek.
     */
    public function test_products_list_loads_the_order_flag_with_the_products(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        Product::factory()->count(3)->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();

        foreach ($response->viewData('products') as $product) {
            $this->assertNotNull($product->getAttribute('order_items_exists'));
        }
    }
}
