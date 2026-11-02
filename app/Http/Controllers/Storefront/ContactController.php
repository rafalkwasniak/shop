<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ContactMessageRequest;
use App\Models\Shop;
use App\Services\ContactFormGuard;
use App\Services\ContactFormMailer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Zakładka „Kontakt" storefrontu: dane firmy + formularz. Strona istnieje tylko,
 * gdy sprzedawca ją włączył (Shop::contactFormEnabled) — inaczej 404.
 */
class ContactController extends Controller
{
    private const SENT = 'Dziękujemy! Wiadomość trafiła do sklepu — odpowiedź przyjdzie na podany adres e-mail.';

    public function show(Request $request, ContactFormGuard $guard): View
    {
        $shop = $this->shop($request);
        $customer = auth('customer')->user();

        return view('storefront.contact', [
            'shop' => $shop,
            'timestamp' => $guard->timestamp(),
            'prefillName' => old('name', $customer ? trim($customer->name.' '.$customer->surname) : ''),
            'prefillEmail' => old('email', $customer?->email ?? ''),
        ]);
    }

    public function store(ContactMessageRequest $request, ContactFormGuard $guard, ContactFormMailer $mailer): RedirectResponse
    {
        $shop = $this->shop($request);

        // Robot dostaje to samo co człowiek — bez sygnału, że wpadł.
        if ($guard->looksLikeBot($request)) {
            return redirect()->to('/kontakt')->with('status', self::SENT);
        }

        if ($guard->ipLimited($request) || $guard->shopLimited($shop)) {
            return redirect()
                ->to('/kontakt')
                ->withInput()
                ->with('error', 'Formularz chwilowo nie przyjmuje kolejnych wiadomości. Spróbuj za jakiś czas albo napisz bezpośrednio na '.$shop->contactFormRecipient().'.');
        }

        $data = $request->validated();
        $mailer->send($shop, $data['name'], $data['email'], $data['message']);
        $guard->hit($request, $shop);

        return redirect()->to('/kontakt')->with('status', self::SENT);
    }

    /**
     * Sklep z subdomeny — tylko z włączonym formularzem i widoczny (sklep-szkic
     * pokazujemy wyłącznie właścicielowi i administratorowi, jak resztę stron).
     */
    private function shop(Request $request): Shop
    {
        $shop = $request->attributes->get('shop');

        abort_unless($shop->contactFormEnabled(), 404);
        abort_if(! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user()), 404);

        return $shop;
    }
}
