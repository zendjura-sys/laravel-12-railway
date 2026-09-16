<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Куда ведёт кнопка «Подати заявку» и ссылки на бота.
 *
 * Адрес раньше брался только из настройки telegram_bot_url, а при пустом
 * значении подставлялся '#'. На свежей базе это означало, что главный
 * призыв лендинга — все четыре кнопки заявки — просто ничего не делал:
 * ссылка есть, курсор меняется, клик впустую.
 *
 * Поэтому: адрес собирается ещё и из username бота (его в админке
 * заполняют в любом случае, он нужен для webhook), а когда Telegram не
 * настроен вообще — метод честно возвращает null, и каждая страница сама
 * решает, что показывать вместо мёртвой ссылки.
 */
class TelegramLink
{
    public static function url(): ?string
    {
        $url = trim((string) Setting::get('telegram_bot_url'));
        if ($url !== '') {
            return $url;
        }

        // В настройках username пишут и как «monsory_bot», и как
        // «@monsory_bot» — ссылка должна получиться в обоих случаях.
        $username = ltrim(trim((string) Setting::get('telegram_bot_username')), '@');
        if ($username !== '') {
            return 'https://t.me/'.$username;
        }

        $fromEnv = trim((string) config('services.telegram.bot_url'));

        return $fromEnv !== '' && $fromEnv !== '#' ? $fromEnv : null;
    }
}
