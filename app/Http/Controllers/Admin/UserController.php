<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FamilyContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'position_key' => $user->position_key,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'search' => $search,
            // key+title одним запросом — тот же порядок и текст, что на
            // сайте и в боте (FamilyContent — общий источник).
            'positions' => array_map(
                fn (array $p) => ['key' => $p['key'], 'title' => $p['title']],
                FamilyContent::positions(),
            ),
        ]);
    }

    /**
     * Викликається сирим axios.put() зі сторінки «Учасники» (не через
     * Inertia-роутер), тому відповідь — завжди чистий JSON, ніколи
     * редірект: back() тут повертав 302, а без заголовка X-Inertia
     * браузер сам повторював PUT на Referer (/admin/users, без id) —
     * той маршрут існує лише під GET, тож виходив паразитний 405 одразу
     * після успішного збереження.
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        try {
            $data = $request->validate([
                'roles' => ['array'],
                'roles.*' => ['string', 'exists:roles,name'],
            ]);

            // Останній admin у системі не можна роздягнути через UI — інакше
            // легко втратити доступ до самої адмінки без ручного втручання в БД.
            if (
                $user->hasRole('admin')
                && ! in_array('admin', $data['roles'] ?? [], true)
                && User::role('admin')->count() <= 1
            ) {
                return response()->json(['errors' => ['roles' => ['Це останній акаунт з роллю "admin" — не можна забрати роль.']]], 422);
            }

            $user->syncRoles($data['roles'] ?? []);

            return response()->json(['status' => 'roles-updated']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Раньше непойманное исключение здесь превращалось в голое
            // "Не вдалося оновити ..." на экране без единой зацепки, что
            // именно пошло не так — ни в браузере, ни на сервере. Теперь
            // хотя бы в логе остаётся класс исключения и сообщение.
            Log::error('admin.users.roles: не вдалося оновити ролі', [
                'target_user_id' => $user->id,
                'actor_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['errors' => ['roles' => ['Сталася помилка на сервері. Спробуйте ще раз.']]], 500);
        }
    }

    /**
     * Посада в родині — окрема від ролей доступу. Призначається вручну:
     * підвищення тут якісне рішення керівництва (див. критерії росту на
     * сайті), а не щось, що можна порахувати автоматично.
     *
     * Валідується проти КЛЮЧІВ (не індексів): ключ — стабільний
     * ідентифікатор посади, який переживає перестановку й видалення
     * рядків у Дизайн → Розділи.
     */
    public function updatePosition(Request $request, User $user): JsonResponse
    {
        try {
            $keys = FamilyContent::positionKeys();

            $data = $request->validate([
                'position_key' => ['nullable', 'string', 'in:'.implode(',', $keys)],
            ]);

            $user->update(['position_key' => $data['position_key'] ?? null]);

            return response()->json(['status' => 'position-updated']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('admin.users.position: не вдалося оновити посаду', [
                'target_user_id' => $user->id,
                'actor_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['errors' => ['position_key' => ['Сталася помилка на сервері. Спробуйте ще раз.']]], 500);
        }
    }
}
