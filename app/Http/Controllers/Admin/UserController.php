<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Support\FamilyContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $roleFilter = $request->string('role')->toString();
        $positionFilter = $request->string('position')->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($roleFilter === '__none__', fn ($q) => $q->whereDoesntHave('roles'))
            ->when($roleFilter !== '' && $roleFilter !== '__none__', fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $roleFilter)))
            ->when($positionFilter === '__none__', fn ($q) => $q->whereNull('position_key'))
            ->when($positionFilter !== '' && $positionFilter !== '__none__', fn ($q) => $q->where('position_key', $positionFilter))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'position_key' => $user->position_key,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'positionFilter' => $positionFilter,
            // key+title одним запросом — тот же порядок и текст, что на
            // сайте и в боте (FamilyContent — общий источник).
            'positions' => array_map(
                fn (array $p) => ['key' => $p['key'], 'title' => $p['title']],
                FamilyContent::positions(),
            ),
            'recentAudit' => UserAuditLog::query()
                ->with(['actor:id,name', 'target:id,name'])
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    /** Журнал дій — Log::info/error лишається для діагностики помилок, тут — видима адмінам історія. */
    private function audit(Request $request, User $target, string $action, array $meta = []): void
    {
        UserAuditLog::create([
            'actor_id' => $request->user()?->id,
            'target_user_id' => $target->id,
            'action' => $action,
            'meta' => $meta,
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

            $this->audit($request, $user, 'roles_updated', ['roles' => $data['roles'] ?? []]);

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

            $this->audit($request, $user, 'position_updated', ['position_key' => $data['position_key'] ?? null]);

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

    /**
     * Ім'я/email учасника — базове редагування, окреме від ролей і
     * посади. Той самий побічний ефект, що й у самостійному
     * ProfileController::update: зміна email скидає email_verified_at,
     * інакше акаунт лишався би "підтвердженим" на чужу поштову скриньку.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $data = $request->validate([
                'first_name' => ['required', 'string', 'max:120'],
                'last_name' => ['nullable', 'string', 'max:120'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            ]);

            $user->fill($data);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();

            $this->audit($request, $user, 'profile_updated', ['email' => $user->email]);

            return response()->json(['status' => 'user-updated', 'user' => $user->only(['id', 'name', 'first_name', 'last_name', 'email'])]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('admin.users.update: не вдалося оновити учасника', [
                'target_user_id' => $user->id,
                'actor_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['errors' => ['email' => ['Сталася помилка на сервері. Спробуйте ще раз.']]], 500);
        }
    }

    /**
     * Генерує новий тимчасовий пароль і показує його адміну ОДИН раз у
     * відповіді — сайт нікуди його не зберігає в явному вигляді (лише
     * хеш), тож переказати пароль учаснику (особисто чи в Telegram) —
     * відповідальність адміна, а не автоматична розсилка поштою.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $password = Str::password(12, symbols: false);

        $user->update(['password' => $password]);

        Log::info('admin.users.reset-password: адмін згенерував новий пароль', [
            'target_user_id' => $user->id,
            'actor_id' => $request->user()?->id,
        ]);

        $this->audit($request, $user, 'password_reset');

        return response()->json([
            'ok' => true,
            'message' => 'Новий пароль згенеровано.',
            'data' => ['password' => $password],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    /**
     * Повне видалення чужого акаунта — на відміну від самостійного
     * ProfileController::destroy (де людина підтверджує СВІЙ пароль),
     * тут підтвердження вже на рівні "тиснеш кнопку в адмінці", тому
     * єдиний запобіжник — не дати видалити самого себе цим шляхом і не
     * дати лишити родину без жодного admin-акаунта.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json(['message' => 'Не можна видалити власний акаунт звідси.'], 422);
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return response()->json(['message' => 'Це останній акаунт з роллю "admin" — не можна його видалити.'], 422);
        }

        Log::info('admin.users.destroy: адмін видалив акаунт учасника', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'actor_id' => $request->user()?->id,
        ]);

        // До delete(): після нього target_user_id у щойно створеному
        // записі й так стане null (nullOnDelete), а ім'я/email лишаться
        // видимими лише завдяки meta, записаному зараз, поки акаунт ще є.
        $this->audit($request, $user, 'account_deleted', ['name' => $user->name, 'email' => $user->email]);

        $user->delete();

        return response()->json(['ok' => true, 'message' => 'Акаунт видалено.', 'data' => null, 'errors' => null, 'redirect' => null]);
    }
}
