<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Ai\AiClient;
use App\Support\Excerpt;
use App\Support\Seo;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pisze opis do wyników wyszukiwania (meta description) z treści, którą
 * sprzedawca już ma. Model dostaje gotowy tekst i ma z niego wycisnąć jedno–dwa
 * zdania zachęty — nie wymyśla oferty od zera.
 *
 * DLACZEGO nie samo obcięcie pierwszych 155 znaków: początek opisu bywa
 * powitaniem albo zdaniem o firmie, a nie tym, co przekona do kliknięcia.
 *
 * Wynik jest ZAPISYWANY w bazie (osobny job), nigdy liczony przy żądaniu —
 * wywołanie modelu przy każdej wizycie Googlebota byłoby absurdem kosztowym.
 */
class SeoDescriptionWriter
{
    /** Zadanie z `config('ai.tasks')`. */
    public const TASK = 'seo_description';

    /**
     * Poniżej tylu znaków treści źródłowej nie ma czego streszczać — model
     * wyprodukowałby ogólnik, a deterministyczne zdanie z faktów (nazwa, cena,
     * sklep) jest równie dobre i darmowe.
     */
    public const MIN_SOURCE_CHARS = 120;

    public function __construct(private readonly AiClient $ai) {}

    /**
     * Opis produktu albo null, gdy nie ma z czego pisać.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie
     */
    public function forProduct(Product $product): ?string
    {
        $source = Excerpt::plainText($product->description);

        if (! self::hasEnoughSource($source)) {
            return null;
        }

        return $this->write(
            'Produkt: '.$product->name."\n\nOpis:\n".$source,
            'produktu w sklepie internetowym',
            $product->shop,
        );
    }

    /**
     * Opis strony głównej sklepu albo null, gdy sprzedawca nie opisał sklepu.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie
     */
    public function forShop(Shop $shop): ?string
    {
        $source = $shop->aboutPlainText();

        if (! self::hasEnoughSource($source)) {
            return null;
        }

        return $this->write(
            'Sklep: '.$shop->name."\n\nOpis:\n".$source,
            'sklepu internetowego',
            $shop,
        );
    }

    /**
     * Opis z dowolnego tekstu — dla przycisku „Wygeneruj z AI", gdzie treść
     * przychodzi WPROST Z FORMULARZA, jeszcze niezapisana. Sprzedawca może więc
     * poprosić o opis do tego, co właśnie napisał, bez zapisywania po drodze.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie
     */
    public function fromText(string $source, Shop $shop, string $name = ''): string
    {
        $content = ($name !== '' ? 'Nazwa: '.$name."\n\n" : '')."Opis:\n".$source;

        return $this->write($content, 'strony w sklepie internetowym', $shop);
    }

    /**
     * Czy jest z czego streszczać.
     */
    public static function hasEnoughSource(?string $source): bool
    {
        return mb_strlen(trim((string) $source)) >= self::MIN_SOURCE_CHARS;
    }

    private function write(string $content, string $subject, Shop $shop): string
    {
        $limit = Seo::MAX_DESCRIPTION;

        $system = 'Jesteś specjalistą SEO piszącym po polsku. Na podstawie przesłanej treści '
            .'napisz meta description '.$subject.' — jedno lub dwa zdania zachęcające do '
            ."kliknięcia w wynik wyszukiwania. Maksymalnie {$limit} znaków. Pisz konkretnie i "
            .'rzeczowo, używaj słów, których szukałby klient. Opieraj się WYŁĄCZNIE na faktach '
            .'z przesłanej treści — nie obiecuj darmowej dostawy, promocji, rabatów, gwarancji '
            .'ani terminów wysyłki, jeśli nie ma ich w tekście. Nie zaczynaj od nazwy sklepu, '
            .'nie używaj cudzysłowów, emoji ani Markdown. Odpowiedz wyłącznie samym opisem, '
            .'bez wstępu i komentarzy.';

        return $this->clean($this->ai->run(self::TASK, $system, $content, $shop));
    }

    /**
     * Sprzątanie po modelu: modele bywają gadatliwe i lubią ozdobniki, więc
     * długość i czystość wymuszamy w KODZIE, nie prośbą w prompcie.
     */
    private function clean(string $text): string
    {
        // Cudzysłowy (proste i drukarskie), Markdown i złamania linii.
        $text = str_replace(['"', '„', '”', '**', '*', '#', "\r"], '', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return Str::limit($text, Seo::MAX_DESCRIPTION, '…', preserveWords: true);
    }
}
