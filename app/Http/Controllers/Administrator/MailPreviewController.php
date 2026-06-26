<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Support\MailBranding;
use Illuminate\Contracts\Support\Renderable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Podgląd szablonów maili w panelu admina. Renderuje ten sam komponent, którego
 * użyją realne maile — żeby „jak widzę, tak wyśle”. Docelowo dojdą tu maile
 * zamówień, potwierdzeń itd. (rejestr `templates()`).
 */
class MailPreviewController extends Controller
{
    public function show(string $template): Renderable
    {
        $templates = $this->templates();

        if (! isset($templates[$template])) {
            throw new NotFoundHttpException("Brak szablonu maila: {$template}");
        }

        return view('mail.preview', [
            'brand' => MailBranding::system(),
            'data' => $templates[$template],
        ]);
    }

    /**
     * Rejestr przykładowych danych do podglądu. Realne maile będą wkładać tu
     * własny „wsad” przez ten sam komponent x-mail.message.
     *
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            'aktywacja' => [
                'preheader' => 'Dokończ zakładanie konta — ustaw hasło.',
                'heading' => 'Witaj w '.config('app.name').'!',
                'greeting' => 'Cześć Anno,',
                'lines' => [
                    'Dziękujemy za rejestrację. Cieszymy się, że chcesz prowadzić swój sklep właśnie u nas.',
                    'Aby dokończyć zakładanie konta, ustaw hasło i uzupełnij dane — zajmie to chwilę.',
                ],
                'actionText' => 'Dokończ rejestrację',
                'actionUrl' => 'https://shop.kwasniak.org/aktywacja/przyklad-token',
                'outroLines' => [
                    'Link jest ważny przez 24 godziny.',
                ],
            ],
        ];
    }
}
