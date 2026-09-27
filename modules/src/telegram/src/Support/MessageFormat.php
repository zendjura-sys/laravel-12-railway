<?php

namespace Addons\TelegramBot\Support;

/**
 * Єдиний візуальний шаблон для автоматичних системних повідомлень бота:
 * жирний заголовок з емодзі, тонкий роздільник і рядки-пункти замість
 * суцільного абзацу з голими переносами. Раніше кожен лисенер сам
 * вигадував формат — однакові за змістом сповіщення виглядали по-різному.
 *
 * $body приймається як уже безпечний HTML (як і скрізь у проєкті —
 * динамічні значення екранує сам виклик через e() ДО передачі сюди),
 * bullets() лише ділить його на рядки й додає булліт, нічого не екранує.
 */
class MessageFormat
{
    public static function card(string $emoji, string $title, ?string $body = null): string
    {
        $text = '<b>'.$emoji.' '.e($title).'</b>';

        if ($body !== null && $body !== '') {
            $text .= "\n<i>┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄</i>\n".self::bullets($body);
        }

        return $text;
    }

    public static function bullets(string $body): string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $body)),
            fn (string $line) => $line !== ''
        ));

        return implode("\n", array_map(fn (string $line) => '▫️ '.$line, $lines));
    }
}
