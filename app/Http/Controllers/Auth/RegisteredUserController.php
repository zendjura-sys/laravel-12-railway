<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FamilyContent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Якщо хтось раніше подав звіт "за друга" з таким самим ім'ям —
     * заведено тіньовий акаунт (User::createShadow, без входу). Замість
     * тихого автозлиття за самим лише ім'ям (можна нарватись на тезку чи
     * підміну чужої статистики) показуємо підтвердження: людина сама каже
     * "так, це я" — і тоді реєстрація ЗАПОВНЮЄ вже наявний рядок замість
     * створення нового, зберігаючи всю історію (звіти/прогресія/премії
     * прив'язані до user_id, який при цьому не змінюється).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            // Фамилия не обязательна: часть людей в игре известна одним ником.
            'last_name' => 'nullable|string|max:120',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'claim_shadow_id' => ['nullable', 'integer'],
            'skip_shadow_check' => ['nullable', 'boolean'],
        ]);

        $fullName = trim($data['first_name'].' '.($data['last_name'] ?? ''));

        if (empty($data['claim_shadow_id']) && empty($data['skip_shadow_check'])) {
            $shadow = User::query()->where('is_shadow', true)->where('name', $fullName)->first();

            if ($shadow) {
                return Inertia::render('Auth/Register', [
                    'shadowMatch' => ['id' => $shadow->id, 'name' => $shadow->name],
                    'previousInput' => $request->only('first_name', 'last_name', 'email'),
                ]);
            }
        }

        if (! empty($data['claim_shadow_id'])) {
            $shadow = User::query()->where('id', $data['claim_shadow_id'])->where('is_shadow', true)->first();

            // Ім'я звіряємо ще раз: між показом підтвердження і сабмітом
            // хтось міг помінятись даними, довіряти самому лише ID небезпечно.
            if (! $shadow || $shadow->name !== $fullName) {
                throw ValidationException::withMessages(['email' => 'Не вдалося підтвердити профіль — спробуйте ще раз.']);
            }

            $shadow->update([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_shadow' => false,
            ]);

            $user = $shadow;
        } else {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                // "Починають усі однаково — зі Стажера" — та же формулировка,
                // что на сайте и в боте. Берём ПЕРВЫЙ ключ из живого списка
                // (не хардкодим 'trainee'): если админ когда-нибудь переставит
                // должности так, что первой станет другая, регистрация должна
                // следовать за этим, а не молча указывать в старую позицию.
                'position_key' => FamilyContent::positionKeys()[0] ?? null,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
