<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Support\DesignSettings;
use Illuminate\Http\JsonResponse;

/**
 * Ті самі дві каруселі, що на головній сторінці сайту (routes/web.php):
 * аватарки учасників, які самі завантажили фото в профілі, і галерея
 * знімків подій, яку веде адмін. Обидва масиви порожні, поки нема
 * реального контенту чи вимкнено в Дизайні.
 */
class GalleryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'memberPhotos' => DesignSettings::showMemberCarousel()
                ? User::query()
                    ->whereNotNull('avatar_path')
                    ->where('avatar_approved', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $u) => ['name' => $u->name, 'position' => $u->position_title, 'url' => $u->avatar_url])
                    ->filter(fn (array $p) => $p['url'] !== null)
                    ->values()
                : [],
            'galleryPhotos' => DesignSettings::showGalleryCarousel()
                ? GalleryPhoto::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (GalleryPhoto $p) => ['url' => $p->url(), 'caption' => $p->caption])
                    ->filter(fn (array $p) => $p['url'] !== null)
                    ->values()
                : [],
        ]);
    }
}
