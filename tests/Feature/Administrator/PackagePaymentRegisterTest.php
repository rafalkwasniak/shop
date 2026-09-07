<?php

namespace Tests\Feature\Administrator;

use App\Jobs\GeneratePackageInvoice;
use App\Livewire\Administrator\PackagePaymentRecorder;
use App\Models\EmailMessage;
use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use App\Support\PackageAttention;
use App\Support\PackageRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Konsola admina — rejestr opłat i rejestracja wpłaty przyjętej poza bramką.
 *
 * Najważniejsze, czego pilnują te testy: wpłata z ręki wchodzi do TEGO SAMEGO
 * rejestru co bramka (więc liczy się do przychodu) i od razu ustawia sklepowi
 * pakiet z terminem — bez tego zostawałby rozjazd „zapłacił, ale nie ma".
 *
 * Osobno pilnowana jest faktura: Fakturownia nie ma sandboxa, więc domyślne
 * wystawianie dokumentu przy każdej wpisanej wpłacie tworzyłoby realne FV.
 */
class PackagePaymentRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_payments_register(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia']);
        PackagePayment::factory()->for($shop)->create(['amount' => 750]);

        $this->actingAs($admin)
            ->get(route('administrator.packages.payments'))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('750,00 zł')
            ->assertSee('Zarejestruj wpłatę');
    }

    public function test_register_sums_only_paid_payments(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create();
        PackagePayment::factory()->for($shop)->create(['amount' => 750]);
        PackagePayment::factory()->pending()->for($shop)->create(['amount' => 1500]);

        // Kafelek sumy mówi o pieniądzach, więc płatność w toku do niego nie wchodzi,
        // choć sam wiersz jest widoczny na liście niżej.
        $this->actingAs($admin)
            ->get(route('administrator.packages.payments'))
            ->assertOk()
            ->assertSee('Wszystko, co wpłynęło')   // podpis pod kafelkiem sumy
            ->assertSee('750,00 zł')
            ->assertSee('1 500,00 zł')             // wisząca widoczna w tabeli...
            ->assertSee('w toku')                  // ...ale oznaczona jako niezakończona
            ->assertSee('Filtry');
    }

    public function test_register_can_be_filtered_by_package(): void
    {
        $admin = User::factory()->admin()->create();
        $booth = Shop::factory()->package('booth')->create(['name' => 'Sklep Stragan']);
        $pavilion = Shop::factory()->package('pavilion')->create(['name' => 'Sklep Pawilon']);
        PackagePayment::factory()->for($booth)->create(['target_package' => 'booth']);
        PackagePayment::factory()->for($pavilion)->create(['target_package' => 'pavilion']);

        $this->actingAs($admin)
            ->get(route('administrator.packages.payments', ['pakiet' => 'pavilion']))
            ->assertOk()
            ->assertSee('Sklep Pawilon')
            ->assertDontSee('Sklep Stragan');
    }

    public function test_seller_cannot_view_register(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.packages.payments'))
            ->assertForbidden();
    }

    public function test_manual_payment_is_recorded_and_applies_the_package(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '700')
            ->set('method', PackagePayment::METHOD_TRANSFER)
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $payment = PackagePayment::firstOrFail();

        $this->assertSame('700.00', $payment->amount);
        $this->assertSame(PackagePayment::STATUS_PAID, $payment->status);
        $this->assertSame(PackagePayment::METHOD_TRANSFER, $payment->method);
        $this->assertSame($admin->id, $payment->recorded_by);

        // Sedno: pakiet i termin sklepu zmieniają się tak samo jak po wpłacie online.
        $shop->refresh();
        $this->assertSame('booth', $shop->package);
        $this->assertTrue($shop->entitlement('online_payments'));
        $this->assertSame(now()->addYear()->format('Y-m-d'), $shop->subscription_ends_at->format('Y-m-d'));

        // Domyślnie sprzedawca dostaje potwierdzenie — para z testem wyłącznika niżej.
        $this->assertSame(1, EmailMessage::count());
    }

    public function test_manual_payment_counts_towards_revenue(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '700')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(700.0, PackageRevenue::revenue()['total']);
        $this->assertSame(700.0, PackageRevenue::revenue()['year']);
    }

    public function test_invoice_is_not_issued_unless_explicitly_asked(): void
    {
        // Fakturownia nie ma sandboxa — domyślne wystawianie znaczyłoby, że każde
        // wpisanie wpłaty tworzy realny dokument.
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertNotPushed(GeneratePackageInvoice::class);
    }

    public function test_invoice_is_issued_when_asked(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->set('issue_invoice', true)
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(GeneratePackageInvoice::class);
    }

    public function test_confirmation_mail_can_be_switched_off(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->set('notify', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_invoice_number_typed_by_hand_silences_the_attention_list(): void
    {
        // Dokument wystawiony poza systemem istnieje — dopominanie się o niego
        // byłoby fałszywym alarmem, a lista traci sens, gdy świeci bez powodu.
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->set('invoice_number', 'FV 12/2026')
            ->call('save')
            ->assertHasNoErrors();

        $keys = array_map(fn (array $group): string => $group['key'], PackageAttention::groups());

        $this->assertNotContains('uninvoiced', $keys);
    }

    public function test_end_date_in_the_past_is_rejected(): void
    {
        // Termin wstecz COFNĄŁBY abonament działającego sklepu — najgorszy możliwy
        // skutek uboczny wpisania wpłaty.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->format('Y-m-d'))
            ->set('new_ends_at', now()->subDay()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors('new_ends_at');

        $this->assertSame(0, PackagePayment::count());
        $this->assertSame(
            now()->addMonths(6)->format('Y-m-d'),
            $shop->refresh()->subscription_ends_at->format('Y-m-d')
        );
    }

    public function test_future_payment_date_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->set('amount', '750')
            ->set('paid_at', now()->addDay()->format('Y-m-d'))
            ->set('new_ends_at', now()->addYear()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors('paid_at');
    }

    public function test_choosing_a_shop_prefills_amount_and_term(): void
    {
        // Bez podpowiedzi admin liczyłby termin przedłużenia w głowie przy każdej
        // wpłacie — a reguła doklejania roku do starego terminu nie jest oczywista.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addDays(20)]);

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->assertSet('target_package', 'booth')
            ->assertSet('amount', '750')
            ->assertSet('new_ends_at', now()->addDays(20)->addYear()->format('Y-m-d'));
    }

    public function test_admin_can_open_the_recording_form(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.packages.payments.create'))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Wystaw fakturę w Fakturowni')
            ->assertSee('Co się zmieni')
            ->assertSee('Jak to działa');
    }

    public function test_summary_shows_what_the_save_will_change(): void
    {
        // Formularz mówi, CO wpisuję; ta kolumna — CO Z TEGO WYNIKNIE. Przy zejściu
        // na tańszy pakiet to jedyne miejsce, które ostrzega, że funkcje droższego
        // zostaną wyłączone — a tego nie da się cofnąć jednym kliknięciem.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('pavilion')->create([
            'name' => 'Kwiaciarnia Zosia',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->assertSee('Wybierz sklep i pakiet')
            ->set('shop_id', (string) $shop->id)
            ->set('target_package', 'booth')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Zejście na tańszy pakiet')
            ->assertSee(now()->addDays(10)->format('d.m.Y'));
    }

    public function test_summary_marks_a_renewal_instead_of_faking_a_change(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addDays(10)]);

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->assertSee('przedłużenie')
            ->assertDontSee('Zejście na tańszy pakiet');
    }

    public function test_summary_warns_about_comped_shops(): void
    {
        // Zapis ustawi termin, ale flaga „gratis" zostaje i pakiet i tak nie
        // wygaśnie — bez tego ostrzeżenia wpłata wyglądałaby na coś, czym nie jest.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['comped' => true]);

        Livewire::actingAs($admin)
            ->test(PackagePaymentRecorder::class)
            ->set('shop_id', (string) $shop->id)
            ->assertSee('dostęp gratisowy');
    }

    public function test_seller_cannot_record_a_payment(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.packages.payments.create'))
            ->assertForbidden();
    }

    /**
     * Wpłatę rejestruje się wyłącznie za pakiet Z OFERTY. „Sklep dedykowany" ma
     * cenę zero i nie jest sprzedawany przez platformę — wpłata za niego nie
     * istnieje, a wybór z listy przypisałby sklepowi preset z uprawnieniami bez
     * limitów, bezterminowo.
     */
    public function test_dedicated_preset_cannot_be_paid_for(): void
    {
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(PackagePaymentRecorder::class, ['shop' => $shop])
            ->set('target_package', 'dedicated')
            ->call('save')
            ->assertHasErrors('target_package');

        $this->assertSame('stall', $shop->fresh()->package);
    }
}
