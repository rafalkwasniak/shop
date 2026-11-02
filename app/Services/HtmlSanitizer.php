<?php

namespace App\Services;

use App\Models\PageImage;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Facades\Storage;

/**
 * Sanityzacja HTML z edytora Trix przez WŁASNĄ, wąską whitelistę (bez dodatkowej
 * biblioteki — w duchu „lekko" na shared-hoście). Przepuszcza tylko proste
 * formatowanie produkowane przez Trix; wszystko inne (skrypty, style, obce tagi,
 * atrybuty) jest usuwane. Sanityzujemy na ZAPIS (w Form Requeście), więc render
 * `{!! $html !!}` na storefroncie jest bezpieczny.
 */
class HtmlSanitizer
{
    /** Dozwolone tagi → dozwolone atrybuty. Bez cytatu i kodu (świadomie usunięte). */
    private const ALLOWED = [
        'div' => [], 'br' => [], 'strong' => [], 'em' => [], 'del' => [],
        'h1' => [], 'h2' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href'],
    ];

    /** Zamiana tagów na wyjściu: nagłówek z edytora ma być H2 (H1 = tytuł strony). */
    private const RENAME = ['h1' => 'h2'];

    /** Tagi usuwane WRAZ z zawartością. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template'];

    /**
     * Sklep, którego zdjęcia przepuszczamy w `<img>` — tylko na czas
     * `cleanPage()`. Null = obrazków nie ma (opisy produktów, opis sklepu,
     * newslettery: tam `<img>` znika razem z atrybutami).
     */
    private ?int $imageShopId = null;

    /**
     * Treść strony „Informacje" — jak `clean()`, plus zdjęcia wgrane do TEGO
     * sklepu (PageImageService). Obrazek spod innego adresu wypada: nikt nie
     * wstawi grafiki z obcego serwera (śledzenie odwiedzin, podmiana treści).
     *
     * Z ramki Trixa (`<figure>` z atrybutami załącznika) zostaje samo `<img>`;
     * podpis (`<figcaption>`), jeśli niepusty, idzie jako zwykła linia tekstu.
     */
    public function cleanPage(string $html, int $shopId): string
    {
        $this->imageShopId = $shopId;

        try {
            return $this->clean($html);
        } finally {
            $this->imageShopId = null;
        }
    }

    public function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->documentElement;
        $clean = $root ? $this->render($root, true) : '';

        return trim($clean);
    }

    private function render(DOMNode $node, bool $isRoot = false): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $this->render($child);
        }

        if ($this->imageShopId !== null && $tag === 'img') {
            return $this->image($node);
        }

        if ($this->imageShopId !== null && $tag === 'figcaption') {
            return trim(strip_tags($inner)) !== '' ? '<div>'.$inner.'</div>' : '';
        }

        // Korzeń-opakowanie oraz tagi spoza whitelisty: zachowaj treść, usuń tag.
        if ($isRoot || ! array_key_exists($tag, self::ALLOWED)) {
            return $inner;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'a') {
            $href = $node->getAttribute('href');

            return $this->isSafeHref($href)
                ? '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="nofollow noopener" target="_blank">'.$inner.'</a>'
                : $inner; // niebezpieczny/pusty link → zostaw sam tekst
        }

        $outputTag = self::RENAME[$tag] ?? $tag;

        return '<'.$outputTag.'>'.$inner.'</'.$outputTag.'>';
    }

    /**
     * `<img>` tylko z pliku zdjęć stron TEGO sklepu. Wychodzi ścieżka WZGLĘDNA
     * (host z atrybutu wyrzucamy — nawet obcy host z naszą ścieżką daje nasz
     * plik), wymiary tylko liczbowe, `loading="lazy"`.
     */
    private function image(DOMElement $node): string
    {
        $path = (string) parse_url(trim($node->getAttribute('src')), PHP_URL_PATH);
        $prefix = (string) parse_url(Storage::disk('public')->url(PageImage::directory((int) $this->imageShopId)), PHP_URL_PATH);

        if (preg_match('~^'.preg_quote($prefix, '~').'/[0-9a-f-]{36}\.webp$~i', $path) !== 1) {
            return '';
        }

        $attributes = 'src="'.htmlspecialchars($path, ENT_QUOTES, 'UTF-8').'"'
            .' alt="'.htmlspecialchars($node->getAttribute('alt'), ENT_QUOTES, 'UTF-8').'"';

        foreach (['width', 'height'] as $dimension) {
            $value = $node->getAttribute($dimension);
            if (ctype_digit($value) && (int) $value > 0 && (int) $value <= 5000) {
                $attributes .= ' '.$dimension.'="'.$value.'"';
            }
        }

        return '<img '.$attributes.' loading="lazy">';
    }

    private function isSafeHref(string $href): bool
    {
        $scheme = strtolower((string) parse_url(trim($href), PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
