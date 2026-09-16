<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Выдать (или завести) администратора.
 *
 * Роль admin ничем не выдаётся автоматически — ни регистрацией, ни
 * сидером: сидеры только создают саму роль и её права. Раньше первого
 * админа поднимали простынёй из tinker с паролем прямо в командной
 * строке — он оставался и в истории shell, и в списке процессов.
 * Здесь пароль спрашивается скрытым вводом и никуда не попадает.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'monsory:make-admin
                            {email : Email пользователя}
                            {--name= : Имя, если пользователя ещё нет}
                            {--reset-password : Задать новый пароль существующему пользователю}';

    protected $description = 'Выдать пользователю роль admin, создав его при необходимости';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error("Не похоже на email: {$email}");

            return self::FAILURE;
        }

        // Роль может отсутствовать, если сидеры ещё не гоняли на этой базе.
        if (! Role::query()->where('name', 'admin')->where('guard_name', 'web')->exists()) {
            $this->error('Роли admin нет в базе. Сначала: php artisan db:seed --class=SystemPermissionsSeeder');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $name = (string) ($this->option('name') ?: $this->ask('Имя', 'Admin'));
            $password = $this->askForPassword();
            if ($password === null) {
                return self::FAILURE;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $this->info("Пользователь создан: {$email}");
        } else {
            $this->line("Пользователь найден: {$user->name} <{$email}>");

            if ($this->option('reset-password')) {
                $password = $this->askForPassword();
                if ($password === null) {
                    return self::FAILURE;
                }
                $user->forceFill(['password' => Hash::make($password)])->save();
                $this->info('Пароль изменён.');
            }
        }

        // Без подтверждённого email вход упирается в экран верификации, а
        // письмо на свежем сервере может и не уйти — для админа это тупик.
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->line('Email отмечен подтверждённым.');
        }

        if ($user->hasRole('admin')) {
            $this->line('Роль admin уже была.');
        } else {
            $user->assignRole('admin');
            $this->info('Роль admin выдана.');
        }

        $this->newLine();
        $this->info("Готово. Вход: {$email}");

        return self::SUCCESS;
    }

    private function askForPassword(): ?string
    {
        $password = (string) $this->secret('Пароль (ввод скрыт)');
        $confirm = (string) $this->secret('Повторите пароль');

        if ($password !== $confirm) {
            $this->error('Пароли не совпадают.');

            return null;
        }

        $check = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);
        if ($check->fails()) {
            foreach ($check->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }
}
