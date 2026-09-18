<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Setting;
use App\Support\FamilyContent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'telegramReady' => $this->telegramReady(),
            'promptTelegramLink' => $request->boolean('link_telegram'),
            // key+title — той самий порядок і текст, що на сайті й у боті
            // (FamilyContent — спільне джерело для позицій).
            'positions' => array_map(
                fn (array $p) => ['key' => $p['key'], 'title' => $p['title']],
                FamilyContent::positions(),
            ),
        ]);
    }

    /**
     * Блок привязки Telegram показываем, только если бот реально готов
     * принять человека: модуль установлен И в настройках есть токен с
     * юзернеймом. Раньше проверялось лишь наличие маршрута, поэтому на
     * свежем сервере в профиле висела кнопка, которая упиралась в бота
     * без токена.
     */
    private function telegramReady(): bool
    {
        return \Illuminate\Support\Facades\Route::has('telegram.status')
            && trim((string) Setting::get('telegram_bot_token')) !== ''
            && trim((string) Setting::get('telegram_bot_username')) !== '';
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Учасник сам вказує свою посаду в родині — підвищення відбуваються в
     * грі, і раніше про них треба було окремо просити адміна оновити сайт,
     * а це забувалось. Посада лишається декоративною (не дає жодних прав
     * доступу — ті керуються ролями, окремо), тому самостійна зміна тут
     * безпечна; єдиний запобіжник — валідність ключа і лог зміни.
     */
    public function updatePosition(Request $request): RedirectResponse
    {
        $keys = FamilyContent::positionKeys();

        $data = $request->validate([
            'position_key' => ['nullable', 'string', 'in:'.implode(',', $keys)],
        ]);

        $user = $request->user();
        $previous = $user->position_key;

        $user->update(['position_key' => $data['position_key'] ?? null]);

        if ($previous !== $user->position_key) {
            Log::info('profile.position: учасник сам змінив собі посаду', [
                'user_id' => $user->id,
                'from' => $previous,
                'to' => $user->position_key,
            ]);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Дата народження — суто за бажанням: якщо не заповнено, ніхто про це
     * ніде не дізнається. Якщо заповнено, TelegramBot (за наявності) щодня
     * перевіряє місяць+день і сам вітає в сімейному чаті — окремого запиту
     * від адміна на це не потрібно.
     */
    public function updateBirthday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'birth_date' => ['nullable', 'date', 'before:today'],
        ]);

        $request->user()->update(['birth_date' => $data['birth_date'] ?? null]);

        return Redirect::route('profile.edit');
    }

    /**
     * Стать — суто за бажанням, впливає лише на граматику дієслів у
     * системних текстах ("подав"/"подала" тощо). Якщо не вказано —
     * лишається чоловіча форма, той самий текст, що й раніше.
     */
    public function updateGender(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gender' => ['nullable', 'in:m,f'],
        ]);

        $request->user()->update(['gender' => $data['gender'] ?? null]);

        return Redirect::route('profile.edit');
    }

    /**
     * Фото профілю — за бажанням. Хто завантажив, потрапляє в карусель
     * учасників родини на головній (якщо адмін її не вимкнув у Дизайні).
     * Старий файл видаляємо: інакше storage копичить кожне завантаження.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_path' => $path]);
        } elseif ($request->boolean('remove')) {
            $user->update(['avatar_path' => null]);
        }

        if ($old && $old !== $user->avatar_path && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
