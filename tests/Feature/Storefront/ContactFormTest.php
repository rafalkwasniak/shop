<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\ContactFormGuard;
use App\Support\MailMarkup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zakładka „Kontakt" storefrontu: dane firmy + formularz, który wysyła mail
 * do sklepu przez outbox.
 *
 * Najwięcej tu o ochronie przed spamem — i o tym, KOMU co odpowiadamy: robot
 * (pułapka, pośpiech, linki) dostaje ciche „wysłano", człowiek, który trafił
 * na limit, dostaje komunikat i adres, pod którym dotrze do sklepu inaczej.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function shop(array $attributes = []): Shop
    {
        return Shop::factory()->active()->create(array_merge([
            'contact_form_enabled' => true,
            'contact_email' => 'sklep@example.com',
            'company_name' => 'Palarnia Testowa sp. z o.o.',
            'street' => 'Kawowa',
            'building_number' => '5',
            'postal_code' => '43-100',
            'city' => 'Tychy',
        ], $attributes));
    }

    /**
     * Formularz wyświetlony „dawno" — człowiek miał czas napisać pytanie.
     *
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        $timestamp = app(ContactFormGuard::class)->timestamp();
        $this->travel(10)->seconds();

        return array_merge([
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'message' => 'Dzień dobry, czy kawa Pallada jest dostępna w ziarnach 250 g?',
            ContactFormGuard::TIMESTAMP => $timestamp,
            ContactFormGuard::HONEYPOT => '',
        ], $overrides);
    }

    private function send(Shop $shop, array $payload)
    {
        return $this->from($this->host($shop).'/kontakt')->post($this->host($shop).'/kontakt', $payload);
    }

    // --- Strona i menu ------------------------------------------------------

    public function test_page_shows_company_data_and_the_form(): void
    {
        $shop = $this->shop();

        $this->get($this->host($shop).'/kontakt')
            ->assertOk()
            ->assertSee('Palarnia Testowa sp. z o.o.')
            ->assertSee('Kawowa 5')
            ->assertSee('sklep@example.com')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false)
            ->assertSee('name="message"', false);
    }

    public function test_disabled_form_means_no_page_and_no_menu_item(): void
    {
        $shop = $this->shop(['contact_form_enabled' => false]);

        $this->get($this->host($shop).'/kontakt')->assertNotFound();
        $this->get($this->host($shop).'/')->assertOk()->assertDontSee('href="/kontakt"', false);
    }

    public function test_enabled_form_appears_in_the_menu(): void
    {
        $shop = $this->shop();

        $this->get($this->host($shop).'/')->assertOk()->assertSee('href="/kontakt"', false);
    }

    public function test_draft_shop_hides_the_page_from_guests(): void
    {
        $shop = Shop::factory()->create(['contact_form_enabled' => true, 'contact_email' => 'sklep@example.com']);

        $this->get($this->host($shop).'/kontakt')->assertNotFound();
    }

    public function test_logged_in_customer_gets_name_and_email_prefilled(): void
    {
        $shop = $this->shop();
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Jan',
            'surname' => 'Kowalski',
            'email' => 'jan@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/kontakt')
            ->assertOk()
            ->assertSee('value="Jan Kowalski"', false)
            ->assertSee('value="jan@example.com"', false);
    }

    // --- Wysyłka ------------------------------------------------------------

    public function test_message_goes_to_the_shop_with_reply_to_the_sender(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload())
            ->assertRedirect('/kontakt')
            ->assertSessionHas('status');

        $mail = EmailMessage::query()->sole();
        $this->assertSame('sklep@example.com', $mail->to_email);
        $this->assertSame('anna@example.com', $mail->reply_to);
        $this->assertSame($shop->name, $mail->from_name);
        $this->assertSame($shop->id, $mail->shop_id);
        $this->assertStringContainsString('Anna', $mail->subject);
        $this->assertStringContainsString('Pallada', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_without_contact_email_the_message_goes_to_the_owner(): void
    {
        $owner = User::factory()->create(['email' => 'wlasciciel@example.com']);
        $shop = $this->shop(['owner_id' => $owner->id, 'contact_email' => null]);

        $this->send($shop, $this->payload());

        $this->assertSame('wlasciciel@example.com', EmailMessage::query()->sole()->to_email);
    }

    /**
     * Nadawca nie dostaje nic. Formularz wysyłający mail na adres wpisany
     * w polu byłby maszynką do rozsyłania spamu w imieniu sklepu.
     */
    public function test_nothing_is_ever_sent_to_the_address_typed_in(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload(['email' => 'ofiara@example.com']));

        $this->assertSame(0, EmailMessage::query()->where('to_email', 'ofiara@example.com')->count());
    }

    /**
     * Treść pisze osoba z zewnątrz: `[podpis](adres)` nie może zamienić się
     * w mailu do sprzedawcy w klikalny link z dowolnym podpisem.
     */
    public function test_markup_in_the_message_does_not_become_a_link(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload(['message' => 'Sprawdź [Twoje zamówienie](https://obcy.example) i **pilnie** odpisz.']));

        $html = collect(EmailMessage::query()->sole()->intro_lines)
            ->flatten()
            ->map(fn (string $line) => MailMarkup::inline($line))
            ->implode("\n");

        $this->assertStringNotContainsString('<a href="https://obcy.example"', $html);
        $this->assertStringNotContainsString('<strong>pilnie</strong>', $html);
    }

    public function test_missing_fields_are_reported(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload(['name' => '', 'email' => 'nie-mail', 'message' => 'krótko']))
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, EmailMessage::query()->count());
    }

    // --- Roboty: ciche odrzucenie -------------------------------------------

    public function test_filled_honeypot_is_silently_dropped(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload([ContactFormGuard::HONEYPOT => 'https://spam.example']))
            ->assertRedirect('/kontakt')
            ->assertSessionHas('status');

        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_sending_right_after_display_is_silently_dropped(): void
    {
        $shop = $this->shop();

        // Znacznik świeży, wystawiony PO przesunięciu zegara w payload().
        $payload = $this->payload();
        $payload[ContactFormGuard::TIMESTAMP] = app(ContactFormGuard::class)->timestamp();

        $this->send($shop, $payload)->assertSessionHas('status');

        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_forged_or_missing_timestamp_is_silently_dropped(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload([ContactFormGuard::TIMESTAMP => '1700000000']))->assertSessionHas('status');
        $this->send($shop, $this->payload([ContactFormGuard::TIMESTAMP => '']))->assertSessionHas('status');

        $this->assertSame(0, EmailMessage::query()->count());
    }

    public function test_more_than_three_links_is_spam_but_three_is_fine(): void
    {
        $shop = $this->shop();

        $this->send($shop, $this->payload(['message' => 'Linki: https://a.pl https://b.pl www.c.pl https://d.pl']));
        $this->assertSame(0, EmailMessage::query()->count());

        $this->send($shop, $this->payload(['message' => 'Porównuję: https://a.pl https://b.pl www.c.pl — który lepszy?']));
        $this->assertSame(1, EmailMessage::query()->count());
    }

    // --- Limity: komunikat dla człowieka --------------------------------------

    public function test_per_ip_limit_tells_the_human_where_else_to_write(): void
    {
        $shop = $this->shop();

        foreach (range(1, 3) as $i) {
            $this->send($shop, $this->payload())->assertSessionHas('status');
        }

        $this->send($shop, $this->payload())
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'sklep@example.com'))
            ->assertSessionHasInput('message');

        $this->assertSame(3, EmailMessage::query()->count());
    }

    public function test_daily_shop_cap_stops_a_flood_from_many_addresses(): void
    {
        config()->set('security.contact_form.per_shop_daily', 2);
        $shop = $this->shop();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->send($shop, $this->payload());
        }

        $this->assertSame(2, EmailMessage::query()->count());
    }

    /** Odrzucony robot nie zjada limitu ludziom z tego samego adresu. */
    public function test_rejected_bots_do_not_use_up_the_limit(): void
    {
        $shop = $this->shop();

        foreach (range(1, 5) as $i) {
            $this->send($shop, $this->payload([ContactFormGuard::HONEYPOT => 'spam']));
        }

        $this->send($shop, $this->payload())->assertSessionHas('status');
        $this->assertSame(1, EmailMessage::query()->count());
    }

    // --- Panel, polityka, mapa strony ----------------------------------------

    public function test_seller_can_turn_the_form_on_in_settings(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id, 'contact_form_enabled' => false]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23', 'contact_form_enabled' => '1'])
            ->assertRedirect(route('seller.settings.edit'));

        $this->assertTrue($shop->fresh()->contact_form_enabled);
    }

    public function test_sitemap_lists_the_contact_page_when_enabled(): void
    {
        $shop = $this->shop();

        $this->get($this->host($shop).'/sitemap.xml')->assertOk()->assertSee('/kontakt</loc>', false);
    }
}
