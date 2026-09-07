<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zdjęcia produktu: dodawanie (z optymalizacją), ustawianie głównego i usuwanie.
 * Wszystko scope'owane do produktu sklepu zalogowanego sprzedawcy.
 *
 * Limit zdjęć czytamy z `shop.product_images.max_per_product`, a nie ze stałej:
 * to próg techniczny (pamięć GD przy wgrywaniu paczki), nie uprawnienie pakietu.
 * Sklep dedykowany podnosi go w `.env`. Ta sama wartość rządzi walidacją przy
 * TWORZENIU produktu (ProductRequest) — tu chodzi o galerię na edycji.
 */
class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, ProductImageService $images): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.config('shop.product_images.max_upload_kb')],
        ], [
            'images.required' => 'Wybierz przynajmniej jedno zdjęcie.',
            'images.*.image' => 'Każdy plik musi być obrazem (PNG, JPG lub WebP).',
            'images.*.mimes' => 'Dozwolone formaty: PNG, JPG, WebP.',
            'images.*.max' => 'Zdjęcie może mieć maksymalnie '.(int) (config('shop.product_images.max_upload_kb') / 1024).' MB.',
        ]);

        $files = $request->file('images', []);

        $maxImages = (int) config('shop.product_images.max_per_product');

        if ($product->images()->count() + count($files) > $maxImages) {
            return response()->json(['message' => 'Produkt może mieć maksymalnie '.$maxImages.' zdjęć.'], 422);
        }

        $position = (int) $product->images()->max('position');
        $created = collect($files)->map(function ($file) use ($product, $images, &$position) {
            $image = $product->images()->create([
                'path' => $images->store($file, $product),
                'position' => ++$position,
            ]);

            return [
                'id' => $image->id,
                'url' => $image->url(),
                'deleteUrl' => route('seller.products.images.destroy', [$product, $image]),
            ];
        });

        return response()->json(['images' => $created->all()]);
    }

    public function reorder(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $ownIds = $product->images()->pluck('id')->all();
        $position = 0;
        foreach ($validated['order'] as $id) {
            if (in_array((int) $id, $ownIds, true)) {
                ProductImage::where('id', $id)->where('product_id', $product->id)->update(['position' => $position++]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->authorizeImage($request, $product, $image);

        // Plik z dysku kasuje hook ProductImage::deleting (jedno miejsce sprzątania).
        $image->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->currentShop()?->id, 403);
    }

    private function authorizeImage(Request $request, Product $product, ProductImage $image): void
    {
        $this->authorizeProduct($request, $product);
        abort_unless($image->product_id === $product->id, 404);
    }
}
