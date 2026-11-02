<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\PageImageRequest;
use App\Models\PageImage;
use App\Services\PageImageService;
use Illuminate\Http\JsonResponse;

/**
 * Wgranie zdjęcia do treści strony (przycisk „Wstaw zdjęcie" w edytorze).
 * Odpowiedź JSON — edytor wstawia `<img>` sam, bez przeładowania.
 *
 * Limit z pakietu (`max_page_images`) liczony na CAŁY sklep. Po zejściu na
 * niższy pakiet zdjęcia już wstawione ZOSTAJĄ (wycięcie ich z treści mogłoby
 * rozbić stronę: „jak widać na zdjęciu poniżej" bez zdjęcia) — blokujemy
 * tylko nowe, dopóki liczba nie spadnie pod limit.
 */
class PageImageController extends Controller
{
    public function store(PageImageRequest $request, PageImageService $images): JsonResponse
    {
        $shop = $request->user()->currentShop();
        $limit = max(0, (int) $shop->entitlement('max_page_images'));
        $used = PageImage::query()->where('shop_id', $shop->id)->count();

        if ($used >= $limit) {
            return response()->json([
                'message' => $used > $limit
                    ? 'Masz '.$used.' zdjęć w stronach, a Twój pakiet pozwala na '.$limit.'. Usuń zdjęcia z którejś strony, żeby dodać nowe.'
                    : 'Twój pakiet pozwala na '.$limit.' zdjęć w stronach. Usuń któreś, żeby dodać nowe.',
            ], 422);
        }

        $image = $images->store($request->file('image'), $shop);

        return response()->json([
            'src' => $image->src(),
            'width' => $image->width,
            'height' => $image->height,
            'used' => $used + 1,
            'limit' => $limit,
        ]);
    }
}
