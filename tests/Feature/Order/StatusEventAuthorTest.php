<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\PanelSection;
use App\Livewire\Seller\OrderStatusManager;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use App\Services\OrderStatusChanger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Autor zmiany statusu (plan-shop-employees, krok 6).
 *
 * Dopóki panel miał jednego użytkownika, ta kolumna byłaby ozdobą. Sens ma
 * dopiero wtedy, gdy w sklepie pracuje kilka osób i pada pytanie „kto to
 * zrobił" — dlatego testy sprawdzają je z pracownikiem, nie z właścicielem.
 */
class StatusEventAuthorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop, 2: Order}
     */
    private function shopWithEmployee(): array
    {
        $shop = Shop::factory()->sellable()->create();
        $shop->assignPackage('pavilion');
        $shop->save();

        $employment = ShopEmployee::factory()
            ->withSections([PanelSection::Orders])
            ->create(['shop_id' => $shop->getKey()]);

        $order = Order::factory()->create([
            'shop_id' => $shop->getKey(),
            'status' => OrderStatus::New,
        ]);

        return [$employment->user->fresh(), $shop->fresh(), $order];
    }

    public function test_employee_who_changed_the_status_is_recorded(): void
    {
        [$employee, , $order] = $this->shopWithEmployee();

        Livewire::actingAs($employee)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Processing->value);

        $event = $order->fresh()->statusEvents()->latest('id')->firstOrFail();

        $this->assertTrue($employee->is($event->author));
        $this->assertSame(trim($employee->name.' '.$employee->surname), $event->authorLabel());
    }

    /**
     * Zmiana bez zalogowanego człowieka — webhook płatności, cron. `null` to
     * poprawna odpowiedź, nie brak danych, więc oś czasu ma ją NAZWAĆ, a nie
     * zostawić puste miejsce.
     */
    public function test_machine_driven_change_is_labelled_automatic(): void
    {
        [, , $order] = $this->shopWithEmployee();

        app(OrderStatusChanger::class)->change($order, OrderStatus::Processing);

        $event = $order->fresh()->statusEvents()->latest('id')->firstOrFail();

        $this->assertNull($event->author);
        $this->assertSame('Automatycznie', $event->authorLabel());
    }

    public function test_timeline_shows_who_did_it(): void
    {
        [$employee, , $order] = $this->shopWithEmployee();

        Livewire::actingAs($employee)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Processing->value)
            ->assertSee($employee->surname);
    }

    /**
     * Skasowanie konta pracownika nie może wyciąć zdarzeń ze środka osi czasu —
     * zamówienie wyglądałoby wtedy, jakby nigdy nie zmieniło stanu. Wpis
     * zostaje, traci wyłącznie podpis.
     */
    public function test_deleting_the_author_keeps_the_history(): void
    {
        [$employee, , $order] = $this->shopWithEmployee();

        Livewire::actingAs($employee)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Processing->value);

        $employee->delete();

        $event = $order->fresh()->statusEvents()->latest('id')->firstOrFail();

        $this->assertNull($event->fresh()->user_id);
        $this->assertSame('Automatycznie', $event->fresh()->authorLabel());
        $this->assertSame(OrderStatus::Processing, $event->to_status);
    }
}
