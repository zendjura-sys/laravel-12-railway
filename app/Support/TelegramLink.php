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
        // Проверяем схему и здесь, а не только при сохранении: значение
        // уходит прямо в href кнопок лендинга, а Vue в :href ничего не
        // санитайзит. Настройка могла быть записана и до того, как
        // появилось правило валидации, — тогда javascript:… выполнился бы
        // в origin сайта у каждого, кто нажмёт «Подати заявку».
        $url = trim((string) Setting::get('telegram_bot_url'));
        if (self::isWebUrl($url)) {
            return $url;
        }

        // В настройках username пишут и как «monsory_bot», и как
        // «@monsory_bot» — ссылка должна получиться в обоих случаях.
        $username = ltrim(trim((string) Setting::get('telegram_bot_username')), '@');
        if ($username !== '') {
            return 'https://t.me/'.$username;
        }

        $fromEnv = trim((string) config('services.telegram.bot_url'));

        return self::isWebUrl($fromEnv) ? $fromEnv : null;
    }

    private static function isWebUrl(string $url): bool
    {
        return $url !== '' && preg_match('#^https?://#i', $url) === 1;
    }
}
